<?php
/**
 * view/memory.php — Memory management UI.
 * Rendered inside layout.php when $view === 'memory'.
 */
$csrfToken = Util::csrfToken();
$user      = Auth::user();
$isAdmin   = Auth::isAdmin();

// Scope labels / badge classes.
$scopeLabel = ['private' => 'Private', 'global' => 'Global'];
$scopeClass = ['private' => 'badge-neutral', 'global' => 'badge-accent'];
?>

<div class="memory-page">

  <!-- ── Page header ─────────────────────────────────────────────────── -->
  <div class="memory-header">
    <h1 class="memory-title">🗂 Memory</h1>
    <p class="memory-subtitle muted">
      Store notes, credentials, and facts you want to remember.
      <strong>Private</strong> entries are yours alone. <strong>Global</strong> non-secret
      entries are also available as context when the AI replies.
    </p>
  </div>

  <!-- ── Create / Edit form ──────────────────────────────────────────── -->
  <section class="memory-form-section">
    <h2><?= $editMem ? 'Edit Memory' : 'New Memory' ?></h2>
    <form method="post" action="/ai/memory.php<?= $editMem ? '?edit=' . (int) $editMem['id'] : '' ?>" class="memory-form">
      <input type="hidden" name="csrf"        value="<?= Util::e($csrfToken) ?>">
      <input type="hidden" name="_mem_action" value="save">
      <input type="hidden" name="mem_id"      value="<?= $editMem ? (int) $editMem['id'] : 0 ?>">

      <div class="form-row">
        <label for="mem-title">Title <span class="muted">(optional)</span></label>
        <input type="text" id="mem-title" name="title" class="input"
               maxlength="200"
               placeholder="e.g. AWS Access Key, Company Name…"
               value="<?= Util::e($editMem['title'] ?? '') ?>">
      </div>

      <div class="form-row">
        <label for="mem-content">Content <span class="required">*</span></label>
        <textarea id="mem-content" name="content" class="input mem-content-area"
                  rows="4" required
                  placeholder="The actual value or note you want to remember…"><?= Util::e($editMem['content'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <label for="mem-tags">Tags <span class="muted">(comma-separated)</span></label>
        <input type="text" id="mem-tags" name="tags" class="input"
               maxlength="500"
               placeholder="api-key, config, server…"
               value="<?= Util::e($editMem['tags'] ?? '') ?>">
      </div>

      <div class="form-row form-row-inline">
        <div>
          <label for="mem-scope">Visibility</label>
          <select id="mem-scope" name="scope" class="input select-small">
            <option value="private" <?= ($editMem['scope'] ?? 'private') === 'private' ? 'selected' : '' ?>>Private (only me)</option>
            <option value="global"  <?= ($editMem['scope'] ?? '') === 'global'  ? 'selected' : '' ?>>Global (all users)</option>
          </select>
        </div>
        <div class="mem-secret-wrap">
          <label class="checkbox-label">
            <input type="checkbox" name="is_secret" value="1"
                   <?= !empty($editMem['is_secret']) ? 'checked' : '' ?>>
            <span>Secret</span>
            <span class="muted help-text">(hidden from AI even if global)</span>
          </label>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <?= $editMem ? 'Update Memory' : 'Save Memory' ?>
        </button>
        <?php if ($editMem): ?>
          <a href="/ai/memory.php" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <!-- ── Search ──────────────────────────────────────────────────────── -->
  <section class="memory-search-section">
    <form method="get" action="/ai/memory.php" class="mem-search-form">
      <input type="search" name="q" class="input mem-search-input"
             value="<?= Util::e($search) ?>"
             placeholder="Search memories…"
             autocomplete="off">
      <button type="submit" class="btn btn-secondary">Search</button>
      <?php if ($search !== ''): ?>
        <a href="/ai/memory.php" class="btn btn-secondary">Clear</a>
      <?php endif; ?>
    </form>
    <?php if ($search !== ''): ?>
      <p class="muted"><?= count($memories) ?> result(s) for "<em><?= Util::e($search) ?></em>"</p>
    <?php endif; ?>
  </section>

  <!-- ── Memory list ─────────────────────────────────────────────────── -->
  <section class="memory-list-section">
    <?php if (!$memories): ?>
      <p class="muted memory-empty">
        <?= $search !== '' ? 'No memories matched your search.' : 'No memories yet — use the form above to add your first one.' ?>
      </p>
    <?php else: ?>
      <div class="memory-list">
        <?php foreach ($memories as $mem):
          $isMine    = (int) $mem['owner_id'] === $userId;
          $scopeKey  = $mem['scope'] ?? 'private';
          $isSecret  = !empty($mem['is_secret']);
          $memTitle  = trim((string) ($mem['title'] ?? ''));
          $preview   = mb_strimwidth(strip_tags($mem['content']), 0, 180, '…');
          $tags      = array_filter(array_map('trim', explode(',', (string) ($mem['tags'] ?? ''))));
          $updatedAt = $mem['updated_at'] ?? '';
        ?>
          <div class="memory-card<?= $isSecret ? ' memory-card--secret' : '' ?><?= !$isMine ? ' memory-card--other' : '' ?>">
            <div class="memory-card-header">
              <span class="memory-card-title">
                <?= $memTitle !== '' ? Util::e($memTitle) : '<em class="muted">Untitled</em>' ?>
              </span>
              <span class="badge <?= $scopeClass[$scopeKey] ?? 'badge-neutral' ?>">
                <?= $scopeLabel[$scopeKey] ?? $scopeKey ?>
              </span>
              <?php if ($isSecret): ?>
                <span class="badge badge-secret" title="Secret — AI never sees this">🔒 Secret</span>
              <?php endif; ?>
              <?php if (!$isMine): ?>
                <span class="badge badge-neutral muted" title="Created by another user">shared</span>
              <?php endif; ?>
            </div>

            <div class="memory-card-content"><?= Util::e($preview) ?></div>

            <?php if ($tags): ?>
              <div class="memory-card-tags">
                <?php foreach ($tags as $tag): ?>
                  <a href="/ai/memory.php?q=<?= urlencode($tag) ?>" class="mem-tag"><?= Util::e($tag) ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="memory-card-footer">
              <span class="muted memory-card-date"><?= Util::e($updatedAt) ?></span>
              <?php if ($isMine): ?>
                <div class="memory-card-actions">
                  <a href="/ai/memory.php?edit=<?= (int) $mem['id'] ?>" class="btn btn-small btn-secondary">Edit</a>
                  <form method="post" action="/ai/memory.php" class="inline-form"
                        onsubmit="return confirm('Delete this memory?')">
                    <input type="hidden" name="csrf"        value="<?= Util::e($csrfToken) ?>">
                    <input type="hidden" name="_mem_action" value="delete">
                    <input type="hidden" name="mem_id"      value="<?= (int) $mem['id'] ?>">
                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</div><!-- .memory-page -->
