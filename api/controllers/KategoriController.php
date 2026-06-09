<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

class KategoriController
{
    public function index(): void
    {
        $tipe = isset($_GET['tipe']) ? cleanString($_GET['tipe']) : '';

        if ($tipe !== '') {
            $errors = validateEnumValue(['tipe' => $tipe], 'tipe', ['pemasukan', 'pengeluaran']);

            if ($errors !== []) {
                errorResponse('Filter kategori tidak valid.', 422, $errors);
            }

            $stmt = db()->prepare(
                'SELECT id_kategori, nama_kategori, tipe_kategori, created_at
                 FROM kategori
                 WHERE tipe_kategori IN (:tipe, \'keduanya\')
                 ORDER BY nama_kategori'
            );
            $stmt->execute(['tipe' => $tipe]);
        } else {
            $stmt = db()->prepare(
                'SELECT id_kategori, nama_kategori, tipe_kategori, created_at
                 FROM kategori
                 ORDER BY nama_kategori'
            );
            $stmt->execute();
        }

        successResponse([
            'kategori' => $stmt->fetchAll(),
        ]);
    }
}
