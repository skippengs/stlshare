<h1><?= e($collection['name']) ?></h1>
<p class="muted">by <?= e($collection['owner_name']) ?> · <span class="badge"><?= $collection['is_public'] ? 'Public' : 'Private' ?></span></p>
<?php if ($collection['description']): ?><p><?= nl2br(e($collection['description'])) ?></p><?php endif; ?>

<?php if (!$models): ?>
  <div class="empty-state card">
    <p>No models in this collection yet.</p>
  </div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($models as $model): ?>
      <div>
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
        <?php if ($isOwner): ?>
          <form method="post" action="/collections/<?= (int) $collection['id'] ?>/remove" style="margin-top:0.4rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="model_id" value="<?= (int) $model['id'] ?>">
            <button type="submit" class="secondary" style="font-size:0.78rem;padding:0.3rem 0.65rem;">Remove</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
