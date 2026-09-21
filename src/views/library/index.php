<div class="page-header">
  <h1 style="margin:0;">Library</h1>
  <a class="btn" href="/upload">Upload model</a>
</div>

<form method="get" action="/" class="search-box">
  <input type="text" name="q" placeholder="Search by title…" value="<?= e($search) ?>">
</form>

<?php if (!$models): ?>
  <div class="empty-state card">
    <p>No models yet.</p>
    <a class="btn" href="/upload">Upload the first one</a>
  </div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($models as $model): ?>
      <a class="model-card" href="/models/<?= (int) $model['id'] ?>">
        <?php if ($model['thumbnail_filename']): ?>
          <img src="/models/<?= (int) $model['id'] ?>/thumb" alt="">
        <?php else: ?>
          <div class="model-thumb-placeholder">No preview yet</div>
        <?php endif; ?>
        <div class="model-card-body">
          <h3><?= e($model['title']) ?></h3>
          <p>by <?= e($model['owner_name']) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p === $page): ?>
          <strong><?= $p ?></strong>
        <?php else: ?>
          <a href="?q=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
