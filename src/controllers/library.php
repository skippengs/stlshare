<?php

function library_index(): void
{
    require_login();

    $db = Database::get();
    $search = trim((string) ($_GET['q'] ?? ''));
    $perPage = 24;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $where = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE m.title LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $countStmt = $db->prepare("SELECT COUNT(*) AS c FROM models m $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['c'];

    $stmt = $db->prepare(
        "SELECT m.*, u.display_name AS owner_name
         FROM models m JOIN users u ON u.id = m.owner_user_id
         $where
         ORDER BY m.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $models = $stmt->fetchAll();

    render('library/index', [
        'pageTitle' => 'Library',
        'models' => $models,
        'search' => $search,
        'page' => $page,
        'totalPages' => max(1, (int) ceil($total / $perPage)),
    ]);
}
