<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user()->fresh();

        return view('profile.index', [
            'user' => $user,
            'twoFactorEnabled' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null,
        ]);
    }

    public function disableTwoFactor(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->two_factor_secret === null && $user->two_factor_confirmed_at === null) {
            throw ValidationException::withMessages([
                'password' => 'Two-factor authentication is already disabled.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Require a fresh password confirmation for future sensitive actions.
        $request->session()->forget('auth.password_confirmed_at');

        return redirect()->route('profile')->with('two-factor-status', 'Two-factor authentication has been disabled.');
    }
}
