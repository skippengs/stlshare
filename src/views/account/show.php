<h1>My account</h1>

<div class="card" style="max-width:480px;">
  <p style="margin:0;"><strong><?= e($user['display_name']) ?></strong><br><span class="muted"><?= e($user['email']) ?></span></p>
  <p class="muted">Role: <span class="badge"><?= e($user['role']) ?></span> · Member since <?= e($user['created_at']) ?></p>

  <div class="muted" style="margin-top:1rem;">
    Storage: <?= e(human_filesize($usedBytes)) ?> used of <?= e(human_filesize($quotaBytes)) ?>
    <div class="quota-bar"><div class="quota-bar-fill" style="width:<?= min(100, $quotaBytes > 0 ? (int) round($usedBytes / $quotaBytes * 100) : 0) ?>%"></div></div>
  </div>
</div>

<div class="card card-danger" style="max-width:480px;">
  <h2 style="margin-top:0;">Delete account</h2>
  <p class="muted">This permanently deletes your account, uploaded models, favorites, and collections. This cannot be undone.</p>
  <form method="post" action="/account/delete" onsubmit="return confirm('Really delete your account and all your models? This cannot be undone.');">
    <?= csrf_field() ?>
    <label for="password">Confirm your password</label>
    <input type="password" id="password" name="password" required>
    <button type="submit" class="danger" style="margin-top:1.1rem;">Delete my account</button>
  </form>
</div>
