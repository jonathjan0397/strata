<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('sessions page renders for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('profile.sessions'));
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Profile/Sessions'));
});

test('sessions page redirects guest to login', function () {
    $this->get(route('profile.sessions'))->assertRedirect(route('login'));
});

test('user can revoke another session', function () {
    $user = User::factory()->create();

    // Insert a fake second session for this user
    DB::table('sessions')->insert([
        'id'            => 'fake-session-abc123',
        'user_id'       => $user->id,
        'ip_address'    => '10.0.0.1',
        'user_agent'    => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120',
        'payload'       => base64_encode(serialize([])),
        'last_activity' => now()->subMinutes(30)->timestamp,
    ]);

    $this->actingAs($user)
         ->delete(route('profile.sessions.destroy', 'fake-session-abc123'));

    $this->assertDatabaseMissing('sessions', ['id' => 'fake-session-abc123']);
});

test('user cannot revoke their current session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete(
        route('profile.sessions.destroy', $this->app['session']->getId())
    );

    $response->assertSessionHasErrors('session');
});

test('user cannot revoke sessions belonging to another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    DB::table('sessions')->insert([
        'id'            => 'user-b-session-xyz',
        'user_id'       => $userB->id,
        'ip_address'    => '10.0.0.2',
        'user_agent'    => 'Mozilla/5.0',
        'payload'       => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($userA)
         ->delete(route('profile.sessions.destroy', 'user-b-session-xyz'));

    // Session for userB must still exist (the query filters by user_id)
    $this->assertDatabaseHas('sessions', ['id' => 'user-b-session-xyz']);
});

test('revoke all others removes only non-current sessions', function () {
    $user = User::factory()->create();

    DB::table('sessions')->insert([
        ['id' => 'other-1', 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'UA', 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp],
        ['id' => 'other-2', 'user_id' => $user->id, 'ip_address' => '2.2.2.2', 'user_agent' => 'UA', 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp],
    ]);

    $currentId = $this->app['session']->getId();

    $this->actingAs($user)->delete(route('profile.sessions.destroy-others'));

    $this->assertDatabaseMissing('sessions', ['id' => 'other-1']);
    $this->assertDatabaseMissing('sessions', ['id' => 'other-2']);
    $this->assertDatabaseHas('sessions', ['id' => $currentId]);
});
