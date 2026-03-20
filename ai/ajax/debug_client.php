<?php
/**
 * ajax/debug_client.php — Browser-side debug events routed into #log.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireLogin();

if (!Util::isPost()) {
    Util::jsonResponse(['error' => 'POST required'], 405);
}

if (!DebugLog::enabled()) {
    Util::jsonResponse(['ok' => false, 'error' => 'Debug mode disabled'], 403);
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$event   = trim((string) ($data['event'] ?? 'client.unknown'));
$payload = $data['payload'] ?? [];

if (!is_array($payload)) {
    $payload = ['value' => $payload];
}

DebugLog::event('client.' . preg_replace('/[^a-z0-9_.-]/i', '_', $event), $payload + [
    'user_id' => Auth::id(),
    'uri'     => (string) ($_SERVER['REQUEST_URI'] ?? ''),
]);

Util::jsonResponse(['ok' => true]);
