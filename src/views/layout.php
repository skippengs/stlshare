<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle ?? config('app')['name']) ?> — <?= e(config('app')['name']) ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <a class="brand" href="<?= base_url('/') ?>"><span class="logo-mark">ST</span>STL Sharer</a>

    <?php if ($user): ?>
      <?php $current = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/'; ?>
      <nav class="sidebar-nav">
        <a href="/" class="<?= $current === '/' ? 'active' : '' ?>"><span class="nav-dot"></span>Library</a>
        <a href="/upload" class="<?= $current === '/upload' ? 'active' : '' ?>"><span class="nav-dot"></span>Upload</a>
        <a href="/favorites" class="<?= $current === '/favorites' ? 'active' : '' ?>"><span class="nav-dot"></span>Favorites</a>
        <a href="/collections" class="<?= str_starts_with($current, '/collections') ? 'active' : '' ?>"><span class="nav-dot"></span>Collections</a>
        <?php if ($user['role'] === 'admin'): ?>
          <a href="/admin" class="<?= $current === '/admin' ? 'active' : '' ?>"><span class="nav-dot"></span>Admin</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

    <span class="sidebar-spacer"></span>

    <div class="sidebar-user">
      <?php if ($user): ?>
        <a class="user-link" href="/account">
          <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['display_name'], 0, 1))) ?></span>
          <?= e($user['display_name']) ?>
        </a>
        <form method="post" action="/logout">
          <?= csrf_field() ?>
          <button type="submit" class="secondary">Log out</button>
        </form>
      <?php else: ?>
        <div class="sidebar-guest">
          <a class="btn secondary" href="/login">Log in</a>
          <a class="btn" href="/register">Register</a>
        </div>
      <?php endif; ?>
    </div>
  </aside>

  <div class="content">
    <main>
      <?php foreach (flash_take() as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
</body>
</html>
