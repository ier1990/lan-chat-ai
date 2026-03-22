<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Util::e($title ?? 'Admin') ?></title>
<link rel="stylesheet" href="/ai/assets/css/main.css">
</head>
<body class="theme-dark">
<div class="app-shell">

<?php if (!$standalone): ?>
<!-- Embedded sidebar (non-standalone mode only) -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span class="workspace-name"><?= Util::e(Settings::get('app.site_name', 'AI Chat')) ?></span>
  </div>
  <div class="sidebar-footer">
    <?= UI::avatar(Auth::user()['display_name'] ?? '?', 'user') ?>
    <span class="user-display-name"><?= Util::e(Auth::user()['display_name'] ?? '') ?></span>
    <a href="/ai/" class="sidebar-link" title="Back to Chat">←</a>
  </div>
</nav>
<?php endif; ?>

<main class="main-content" id="main">
  <div class="admin-panel">

    <aside class="admin-sidebar">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
        <h2 class="admin-title">Admin</h2>
        <a href="/ai/" class="btn btn-small" style="font-size:.78rem;">← Chat</a>
      </div>

      <?php
      $adminSections = [
          'settings'  => 'Settings',
          'rooms'     => 'Rooms',
          'users'     => 'Users',
          'ai_users'  => 'AI Users',
          'personas'  => 'Personas',
          'providers' => 'AI Setup',
          'webhooks'  => 'Webhooks',
          'memories'  => 'Memories',
      ];
      foreach ($adminSections as $sec => $label):
      ?>
        <a href="<?= Util::e($buildAdminUrl(['section' => $sec])) ?>"
           class="admin-nav-item<?= UI::activeClass($activeSection === $sec) ?>">
          <?= Util::e($label) ?>
        </a>
      <?php endforeach; ?>
    </aside>

    <div class="admin-content">
      <?php if (!empty($flash)): ?>
        <?= UI::flash($flashType ?? 'success', $flash) ?>
      <?php endif; ?>

      <?php
      $sectionView = __DIR__ . '/' . $activeSection . '.php';
      if (file_exists($sectionView)) {
          require $sectionView;
      } else {
          echo '<p class="muted">Section not found.</p>';
      }
      ?>
    </div>

  </div>
</main>
</div>
</body>
</html>
