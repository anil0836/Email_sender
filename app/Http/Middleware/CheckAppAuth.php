<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAppAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Check session or Auth
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);

        if (!$userId) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return redirect()->route('login');
        }

        $user = Auth::user() ?: User::find($userId);
        if (!$user) {
            session()->flush();
            Auth::logout();
            return redirect()->route('login');
        }

        if ($user->is_blocked) {
            session()->flush();
            Auth::logout();
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Account is blocked'], 403);
            }
            return redirect()->route('login')->with('danger', 'Your account has been blocked. Please contact the administrator.');
        }

        // Sync session details
        session([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'email' => $user->email,
        ]);

        if (!Auth::check()) {
            Auth::login($user);
        }

        // Role check if specified
        if (!empty($roles)) {
            $allowedRoles = is_array($roles) ? $roles : explode(',', $roles);
            if (!in_array($user->role, $allowedRoles)) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['error' => 'Unauthorized access.'], 403);
                }
                return redirect()->route('dashboard_view')->with('danger', 'Unauthorized access.');
            }
        }

        return $next($request);
    }
}
