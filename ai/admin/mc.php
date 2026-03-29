<?php
/**
 * mc.php — Memory Coder: Browse the codebase, view files, and generate AI summaries saved to MySQL.
 *
 * URL: /ai/admin/mc.php
 * Purpose: Help AI users (and admins) learn/refresh knowledge of the codebase by generating
 *          and storing per-file summaries using a configured AI user.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if (!file_exists(AI_INSTALLED_FLAG)) {
    Util::redirect('/ai/install.php');
}

Auth::requireLogin();
Auth::requireAdmin();

// ── Table DDL (lazy) ──────────────────────────────────────────────────────────

DB::query(
    "CREATE TABLE IF NOT EXISTS mc_summaries (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        file_path  VARCHAR(500) NOT NULL,
        ai_user_id INT UNSIGNED DEFAULT NULL,
        summary    TEXT NOT NULL,
        tokens_in  INT UNSIGNED DEFAULT NULL,
        tokens_out INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_mc_file (file_path(400))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// ── Helpers ───────────────────────────────────────────────────────────────────

function mc_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mc_join(string $base, string $sub): string
{
    return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($sub, '/\\');
}

/** Resolve a user-supplied relative path to an absolute path, confined to $baseReal. */
function mc_resolve(string $rel, string $baseReal): string
{
    if ($rel === '') return $baseReal;
    $candidate = mc_join($baseReal, $rel);
    $real = realpath($candidate);
    if ($real === false) return $baseReal;
    // Normalize for cross-platform comparison
    $rn = str_replace('\\', '/', $real);
    $bn = str_replace('\\', '/', $baseReal);
    if (strpos($rn . '/', $bn . '/') !== 0) return $baseReal;
    return $real;
}

/** Get path relative to base, using forward slashes. */
function mc_rel(string $full, string $baseReal): string
{
    $rn = str_replace('\\', '/', $full);
    $bn = str_replace('\\', '/', $baseReal);
    return ltrim(substr($rn, strlen($bn)), '/');
}

function mc_norm(string $path): string
{
    return str_replace('\\', '/', $path);
}

function mc_human_size(?int $bytes): string
{
    if ($bytes === null) return '—';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ── Config ────────────────────────────────────────────────────────────────────

$defaultBase = dirname(AI_ROOT); // htdocs root by default
$baseDir = Settings::get('mc.base_dir', $defaultBase);
if (!$baseDir || !is_dir($baseDir)) $baseDir = $defaultBase;
$baseReal = realpath($baseDir) ?: $baseDir;

$aiUsers = AiUsers::getAllConfigs();

// ── POST: save base dir ───────────────────────────────────────────────────────

$flash     = '';
$flashType = 'success';

if (Util::isPost() && isset($_POST['_mc_basedir'])) {
    Util::requireCsrf();
    $newDir = trim((string) ($_POST['mc_base_dir'] ?? ''));
    if ($newDir !== '' && is_dir($newDir)) {
        Settings::set('mc.base_dir', $newDir);
        Settings::flush();
        $baseDir  = $newDir;
        $baseReal = realpath($baseDir) ?: $baseDir;
        $flash    = 'Base directory saved.';
    } else {
        $flash     = 'Directory not found: ' . mc_e($newDir);
        $flashType = 'error';
    }
}

// ── POST: delete summary ──────────────────────────────────────────────────────

if (Util::isPost() && isset($_POST['_mc_del_summary'])) {
    Util::requireCsrf();
    $fp = trim((string) ($_POST['file_path'] ?? ''));
    if ($fp !== '') {
        DB::query('DELETE FROM mc_summaries WHERE file_path = ?', [$fp]);
        $flash = 'Summary deleted.';
    }
}

// ── POST/AJAX: summarize file ─────────────────────────────────────────────────

if (Util::isPost() && isset($_POST['_mc_summarize'])) {
    Util::requireCsrf();
    header('Content-Type: application/json');

    $rel      = (string) ($_POST['rel_path']   ?? '');
    $aiUserId = (int)    ($_POST['ai_user_id'] ?? 0);

    $fullPath = mc_resolve($rel, $baseReal);
    $fullN    = mc_norm($fullPath);
    $baseN    = mc_norm($baseReal);

    if (!is_file($fullPath) || !is_readable($fullPath) || strpos($fullN . '/', $baseN . '/') !== 0) {
        echo json_encode(['ok' => false, 'error' => 'File not accessible.']);
        exit;
    }

    // Pick AI user
    $aiConfig = null;
    foreach ($aiUsers as $u) {
        if ((int) $u['user_id'] === $aiUserId) { $aiConfig = $u; break; }
    }
    if (!$aiConfig) $aiConfig = $aiUsers[0] ?? null;
    if (!$aiConfig || empty($aiConfig['base_url'])) {
        echo json_encode(['ok' => false, 'error' => 'No AI user with a configured endpoint. Set one up in Admin → AI Users.']);
        exit;
    }

    // Read file (cap at 150 KB)
    $maxRead  = 150 * 1024;
    $fileSize = filesize($fullPath);
    $content  = @file_get_contents($fullPath, false, null, 0, $maxRead);
    if ($content === false) {
        echo json_encode(['ok' => false, 'error' => 'Could not read file.']);
        exit;
    }
    // Skip binary
    if (strpos(substr($content, 0, 512), "\0") !== false) {
        echo json_encode(['ok' => false, 'error' => 'Binary file — skipped.']);
        exit;
    }
    $truncated = ($fileSize > $maxRead);

    // Build AiProvider from AI user config
    $provider = ['base_url' => $aiConfig['base_url'], 'api_key' => $aiConfig['api_key'] ?? ''];
    $model    = ['model_key' => $aiConfig['model_default'], 'temperature_default' => 0.2, 'max_tokens' => 1200];
    $ap       = new AiProvider($provider, $model);

    $sysPrompt = "You are analyzing a source code file to build codebase memory for an AI assistant. "
        . "Write a concise technical summary (under 300 words): what this file does, its key classes/functions/logic, "
        . "important dependencies or integrations, and how it fits into the broader system. Be specific, not generic.";

    $userMsg = "File: " . basename($fullPath)
        . ($truncated ? " [first 150 KB shown, file is " . mc_human_size($fileSize) . " total]" : "")
        . "\n\n```\n" . $content . "\n```";

    try {
        $result  = $ap->chat(
            [['role' => 'system', 'content' => $sysPrompt], ['role' => 'user', 'content' => $userMsg]],
            ['temperature' => 0.2, 'max_tokens' => 1200]
        );
        $summary = trim($result['text'] ?? '');
        if ($summary === '') {
            echo json_encode(['ok' => false, 'error' => 'AI returned empty response.']);
            exit;
        }

        // Upsert
        $existing = DB::fetchColumn('SELECT id FROM mc_summaries WHERE file_path = ?', [$fullN]);
        $row = [
            'ai_user_id' => $aiUserId ?: null,
            'summary'    => $summary,
            'tokens_in'  => $result['tokens_in']  ?? null,
            'tokens_out' => $result['tokens_out'] ?? null,
            'updated_at' => Util::now(),
        ];
        if ($existing) {
            DB::update('mc_summaries', $row, 'id = ?', [(int) $existing]);
        } else {
            DB::insert('mc_summaries', array_merge(['file_path' => $fullN], $row));
        }

        echo json_encode(['ok' => true, 'summary' => $summary]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── GET: raw file serve ───────────────────────────────────────────────────────

if (isset($_GET['raw'])) {
    $rel      = (string) ($_GET['rel'] ?? '');
    $fullPath = mc_resolve($rel, $baseReal);
    $fullN    = mc_norm($fullPath);
    $baseN    = mc_norm($baseReal);

    if (!is_file($fullPath) || !is_readable($fullPath) || strpos($fullN . '/', $baseN . '/') !== 0) {
        http_response_code(404); echo 'Not found.'; exit;
    }
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $fi   = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $fullPath);
        finfo_close($fi);
    }
    $dl     = isset($_GET['dl']);
    $inline = !$dl && (strpos($mime, 'text/') === 0 || strpos($mime, 'image/') === 0);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fullPath));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($fullPath) . '"');
    readfile($fullPath);
    exit;
}

// ── Directory listing ─────────────────────────────────────────────────────────

$reqDir     = (string) ($_GET['dir'] ?? '');
$current    = mc_resolve($reqDir, $baseReal);
$relCurrent = mc_rel($current, $baseReal);

$showHidden = isset($_GET['hidden']);

// Inline file view
$viewFile    = '';
$viewContent = '';
$viewFullN   = '';
if (isset($_GET['view'])) {
    $vn   = basename((string) $_GET['view']);
    $vdir = mc_resolve((string) ($_GET['dir'] ?? ''), $baseReal);
    $vf   = realpath(mc_join($vdir, $vn));
    if ($vf && is_file($vf) && is_readable($vf) && strpos(mc_norm($vf) . '/', mc_norm($baseReal) . '/') === 0) {
        $viewFile  = $vn;
        $viewFullN = mc_norm($vf);
        $fsize     = filesize($vf);
        $h         = fopen($vf, 'rb'); $chunk = fread($h, 512); fclose($h);
        if (strpos($chunk, "\0") !== false) {
            $viewContent = '[Binary file — use Download]';
        } else {
            $maxShow     = 200 * 1024;
            $viewContent = $fsize > $maxShow
                ? substr(file_get_contents($vf), 0, $maxShow) . "\n\n[... truncated at 200 KB ...]"
                : file_get_contents($vf);
        }
    }
}

// Scan directory
$items = @scandir($current) ?: [];
$rows  = [];
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    if (!$showHidden && $item[0] === '.') continue;
    $full  = mc_join($current, $item);
    $isDir = is_dir($full);
    $rows[] = [
        'name'   => $item,
        'is_dir' => $isDir,
        'size'   => $isDir ? null : (is_file($full) ? (int) filesize($full) : null),
        'mtime'  => (int) filemtime($full),
        'rel'    => $relCurrent === '' ? $item : $relCurrent . '/' . $item,
        'fullN'  => mc_norm(realpath($full) ?: $full),
    ];
}
usort($rows, fn($a, $b) => $a['is_dir'] !== $b['is_dir'] ? ($a['is_dir'] ? -1 : 1) : strcasecmp($a['name'], $b['name']));

// Batch-load summary status for files in this dir
$fileNorms = array_column(array_filter($rows, fn($r) => !$r['is_dir']), 'fullN');
$summaryMap = [];
if ($fileNorms) {
    $ph   = implode(',', array_fill(0, count($fileNorms), '?'));
    $sums = DB::fetchAll("SELECT file_path, updated_at FROM mc_summaries WHERE file_path IN ($ph)", $fileNorms);
    foreach ($sums as $s) $summaryMap[$s['file_path']] = $s['updated_at'];
}

// Load summary for viewed file
$viewSummary = ($viewFullN !== '') ? DB::fetch('SELECT * FROM mc_summaries WHERE file_path = ?', [$viewFullN]) : null;

// Breadcrumb
$breadParts = $relCurrent === '' ? [] : explode('/', $relCurrent);

// AI user selection (session-persisted)
if (isset($_GET['ai_user'])) {
    $_SESSION['mc_ai_user'] = (int) $_GET['ai_user'];
}
$selectedAiUser = (int) ($_SESSION['mc_ai_user'] ?? ($aiUsers[0]['user_id'] ?? 0));

$csrf = Util::csrfToken();

// ── Render ────────────────────────────────────────────────────────────────────

// Precompute nav params used in both panels
$homeParams = [];
if ($selectedAiUser) $homeParams['ai_user'] = $selectedAiUser;
if ($showHidden)     $homeParams['hidden']   = '1';

$toggleParams = array_merge($homeParams, ['dir' => $reqDir]);
if (!$showHidden) $toggleParams['hidden'] = '1'; else unset($toggleParams['hidden']);

$parentRel = '';
$upParams  = [];
if ($current !== $baseReal) {
    $parentRel = dirname($relCurrent);
    if ($parentRel === '.') $parentRel = '';
    $upParams = array_merge($homeParams, $parentRel !== '' ? ['dir' => $parentRel] : []);
}

$rawRel = ($viewFile !== '') ? mc_rel($current . DIRECTORY_SEPARATOR . $viewFile, $baseReal) : '';

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Memory Coder</title>
<style>
/* ── Reset & base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body   { height: 100%; overflow: hidden; background: #0f1113; color: #dde4ed;
               font-family: system-ui, Arial, sans-serif; font-size: 14px; }

/* ── Two-panel shell ── */
.mc-shell    { display: flex; height: 100vh; }

/* LEFT panel — file browser */
.mc-left     { width: 340px; min-width: 220px; flex-shrink: 0;
               display: flex; flex-direction: column;
               border-right: 1px solid #1e2530; background: #0c0e10; }
.mc-left-head { flex-shrink: 0; padding: .6rem .8rem;
                border-bottom: 1px solid #1e2530; background: #0f1113; }
.mc-left-body { flex: 1; overflow-y: auto; overflow-x: hidden; }

/* RIGHT panel — viewer + summary, simple scroll */
.mc-right    { flex: 1; min-width: 0; display: flex; flex-direction: column;
               overflow-y: auto; overflow-x: hidden; }
.mc-right-head { flex-shrink: 0; display: flex; align-items: center; gap: .5rem;
                 padding: .55rem .9rem; border-bottom: 1px solid #1e2530;
                 background: #0f1113; position: sticky; top: 0; z-index: 10; }
.mc-right-empty { flex: 1; display: flex; align-items: center; justify-content: center;
                  color: #4a5568; font-size: .9rem; }

/* ── Shared buttons ── */
a, button    { color: #dde4ed; }
.mc-btn      { display: inline-block; padding: .18rem .5rem; font-size: .78rem;
               border-radius: 4px; border: 1px solid #2a3240;
               background: rgba(255,255,255,.05); color: #dde4ed;
               text-decoration: none; cursor: pointer; white-space: nowrap;
               line-height: 1.5; }
.mc-btn:hover       { background: rgba(255,255,255,.13); text-decoration: none; }
.mc-btn-ai          { border-color: rgba(147,51,234,.45); background: rgba(147,51,234,.1); }
.mc-btn-ai:hover    { background: rgba(147,51,234,.25); }
.mc-btn-ai.has-sum  { border-color: rgba(34,197,94,.45); background: rgba(34,197,94,.09); }
.mc-btn-danger      { border-color: rgba(220,38,38,.4);  background: rgba(220,38,38,.08); }
.mc-btn-danger:hover{ background: rgba(220,38,38,.2); }

/* ── Left head: title row ── */
.mc-title-row { display: flex; align-items: center; gap: .5rem; margin-bottom: .5rem; }
.mc-title-row h1 { font-size: 1rem; font-weight: 600; flex: 1; }

/* ── Config form (collapsible) ── */
.mc-cfg      { font-size: .78rem; }
.mc-cfg-row  { display: flex; gap: .35rem; align-items: center; margin-bottom: .3rem; flex-wrap: wrap; }
.mc-cfg-row label { color: #7a8694; white-space: nowrap; }
.mc-cfg-row input[type=text] { flex: 1; min-width: 100px; font-size: .77rem;
                                padding: .18rem .35rem; background: #161b22;
                                border: 1px solid #2a3240; border-radius: 3px; color: #dde4ed; }
.mc-cfg-row select { font-size: .77rem; padding: .18rem .3rem;
                     background: #161b22; border: 1px solid #2a3240;
                     border-radius: 3px; color: #dde4ed; max-width: 160px; }

/* ── Breadcrumb ── */
.mc-bread    { font-size: .78rem; color: #7a8694; padding: .4rem .8rem .2rem;
               border-bottom: 1px solid #1a1f27; }
.mc-bread a  { color: #4f9cf9; text-decoration: none; }
.mc-bread a:hover { text-decoration: underline; }
.mc-bread span { margin: 0 .2rem; }

/* ── Flash ── */
.mc-flash    { margin: .4rem .8rem; padding: .4rem .7rem; border-radius: 4px;
               font-size: .8rem; }
.mc-flash.ok  { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); color: #86efac; }
.mc-flash.err { background: rgba(220,38,38,.12); border: 1px solid rgba(220,38,38,.3); color: #fca5a5; }

/* ── File table ── */
.mc-table    { width: 100%; border-collapse: collapse; font-size: .8rem; }
.mc-table th { text-align: left; padding: .35rem .7rem;
               border-bottom: 1px solid #1e2530; color: #7a8694;
               font-weight: 500; font-size: .73rem; text-transform: uppercase;
               position: sticky; top: 0; background: #0c0e10; z-index: 5; }
.mc-table td { padding: .32rem .7rem; border-bottom: 1px solid #131820;
               vertical-align: middle; }
.mc-table tr:hover td { background: rgba(255,255,255,.03); }
.mc-td-name  { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mc-td-name a { color: #4f9cf9; text-decoration: none; }
.mc-td-name a:hover { text-decoration: underline; }
.mc-muted    { color: #7a8694; font-size: .75rem; white-space: nowrap; }
.mc-badge    { font-size: .68rem; color: #4ade80; }
.mc-actions  { display: flex; gap: .25rem; flex-wrap: nowrap; }

/* ── Right: code viewer ── */
.mc-code-wrap { background: #080a0c; }
.mc-code-wrap pre { margin: 0; padding: .9rem 1rem; font-size: .78rem;
                    line-height: 1.55; white-space: pre; font-family: 'Consolas','Courier New',monospace;
                    overflow-x: auto; color: #c9d1d9; }

/* ── Right: AI / summary section ── */
.mc-sum-bar  { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center;
               padding: .55rem .9rem; background: rgba(88,28,135,.08);
               border-top: 1px solid #1e2530; border-bottom: 1px solid #1e2530; }
.mc-sum-bar label { font-size: .78rem; color: #7a8694; }
.mc-sum-bar select { font-size: .78rem; padding: .15rem .3rem; background: #161b22;
                     border: 1px solid #2a3240; border-radius: 3px; color: #dde4ed; max-width: 150px; }
.mc-spinner  { font-size: .78rem; color: #7a8694; display: none; }
.mc-spinner.on { display: inline; }

.mc-sum-box  { padding: 1rem 1.1rem; }
.mc-sum-box h4 { font-size: .82rem; color: #4ade80; margin-bottom: .5rem; font-weight: 500; }
.mc-sum-box p { font-size: .84rem; line-height: 1.7; white-space: pre-wrap; color: #c9d1d9; }
.mc-sum-none { padding: .9rem 1.1rem; color: #4a5568; font-size: .82rem; font-style: italic; }

/* ── Toast ── */
#mc-toast    { display: none; position: fixed; bottom: 1.2rem; right: 1.2rem;
               max-width: 360px; padding: .7rem 2rem .7rem 1rem;
               border-radius: 6px; font-size: .82rem; line-height: 1.5;
               z-index: 9999; box-shadow: 0 4px 20px rgba(0,0,0,.5);
               white-space: pre-wrap; word-break: break-word; }
</style>
</head>
<body>
<div class="mc-shell">

<!-- ════════════════ LEFT PANEL ════════════════ -->
<div class="mc-left">

  <!-- sticky header -->
  <div class="mc-left-head">

    <div class="mc-title-row">
      <h1>Memory Coder</h1>
      <a href="/ai/admin/" class="mc-btn">← Admin</a>
    </div>

    <?php if ($flash !== ''): ?>
      <div class="mc-flash <?= $flashType === 'error' ? 'err' : 'ok' ?>"><?= mc_e($flash) ?></div>
    <?php endif; ?>

    <div class="mc-cfg">
      <!-- Root dir -->
      <form method="post" class="mc-cfg-row">
        <input type="hidden" name="csrf" value="<?= mc_e($csrf) ?>">
        <input type="hidden" name="_mc_basedir" value="1">
        <label>Root:</label>
        <input type="text" name="mc_base_dir" value="<?= mc_e($baseDir) ?>">
        <button type="submit" class="mc-btn">Set</button>
      </form>

      <!-- AI user + hidden toggle -->
      <div class="mc-cfg-row">
        <?php if ($aiUsers): ?>
          <label>AI:</label>
          <form method="get" style="display:contents;">
            <?php if ($reqDir !== ''): ?>
              <input type="hidden" name="dir" value="<?= mc_e($reqDir) ?>">
            <?php endif; ?>
            <?php if ($showHidden): ?>
              <input type="hidden" name="hidden" value="1">
            <?php endif; ?>
            <select name="ai_user" onchange="this.form.submit()">
              <?php foreach ($aiUsers as $u): ?>
                <option value="<?= (int)$u['user_id'] ?>"<?= $selectedAiUser === (int)$u['user_id'] ? ' selected' : '' ?>>
                  <?= mc_e($u['display_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        <?php else: ?>
          <span style="color:#7a8694;font-size:.75rem;">No AI users — <a href="/ai/admin/?section=ai_users" style="color:#4f9cf9;">configure</a></span>
        <?php endif; ?>
        <a href="?<?= http_build_query($toggleParams) ?>" class="mc-btn" style="margin-left:auto;">
          <?= $showHidden ? 'Hide .' : 'Show .' ?>
        </a>
      </div>
    </div>

  </div><!-- /.mc-left-head -->

  <!-- breadcrumb -->
  <div class="mc-bread">
    <a href="?<?= http_build_query($homeParams) ?>"><?= mc_e(basename($baseReal) ?: $baseReal) ?></a>
    <?php
    $accum = '';
    foreach ($breadParts as $part) {
        if ($part === '') continue;
        $accum = $accum === '' ? $part : $accum . '/' . $part;
        $pParams = array_merge($homeParams, ['dir' => $accum]);
        echo '<span>/</span><a href="?' . http_build_query($pParams) . '">' . mc_e($part) . '</a>';
    }
    ?>
    <?php if ($current !== $baseReal): ?>
      &nbsp;<a href="?<?= http_build_query($upParams) ?>" class="mc-btn" style="font-size:.7rem;">↑ up</a>
    <?php endif; ?>
  </div>

  <!-- file table -->
  <div class="mc-left-body">
    <table class="mc-table">
      <thead>
        <tr><th>Name</th><th>Size</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
          $rRel       = $r['rel'];
          $rName      = $r['name'];
          $isDir      = $r['is_dir'];
          $hasSummary = !$isDir && isset($summaryMap[$r['fullN']]);
          $browseP    = array_merge($homeParams, ['dir' => $rRel]);
          $viewP      = array_merge($homeParams, ['dir' => $relCurrent, 'view' => $rName]);
          $isActive   = ($viewFile === $rName && !$isDir);
      ?>
      <tr<?= $isActive ? ' style="background:rgba(79,156,249,.08);"' : '' ?>>
        <td class="mc-td-name" title="<?= mc_e($rName) ?>">
          <?php if ($isDir): ?>
            <a href="?<?= http_build_query($browseP) ?>">📁 <?= mc_e($rName) ?></a>
          <?php else: ?>
            <a href="?<?= http_build_query($viewP) ?>" style="color:<?= $isActive ? '#4f9cf9' : '#dde4ed' ?>;"><?= mc_e($rName) ?></a>
            <?php if ($hasSummary): ?><span class="mc-badge"> ✓</span><?php endif; ?>
          <?php endif; ?>
        </td>
        <td class="mc-muted"><?= $isDir ? '' : mc_human_size($r['size']) ?></td>
        <td>
          <?php if ($isDir): ?>
            <div class="mc-actions">
              <a href="?<?= http_build_query($browseP) ?>" class="mc-btn">Open</a>
            </div>
          <?php else: ?>
            <div class="mc-actions">
              <?php if ($aiUsers): ?>
                <button type="button"
                  class="mc-btn mc-btn-ai<?= $hasSummary ? ' has-sum' : '' ?>"
                  data-rel="<?= mc_e($rRel) ?>" data-name="<?= mc_e($rName) ?>"
                  onclick="mcSummarize(this)"><?= $hasSummary ? '↺' : 'AI' ?></button>
              <?php endif; ?>
              <a href="?raw=1&rel=<?= rawurlencode($rRel) ?>&dl=1" class="mc-btn" title="Download">↓</a>
            </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="3" class="mc-muted" style="padding:.6rem .7rem;">Empty.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div><!-- /.mc-left-body -->

</div><!-- /.mc-left -->

<!-- ════════════════ RIGHT PANEL ════════════════ -->
<div class="mc-right">

<?php if ($viewFile !== ''): ?>

  <!-- sticky file header -->
  <div class="mc-right-head">
    <strong style="font-size:.9rem;"><?= mc_e($viewFile) ?></strong>
    <a href="?raw=1&rel=<?= rawurlencode($rawRel) ?>" target="_blank" class="mc-btn">Raw</a>
    <a href="?raw=1&rel=<?= rawurlencode($rawRel) ?>&dl=1" class="mc-btn">Download</a>
  </div>

  <!-- code -->
  <div class="mc-code-wrap">
    <pre><?= mc_e($viewContent) ?></pre>
  </div>

  <!-- AI summarize bar -->
  <div class="mc-sum-bar">
    <?php if ($aiUsers): ?>
      <label>AI:</label>
      <select id="mc-view-ai-user">
        <?php foreach ($aiUsers as $u): ?>
          <option value="<?= (int)$u['user_id'] ?>"<?= $selectedAiUser === (int)$u['user_id'] ? ' selected' : '' ?>>
            <?= mc_e($u['display_name']) ?> — <?= mc_e($u['model_default']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="mc-btn mc-btn-ai"
        data-rel="<?= mc_e($rawRel) ?>" data-name="<?= mc_e($viewFile) ?>"
        onclick="mcSummarizeViewer(this)"><?= $viewSummary ? 'Re-summarize' : 'Summarize' ?></button>
      <span class="mc-spinner" id="mc-view-spinner">Generating…</span>
    <?php else: ?>
      <span class="mc-muted">No AI users configured.</span>
    <?php endif; ?>
  </div>

  <!-- summary -->
  <?php if ($viewSummary): ?>
    <div class="mc-sum-box" id="mc-sum-display">
      <h4>Summary <span style="font-weight:400;color:#7a8694;font-size:.75rem;">— <?= mc_e($viewSummary['updated_at']) ?></span></h4>
      <p id="mc-sum-text"><?= mc_e($viewSummary['summary']) ?></p>
      <form method="post" style="margin-top:.8rem;">
        <input type="hidden" name="csrf" value="<?= mc_e($csrf) ?>">
        <input type="hidden" name="_mc_del_summary" value="1">
        <input type="hidden" name="file_path" value="<?= mc_e($viewFullN) ?>">
        <button type="submit" class="mc-btn mc-btn-danger"
          onclick="return confirm('Delete summary?')">Delete summary</button>
      </form>
    </div>
  <?php else: ?>
    <div class="mc-sum-none" id="mc-sum-display" style="display:none;">
      <h4 style="color:#4ade80;margin-bottom:.5rem;">Summary</h4>
      <p id="mc-sum-text"></p>
    </div>
    <p class="mc-sum-none" id="mc-sum-placeholder">No summary yet — click Summarize above.</p>
  <?php endif; ?>

<?php else: ?>
  <div class="mc-right-empty">Select a file on the left to view it.</div>
<?php endif; ?>

</div><!-- /.mc-right -->
</div><!-- /.mc-shell -->

<div id="mc-toast"></div>

<script>
(function () {
  var csrf     = <?= json_encode($csrf) ?>;
  var selfUrl  = <?= json_encode(strtok($_SERVER['REQUEST_URI'] ?? '', '?')) ?>;
  var defAiId  = <?= json_encode($selectedAiUser) ?>;

  function toast(msg, err) {
    var el = document.getElementById('mc-toast');
    el.textContent = msg;
    el.style.background   = err ? '#1f0d0d' : '#0d1f14';
    el.style.border       = '1px solid ' + (err ? 'rgba(220,38,38,.4)' : 'rgba(34,197,94,.4)');
    el.style.color        = err ? '#fca5a5' : '#d1fae5';
    el.style.display      = 'block';
    clearTimeout(el._t);
    el._t = setTimeout(function(){ el.style.display='none'; }, err ? 8000 : 6000);
  }

  function doSummarize(rel, uid, cb) {
    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('_mc_summarize', '1');
    fd.append('rel_path', rel);
    fd.append('ai_user_id', uid);
    fetch(selfUrl, { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d){ d.ok ? cb(d.summary) : toast('Error: ' + d.error, true); })
      .catch(function(e){ toast('Request failed: ' + e, true); });
  }

  // Left-panel Summarize button (AI / ↺)
  window.mcSummarize = function(btn) {
    var rel  = btn.getAttribute('data-rel');
    var name = btn.getAttribute('data-name');
    var sel  = document.getElementById('mc-view-ai-user');
    var uid  = sel ? parseInt(sel.value, 10) : defAiId;
    btn.disabled = true; btn.textContent = '…';
    doSummarize(rel, uid, function(summary) {
      btn.disabled = false; btn.textContent = '↺';
      btn.classList.add('has-sum');
      var td = btn.closest('tr').querySelector('.mc-td-name');
      if (td && !td.querySelector('.mc-badge')) {
        var b = document.createElement('span'); b.className = 'mc-badge'; b.textContent = ' ✓';
        td.appendChild(b);
      }
      toast(name + '\n\n' + summary.substring(0, 300) + (summary.length > 300 ? '…' : ''), false);
    });
  };

  // Right-panel Summarize button
  window.mcSummarizeViewer = function(btn) {
    var rel  = btn.getAttribute('data-rel');
    var sel  = document.getElementById('mc-view-ai-user');
    var uid  = sel ? parseInt(sel.value, 10) : defAiId;
    var spin = document.getElementById('mc-view-spinner');
    var disp = document.getElementById('mc-sum-display');
    var txt  = document.getElementById('mc-sum-text');
    var ph   = document.getElementById('mc-sum-placeholder');
    btn.disabled = true;
    if (spin) spin.classList.add('on');
    doSummarize(rel, uid, function(summary) {
      btn.disabled = false; btn.textContent = 'Re-summarize';
      if (spin) spin.classList.remove('on');
      if (txt)  txt.textContent = summary;
      if (disp) { disp.style.display = ''; var h4 = disp.querySelector('h4');
        if (h4) h4.innerHTML = 'Summary <span style="font-weight:400;color:#7a8694;font-size:.75rem;">— just now</span>'; }
      if (ph) ph.style.display = 'none';
    });
  };

})();
</script>
</body>
</html>
