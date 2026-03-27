<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
});

test('users can authenticate with valid credentials', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email'    => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('authenticated users are redirected away from login', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/login');
    $response->assertRedirect(route('dashboard'));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('unauthenticated users are redirected to login', function () {
    $response = $this->get('/');
    $response->assertRedirect('/login');
});
