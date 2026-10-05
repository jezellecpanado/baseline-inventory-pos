<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && request()->session()->get('login_completed') === true) {
            return redirect()->route($this->dashboardRouteName(Auth::user()));
        }

        if (Auth::check()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $credentials['active'] = true;
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['username' => 'The username or password is incorrect, or this account is inactive.'])->onlyInput('username');
        }
        $request->session()->regenerate();
        $request->session()->put('login_completed', true);

        return redirect()->route($this->dashboardRouteName($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function dashboardRouteName(User $user): string
    {
        return match ($user->role) {
            User::ROLE_ADMIN => 'admin.index',
            User::ROLE_WAREHOUSE => 'warehouse',
            User::ROLE_POS => 'pos',
            default => 'dashboard',
        };
    }
}
