<?php
/**
 * ajax/login.php — Authenticate and return session state as JSON.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if (!Util::isPost()) {
    Util::jsonResponse(['error' => 'POST required'], 405);
}
Util::requireCsrf();

$username = Util::post('username');
$password = Util::post('password');

if (!$username || !$password) {
    Util::jsonResponse(['error' => 'Username and password required.'], 400);
}

if (!Auth::attempt($username, $password)) {
    Util::jsonResponse(['error' => 'Invalid username or password.'], 401);
}

Util::jsonResponse([
    'ok'           => true,
    'user_id'      => Auth::id(),
    'display_name' => Auth::user()['display_name'],
    'is_admin'     => Auth::isAdmin(),
]);
