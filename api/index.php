<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/validation.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/KamarController.php';
require_once __DIR__ . '/controllers/KategoriController.php';
require_once __DIR__ . '/controllers/TransaksiController.php';
require_once __DIR__ . '/controllers/LaporanController.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = isset($_GET['route'])
        ? '/' . trim((string) $_GET['route'], '/')
        : (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    if ($basePath !== '' && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }

    $path = '/' . trim($path, '/');

    if (strpos($path, '/index.php') === 0) {
        $path = substr($path, strlen('/index.php'));
        $path = '/' . trim($path, '/');
    }

    if ($method === 'GET' && $path === '/') {
        successResponse([
            'service' => 'SiKuKos API',
            'database' => 'sikukos',
            'status' => 'ready',
        ]);
    }

    if ($method === 'POST' && $path === '/login') {
        (new AuthController())->login();
    }

    if ($method === 'POST' && $path === '/password/change') {
        (new AuthController())->changePassword();
    }

    if ($method === 'POST' && $path === '/password/reset-default') {
        (new AuthController())->resetDefaultPassword();
    }

    if ($method === 'GET' && $path === '/kamar') {
        (new KamarController())->index();
    }

    if ($method === 'POST' && $path === '/kamar') {
        (new KamarController())->store();
    }

    if ($method === 'POST' && $path === '/kamar/update') {
        (new KamarController())->update();
    }

    if ($method === 'POST' && $path === '/kamar/delete') {
        (new KamarController())->destroy();
    }

    if ($method === 'GET' && $path === '/kategori') {
        (new KategoriController())->index();
    }

    if ($method === 'GET' && $path === '/dashboard') {
        (new LaporanController())->dashboard();
    }

    if ($method === 'GET' && $path === '/laporan') {
        (new LaporanController())->monthlyReport();
    }

    if ($method === 'GET' && $path === '/laporan/bulan') {
        (new LaporanController())->availableMonths();
    }

    if ($method === 'POST' && $path === '/transaksi') {
        (new TransaksiController())->store();
    }

    if ($method === 'GET' && $path === '/transaksi') {
        (new TransaksiController())->index();
    }

    if ($method === 'GET' && $path === '/ringkasan-hari-ini') {
        (new TransaksiController())->todaySummary();
    }

    if ($method === 'GET' && $path === '/sewa/status') {
        (new TransaksiController())->rentStatus();
    }

    errorResponse('Endpoint belum tersedia.', 404);
} catch (Throwable $exception) {
    errorResponse('Terjadi kesalahan server.', 500);
}
