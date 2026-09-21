<h1>My favorites</h1>

<?php if (!$models): ?>
  <div class="empty-state card">
    <p>No favorites yet. Browse the <a href="/">library</a> and add some.</p>
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
<?php endif; ?>
