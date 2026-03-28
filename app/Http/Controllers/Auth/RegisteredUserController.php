<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TemplateMailable;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\WorkflowEngine;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('client');

        event(new Registered($user));

        try {
            Mail::to($user->email)->send(new TemplateMailable('auth.welcome', [
                'name'      => $user->name,
                'app_name'  => config('app.name'),
                'login_url' => route('login'),
            ]));
        } catch (\Throwable) {
            // mail failure must not block registration
        }

        AuditLogger::log('client.registered', $user, [], $user->id);
        WorkflowEngine::fire('client.registered', $user);

        Auth::login($user);

        return redirect()->route('admin.dashboard');
    }
}
