<h1><?= e($model['title']) ?></h1>
<p class="muted">by <?= e($model['owner_name']) ?> · <?= e(human_filesize((int) $model['file_size_bytes'])) ?> · uploaded <?= e($model['created_at']) ?></p>

<div id="viewer-container" style="width:100%;height:480px;border-radius:6px;overflow:hidden;background:#0f1e15;border:1px solid var(--border);position:relative;">
  <div id="viewer-loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.9rem;">Loading model…</div>
</div>

<div class="actions-row">
  <button type="button" id="btn-wireframe" class="secondary">Toggle wireframe</button>
  <button type="button" id="btn-bg" class="secondary">Toggle background</button>
  <a class="btn secondary" href="/models/<?= (int) $model['id'] ?>/stl" download>Download STL</a>

  <form class="inline" method="post" action="/models/<?= (int) $model['id'] ?>/favorite">
    <?= csrf_field() ?>
    <button type="submit"><?= $isFavorited ? 'Remove favorite' : 'Add to favorites' ?></button>
  </form>

  <?php if ($canDelete): ?>
    <form class="inline" method="post" action="/models/<?= (int) $model['id'] ?>/delete" onsubmit="return confirm('Delete this model? This cannot be undone.');">
      <?= csrf_field() ?>
      <button type="submit" class="danger">Delete</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($model['description']): ?>
  <div class="card">
    <p style="margin:0;"><?= nl2br(e($model['description'])) ?></p>
  </div>
<?php endif; ?>

<div class="card">
  <h2 style="margin-top:0;">Add to a collection</h2>
  <?php if ($myCollections): ?>
    <form method="post" action="" id="add-to-collection-form" style="display:flex;gap:0.5rem;align-items:flex-end;">
      <div style="flex:1;">
        <label for="collection_id" style="margin-top:0;">Collection</label>
        <select id="collection_id" name="collection_id"></select>
      </div>
      <button type="submit">Add</button>
    </form>
    <script>
      (function() {
        var collections = <?= json_encode($myCollections) ?>;
        var select = document.getElementById('collection_id');
        collections.forEach(function(c) {
          var opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = c.name;
          select.appendChild(opt);
        });
        document.getElementById('add-to-collection-form').addEventListener('submit', function(e) {
          e.preventDefault();
          this.action = '/collections/' + select.value + '/add';
          var csrf = document.createElement('input');
          csrf.type = 'hidden'; csrf.name = 'csrf_token';
          csrf.value = document.querySelector('meta[name="csrf-token"]').content;
          this.appendChild(csrf);
          var modelId = document.createElement('input');
          modelId.type = 'hidden'; modelId.name = 'model_id'; modelId.value = '<?= (int) $model['id'] ?>';
          this.appendChild(modelId);
          HTMLFormElement.prototype.submit.call(this);
        });
      })();
    </script>
  <?php else: ?>
    <p class="muted" style="margin:0;">You don't have any collections yet. <a href="/collections">Create one</a>.</p>
  <?php endif; ?>
</div>

<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>
<script type="module">
  import { initViewer, maybeUploadThumbnail } from '/assets/js/viewer.js';

  const container = document.getElementById('viewer-container');
  const loading = document.getElementById('viewer-loading');
  const stlUrl = '/models/<?= (int) $model['id'] ?>/stl';
  const needsThumbnail = <?= ($isOwner && !$model['thumbnail_filename']) ? 'true' : 'false' ?>;
  const modelId = <?= (int) $model['id'] ?>;

  const viewer = initViewer(container, stlUrl, {
    dark: true,
    onLoaded(renderer) {
      loading.remove();
      if (needsThumbnail) {
        maybeUploadThumbnail(modelId, renderer);
      }
    },
    onError() {
      loading.textContent = 'Could not load this model.';
    },
  });

  document.getElementById('btn-wireframe').addEventListener('click', () => viewer.toggleWireframe());
  document.getElementById('btn-bg').addEventListener('click', () => viewer.toggleBackground());
</script>
