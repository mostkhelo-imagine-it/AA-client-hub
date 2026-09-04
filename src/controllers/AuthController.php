<?php
declare(strict_types=1);

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check() && Auth::user()) {
            redirect('/');
        }
        render_bare('auth/login', ['error' => flash('error')]);
    }

    public static function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '' || !Auth::attempt($email, $password)) {
            flash('error', 'That email and password combination doesn\'t match an active account.');
            redirect('/login');
        }

        $user = Auth::user();
        if ($user && (int) $user['must_reset_password'] === 1) {
            redirect('/reset-password');
        }
        redirect('/');
    }

    public static function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }

    public static function showResetPassword(): void
    {
        Auth::requireLogin();
        render('auth/reset_password', ['error' => flash('error')]);
    }

    public static function resetPassword(): void
    {
        Auth::requireLogin();
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');

        if (strlen($password) < 10) {
            flash('error', 'Password must be at least 10 characters.');
            redirect('/reset-password');
        }
        if ($password !== $confirm) {
            flash('error', 'Passwords do not match.');
            redirect('/reset-password');
        }

        Auth::setPassword(Auth::id(), $password);
        Activity::log('auth.password_reset', 'user', Auth::id());
        flash('success', 'Password updated.');
        redirect('/');
    }
}
