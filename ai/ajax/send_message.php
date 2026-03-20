<?php
/**
 * ajax/send_message.php — Post a message and optionally trigger an AI reply.
 *
 * POST body (JSON or form): room_id, message[, csrf]
 * Also handles polling: GET ?room_id=&after_id= returns new messages.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireLogin();

DebugLog::request('ajax.send_message', null);

// ── Polling mode (GET) ──────────────────────────────────────────────────────
if (!Util::isPost()) {
    $roomId  = (int) Util::get('room_id');
    $afterId = (int) Util::get('after_id', '0');
    if (!$roomId) {
        DebugLog::event('debug.error', ['route' => 'ajax.send_message.poll', 'error' => 'room_id required']);
        Util::jsonResponse(['error' => 'room_id required'], 400);
    }
    $messages = Messages::since($roomId, $afterId);
    DebugLog::event('debug.response', [
        'route'         => 'ajax.send_message.poll',
        'ok'            => true,
        'room_id'       => $roomId,
        'after_id'      => $afterId,
        'message_count' => count($messages),
    ]);
    Util::jsonResponse([
        'ok'       => true,
        'messages' => $messages,
    ]);
}

// ── Send mode (POST) ────────────────────────────────────────────────────────
Util::requireCsrf();

// Accept JSON or form body.
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

DebugLog::request('ajax.send_message.post', is_array($data) ? $data : null);

$roomId = (int) ($data['room_id'] ?? 0);
$text   = trim((string) ($data['message'] ?? ''));

if (!$roomId || $text === '') {
    DebugLog::event('debug.error', ['route' => 'ajax.send_message.post', 'error' => 'room_id and message required', 'room_id' => $roomId]);
    Util::jsonResponse(['error' => 'room_id and message are required'], 400);
}

$room = Rooms::getById($roomId);
if (!$room) {
    DebugLog::event('debug.error', ['route' => 'ajax.send_message.post', 'error' => 'room not found', 'room_id' => $roomId]);
    Util::jsonResponse(['error' => 'Room not found'], 404);
}

if (!Permissions::canPostToRoom($roomId, 'user', Auth::id())) {
    DebugLog::event('debug.error', ['route' => 'ajax.send_message.post', 'error' => 'cannot post', 'room_id' => $roomId, 'user_id' => Auth::id()]);
    Util::jsonResponse(['error' => 'You cannot post to this room'], 403);
}

// Truncate oversized input.
if (mb_strlen($text) > 8000) {
    $text = mb_substr($text, 0, 8000);
}

$msgId   = Messages::post($roomId, 'user', Auth::id(), $text);
$newMsgs = Messages::since($roomId, $msgId - 1);

// ── AI user DM auto-reply logic (role=ai) ─────────────────────────────────
$dmAiUser = AiUsers::getDmAiUserForRoom($roomId, (int) Auth::id());
if ($dmAiUser) {
    try {
        $history = Messages::forRoom($roomId, 20);
        $chatMsgs = [];

        $persona = null;
        if (!empty($dmAiUser['config']['persona_id'])) {
            $persona = Personas::getById((int) $dmAiUser['config']['persona_id']);
        }
        if ($persona && !empty($persona['system_prompt'])) {
            $chatMsgs[] = ['role' => 'system', 'content' => $persona['system_prompt']];
        }

        foreach ($history as $h) {
            $isAssistant = $h['sender_type'] === 'user' && (int) $h['sender_id'] === (int) $dmAiUser['id'];
            $chatMsgs[] = [
                'role'    => $isAssistant ? 'assistant' : 'user',
                'content' => $h['message_text'],
            ];
        }

        $start   = microtime(true);
        $result  = AiUsers::chat($dmAiUser['config'], $chatMsgs);
        $latency = (int) round((microtime(true) - $start) * 1000);

        Messages::post(
            $roomId,
            'user',
            (int) $dmAiUser['id'],
            $result['text'],
            'ai_reply',
            [
                'tokens_in'  => $result['tokens_in'],
                'tokens_out' => $result['tokens_out'],
                'latency_ms' => $latency,
                'model'      => $result['model'],
                'provider'   => (string) ($dmAiUser['config']['provider_key'] ?? 'openai_compat'),
                'ai_user'    => $dmAiUser['username'] ?? '',
            ]
        );

        $newMsgs = Messages::since($roomId, $msgId - 1);
    } catch (Throwable $e) {
        error_log('AI user reply failed: ' . $e->getMessage());
        DebugLog::event('debug.error', [
            'route'   => 'ajax.send_message.post',
            'error'   => 'ai user reply failed',
            'detail'  => $e->getMessage(),
            'room_id' => $roomId,
            'ai_user' => $dmAiUser['username'] ?? '',
        ]);
    }
}

// ── AI reply logic ──────────────────────────────────────────────────────────
if (!$dmAiUser && Permissions::personaShouldReply($roomId, $text)) {
    $settings     = Rooms::settings($roomId);
    $personaId    = (int) ($settings['ai_persona_id'] ?? 0);
    $persona      = $personaId ? Personas::getById($personaId) : Personas::getDefault();
    $provider     = AiProvider::getActive();

    if ($persona && $provider) {
        try {
            // Build message history for context.
            $history = Messages::forRoom($roomId, 20);
            $chatMsgs = [];
            if ($persona['system_prompt']) {
                $chatMsgs[] = ['role' => 'system', 'content' => $persona['system_prompt']];
            }
            foreach ($history as $h) {
                $chatMsgs[] = [
                    'role'    => $h['sender_type'] === 'persona' ? 'assistant' : 'user',
                    'content' => $h['message_text'],
                ];
            }

            $start    = microtime(true);
            $result   = $provider->chat($chatMsgs);
            $latency  = (int) round((microtime(true) - $start) * 1000);

            $aiMsgId = Messages::post(
                $roomId,
                'persona',
                $persona['id'],
                $result['text'],
                'ai_reply',
                [
                    'tokens_in'  => $result['tokens_in'],
                    'tokens_out' => $result['tokens_out'],
                    'latency_ms' => $latency,
                    'model'      => $result['model'],
                    'provider'   => $provider->providerKey(),
                ]
            );
            $newMsgs = Messages::since($roomId, $msgId - 1);
        } catch (Throwable $e) {
            // Non-fatal — user message was already saved; log and continue.
            error_log('AI reply failed: ' . $e->getMessage());
            DebugLog::event('debug.error', ['route' => 'ajax.send_message.post', 'error' => 'ai reply failed', 'detail' => $e->getMessage(), 'room_id' => $roomId]);
        }
    }
}

DebugLog::event('debug.response', [
    'route'         => 'ajax.send_message.post',
    'ok'            => true,
    'room_id'       => $roomId,
    'posted_msg_id' => $msgId,
    'message_count' => count($newMsgs),
]);

Util::jsonResponse(['ok' => true, 'messages' => $newMsgs]);
