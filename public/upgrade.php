<?php
declare(strict_types=1);

// Admin-only migration runner — applies db/migrations/*.sql files that
// haven't been recorded in schema_migrations yet. This is the "tiny custom
// PHP migration runner" mentioned in CLAUDE.md, for when a schema change
// needs deploying to Mijndomein with no SSH/artisan available. See
// db/migrations/README.md for how migrations are authored.

session_start();

spl_autoload_register(function (string $class): void {
    $path = __DIR__ . '/../src/lib/' . $class . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/../src/lib/helpers.php';

$user = require_admin();

$rootDir = dirname(__DIR__);
$migrationsDir = $rootDir . '/db/migrations';

$db = Database::get();
$applied = $db->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$allMigrations = array_map('basename', glob($migrationsDir . '/*.sql') ?: []);
sort($allMigrations);
$pending = array_values(array_diff($allMigrations, $applied));

$ran = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pending) {
    csrf_verify();

    foreach ($pending as $migration) {
        $path = $migrationsDir . '/' . $migration;
        try {
            // Not wrapped in an explicit transaction: if the migration
            // contains DDL (ALTER TABLE, CREATE TABLE), MySQL implicitly
            // commits it anyway, which leaves PDO's transaction state out of
            // sync with the server and makes rollBack() unreliable. Write
            // migrations to be safe to re-run (IF NOT EXISTS / idempotent
            // UPDATEs) so a failure partway through can just be fixed and
            // re-submitted.
            $sql = file_get_contents($path);
            foreach (split_sql_statements($sql) as $statement) {
                $db->exec($statement);
            }
            $stmt = $db->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
            $stmt->execute([$migration]);
            $ran[] = $migration;
        } catch (Throwable $e) {
            $errors[] = "$migration: " . $e->getMessage();
            break; // stop at the first failure — later migrations may depend on this one
        }
    }

    // Recompute what's still pending after this run.
    $applied = $db->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $pending = array_values(array_diff($allMigrations, $applied));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Upgrade — STL Sharer</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <div class="content"><main>
  <h1>Database upgrade</h1>

  <?php foreach ($ran as $migration): ?>
    <div class="flash flash-success">Applied <?= e($migration) ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <div class="card">
    <?php if (!$allMigrations): ?>
      <p class="muted" style="margin:0;">No migrations exist yet — nothing to run. See <code>db/migrations/README.md</code> for how to add one.</p>
    <?php elseif (!$pending): ?>
      <p class="muted" style="margin:0;">Up to date. All <?= count($allMigrations) ?> migration(s) have been applied.</p>
    <?php else: ?>
      <h2 style="margin-top:0;">Pending migrations</h2>
      <table>
        <?php foreach ($pending as $migration): ?>
          <tr><td class="mono"><?= e($migration) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <form method="post" style="margin-top:1rem;" onsubmit="return confirm('Run <?= count($pending) ?> pending migration(s) against the live database?');">
        <?= csrf_field() ?>
        <button type="submit">Run migrations</button>
      </form>
    <?php endif; ?>
  </div>

  <p style="margin-top:1.5rem;"><a href="/admin">Back to admin</a></p>
  </main></div>
</div>
</body>
</html>
