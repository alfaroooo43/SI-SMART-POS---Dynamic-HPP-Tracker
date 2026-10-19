<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function refresh(): void
    {
        $current = self::user();
        if (!$current) {
            return;
        }
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, role, active FROM users WHERE id = ? LIMIT 1'
        );
        $statement->execute([$current['id']]);
        $user = $statement->fetch();
        if (!$user || !(bool) $user['active']) {
            self::logout();
            flash('error', 'Akun Anda tidak lagi aktif. Hubungi pemilik usaha.');
            return;
        }
        unset($user['active']);
        $_SESSION['user'] = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, password, role, active FROM users WHERE email = ? LIMIT 1'
        );
        $statement->execute([mb_strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!$user || !(bool) $user['active'] || !password_verify($password, $user['password'])) {
            return false;
        }

        session_regenerate_id(true);
        unset($_SESSION['pos_preview']);
        unset($user['password'], $user['active']);
        $_SESSION['user'] = $user;
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        unset($_SESSION['pos_preview']);
        session_regenerate_id(true);
    }

    public static function requireRole(array $roles): void
    {
        $user = self::user();
        if (!$user) {
            flash('error', 'Silakan masuk untuk melanjutkan.');
            redirect('?page=login');
        }
        if (!in_array($user['role'], $roles, true)) {
            http_response_code(403);
            flash('error', 'Anda tidak memiliki izin untuk melakukan tindakan tersebut.');
            redirect('?page=dashboard');
        }
    }
}
