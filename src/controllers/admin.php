<?php

function admin_dashboard(): void
{
    require_admin();
    $db = Database::get();

    $pending = $db->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at")->fetchAll();
    $others = $db->query("SELECT * FROM users WHERE status != 'pending' ORDER BY created_at")->fetchAll();

    $totalUsage = (int) $db->query('SELECT COALESCE(SUM(file_size_bytes), 0) AS total FROM models')->fetch()['total'];
    $modelCount = (int) $db->query('SELECT COUNT(*) AS c FROM models')->fetch()['c'];

    $usageByUser = $db->query(
        "SELECT u.id, u.display_name, u.email, u.storage_quota_bytes,
                COALESCE(SUM(m.file_size_bytes), 0) AS used_bytes
         FROM users u
         LEFT JOIN models m ON m.owner_user_id = u.id
         GROUP BY u.id
         ORDER BY used_bytes DESC"
    )->fetchAll();

    render('admin/dashboard', [
        'pageTitle' => 'Admin',
        'pending' => $pending,
        'others' => $others,
        'totalUsage' => $totalUsage,
        'modelCount' => $modelCount,
        'usageByUser' => $usageByUser,
    ]);
}

function admin_find_user_or_404(int $id): array
{
    $stmt = Database::get()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        render('errors/404', ['pageTitle' => 'Not found']);
        exit;
    }
    return $user;
}

function admin_user_approve(string $id): void
{
    $admin = require_admin();
    csrf_verify();
    $target = admin_find_user_or_404((int) $id);

    $stmt = Database::get()->prepare(
        "UPDATE users SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$admin['id'], $target['id']]);

    flash_set('success', $target['display_name'] . ' approved.');
    redirect('/admin');
}

function admin_user_reject(string $id): void
{
    require_admin();
    csrf_verify();
    $target = admin_find_user_or_404((int) $id);

    $stmt = Database::get()->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$target['id']]);

    flash_set('success', $target['display_name'] . ' rejected.');
    redirect('/admin');
}

function admin_user_suspend(string $id): void
{
    $admin = require_admin();
    csrf_verify();
    $target = admin_find_user_or_404((int) $id);

    if ((int) $target['id'] === (int) $admin['id']) {
        flash_set('error', 'You cannot suspend your own account.');
        redirect('/admin');
    }

    $stmt = Database::get()->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
    $stmt->execute([$target['id']]);

    flash_set('success', $target['display_name'] . ' suspended.');
    redirect('/admin');
}

function admin_user_reinstate(string $id): void
{
    $admin = require_admin();
    csrf_verify();
    $target = admin_find_user_or_404((int) $id);

    $stmt = Database::get()->prepare(
        "UPDATE users SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$admin['id'], $target['id']]);

    flash_set('success', $target['display_name'] . ' reinstated.');
    redirect('/admin');
}

function admin_user_quota(string $id): void
{
    require_admin();
    csrf_verify();
    $target = admin_find_user_or_404((int) $id);

    $raw = trim((string) ($_POST['quota_gb'] ?? ''));
    $quotaBytes = $raw === '' ? null : (int) round(((float) $raw) * 1024 * 1024 * 1024);

    $stmt = Database::get()->prepare('UPDATE users SET storage_quota_bytes = ? WHERE id = ?');
    $stmt->execute([$quotaBytes, $target['id']]);

    flash_set('success', 'Quota updated for ' . $target['display_name'] . '.');
    redirect('/admin');
}
