<h2>Webhook Sources</h2>
<?php $webhooks = DB::fetchAll('SELECT ws.*, r.name AS room_name FROM webhook_sources ws JOIN rooms r ON r.id = ws.target_room_id'); ?>
<?php if ($webhooks): ?>
  <table class="data-table">
    <thead><tr><th>Name</th><th>Key</th><th>Target Room</th><th>Enabled</th></tr></thead>
    <tbody>
      <?php foreach ($webhooks as $wh): ?>
        <tr>
          <td><?= Util::e($wh['name']) ?></td>
          <td><code><?= Util::e($wh['webhook_key']) ?></code></td>
          <td><?= Util::e($wh['room_name']) ?></td>
          <td><?= $wh['is_enabled'] ? '✓' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class="muted">No webhooks yet. Create one from the Rooms section.</p>
  <p class="muted">POST to <code>/ai/webhook.php?key=YOUR_KEY</code> with a JSON body containing <code>text</code>.</p>
<?php endif; ?>
