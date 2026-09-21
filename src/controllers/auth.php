<?php

function auth_register_form(): void
{
    if (current_user()) {
        redirect('/');
    }
    render('auth/register', ['pageTitle' => 'Register']);
}

function auth_register_submit(): void
{
    csrf_verify();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $consent = isset($_POST['consent']);

    $errors = [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (Auth::emailExists($email)) {
        $errors[] = 'An account with that email already exists.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($displayName === '' || mb_strlen($displayName) > 100) {
        $errors[] = 'Enter a display name (up to 100 characters).';
    }
    if (!$consent) {
        $errors[] = 'You must accept the privacy policy and terms to register.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash_set('error', $error);
        }
        render('auth/register', [
            'pageTitle' => 'Register',
            'old' => ['email' => $email, 'display_name' => $displayName],
        ]);
        return;
    }

    $user = Auth::register($email, $password, $displayName);

    if ($user['status'] === 'approved') {
        Auth::login($user);
        flash_set('success', 'Welcome! As the first account, you are the admin.');
        redirect('/');
    }

    flash_set('success', 'Account created. An admin needs to approve it before you can log in.');
    redirect('/login');
}

function auth_login_form(): void
{
    if (current_user()) {
        redirect('/');
    }
    render('auth/login', ['pageTitle' => 'Log in']);
}

function auth_login_submit(): void
{
    csrf_verify();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $user = Auth::attempt($email, $password);
    if (!$user) {
        flash_set('error', 'Incorrect email or password.');
        redirect('/login');
    }

    if ($user['status'] === 'pending') {
        Auth::login($user);
        redirect('/pending');
    }
    if ($user['status'] !== 'approved') {
        flash_set('error', 'Your account is not active. Contact the admin.');
        redirect('/login');
    }

    Auth::login($user);
    redirect('/');
}

function auth_logout(): void
{
    csrf_verify();
    Auth::logout();
    redirect('/login');
}

function auth_pending(): void
{
    $user = current_user();
    if (!$user) {
        redirect('/login');
    }
    if ($user['status'] !== 'pending') {
        redirect('/');
    }
    render('auth/pending', ['pageTitle' => 'Awaiting approval']);
}
