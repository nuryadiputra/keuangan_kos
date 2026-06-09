<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/rent.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

class TransaksiController
{
    public function index(): void
    {
        $filters = $this->transactionFilters();
        $page = $this->positiveQueryInt('page', 1);
        $limit = min($this->positiveQueryInt('limit', 10), 100);
        $offset = ($page - 1) * $limit;
        $pdo = db();

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM transaksi t
             INNER JOIN kategori kt ON kt.id_kategori = t.id_kategori
             LEFT JOIN kamar k ON k.id_kamar = t.id_kamar
             ' . $filters['where']
        );
        $countStmt->execute($filters['params']);
        $total = (int) (($countStmt->fetch()['total'] ?? 0));

        $summaryStmt = $pdo->prepare(
            'SELECT t.tipe_transaksi, COALESCE(SUM(t.nominal), 0) AS total
             FROM transaksi t
             INNER JOIN kategori kt ON kt.id_kategori = t.id_kategori
             LEFT JOIN kamar k ON k.id_kamar = t.id_kamar
             ' . $filters['where'] . '
             GROUP BY t.tipe_transaksi'
        );
        $summaryStmt->execute($filters['params']);

        $income = 0.0;
        $expense = 0.0;

        foreach ($summaryStmt->fetchAll() as $row) {
            if ($row['tipe_transaksi'] === 'pemasukan') {
                $income = (float) $row['total'];
            }

            if ($row['tipe_transaksi'] === 'pengeluaran') {
                $expense = (float) $row['total'];
            }
        }

        $stmt = $pdo->prepare(
            'SELECT
                t.id_transaksi,
                t.tanggal,
                t.tipe_transaksi,
                t.nominal,
                t.metode_pembayaran,
                t.keterangan,
                k.nomor_kamar,
                kt.id_kategori,
                kt.nama_kategori
             FROM transaksi t
             INNER JOIN kategori kt ON kt.id_kategori = t.id_kategori
             LEFT JOIN kamar k ON k.id_kamar = t.id_kamar
             ' . $filters['where'] . '
             ORDER BY t.created_at DESC, t.id_transaksi DESC
             LIMIT :limit OFFSET :offset'
        );

        foreach ($filters['params'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $totalPages = max(1, (int) ceil($total / $limit));

        successResponse([
            'transaksi' => array_map([$this, 'mapTransactionRow'], $stmt->fetchAll()),
            'summary' => [
                'income' => $income,
                'expense' => $expense,
                'laba' => $income - $expense,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function store(): void
    {
        $body = readJsonBody();
        $errors = mergeErrors(
            validateRequiredFields($body, [
                'id_user',
                'id_kategori',
                'tanggal',
                'tipe_transaksi',
                'nominal',
                'metode_pembayaran',
                'keterangan',
            ]),
            validateIntId($body, 'id_user'),
            validateIntId($body, 'id_kategori'),
            validateIntId($body, 'id_kamar', false),
            validateDateYmd($body, 'tanggal'),
            validateEnumValue($body, 'tipe_transaksi', ['pemasukan', 'pengeluaran']),
            validatePositiveNumber($body, 'nominal'),
            validateEnumValue($body, 'metode_pembayaran', ['Tunai', 'Transfer Bank', 'QRIS', 'OVO / GoPay']),
            validateMaxLength($body, 'keterangan', 255)
        );

        if ($errors !== []) {
            errorResponse('Data transaksi tidak valid.', 422, $errors);
        }

        $pdo = db();
        $idUser = (int) $body['id_user'];
        $idKategori = (int) $body['id_kategori'];
        $idKamar = empty($body['id_kamar']) ? null : (int) $body['id_kamar'];
        $tanggal = cleanString($body['tanggal']);
        $tipe = cleanString($body['tipe_transaksi']);
        $nominal = (float) $body['nominal'];
        $metode = cleanString($body['metode_pembayaran']);
        $keterangan = cleanString($body['keterangan']);

        $this->ensureUserExists($pdo, $idUser);
        $kategori = $this->ensureKategoriValid($pdo, $idKategori, $tipe);

        if ($tipe === 'pemasukan' && $kategori['nama_kategori'] === 'Sewa Bulanan' && $idKamar === null) {
            errorResponse('Kamar wajib dipilih untuk pembayaran Sewa Bulanan.', 422, [
                'id_kamar' => 'Kamar wajib dipilih untuk pembayaran Sewa Bulanan.',
            ]);
        }

        if ($idKamar !== null) {
            $this->ensureKamarExists($pdo, $idKamar);
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO transaksi
                    (id_user, id_kamar, id_kategori, tanggal, tipe_transaksi, nominal, metode_pembayaran, keterangan)
                 VALUES
                    (:id_user, :id_kamar, :id_kategori, :tanggal, :tipe_transaksi, :nominal, :metode_pembayaran, :keterangan)'
            );
            $stmt->execute([
                'id_user' => $idUser,
                'id_kamar' => $idKamar,
                'id_kategori' => $idKategori,
                'tanggal' => $tanggal,
                'tipe_transaksi' => $tipe,
                'nominal' => $nominal,
                'metode_pembayaran' => $metode,
                'keterangan' => $keterangan,
            ]);

            $idTransaksi = (int) $pdo->lastInsertId();

            if ($tipe === 'pemasukan' && $idKamar !== null && $kategori['nama_kategori'] === 'Sewa Bulanan') {
                $this->syncMonthlyRentStatus($pdo, $idKamar, $tanggal);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        successResponse([
            'transaksi' => $this->findTransaction($pdo, $idTransaksi),
        ], 'Transaksi berhasil disimpan.', 201);
    }

    public function todaySummary(): void
    {
        $today = isset($_GET['tanggal']) ? cleanString($_GET['tanggal']) : date('Y-m-d');
        $errors = validateDateYmd(['tanggal' => $today], 'tanggal');

        if ($errors !== []) {
            errorResponse('Format tanggal ringkasan tidak valid.', 422, $errors);
        }

        $stmt = db()->prepare(
            'SELECT tipe_transaksi, COALESCE(SUM(nominal), 0) AS total
             FROM transaksi
             WHERE tanggal = :tanggal
             GROUP BY tipe_transaksi'
        );
        $stmt->execute([
            'tanggal' => $today,
        ]);

        $income = 0.0;
        $expense = 0.0;

        foreach ($stmt->fetchAll() as $row) {
            if ($row['tipe_transaksi'] === 'pemasukan') {
                $income = (float) $row['total'];
            }

            if ($row['tipe_transaksi'] === 'pengeluaran') {
                $expense = (float) $row['total'];
            }
        }

        successResponse([
            'tanggal' => $today,
            'summary' => [
                'income' => $income,
                'expense' => $expense,
                'laba' => $income - $expense,
            ],
        ]);
    }

    public function rentStatus(): void
    {
        $idKamar = isset($_GET['id_kamar']) ? (int) $_GET['id_kamar'] : 0;
        $tanggal = isset($_GET['tanggal']) ? cleanString($_GET['tanggal']) : date('Y-m-d');
        $errors = mergeErrors(
            validateIntId(['id_kamar' => $idKamar], 'id_kamar'),
            validateDateYmd(['tanggal' => $tanggal], 'tanggal')
        );

        if ($errors !== []) {
            errorResponse('Data status sewa tidak valid.', 422, $errors);
        }

        successResponse([
            'billing' => rentPaymentStatus(db(), $idKamar, rentMonthFromDate($tanggal)),
        ]);
    }

    private function ensureUserExists(PDO $pdo, int $idUser): void
    {
        $stmt = $pdo->prepare('SELECT id_user FROM users WHERE id_user = :id_user LIMIT 1');
        $stmt->execute(['id_user' => $idUser]);

        if (!$stmt->fetch()) {
            errorResponse('User tidak ditemukan.', 422, ['id_user' => 'User tidak valid.']);
        }
    }

    private function transactionFilters(): array
    {
        $clauses = [];
        $params = [];

        $jenis = isset($_GET['jenis']) ? cleanString($_GET['jenis']) : '';
        if ($jenis !== '') {
            $errors = validateEnumValue(['jenis' => $jenis], 'jenis', ['pemasukan', 'pengeluaran']);
            if ($errors !== []) {
                errorResponse('Filter jenis tidak valid.', 422, $errors);
            }

            $clauses[] = 't.tipe_transaksi = :jenis';
            $params['jenis'] = $jenis;
        }

        $kategori = isset($_GET['kategori']) ? cleanString($_GET['kategori']) : '';
        if ($kategori !== '') {
            if (ctype_digit($kategori)) {
                $clauses[] = 't.id_kategori = :kategori_id';
                $params['kategori_id'] = (int) $kategori;
            } else {
                $clauses[] = 'kt.nama_kategori = :kategori_nama';
                $params['kategori_nama'] = $kategori;
            }
        }

        return [
            'where' => $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses),
            'params' => $params,
        ];
    }

    private function positiveQueryInt(string $field, int $default): int
    {
        $value = isset($_GET[$field]) ? (int) $_GET[$field] : $default;
        return max(1, $value);
    }

    private function ensureKamarExists(PDO $pdo, int $idKamar): void
    {
        $stmt = $pdo->prepare('SELECT id_kamar FROM kamar WHERE id_kamar = :id_kamar LIMIT 1');
        $stmt->execute(['id_kamar' => $idKamar]);

        if (!$stmt->fetch()) {
            errorResponse('Kamar tidak ditemukan.', 422, ['id_kamar' => 'Kamar tidak valid.']);
        }
    }

    private function ensureKategoriValid(PDO $pdo, int $idKategori, string $tipe): array
    {
        $stmt = $pdo->prepare(
            'SELECT id_kategori, nama_kategori, tipe_kategori
             FROM kategori
             WHERE id_kategori = :id_kategori
             LIMIT 1'
        );
        $stmt->execute(['id_kategori' => $idKategori]);
        $kategori = $stmt->fetch();

        if (!$kategori) {
            errorResponse('Kategori tidak ditemukan.', 422, ['id_kategori' => 'Kategori tidak valid.']);
        }

        if (!in_array($kategori['tipe_kategori'], [$tipe, 'keduanya'], true)) {
            errorResponse('Kategori tidak sesuai jenis transaksi.', 422, ['id_kategori' => 'Kategori tidak sesuai jenis transaksi.']);
        }

        return $kategori;
    }

    private function syncMonthlyRentStatus(PDO $pdo, int $idKamar, string $tanggal): void
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);

        if (!$date) {
            return;
        }

        $status = rentPaymentStatus($pdo, $idKamar, rentMonthFromDate($date->format('Y-m-d')));
        $statusPembayaran = $status['status_pembayaran'] === 'lunas' ? 'lunas' : 'belum_bayar';
        $update = $pdo->prepare(
            'UPDATE kamar
             SET status_kamar = \'terisi\', status_pembayaran = :status_pembayaran
             WHERE id_kamar = :id_kamar'
        );
        $update->execute([
            'id_kamar' => $idKamar,
            'status_pembayaran' => $statusPembayaran,
        ]);
    }

    private function findTransaction(PDO $pdo, int $idTransaksi): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                t.id_transaksi,
                t.tanggal,
                t.tipe_transaksi,
                t.nominal,
                t.metode_pembayaran,
                t.keterangan,
                k.nomor_kamar,
                kt.id_kategori,
                kt.nama_kategori
             FROM transaksi t
             INNER JOIN kategori kt ON kt.id_kategori = t.id_kategori
             LEFT JOIN kamar k ON k.id_kamar = t.id_kamar
             WHERE t.id_transaksi = :id_transaksi
             LIMIT 1'
        );
        $stmt->execute(['id_transaksi' => $idTransaksi]);
        $row = $stmt->fetch();

        if (!$row) {
            return [];
        }

        return $this->mapTransactionRow($row);
    }

    private function mapTransactionRow(array $row): array
    {
        return [
            'id' => (int) $row['id_transaksi'],
            'date' => $row['tanggal'],
            'type' => $row['tipe_transaksi'],
            'kategori_id' => (int) $row['id_kategori'],
            'kategori' => $row['nama_kategori'],
            'kamar' => $row['nomor_kamar'] ? 'Kamar ' . $row['nomor_kamar'] : '-',
            'note' => $row['keterangan'],
            'amount' => (float) $row['nominal'],
            'metode' => $row['metode_pembayaran'],
        ];
    }
}
