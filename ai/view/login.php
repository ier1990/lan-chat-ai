<?php
/**
 * view/login.php — Standalone login page (no layout shell).
 *
 * Used when the user is not authenticated.
 */
$siteName = '';
try {
    $cfg = require __DIR__ . '/../config.php';
    DB::connect($cfg['db']);
    $siteName = (string) (DB::fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'app.site_name'") ?? 'AI Chat');
} catch (Throwable) {
    $siteName = 'AI Chat';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Util::e($siteName) ?> — Login</title>
<link rel="stylesheet" href="/ai/assets/css/main.css">
</head>
<body class="theme-dark login-page">

<div class="login-card">
  <h1 class="login-title"><?= Util::e($siteName) ?></h1>
  <p class="login-sub">Sign in to continue</p>

  <?php if (!empty($loginError)): ?>
    <?= UI::flash('error', $loginError) ?>
  <?php endif; ?>

  <form method="post" class="login-form">
    <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
    <input type="hidden" name="_login" value="1">

    <div class="field">
      <label for="username">Username</label>
      <input type="text"     id="username" name="username" required autofocus autocomplete="username">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary btn-full">Sign in →</button>
  </form>
</div>

</body>
</html>
