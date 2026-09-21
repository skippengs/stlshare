<?php

function collections_index(): void
{
    $user = require_login();

    $stmt = Database::get()->prepare('SELECT * FROM collections WHERE owner_user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user['id']]);

    render('collections/index', [
        'pageTitle' => 'My collections',
        'collections' => $stmt->fetchAll(),
    ]);
}

function collections_create(): void
{
    $user = require_login();
    csrf_verify();

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $isPublic = isset($_POST['is_public']) ? 1 : 0;

    if ($name === '' || mb_strlen($name) > 150) {
        flash_set('error', 'Enter a collection name (up to 150 characters).');
        redirect('/collections');
    }

    $stmt = Database::get()->prepare(
        'INSERT INTO collections (owner_user_id, name, description, is_public) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$user['id'], $name, $description !== '' ? $description : null, $isPublic]);

    flash_set('success', 'Collection created.');
    redirect('/collections');
}

function collections_find_or_404(int $id): array
{
    $stmt = Database::get()->prepare('SELECT c.*, u.display_name AS owner_name FROM collections c JOIN users u ON u.id = c.owner_user_id WHERE c.id = ?');
    $stmt->execute([$id]);
    $collection = $stmt->fetch();
    if (!$collection) {
        http_response_code(404);
        render('errors/404', ['pageTitle' => 'Not found']);
        exit;
    }
    return $collection;
}

function collections_show(string $id): void
{
    $user = require_login();
    $collection = collections_find_or_404((int) $id);

    $isOwner = (int) $collection['owner_user_id'] === (int) $user['id'];
    if (!$isOwner && !$collection['is_public']) {
        http_response_code(403);
        render('errors/403', ['pageTitle' => 'Private collection']);
        return;
    }

    $stmt = Database::get()->prepare(
        'SELECT m.*, u.display_name AS owner_name
         FROM collection_models cm
         JOIN models m ON m.id = cm.model_id
         JOIN users u ON u.id = m.owner_user_id
         WHERE cm.collection_id = ?
         ORDER BY cm.sort_order, cm.model_id'
    );
    $stmt->execute([$collection['id']]);

    render('collections/show', [
        'pageTitle' => $collection['name'],
        'collection' => $collection,
        'isOwner' => $isOwner,
        'models' => $stmt->fetchAll(),
    ]);
}

function collections_delete(string $id): void
{
    $user = require_login();
    csrf_verify();
    $collection = collections_find_or_404((int) $id);

    if ((int) $collection['owner_user_id'] !== (int) $user['id'] && $user['role'] !== 'admin') {
        http_response_code(403);
        exit;
    }

    $stmt = Database::get()->prepare('DELETE FROM collections WHERE id = ?');
    $stmt->execute([$collection['id']]);

    flash_set('success', 'Collection deleted.');
    redirect('/collections');
}

function collections_add_model(string $id): void
{
    $user = require_login();
    csrf_verify();
    $collection = collections_find_or_404((int) $id);

    if ((int) $collection['owner_user_id'] !== (int) $user['id']) {
        http_response_code(403);
        exit;
    }

    $modelId = (int) ($_POST['model_id'] ?? 0);
    models_find_or_404($modelId);

    $stmt = Database::get()->prepare(
        'INSERT IGNORE INTO collection_models (collection_id, model_id) VALUES (?, ?)'
    );
    $stmt->execute([$collection['id'], $modelId]);

    flash_set('success', 'Added to collection.');
    redirect('/models/' . $modelId);
}

function collections_remove_model(string $id): void
{
    $user = require_login();
    csrf_verify();
    $collection = collections_find_or_404((int) $id);

    if ((int) $collection['owner_user_id'] !== (int) $user['id']) {
        http_response_code(403);
        exit;
    }

    $modelId = (int) ($_POST['model_id'] ?? 0);
    $stmt = Database::get()->prepare('DELETE FROM collection_models WHERE collection_id = ? AND model_id = ?');
    $stmt->execute([$collection['id'], $modelId]);

    flash_set('success', 'Removed from collection.');
    redirect('/collections/' . $collection['id']);
}
