<?php
/**
 * view/chat.php — Chat panel (included by layout.php when $view === 'chat').
 */
?>
<div class="chat-panel">

  <!-- Room header -->
  <header class="room-header">
    <?php if ($currentRoom): ?>
      <span class="room-header-icon"><?= UI::roomIcon($currentRoom['room_type']) ?></span>
      <span class="room-header-name"><?= Util::e($currentRoom['name']) ?></span>
      <?php if ($currentRoom['room_type'] === 'channel'): ?>
        <span class="room-header-type badge-channel">channel</span>
      <?php elseif ($currentRoom['room_type'] === 'log'): ?>
        <span class="room-header-type badge-log">log stream</span>
      <?php endif; ?>
      <?php
        $rs = $roomSettings ?? [];
        if ($currentRoom['room_type'] !== 'dm' && !empty($rs['ai_enabled'])):
      ?>
        <span class="ai-badge" title="AI is active in this room">⊕ AI <?= Util::e($rs['ai_trigger_mode'] ?? 'manual') ?></span>
      <?php endif; ?>
      <?php
        // DM metadata bar: persona, model, provider
        $dm = $dmMeta ?? null;
        if ($currentRoom['room_type'] === 'dm' && $dm):
          $dmParts = [];
          if (!empty($dm['persona_name']))    $dmParts[] = '⊕ ' . Util::e($dm['persona_name']);
          if (!empty($dm['model']))           $dmParts[] = Util::e($dm['model']);
          if (!empty($dm['provider']))        $dmParts[] = Util::e($dm['provider']);
      ?>
        <span id="dm-meta-bar" class="dm-meta-bar"><?= implode(' · ', $dmParts) ?></span>
      <?php else: ?>
        <span id="dm-meta-bar" class="dm-meta-bar" hidden></span>
      <?php endif; ?>
    <?php else: ?>
      <span class="room-header-name muted">Select a room</span>
      <span id="dm-meta-bar" class="dm-meta-bar" hidden></span>
    <?php endif; ?>
  </header>

  <!-- Messages scroll area -->
  <div class="messages-area" id="messages-area">
    <?php if (!$messages): ?>
      <div class="empty-state">No messages yet. Say something!</div>
    <?php else: ?>
      <?php foreach ($messages as $msg): ?>
        <?php require __DIR__ . '/parts/message.php'; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Composer -->
  <?php if ($currentRoom): ?>
  <div class="composer" id="composer">
    <form id="message-form" autocomplete="off">
      <input type="hidden" id="room-id-input" value="<?= (int)$currentRoom['id'] ?>">
      <textarea
        id="message-input"
        name="message"
        rows="1"
        placeholder="Message #<?= Util::e($currentRoom['name']) ?>  (Enter to send, Shift+Enter for newline, /help for commands)"
      ></textarea>
      <button type="button" class="send-btn" title="Send">↵</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Slash output modal (ephemeral, client-only) -->
  <div class="modal-backdrop" id="slash-output-backdrop" hidden>
    <div class="modal slash-output-modal" role="dialog" aria-modal="true" aria-labelledby="slash-output-title">
      <div class="modal-header">
        <span class="modal-title" id="slash-output-title">Command Output</span>
        <button class="btn-icon modal-close" id="slash-output-close" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <pre id="slash-output-content" class="slash-output-content"></pre>
      </div>
    </div>
  </div>

</div>
