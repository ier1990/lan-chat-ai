<?php
/**
 * view/layout.php — Main app shell.
 *
 * Expected variables set by the calling page:
 *   $title        string
 *   $view         'chat' | 'admin'
 *   $rooms        array  (all rooms visible to user)
 *   $currentRoom  array|null
 *   $messages     array  (for chat view)
 *   $personas     array
 *   $flash        string (optional success/error message)
 */
$siteName = Settings::get('app.site_name', 'AI Chat');
$user     = Auth::user();
$isStandaloneAdmin = ($view ?? '') === 'admin' && !empty($standalone);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Util::e($title ?? $siteName) ?></title>
<link rel="stylesheet" href="/ai/assets/css/main.css">
</head>
<body class="theme-dark">

<div class="app-shell">

  <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
  <?php if (!$isStandaloneAdmin): ?>
  <nav class="sidebar" id="sidebar">

    <div class="sidebar-header">
      <span class="workspace-name"><?= Util::e($siteName) ?></span>
    </div>

    <!-- Channels -->
    <div class="sidebar-section">
      <div class="section-label">
        Channels
        <button class="btn-icon js-new-room" title="New channel" data-type="channel">+</button>
      </div>
      <?php foreach ($rooms as $room): ?>
        <?php if (in_array($room['room_type'], ['channel','log'], true)): ?>
          <a href="/ai/?room=<?= Util::e($room['slug']) ?>"
             class="room-item js-room-link<?= UI::activeRoomClass((int)$room['id'], (int)($currentRoom['id'] ?? 0)) ?>"
             data-room-id="<?= (int)$room['id'] ?>"
             data-room-slug="<?= Util::e($room['slug']) ?>">
            <span class="room-icon"><?= UI::roomIcon($room['room_type']) ?></span>
            <span class="room-name"><?= Util::e($room['name']) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- Direct Messages -->
    <div class="sidebar-section">
      <div class="section-label">
        Direct Messages
        <button class="btn-icon js-new-dm" title="New DM">+</button>
      </div>
      <?php foreach ($rooms as $room): ?>
        <?php if (in_array($room['room_type'], ['dm','group'], true)): ?>
          <a href="/ai/?room=<?= Util::e($room['slug']) ?>"
             class="room-item js-room-link<?= UI::activeRoomClass((int)$room['id'], (int)($currentRoom['id'] ?? 0)) ?>"
             data-room-id="<?= (int)$room['id'] ?>"
             data-room-slug="<?= Util::e($room['slug']) ?>">
            <span class="room-icon">@</span>
            <span class="room-name"><?= Util::e($room['name']) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- AI Personas shortcut -->
    <div class="sidebar-section">
      <div class="section-label">AI</div>
      <?php foreach ($personas as $persona): ?>
        <button class="room-item js-new-dm btn-plain"
                data-type="dm"
                data-other-type="persona"
                data-other-id="<?= (int)$persona['id'] ?>"
                title="Chat with <?= Util::e($persona['name']) ?>">
          <span class="room-icon">⊕</span>
          <span class="room-name"><?= Util::e($persona['name']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
      <?= UI::avatar($user['display_name'] ?? '?', 'user') ?>
      <span class="user-display-name"><?= Util::e($user['display_name'] ?? '') ?></span>
      <?php if (Auth::isAdmin()): ?>
        <a href="/ai/admin.php?standalone=1" target="_blank" rel="noopener" class="sidebar-link" title="Admin (new tab)">⚙</a>
      <?php endif; ?>
      <a href="/ai/memory.php" class="sidebar-link<?= ($view ?? '') === 'memory' ? ' sidebar-link--active' : '' ?>" title="Memory">🗂</a>
      <button class="sidebar-link js-logout" title="Logout">⏻</button>
    </div>

  </nav>
  <?php endif; ?>

  <!-- ── Main content ──────────────────────────────────────────────────── -->
  <main class="main-content" id="main">
    <?php if (!empty($flash)): ?>
      <?= UI::flash($flashType ?? 'success', $flash) ?>
    <?php endif; ?>

    <?php
    if ($view === 'chat')   require __DIR__ . '/chat.php';
    if ($view === 'admin')  require __DIR__ . '/admin.php';
    if ($view === 'memory') require __DIR__ . '/memory.php';
    ?>
  </main>

</div><!-- .app-shell -->

<!-- DM user picker modal -->
<?php if (!$isStandaloneAdmin): ?>
<div class="modal-backdrop" id="dm-picker-backdrop" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dm-picker-title">
    <div class="modal-header">
      <span class="modal-title" id="dm-picker-title">New Direct Message</span>
      <button class="btn-icon modal-close" id="dm-picker-close" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <input type="search" id="dm-picker-search" class="input" placeholder="Search users…" autocomplete="off">
      <ul class="dm-picker-list" id="dm-picker-list"></ul>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Global JS state -->
<?php if (!$isStandaloneAdmin): ?>
<script>
window.AI = {
  csrf:        <?= json_encode(Util::csrfToken()) ?>,
  userId:      <?= json_encode(Auth::id()) ?>,
  roomId:      <?= json_encode((int) ($currentRoom['id'] ?? 0)) ?>,
  roomSlug:    <?= json_encode($currentRoom['slug'] ?? '') ?>,
  pollMs:      <?= json_encode((int) Settings::get('room.poll_interval_ms', 3000)) ?>,
  debugEnabled: <?= json_encode((bool) Settings::get('app.debug_mode', 0)) ?>,
  users:       <?= json_encode(array_values(array_filter($users ?? [], fn($u) => (int)$u['id'] !== Auth::id()))) ?>,
};
</script>
<script src="/ai/assets/js/chat.js"></script>
<?php endif; ?>
</body>
</html>
