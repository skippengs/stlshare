<div class="card center-card">
  <h1>Upload a model</h1>

  <div class="muted">
    <?= e(human_filesize($usedBytes)) ?> used of <?= e(human_filesize($quotaBytes)) ?>
    <div class="quota-bar"><div class="quota-bar-fill" style="width:<?= min(100, $quotaBytes > 0 ? (int) round($usedBytes / $quotaBytes * 100) : 0) ?>%"></div></div>
  </div>

  <form method="post" action="/upload" enctype="multipart/form-data" style="margin-top:0.5rem;">
    <?= csrf_field() ?>
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="<?= e($old['title'] ?? '') ?>" required>

    <label for="description">Description</label>
    <textarea id="description" name="description" rows="4"><?= e($old['description'] ?? '') ?></textarea>

    <label for="stl_file">STL file (max 100 MB)</label>
    <input type="file" id="stl_file" name="stl_file" accept=".stl" required>

    <button type="submit" style="margin-top:1.25rem;width:100%;">Upload</button>
  </form>
</div>
