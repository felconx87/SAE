<?php

require_once __DIR__ . '/bootstrap.php';

appLoadEnv(__DIR__ . '/.env');
appStartSession();

function authUsername(): string
{
    return (string) env('AUTH_USERNAME', 'admin');
}

function authPasswordHash(): string
{
    return (string) env('AUTH_PASSWORD_HASH', '');
}

function authPasswordPlain(): string
{
    return (string) env('AUTH_PASSWORD', '');
}

function isAuthenticated(): bool
{
    return !empty($_SESSION['auth_user']) && is_string($_SESSION['auth_user']);
}

function currentAuthUser(): string
{
    return isAuthenticated() ? (string) $_SESSION['auth_user'] : '';
}

function attemptLogin(string $username, string $password): bool
{
    $expectedUser = authUsername();
    $hash = authPasswordHash();
    $plain = authPasswordPlain();

    if (!hash_equals($expectedUser, $username)) {
        return false;
    }

    $validPassword = false;
    if ($hash !== '') {
        $validPassword = password_verify($password, $hash);
    } elseif ($plain !== '') {
        $validPassword = hash_equals($plain, $password);
    }

    if (!$validPassword) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['auth_user'] = $username;
    return true;
}

function requireAuth(): void
{
    if (isAuthenticated()) {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? 'index.php';
    $redirect = urlencode((string) $uri);
    header('Location: login.php?redirect=' . $redirect);
    exit;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}
