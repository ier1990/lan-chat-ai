<?php
/**
 * webhook.php — Inbound webhook receiver.
 *
 * POST /ai/webhook.php?key=<webhook_key>
 *
 * Any service (cron, Apache, external script) can POST here and the payload
 * appears as a log message in the target room.
 *
 * Security: validated by secret webhook_key only. No session/auth required.
 */
require_once __DIR__ . '/lib/bootstrap.php';

if (!file_exists(AI_INSTALLED_FLAG)) {
    Util::jsonResponse(['error' => 'Not installed'], 503);
}

if (!Settings::get('webhook.enabled', true)) {
    Util::jsonResponse(['error' => 'Webhooks disabled'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Util::jsonResponse(['error' => 'POST required'], 405);
}

$key = trim(Util::get('key'));
if ($key === '') {
    Util::jsonResponse(['error' => 'Missing key'], 400);
}

$source = DB::fetch(
    'SELECT * FROM webhook_sources WHERE webhook_key = ? AND is_enabled = 1',
    [$key]
);
if (!$source) {
    Util::jsonResponse(['error' => 'Invalid or disabled key'], 403);
}

// Accept JSON body or form POST.
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
    $data = $_POST;
}

$text = trim((string) ($data['text'] ?? $data['message'] ?? $body));
if ($text === '') {
    Util::jsonResponse(['error' => 'Empty payload'], 400);
}

// Truncate oversized payloads.
if (mb_strlen($text) > 4000) {
    $text = mb_substr($text, 0, 4000) . '…';
}

$msgId = Messages::post(
    (int) $source['target_room_id'],
    'webhook',
    (int) $source['id'],
    $text,
    'log',
    ['source_type' => $source['source_type'], 'source_name' => $source['name']]
);

Util::jsonResponse(['ok' => true, 'message_id' => $msgId]);
