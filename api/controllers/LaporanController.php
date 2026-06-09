<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/rent.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

class LaporanController
{
    public function dashboard(): void
    {
        $bulan = isset($_GET['bulan']) ? cleanString($_GET['bulan']) : date('Y-m');
        $range = $this->monthRange($bulan);
        $pdo = db();

        $summary = $this->dashboardSummary($pdo, $range['start'], $range['end']);
        $rooms = $this->roomSummary($pdo);

        successResponse([
            'bulan' => $bulan,
            'summary' => $summary,
            'rooms' => $rooms,
            'chart' => $this->dashboardChart($pdo, $range['start'], $range['end']),
            'recent' => $this->dashboardRecentTransactions($pdo, $range['start'], $range['end']),
        ]);
    }

    public function monthlyReport(): void
    {
        $bulan = isset($_GET['bulan']) ? cleanString($_GET['bulan']) : date('Y-m');
        $range = $this->monthRange($bulan);
        $pdo = db();
        $summary = $this->dashboardSummary($pdo, $range['start'], $range['end']);
        $saldoAwal = $this->balanceBefore($pdo, $range['start']);

        successResponse([
            'bulan' => $bulan,
            'summary' => $summary,
            'income_categories' => $this->categoryTotals($pdo, $range['start'], $range['end'], 'pemasukan'),
            'expense_categories' => $this->categoryTotals($pdo, $range['start'], $range['end'], 'pengeluaran'),
            'balance' => [
                'saldo_awal' => $saldoAwal,
                'income' => $summary['income'],
                'expense' => $summary['expense'],
                'saldo_akhir' => $saldoAwal + $summary['laba'],
            ],
            'room_status' => $this->monthlyRoomStatus($pdo, $bulan),
        ]);
    }

    public function availableMonths(): void
    {
        $stmt = db()->prepare(
            'SELECT DISTINCT DATE_FORMAT(tanggal, \'%Y-%m\') AS bulan
             FROM transaksi
             ORDER BY bulan DESC'
        );
        $stmt->execute();

        $months = array_column($stmt->fetchAll(), 'bulan');
        $currentMonth = date('Y-m');

        if (!in_array($currentMonth, $months, true)) {
            array_unshift($months, $currentMonth);
        }

        successResponse([
            'months' => $months,
        ]);
    }

    private function monthRange(string $bulan): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            errorResponse('Format bulan harus YYYY-MM.', 422, ['bulan' => 'Format bulan tidak valid.']);
        }

        $start = DateTimeImmutable::createFromFormat('Y-m-d', $bulan . '-01');

        if (!$start) {
            errorResponse('Format bulan tidak valid.', 422, ['bulan' => 'Format bulan tidak valid.']);
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $start->modify('first day of next month')->format('Y-m-d'),
        ];
    }

    private function dashboardSummary(PDO $pdo, string $start, string $end): array
    {
        $stmt = $pdo->prepare(
            'SELECT tipe_transaksi, COALESCE(SUM(nominal), 0) AS total
             FROM transaksi
             WHERE tanggal >= :start_date AND tanggal < :end_date
             GROUP BY tipe_transaksi'
        );
        $stmt->execute([
            'start_date' => $start,
            'end_date' => $end,
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

        return [
            'income' => $income,
            'expense' => $expense,
            'laba' => $income - $expense,
        ];
    }

    private function roomSummary(PDO $pdo): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status_kamar = \'terisi\' THEN 1 ELSE 0 END), 0) AS terisi
             FROM kamar'
        );
        $stmt->execute();
        $row = $stmt->fetch() ?: ['total' => 0, 'terisi' => 0];

        return [
            'total' => (int) $row['total'],
            'terisi' => (int) $row['terisi'],
        ];
    }

    private function dashboardChart(PDO $pdo, string $start, string $end): array
    {
        $buckets = [
            ['label' => '1-7', 'income' => 0.0, 'expense' => 0.0],
            ['label' => '8-14', 'income' => 0.0, 'expense' => 0.0],
            ['label' => '15-21', 'income' => 0.0, 'expense' => 0.0],
            ['label' => '22-28', 'income' => 0.0, 'expense' => 0.0],
            ['label' => '29-31', 'income' => 0.0, 'expense' => 0.0],
        ];

        $stmt = $pdo->prepare(
            'SELECT tanggal, tipe_transaksi, COALESCE(SUM(nominal), 0) AS total
             FROM transaksi
             WHERE tanggal >= :start_date AND tanggal < :end_date
             GROUP BY tanggal, tipe_transaksi
             ORDER BY tanggal ASC'
        );
        $stmt->execute([
            'start_date' => $start,
            'end_date' => $end,
        ]);

        foreach ($stmt->fetchAll() as $row) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $row['tanggal']);
            if (!$date) {
                continue;
            }

            $bucketIndex = min(4, intdiv(((int) $date->format('j')) - 1, 7));
            $key = $row['tipe_transaksi'] === 'pemasukan' ? 'income' : 'expense';
            $buckets[$bucketIndex][$key] += (float) $row['total'];
        }

        return $buckets;
    }

    private function dashboardRecentTransactions(PDO $pdo, string $start, string $end): array
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
                kt.nama_kategori
             FROM transaksi t
             INNER JOIN kategori kt ON kt.id_kategori = t.id_kategori
             LEFT JOIN kamar k ON k.id_kamar = t.id_kamar
             WHERE t.tanggal >= :start_date AND t.tanggal < :end_date
             ORDER BY t.created_at DESC, t.id_transaksi DESC
             LIMIT 5'
        );
        $stmt->execute([
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id_transaksi'],
                'date' => $row['tanggal'],
                'type' => $row['tipe_transaksi'],
                'kategori' => $row['nama_kategori'],
                'kamar' => $row['nomor_kamar'] ? 'Kamar ' . $row['nomor_kamar'] : '-',
                'note' => $row['keterangan'],
                'amount' => (float) $row['nominal'],
                'metode' => $row['metode_pembayaran'],
            ];
        }, $stmt->fetchAll());
    }

    private function categoryTotals(PDO $pdo, string $start, string $end, string $type): array
    {
        $stmt = $pdo->prepare(
            'SELECT kt.nama_kategori, COALESCE(SUM(t.nominal), 0) AS total
             FROM transaksi t
             INNER JOIN kategori kt ON kt.id_kategori = t.id_kategori
             WHERE t.tanggal >= :start_date
               AND t.tanggal < :end_date
               AND t.tipe_transaksi = :type
             GROUP BY kt.id_kategori, kt.nama_kategori
             ORDER BY kt.nama_kategori'
        );
        $stmt->execute([
            'start_date' => $start,
            'end_date' => $end,
            'type' => $type,
        ]);

        return array_map(static function (array $row): array {
            return [
                'kategori' => $row['nama_kategori'],
                'total' => (float) $row['total'],
            ];
        }, $stmt->fetchAll());
    }

    private function balanceBefore(PDO $pdo, string $start): float
    {
        $stmt = $pdo->prepare(
            'SELECT tipe_transaksi, COALESCE(SUM(nominal), 0) AS total
             FROM transaksi
             WHERE tanggal < :start_date
             GROUP BY tipe_transaksi'
        );
        $stmt->execute(['start_date' => $start]);

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

        return $income - $expense;
    }

    private function monthlyRoomStatus(PDO $pdo, string $bulan): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                k.id_kamar,
                k.nomor_kamar,
                k.nama_penyewa,
                k.status_kamar,
                k.harga_sewa
             FROM kamar k
             ORDER BY CAST(k.nomor_kamar AS UNSIGNED), k.nomor_kamar'
        );
        $stmt->execute();

        return array_map(static function (array $row) use ($pdo, $bulan): array {
            $status = rentPaymentStatusForRoom($pdo, $row, $bulan);

            return [
                'id' => $status['id_kamar'],
                'nomor' => $status['nomor_kamar'],
                'penyewa' => $status['nama_penyewa'],
                'status_kamar' => $status['status_kamar'],
                'harga_sewa' => $status['harga_sewa'],
                'total_bayar' => $status['total_bayar'],
                'sisa_tagihan' => $status['sisa_tagihan'],
                'kredit_bulan_depan' => $status['kredit_bulan_depan'],
                'status_pembayaran' => $status['status_pembayaran'],
            ];
        }, $stmt->fetchAll());
    }
}
