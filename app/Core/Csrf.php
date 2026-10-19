<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(): void
    {
        $submitted = $_POST['_csrf'] ?? '';
        if (!is_string($submitted) || !hash_equals(self::token(), $submitted)) {
            http_response_code(419);
            throw new \RuntimeException('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba kembali.');
        }
    }
}
