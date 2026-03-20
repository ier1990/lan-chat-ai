<?php
/**
 * view/parts/message.php — Single message row partial.
 *
 * Expected variable: $msg (array from Messages::withSenderInfo)
 */
$sType     = $msg['sender_type']  ?? 'user';
$mType     = $msg['message_type'] ?? 'text';
$meta      = $msg['meta']         ?? [];
$isAi      = ($sType === 'persona');
$isAiReply = ($mType === 'ai_reply');
$isWebhook = ($sType === 'webhook');
$isSystem  = ($sType === 'system');

$rowClass = 'message-row'
    . ($isAi      ? ' message-ai'      : '')
    . ($isWebhook ? ' message-webhook'  : '')
    . ($isSystem  ? ' message-system'   : '');

// Badge: show AI badge for persona-type OR any ai_reply message
$badge = '';
if ($isAi || $isAiReply) {
    $badge = '<span class="badge badge-ai">AI</span>';
} elseif ($isWebhook) {
    $badge = '<span class="badge badge-hook">↓</span>';
} elseif ($isSystem) {
    $badge = '<span class="badge badge-sys">SYS</span>';
}

$timeStr = '';
if (!empty($msg['created_at'])) {
    try {
        $timeStr = (new DateTime($msg['created_at']))->format('g:i A');
    } catch (Exception $ignored) {}
}

// ── AI metadata footer ────────────────────────────────────────────────────
$aiMetaHtml = '';
if ($isAiReply && !empty($meta)) {
    $parts = [];
    // Persona name: skip for persona-type (sender_name already IS the persona)
    if (!empty($meta['persona_name']) && !$isAi) {
        $parts[] = '⊕ ' . Util::e($meta['persona_name']);
    }
    if (!empty($meta['model']))    { $parts[] = Util::e($meta['model']); }
    if (!empty($meta['provider'])) { $parts[] = Util::e($meta['provider']); }
    $tIn  = isset($meta['tokens_in'])  ? (int) $meta['tokens_in']  : null;
    $tOut = isset($meta['tokens_out']) ? (int) $meta['tokens_out'] : null;
    if ($tIn !== null || $tOut !== null) {
        $parts[] = ($tIn ?? 0) . '↑ ' . ($tOut ?? 0) . '↓';
    }
    if (!empty($meta['latency_ms'])) {
        $parts[] = number_format((int) $meta['latency_ms']) . 'ms';
    }
    if ($parts) {
        $aiMetaHtml = '<div class="message-ai-meta">' . implode(' · ', $parts) . '</div>';
    }
}
?>
<div class="<?= $rowClass ?>" data-msg-id="<?= (int) $msg['id'] ?>">
  <div class="message-avatar">
    <?= UI::avatar((string) ($msg['sender_name'] ?? '?'), $sType) ?>
  </div>
  <div class="message-body">
    <div class="message-meta">
      <span class="message-author"><?= Util::e((string) ($msg['sender_name'] ?? '?')) ?></span>
      <?= $badge ?>
      <span class="message-time" title="<?= Util::e((string) ($msg['created_at'] ?? '')) ?>">
        <?= $timeStr ?>
      </span>
    </div>
    <div class="message-text"><?= nl2br(Util::e((string) ($msg['message_text'] ?? ''))) ?></div>
    <?= $aiMetaHtml ?>
  </div>
</div>
