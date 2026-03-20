<?php
/**
 * ajax/logout.php — Destroy session and confirm.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if (!Util::isPost()) {
    Util::jsonResponse(['error' => 'POST required'], 405);
}
Util::requireCsrf();

Auth::logout();
session_destroy();

Util::jsonResponse(['ok' => true]);
