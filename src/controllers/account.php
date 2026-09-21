<?php

function account_show(): void
{
    $user = require_login();

    render('account/show', [
        'pageTitle' => 'My account',
        'usedBytes' => user_used_bytes((int) $user['id']),
        'quotaBytes' => user_quota_bytes($user),
    ]);
}

/** Right to erasure: delete the user's own account, models, and files. */
function account_delete(): void
{
    $user = require_login();
    csrf_verify();

    $password = (string) ($_POST['password'] ?? '');
    if (!password_verify($password, $user['password_hash'])) {
        flash_set('error', 'Incorrect password. Account not deleted.');
        redirect('/account');
    }

    $db = Database::get();
    $stmt = $db->prepare('SELECT stored_filename, thumbnail_filename FROM models WHERE owner_user_id = ?');
    $stmt->execute([$user['id']]);
    foreach ($stmt->fetchAll() as $model) {
        @unlink(config('storage')['models_dir'] . '/' . $model['stored_filename']);
        if ($model['thumbnail_filename']) {
            @unlink(config('storage')['thumbnails_dir'] . '/' . $model['thumbnail_filename']);
        }
    }

    // ON DELETE CASCADE removes the user's models, favorites, and collections.
    $del = $db->prepare('DELETE FROM users WHERE id = ?');
    $del->execute([$user['id']]);

    Auth::logout();
    flash_set('success', 'Your account and files have been deleted.');
    redirect('/login');
}
