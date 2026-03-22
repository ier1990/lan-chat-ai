<h2>Memory Store</h2>
<p class="muted">All user memories. Admins can see and delete any entry. Only <strong>Global + non-secret</strong> entries are injected as AI context.</p>

<details class="mem-admin-create">
  <summary class="btn btn-small btn-secondary" style="display:inline-block;cursor:pointer;">+ Add Global Memory</summary>
  <form method="post" action="<?= Util::e($buildAdminUrl(['section' => 'memories'])) ?>" class="memory-form" style="margin-top:.75rem">
    <input type="hidden" name="csrf"           value="<?= Util::e(Util::csrfToken()) ?>">
    <input type="hidden" name="_memory_action" value="save">
    <input type="hidden" name="mem_id"         value="0">
    <input type="hidden" name="scope"          value="global">
    <div class="form-row">
      <label>Title</label>
      <input type="text" name="title" class="input" maxlength="200" placeholder="Descriptive label…">
    </div>
    <div class="form-row">
      <label>Content <span class="required">*</span></label>
      <textarea name="content" class="input mem-content-area" rows="3" required placeholder="Fact or note to share with all users and the AI…"></textarea>
    </div>
    <div class="form-row">
      <label>Tags</label>
      <input type="text" name="tags" class="input" maxlength="500" placeholder="comma, separated">
    </div>
    <div class="form-row">
      <label class="checkbox-label">
        <input type="checkbox" name="is_secret" value="1"> Secret (hidden from AI)
      </label>
    </div>
    <button type="submit" class="btn btn-primary">Save Global Memory</button>
  </form>
</details>

<?php if (!$allMemories): ?>
  <p class="muted" style="margin-top:1rem">No memories stored yet.</p>
<?php else: ?>
  <table class="data-table" style="margin-top:1rem">
    <thead>
      <tr>
        <th>Owner</th><th>Scope</th><th>Secret</th><th>Title</th>
        <th>Content preview</th><th>Tags</th><th>Updated</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($allMemories as $mem): ?>
        <tr>
          <td class="muted"><?= Util::e($mem['owner_username'] ?? '—') ?></td>
          <td>
            <span class="badge <?= $mem['scope'] === 'global' ? 'badge-accent' : 'badge-neutral' ?>">
              <?= Util::e($mem['scope']) ?>
            </span>
          </td>
          <td><?= $mem['is_secret'] ? '🔒' : '' ?></td>
          <td><?= Util::e($mem['title'] ?: '—') ?></td>
          <td class="muted"><?= Util::e(mb_strimwidth($mem['content'], 0, 80, '…')) ?></td>
          <td class="muted"><?= Util::e($mem['tags']) ?></td>
          <td class="muted"><?= Util::e($mem['updated_at']) ?></td>
          <td>
            <form method="post" action="<?= Util::e($buildAdminUrl(['section' => 'memories'])) ?>"
                  onsubmit="return confirm('Delete this memory?')" class="inline-form">
              <input type="hidden" name="csrf"           value="<?= Util::e(Util::csrfToken()) ?>">
              <input type="hidden" name="_memory_action" value="delete">
              <input type="hidden" name="mem_id"         value="<?= (int) $mem['id'] ?>">
              <button class="btn btn-small btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
