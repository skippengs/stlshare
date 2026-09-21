<?php

function models_upload_form(): void
{
    $user = require_login();
    render('library/upload', [
        'pageTitle' => 'Upload model',
        'usedBytes' => user_used_bytes((int) $user['id']),
        'quotaBytes' => user_quota_bytes($user),
    ]);
}

function models_upload_submit(): void
{
    $user = require_login();
    csrf_verify();

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    $errors = [];
    if ($title === '' || mb_strlen($title) > 255) {
        $errors[] = 'Enter a title (up to 255 characters).';
    }

    $file = $_FILES['stl_file'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Choose an STL file to upload.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload failed (error code ' . $file['error'] . ').';
    } else {
        $maxBytes = config('upload')['max_bytes'];
        if ($file['size'] > $maxBytes) {
            $errors[] = 'File is too large (max ' . human_filesize($maxBytes) . ').';
        }
        if (!str_ends_with(strtolower($file['name']), '.stl')) {
            $errors[] = 'Only .stl files are accepted.';
        }
        $quota = user_quota_bytes($user);
        $used = user_used_bytes((int) $user['id']);
        if (empty($errors) && ($used + $file['size']) > $quota) {
            $errors[] = sprintf(
                'This upload would exceed your storage quota (%s used of %s).',
                human_filesize($used),
                human_filesize($quota)
            );
        }
        if (empty($errors) && !StlValidator::isValid($file['tmp_name'])) {
            $errors[] = 'This does not look like a valid STL file.';
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash_set('error', $error);
        }
        render('library/upload', [
            'pageTitle' => 'Upload model',
            'usedBytes' => user_used_bytes((int) $user['id']),
            'quotaBytes' => user_quota_bytes($user),
            'old' => ['title' => $title, 'description' => $description],
        ]);
        return;
    }

    $storedFilename = bin2hex(random_bytes(16)) . '.stl';
    $destination = config('storage')['models_dir'] . '/' . $storedFilename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        flash_set('error', 'Could not save the uploaded file. Try again.');
        redirect('/upload');
    }

    $stmt = Database::get()->prepare(
        'INSERT INTO models (owner_user_id, title, description, original_filename, stored_filename, file_size_bytes)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'],
        $title,
        $description !== '' ? $description : null,
        basename($file['name']),
        $storedFilename,
        $file['size'],
    ]);

    $modelId = (int) Database::get()->lastInsertId();
    flash_set('success', 'Model uploaded.');
    redirect('/models/' . $modelId);
}

function models_find_or_404(int $id): array
{
    $stmt = Database::get()->prepare(
        'SELECT m.*, u.display_name AS owner_name
         FROM models m JOIN users u ON u.id = m.owner_user_id
         WHERE m.id = ?'
    );
    $stmt->execute([$id]);
    $model = $stmt->fetch();
    if (!$model) {
        http_response_code(404);
        render('errors/404', ['pageTitle' => 'Not found']);
        exit;
    }
    return $model;
}

function models_show(string $id): void
{
    $user = require_login();
    $model = models_find_or_404((int) $id);

    $favStmt = Database::get()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND model_id = ?');
    $favStmt->execute([$user['id'], $model['id']]);
    $isFavorited = (bool) $favStmt->fetch();

    $collStmt = Database::get()->prepare('SELECT * FROM collections WHERE owner_user_id = ? ORDER BY name');
    $collStmt->execute([$user['id']]);
    $myCollections = $collStmt->fetchAll();

    render('library/show', [
        'pageTitle' => $model['title'],
        'model' => $model,
        'isOwner' => (int) $model['owner_user_id'] === (int) $user['id'],
        'isFavorited' => $isFavorited,
        'myCollections' => $myCollections,
        'canDelete' => (int) $model['owner_user_id'] === (int) $user['id'] || $user['role'] === 'admin',
    ]);
}

function models_stream_file(string $dir, ?string $filename, string $contentType, string $downloadName): void
{
    require_login();
    if (!$filename) {
        http_response_code(404);
        exit;
    }
    $path = $dir . '/' . $filename;
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
}

function models_stream_stl(string $id): void
{
    $model = models_find_or_404((int) $id);
    models_stream_file(
        config('storage')['models_dir'],
        $model['stored_filename'],
        'model/stl',
        $model['original_filename']
    );
}

function models_stream_thumb(string $id): void
{
    $model = models_find_or_404((int) $id);
    if (!$model['thumbnail_filename']) {
        http_response_code(404);
        exit;
    }
    models_stream_file(
        config('storage')['thumbnails_dir'],
        $model['thumbnail_filename'],
        'image/png',
        'thumbnail.png'
    );
}

function models_save_thumbnail(string $id): void
{
    $user = require_login();
    $model = models_find_or_404((int) $id);

    if ((int) $model['owner_user_id'] !== (int) $user['id']) {
        http_response_code(403);
        exit;
    }
    csrf_verify_header();
    if ($model['thumbnail_filename']) {
        http_response_code(204);
        exit; // already have one
    }

    $data = file_get_contents('php://input');
    $prefix = 'data:image/png;base64,';
    if (!str_starts_with($data, $prefix)) {
        http_response_code(400);
        exit;
    }
    $binary = base64_decode(substr($data, strlen($prefix)), true);
    if ($binary === false) {
        http_response_code(400);
        exit;
    }

    $filename = bin2hex(random_bytes(16)) . '.png';
    file_put_contents(config('storage')['thumbnails_dir'] . '/' . $filename, $binary);

    $stmt = Database::get()->prepare('UPDATE models SET thumbnail_filename = ? WHERE id = ?');
    $stmt->execute([$filename, $model['id']]);

    http_response_code(204);
}

function models_delete(string $id): void
{
    $user = require_login();
    csrf_verify();
    $model = models_find_or_404((int) $id);

    if ((int) $model['owner_user_id'] !== (int) $user['id'] && $user['role'] !== 'admin') {
        http_response_code(403);
        exit;
    }

    @unlink(config('storage')['models_dir'] . '/' . $model['stored_filename']);
    if ($model['thumbnail_filename']) {
        @unlink(config('storage')['thumbnails_dir'] . '/' . $model['thumbnail_filename']);
    }

    $stmt = Database::get()->prepare('DELETE FROM models WHERE id = ?');
    $stmt->execute([$model['id']]);

    flash_set('success', 'Model deleted.');
    redirect('/');
}
