<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

test('users without 2fa are logged in directly', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
});

test('users with confirmed 2fa are redirected to challenge', function () {
    $google2fa = new Google2FA();
    $secret    = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret'       => encrypt($secret),
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->post('/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('two-factor.challenge'));
    $this->assertEquals($user->id, session('two_factor_login_id'));
});

test('challenge page requires pending session', function () {
    $response = $this->get('/two-factor-challenge');
    $response->assertRedirect(route('login'));
});

test('valid otp code completes login', function () {
    $google2fa = new Google2FA();
    $secret    = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret'       => encrypt($secret),
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->withSession(['two_factor_login_id' => $user->id]);

    $otp = $google2fa->getCurrentOtp($secret);

    $response = $this->post('/two-factor-challenge', ['code' => $otp]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
});

test('invalid otp code returns error', function () {
    $google2fa = new Google2FA();
    $secret    = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret'       => encrypt($secret),
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->withSession(['two_factor_login_id' => $user->id]);

    $response = $this->post('/two-factor-challenge', ['code' => '000000']);

    $this->assertGuest();
    $response->assertSessionHasErrors('code');
});

test('user can enable and confirm 2fa', function () {
    $user = User::factory()->create();

    // Enable — generates secret
    $this->actingAs($user)->post(route('two-factor.enable'));
    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();

    // Confirm with valid OTP
    $google2fa = new Google2FA();
    $secret    = decrypt($user->two_factor_secret);
    $otp       = $google2fa->getCurrentOtp($secret);

    $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $otp]);
    $user->refresh();
    expect($user->two_factor_enabled)->toBeTrue();
    expect($user->two_factor_confirmed_at)->not->toBeNull();
});

test('user can disable 2fa', function () {
    $google2fa = new Google2FA();
    $secret    = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret'       => encrypt($secret),
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)->delete(route('two-factor.disable'));
    $user->refresh();

    expect($user->two_factor_secret)->toBeNull();
    expect($user->two_factor_enabled)->toBeFalse();
    expect($user->two_factor_confirmed_at)->toBeNull();
});
