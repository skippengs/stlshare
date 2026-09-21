<h1>My collections</h1>

<div class="card" style="max-width:440px;">
  <h2 style="margin-top:0;">New collection</h2>
  <form method="post" action="/collections">
    <?= csrf_field() ?>
    <label for="name">Name</label>
    <input type="text" id="name" name="name" required>

    <label for="description">Description</label>
    <textarea id="description" name="description" rows="2"></textarea>

    <label class="checkbox-label" style="margin-top:0.9rem;">
      <input type="checkbox" name="is_public">
      <span>Visible to other approved members</span>
    </label>

    <button type="submit" style="margin-top:1.1rem;">Create</button>
  </form>
</div>

<?php if (!$collections): ?>
  <div class="empty-state card">
    <p>No collections yet.</p>
  </div>
<?php else: ?>
  <div class="card">
    <table>
      <tr><th>Name</th><th>Visibility</th><th>Created</th><th></th></tr>
      <?php foreach ($collections as $c): ?>
        <tr>
          <td><a href="/collections/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a></td>
          <td><span class="badge"><?= $c['is_public'] ? 'Public (members)' : 'Private' ?></span></td>
          <td class="muted"><?= e($c['created_at']) ?></td>
          <td>
            <form class="inline" method="post" action="/collections/<?= (int) $c['id'] ?>/delete" onsubmit="return confirm('Delete this collection?');">
              <?= csrf_field() ?>
              <button type="submit" class="danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>
