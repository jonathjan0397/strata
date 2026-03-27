<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('email verification notice is shown to unverified users', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
});

test('email verification notice redirects already-verified users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
         ->get(route('verification.notice'))
         ->assertRedirect(route('dashboard'));
});

test('verified users can access dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertStatus(200);
});

test('unverified users are redirected from dashboard to verification notice', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('email can be verified via signed url', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard').'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => 'bad-hash']
    );

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification email can be resent', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertSessionHas('status', 'verification-link-sent');
});

test('resend redirects already-verified users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
         ->post(route('verification.send'))
         ->assertRedirect(route('dashboard'));
});
