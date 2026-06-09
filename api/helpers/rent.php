<?php
declare(strict_types=1);

function rentMonthRange(string $bulan): array
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

function rentMonthFromDate(string $tanggal): string
{
    return substr($tanggal, 0, 7);
}

function rentPaymentStatus(PDO $pdo, int $idKamar, string $bulan): array
{
    $stmt = $pdo->prepare(
        'SELECT
            id_kamar,
            nomor_kamar,
            nama_penyewa,
            status_kamar,
            harga_sewa,
            created_at,
            updated_at
         FROM kamar
         WHERE id_kamar = :id_kamar
         LIMIT 1'
    );
    $stmt->execute(['id_kamar' => $idKamar]);
    $room = $stmt->fetch();

    if (!$room) {
        errorResponse('Kamar tidak ditemukan.', 422, ['id_kamar' => 'Kamar tidak valid.']);
    }

    return rentPaymentStatusForRoom($pdo, $room, $bulan);
}

function rentPaymentStatusForRoom(PDO $pdo, array $room, string $bulan): array
{
    $range = rentMonthRange($bulan);
    $hargaSewa = (float) ($room['harga_sewa'] ?? 0);
    $base = [
        'id_kamar' => (int) $room['id_kamar'],
        'nomor_kamar' => $room['nomor_kamar'],
        'nama_penyewa' => $room['nama_penyewa'],
        'status_kamar' => $room['status_kamar'],
        'harga_sewa' => $hargaSewa,
        'bulan' => $bulan,
        'total_bayar' => 0.0,
        'sisa_tagihan' => $hargaSewa,
        'kredit_bulan_depan' => 0.0,
        'status_pembayaran' => 'belum_bayar',
    ];

    if (($room['status_kamar'] ?? '') === 'kosong') {
        return array_merge($base, [
            'sisa_tagihan' => 0.0,
            'status_pembayaran' => 'kosong',
        ]);
    }

    if ($hargaSewa <= 0) {
        return $base;
    }

    $firstStmt = $pdo->prepare(
        'SELECT MIN(DATE_FORMAT(t.tanggal, \'%Y-%m\')) AS bulan_awal
         FROM transaksi t
         INNER JOIN kategori kt ON kt.id_kategori = t.id_kategori
         WHERE t.id_kamar = :id_kamar
           AND t.tipe_transaksi = \'pemasukan\'
           AND kt.nama_kategori = \'Sewa Bulanan\''
    );
    $firstStmt->execute(['id_kamar' => (int) $room['id_kamar']]);
    $firstMonth = $firstStmt->fetch()['bulan_awal'] ?? null;
    $baseMonth = $firstMonth && strcmp((string) $firstMonth, $bulan) < 0 ? (string) $firstMonth : $bulan;

    $paidStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(t.nominal), 0) AS total_bayar
         FROM transaksi t
         INNER JOIN kategori kt ON kt.id_kategori = t.id_kategori
         WHERE t.id_kamar = :id_kamar
           AND t.tanggal < :end_date
           AND t.tipe_transaksi = \'pemasukan\'
           AND kt.nama_kategori = \'Sewa Bulanan\''
    );
    $paidStmt->execute([
        'id_kamar' => (int) $room['id_kamar'],
        'end_date' => $range['end'],
    ]);

    $totalPaidUntilMonth = (float) ($paidStmt->fetch()['total_bayar'] ?? 0);
    $monthsBefore = rentMonthsBetween($baseMonth, $bulan);
    $allocatedBefore = $monthsBefore * $hargaSewa;
    $availableForMonth = max(0.0, $totalPaidUntilMonth - $allocatedBefore);
    $paidForMonth = min($hargaSewa, $availableForMonth);
    $remaining = max(0.0, $hargaSewa - $paidForMonth);
    $creditNextMonth = max(0.0, $availableForMonth - $hargaSewa);

    $status = 'belum_bayar';
    if ($paidForMonth >= $hargaSewa) {
        $status = 'lunas';
    } elseif ($paidForMonth > 0) {
        $status = 'sebagian';
    }

    return array_merge($base, [
        'total_bayar' => $paidForMonth,
        'sisa_tagihan' => $remaining,
        'kredit_bulan_depan' => $creditNextMonth,
        'status_pembayaran' => $status,
    ]);
}

function rentMonthsBetween(string $fromMonth, string $toMonth): int
{
    [$fromYear, $fromMonthNumber] = array_map('intval', explode('-', $fromMonth));
    [$toYear, $toMonthNumber] = array_map('intval', explode('-', $toMonth));

    return max(0, (($toYear - $fromYear) * 12) + ($toMonthNumber - $fromMonthNumber));
}
