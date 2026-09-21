<?php

function favorites_toggle(string $id): void
{
    $user = require_login();
    csrf_verify();
    $model = models_find_or_404((int) $id);

    $db = Database::get();
    $stmt = $db->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND model_id = ?');
    $stmt->execute([$user['id'], $model['id']]);

    if ($stmt->fetch()) {
        $del = $db->prepare('DELETE FROM favorites WHERE user_id = ? AND model_id = ?');
        $del->execute([$user['id'], $model['id']]);
        flash_set('success', 'Removed from favorites.');
    } else {
        $ins = $db->prepare('INSERT INTO favorites (user_id, model_id) VALUES (?, ?)');
        $ins->execute([$user['id'], $model['id']]);
        flash_set('success', 'Added to favorites.');
    }

    redirect('/models/' . $model['id']);
}

function favorites_index(): void
{
    $user = require_login();

    $stmt = Database::get()->prepare(
        'SELECT m.*, u.display_name AS owner_name
         FROM favorites f
         JOIN models m ON m.id = f.model_id
         JOIN users u ON u.id = m.owner_user_id
         WHERE f.user_id = ?
         ORDER BY f.created_at DESC'
    );
    $stmt->execute([$user['id']]);

    render('library/favorites', [
        'pageTitle' => 'My favorites',
        'models' => $stmt->fetchAll(),
    ]);
}
