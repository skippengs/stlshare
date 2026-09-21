<?php

final class Auth
{
    public static function register(string $email, string $password, string $displayName): array
    {
        $db = Database::get();

        $isFirstUser = (int) $db->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'] === 0;

        $stmt = $db->prepare(
            'INSERT INTO users (email, password_hash, display_name, role, status, approved_at, consented_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $displayName,
            $isFirstUser ? 'admin' : 'member',
            $isFirstUser ? 'approved' : 'pending',
            $isFirstUser ? date('Y-m-d H:i:s') : null,
        ]);

        $id = (int) $db->lastInsertId();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function attempt(string $email, string $password): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function emailExists(string $email): bool
    {
        $stmt = Database::get()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return (bool) $stmt->fetch();
    }
}
