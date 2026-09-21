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
<header class="site-header">
  <a class="brand" href="<?= base_url('/') ?>"><span class="logo-dot"></span><?= e(config('app')['name']) ?></a>
  <?php if ($user): ?>
    <?php $current = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/'; ?>
    <nav class="site-nav">
      <a href="/" class="<?= $current === '/' ? 'active' : '' ?>">Library</a>
      <a href="/upload" class="<?= $current === '/upload' ? 'active' : '' ?>">Upload</a>
      <a href="/favorites" class="<?= $current === '/favorites' ? 'active' : '' ?>">Favorites</a>
      <a href="/collections" class="<?= str_starts_with($current, '/collections') ? 'active' : '' ?>">Collections</a>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="/admin" class="<?= $current === '/admin' ? 'active' : '' ?>">Admin</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
  <span class="header-spacer"></span>
  <div class="header-right">
    <?php if ($user): ?>
      <a class="user-chip" href="/account">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['display_name'], 0, 1))) ?></span>
        <?= e($user['display_name']) ?>
      </a>
      <form method="post" action="/logout">
        <?= csrf_field() ?>
        <button type="submit" class="secondary">Log out</button>
      </form>
    <?php else: ?>
      <a class="btn secondary" href="/login">Log in</a>
      <a class="btn" href="/register">Register</a>
    <?php endif; ?>
  </div>
</header>
<main>
  <?php foreach (flash_take() as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
