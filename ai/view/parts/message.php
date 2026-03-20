<?php
/**
 * view/parts/message.php — Single message row partial.
 *
 * Expected variable: $msg (array from Messages::withSenderInfo)
 */
$isAi      = $msg['sender_type'] === 'persona';
$isWebhook = $msg['sender_type'] === 'webhook';
$isSystem  = $msg['sender_type'] === 'system';
$rowClass  = 'message-row'
           . ($isAi      ? ' message-ai'      : '')
           . ($isWebhook ? ' message-webhook'  : '')
           . ($isSystem  ? ' message-system'   : '');
?>
<div class="<?= $rowClass ?>" data-msg-id="<?= (int)$msg['id'] ?>">
  <div class="message-avatar">
    <?= UI::avatar($msg['sender_name'], $msg['sender_type']) ?>
  </div>
  <div class="message-body">
    <div class="message-meta">
      <span class="message-author"><?= Util::e($msg['sender_name']) ?></span>
      <?= UI::senderBadge($msg['sender_type']) ?>
      <span class="message-time" title="<?= Util::e($msg['created_at']) ?>">
        <?= Util::e(date('H:i', strtotime($msg['created_at']))) ?>
      </span>
    </div>
    <div class="message-text"><?= nl2br(Util::e($msg['message_text'])) ?></div>
  </div>
</div>
