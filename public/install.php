<?php
declare(strict_types=1);

// One-time web installer — for Mijndomein/Plesk shared hosting where there's
// no SSH/CLI to run migrations by hand (see CLAUDE.md "Plain PHP on
// Mijndomein"). Creates the database schema and writes src/config.local.php
// with the DB credentials you enter below.
//
// SECURITY: delete this file from the server once installation succeeds.
// It refuses to touch an already-installed database, but there's no reason
// to leave a setup script reachable on a live site.

session_start();
require __DIR__ . '/../src/lib/helpers.php';

$rootDir = dirname(__DIR__);
$schemaPath = $rootDir . '/db/schema.sql';
$migrationsDir = $rootDir . '/db/migrations';
$localConfigPath = $rootDir . '/src/config.local.php';

function try_connect(string $host, int $port, string $name, string $user, string $pass): array
{
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return [$pdo, null];
    } catch (PDOException $e) {
        return [null, $e->getMessage()];
    }
}

function already_installed(): bool
{
    $db = config('db');
    [$pdo] = try_connect($db['host'], (int) $db['port'], $db['name'], $db['user'], $db['pass']);
    if (!$pdo) {
        return false;
    }
    try {
        $count = (int) $pdo->query("SELECT COUNT(*) AS c FROM users")->fetch()['c'];
        return $count > 0;
    } catch (PDOException) {
        return false; // users table doesn't exist yet — not installed
    }
}

function check_requirements(string $rootDir): array
{
    $checks = [];
    $checks[] = ['PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), 'Running ' . PHP_VERSION];
    foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'json', 'session'] as $ext) {
        $checks[] = ["ext-$ext", extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing'];
    }
    foreach (['storage/models' => 'models_dir', 'storage/thumbnails' => 'thumbnails_dir'] as $rel => $key) {
        $path = $rootDir . '/' . $rel;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $checks[] = [$rel . ' writable', is_dir($path) && is_writable($path), $path];
    }
    $checks[] = ['src/ writable (to write config.local.php)', is_writable(dirname(__DIR__) . '/src'), dirname(__DIR__) . '/src'];
    return $checks;
}

function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Could not read $path");
    }
    foreach (split_sql_statements($sql) as $statement) {
        $pdo->exec($statement);
    }
}

$installed = already_installed();
$errors = [];
$success = false;

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $host = trim((string) ($_POST['host'] ?? ''));
    $port = (int) ($_POST['port'] ?? 3306);
    $name = trim((string) ($_POST['name'] ?? ''));
    $user = trim((string) ($_POST['user'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');

    if ($host === '' || $name === '' || $user === '') {
        $errors[] = 'Host, database name, and user are required.';
    }

    [$pdo, $connectError] = $errors ? [null, null] : try_connect($host, $port, $name, $user, $pass);
    if (!$pdo && !$errors) {
        $errors[] = 'Could not connect: ' . $connectError;
    }

    if (!$errors && $pdo) {
        try {
            // No explicit transaction here: CREATE TABLE causes an implicit
            // commit in MySQL anyway, which leaves PDO's transaction state
            // out of sync with the server and makes rollBack() unreliable.
            // CREATE TABLE IF NOT EXISTS is idempotent, so a failed run can
            // just be fixed and re-submitted.
            run_sql_file($pdo, $schemaPath);

            $stmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (?)');
            foreach (glob($migrationsDir . '/*.sql') ?: [] as $file) {
                $stmt->execute([basename($file)]);
            }

            $configArray = ['db' => ['host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'pass' => $pass]];
            $php = "<?php\n// Written by install.php on " . date('Y-m-d H:i:s') . ". Do not commit this file.\nreturn "
                . var_export($configArray, true) . ";\n";

            if (file_put_contents($localConfigPath, $php) === false) {
                $errors[] = 'Schema created, but could not write src/config.local.php (check the directory is writable). '
                    . 'Create it by hand from src/config.local.php.example with these values instead.';
            } else {
                $success = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'Setup failed: ' . $e->getMessage();
        }
    }
}

$requirements = check_requirements($rootDir);
$requirementsOk = !array_filter($requirements, fn($c) => !$c[1]);
$dbDefaults = config('db');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Install — STL Sharer</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<main style="max-width:640px;margin:2.5rem auto;padding:0 1.5rem;">
  <h1>STL Sharer — Install</h1>

  <?php if ($installed): ?>
    <div class="card card-danger">
      <h2 style="margin-top:0;">Already installed</h2>
      <p>A database with existing users was found at the configured connection — this installer won't touch it.</p>
      <p><strong>Delete <code>public/install.php</code> from the server now.</strong> If you need to change schema later, use <a href="/upgrade.php">upgrade.php</a> (admin login required) instead.</p>
      <p><a class="btn" href="/">Go to the app</a></p>
    </div>

  <?php elseif ($success): ?>
    <div class="card">
      <h2 style="margin-top:0;">Installed</h2>
      <p>Database schema created and <code>src/config.local.php</code> written.</p>
      <p><strong>Delete <code>public/install.php</code> from the server now</strong> — leaving a working installer reachable on a live site is a needless risk, and it will refuse to do anything useful once a user exists anyway.</p>
      <p>Next: <a class="btn" href="/register">Create the admin account</a> (the first account registered becomes admin automatically).</p>
    </div>

  <?php else: ?>
    <div class="card">
      <h2 style="margin-top:0;">Requirements</h2>
      <table>
        <?php foreach ($requirements as [$label, $ok, $detail]): ?>
          <tr>
            <td><?= e($label) ?></td>
            <td><span class="badge" style="<?= $ok ? '' : 'background:var(--error-bg);color:var(--error-text);' ?>"><?= $ok ? 'OK' : 'FAIL' ?></span></td>
            <td class="muted mono"><?= e((string) $detail) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <?php if (!$requirementsOk): ?>
      <div class="flash flash-error">Fix the failing requirements above before continuing.</div>
    <?php else: ?>
      <div class="card">
        <h2 style="margin-top:0;">Database connection</h2>
        <?php foreach ($errors as $error): ?>
          <div class="flash flash-error"><?= e($error) ?></div>
        <?php endforeach; ?>
        <form method="post">
          <?= csrf_field() ?>
          <label for="host">Host</label>
          <input type="text" id="host" name="host" value="<?= e((string) ($_POST['host'] ?? $dbDefaults['host'])) ?>" required>

          <label for="port">Port</label>
          <input type="number" id="port" name="port" value="<?= e((string) ($_POST['port'] ?? $dbDefaults['port'])) ?>" required>

          <label for="name">Database name</label>
          <input type="text" id="name" name="name" value="<?= e((string) ($_POST['name'] ?? $dbDefaults['name'])) ?>" required>

          <label for="user">Database user</label>
          <input type="text" id="user" name="user" value="<?= e((string) ($_POST['user'] ?? $dbDefaults['user'])) ?>" required>

          <label for="pass">Database password</label>
          <input type="password" id="pass" name="pass" value="">

          <button type="submit" style="margin-top:1.25rem;">Install</button>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>
