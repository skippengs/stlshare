<h1>Admin</h1>

<div class="card">
  <p class="muted" style="margin:0;">Total disk usage: <strong style="color:var(--text);"><?= e(human_filesize($totalUsage)) ?></strong> across <?= (int) $modelCount ?> models.</p>
</div>

<h2>Pending approval</h2>
<?php if (!$pending): ?>
  <p class="muted">No pending signups.</p>
<?php else: ?>
  <div class="card">
    <table>
      <tr><th>Name</th><th>Email</th><th>Requested</th><th></th></tr>
      <?php foreach ($pending as $u): ?>
        <tr>
          <td><?= e($u['display_name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td class="muted"><?= e($u['created_at']) ?></td>
          <td>
            <form class="inline" method="post" action="/admin/users/<?= (int) $u['id'] ?>/approve">
              <?= csrf_field() ?>
              <button type="submit">Approve</button>
            </form>
            <form class="inline" method="post" action="/admin/users/<?= (int) $u['id'] ?>/reject">
              <?= csrf_field() ?>
              <button type="submit" class="danger">Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>

<h2>All members</h2>
<div class="card">
  <table>
    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr>
    <?php foreach ($others as $u): ?>
      <tr>
        <td><?= e($u['display_name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="badge"><?= e($u['role']) ?></span></td>
        <td><?= e($u['status']) ?></td>
        <td>
          <?php if ($u['status'] === 'approved' && $u['role'] !== 'admin'): ?>
            <form class="inline" method="post" action="/admin/users/<?= (int) $u['id'] ?>/suspend">
              <?= csrf_field() ?>
              <button type="submit" class="secondary">Suspend</button>
            </form>
          <?php elseif ($u['status'] === 'suspended'): ?>
            <form class="inline" method="post" action="/admin/users/<?= (int) $u['id'] ?>/reinstate">
              <?= csrf_field() ?>
              <button type="submit">Reinstate</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<h2>Storage usage &amp; quota overrides</h2>
<div class="card">
  <table>
    <tr><th>Name</th><th>Used</th><th>Quota override</th></tr>
    <?php foreach ($usageByUser as $u): ?>
      <tr>
        <td><?= e($u['display_name']) ?> <span class="muted">(<?= e($u['email']) ?>)</span></td>
        <td><?= e(human_filesize((int) $u['used_bytes'])) ?></td>
        <td>
          <form class="inline" method="post" action="/admin/users/<?= (int) $u['id'] ?>/quota" style="display:flex;gap:0.4rem;align-items:center;">
            <?= csrf_field() ?>
            <input type="number" step="0.1" min="0" name="quota_gb" style="width:80px;margin:0;"
                   value="<?= $u['storage_quota_bytes'] !== null ? e((string) round($u['storage_quota_bytes'] / 1073741824, 2)) : '' ?>"
                   placeholder="default">
            <span class="muted">GB</span>
            <button type="submit">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
