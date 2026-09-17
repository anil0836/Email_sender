<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Show login form.
     */
    public function showLogin()
    {
        if (Auth::check() || session()->has('user_id')) {
            return redirect()->route('dashboard_view');
        }

        return view('auth.login');
    }

    /**
     * Handle login submission.
     */
    public function login(Request $request)
    {
        $loginId = $request->input('email') ?: $request->input('username');
        $password = $request->input('password');

        if (!$loginId || !$password) {
            return back()->with('danger', 'Email and password are required.');
        }

        $user = User::where('email', $loginId)
            ->orWhere('username', $loginId)
            ->first();

        if ($user && Hash::check($password, $user->password)) {
            if ($user->is_blocked) {
                return back()->with('danger', 'Your account has been blocked. Please contact the administrator.');
            }

            // Log the user in
            Auth::login($user);
            session([
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'email' => $user->email,
            ]);

            $this->auditService->logActivity($user->id, 'Login', 'Successful login', $request->ip());

            return redirect()->route('dashboard_view')->with('success', 'Successfully logged in!');
        }

        $this->auditService->logActivity(null, 'Login Failed', "Failed attempt for email/username: {$loginId}", $request->ip());

        return back()->with('danger', 'Invalid email or password.');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);
        if ($userId) {
            $this->auditService->logActivity($userId, 'Logout', 'Logged out', $request->ip());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Successfully logged out.');
    }
}
