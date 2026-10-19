<?php
declare(strict_types=1);

require __DIR__ . '/app/Core/bootstrap.php';

try {
    $controller = new App\Controllers\AppController();
    $controller->dispatch();
} catch (Throwable $exception) {
    error_log('SMART POS startup failed: ' . $exception);
    http_response_code(503);
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Database belum siap</title><body style="font:16px Segoe UI,sans-serif;background:#f5f7f6;color:#1f2937;padding:10vh 8vw"><h1 style="color:#166534">SI SMART POS belum dapat terhubung</h1><p>Pastikan MySQL aktif, database sudah dibuat dari <code>database/schema.sql</code>, dan konfigurasi pada <code>config/config.php</code> sudah sesuai.</p></body></html>';
}
