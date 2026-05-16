<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordManagementController extends Controller
{
    public function forgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
        ]);

        $login = Str::lower(trim($validated['login']));
        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [$login])
            ->orWhereRaw('LOWER(email) = ?', [$login])
            ->first();
        $link = null;

        if ($user) {
            $token = Password::broker()->createToken($user);
            $link = route('password.reset', ['token' => $token, 'email' => $user->email]);
            $auditLogService->record('password.reset.requested', $user, 'Password reset requested.', ['email' => $user->email]);
        }

        $response = back()->with('status', 'If the account exists, a password reset link has been prepared.');

        if (app()->environment('local') && $link) {
            $response->with('reset_link_preview', $link);
        }

        return $response;
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function reset(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) use ($auditLogService): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                $auditLogService->record('password.reset.completed', $user, 'Password reset completed.');
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    public function changeForm(): View
    {
        return view('auth.change-password');
    }

    public function change(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is not correct.']);
        }

        $user->update(['password' => $validated['password']]);
        $auditLogService->record('password.changed', $user, 'User changed own password.');

        return back()->with('status', 'Password changed successfully.');
    }
}
