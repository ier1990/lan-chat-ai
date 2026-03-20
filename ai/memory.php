<?php
/**
 * memory.php — User-facing memory management page.
 *
 * Every authenticated user can:
 *   - Create private memories (only they and admins can see)
 *   - Create global memories (all users can read; non-secret ones are available as AI context)
 *   - Mark a memory as secret (never injected into AI prompts regardless of scope)
 *   - Edit and delete their own memories
 *   - Search their visible memories (own private + all global)
 */
require_once __DIR__ . '/lib/bootstrap.php';

if (!file_exists(AI_INSTALLED_FLAG)) {
    Util::redirect('/ai/install.php');
}

Auth::requireLogin();
Memory::ensureTable();

$flash     = '';
$flashType = 'success';
$userId    = (int) Auth::id();

// ── Action handling ────────────────────────────────────────────────────────
if (Util::isPost()) {
    Util::requireCsrf();
    $memAction = Util::post('_mem_action');

    if ($memAction === 'save') {
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '') {
            $flash     = 'Content is required.';
            $flashType = 'error';
        } else {
            Memory::save([
                'id'        => (int) Util::post('mem_id'),
                'title'     => Util::post('title'),
                'content'   => $content,
                'tags'      => Util::post('tags'),
                'scope'     => Util::post('scope', 'private'),
                'is_secret' => Util::post('is_secret', '0') === '1' ? 1 : 0,
            ], $userId);
            $flash = 'Memory saved.';
        }
    }

    if ($memAction === 'delete') {
        $memId = (int) Util::post('mem_id');
        if (Memory::delete($memId, $userId)) {
            $flash = 'Memory deleted.';
        } else {
            $flash     = 'Memory not found or access denied.';
            $flashType = 'error';
        }
    }
}

// ── Data loading ───────────────────────────────────────────────────────────
$search   = trim(Util::get('q', ''));
$memories = $search !== '' ? Memory::search($search, $userId) : Memory::forUser($userId);

// Edit mode: pre-fill form with an existing memory.
$editId  = (int) Util::get('edit');
$editMem = null;
if ($editId > 0) {
    $candidate = Memory::getById($editId);
    // Only allow editing own memories (not other users' global memories).
    if ($candidate && (int) $candidate['owner_id'] === $userId) {
        $editMem = $candidate;
    }
}

// Variables required by layout.php.
$title       = 'Memory — ' . Settings::get('app.site_name', 'AI Chat');
$rooms       = Rooms::forUser($userId);
$personas    = Personas::getAll();
$currentRoom = null;
$messages    = [];
$roomSettings = [];
$dmMeta      = null;
$view        = 'memory';

require __DIR__ . '/view/layout.php';
