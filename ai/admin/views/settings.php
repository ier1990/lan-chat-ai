<h2>Settings</h2>
<div class="tab-bar">
  <?php foreach ($settingsTabs as $st): ?>
    <a href="<?= Util::e($buildAdminUrl(['section' => 'settings', 'tab' => $st])) ?>"
       class="tab<?= UI::activeClass($tab === $st) ?>">
      <?= Util::e(ucfirst($st)) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($tabFields): ?>
  <form method="post" class="settings-form" action="<?= Util::e($buildAdminUrl(['section' => 'settings', 'tab' => $tab])) ?>">
    <input type="hidden" name="_save_settings" value="1">
    <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
    <?php foreach ($tabFields as $meta): ?>
      <?= SettingsMeta::renderField($meta) ?>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary">Save Settings</button>
  </form>
<?php else: ?>
  <p class="muted">Select a tab to edit settings.</p>
<?php endif; ?>
