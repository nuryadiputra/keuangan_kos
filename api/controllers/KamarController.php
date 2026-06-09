<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/rent.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

class KamarController
{
    public function index(): void
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT
                k.id_kamar,
                k.nomor_kamar,
                k.nama_penyewa,
                k.status_kamar,
                k.harga_sewa,
                k.created_at,
                k.updated_at
             FROM kamar k
             ORDER BY CAST(k.nomor_kamar AS UNSIGNED), k.nomor_kamar'
        );
        $stmt->execute();
        $bulan = date('Y-m');

        successResponse([
            'kamar' => array_map(
                static fn (array $room): array => rentPaymentStatusForRoom($pdo, $room, $bulan),
                $stmt->fetchAll()
            ),
        ]);
    }

    public function store(): void
    {
        $body = readJsonBody();
        $data = $this->validatedPayload($body);
        $pdo = db();

        $this->ensureNomorAvailable($pdo, $data['nomor_kamar']);

        $stmt = $pdo->prepare(
            'INSERT INTO kamar
                (nomor_kamar, nama_penyewa, status_kamar, harga_sewa, status_pembayaran)
             VALUES
                (:nomor_kamar, :nama_penyewa, :status_kamar, :harga_sewa, :status_pembayaran)'
        );
        $stmt->execute($data);

        successResponse([
            'kamar' => $this->findKamar($pdo, (int) $pdo->lastInsertId()),
        ], 'Kamar berhasil ditambahkan.', 201);
    }

    public function update(): void
    {
        $body = readJsonBody();
        $errors = validateIntId($body, 'id_kamar');

        if ($errors !== []) {
            errorResponse('Data kamar tidak valid.', 422, $errors);
        }

        $idKamar = (int) $body['id_kamar'];
        $data = $this->validatedPayload($body);
        $pdo = db();

        $this->ensureKamarExists($pdo, $idKamar);
        $this->ensureNomorAvailable($pdo, $data['nomor_kamar'], $idKamar);

        $stmt = $pdo->prepare(
            'UPDATE kamar
             SET nomor_kamar = :nomor_kamar,
                 nama_penyewa = :nama_penyewa,
                 status_kamar = :status_kamar,
                 harga_sewa = :harga_sewa,
                 status_pembayaran = :status_pembayaran
             WHERE id_kamar = :id_kamar'
        );
        $stmt->execute($data + ['id_kamar' => $idKamar]);

        successResponse([
            'kamar' => $this->findKamar($pdo, $idKamar),
        ], 'Kamar berhasil diupdate.');
    }

    public function destroy(): void
    {
        $body = readJsonBody();
        $errors = validateIntId($body, 'id_kamar');

        if ($errors !== []) {
            errorResponse('Data kamar tidak valid.', 422, $errors);
        }

        $pdo = db();
        $idKamar = (int) $body['id_kamar'];
        $this->ensureKamarExists($pdo, $idKamar);

        $stmt = $pdo->prepare('DELETE FROM kamar WHERE id_kamar = :id_kamar');
        $stmt->execute(['id_kamar' => $idKamar]);

        successResponse([], 'Kamar berhasil dihapus.');
    }

    private function validatedPayload(array $body): array
    {
        $errors = mergeErrors(
            validateRequiredFields($body, [
                'nomor_kamar',
                'status_kamar',
                'harga_sewa',
                'status_pembayaran',
            ]),
            validateMaxLength($body, 'nomor_kamar', 10),
            validateMaxLength($body, 'nama_penyewa', 100),
            validateEnumValue($body, 'status_kamar', ['terisi', 'kosong']),
            validateEnumValue($body, 'status_pembayaran', ['lunas', 'belum_bayar']),
            validatePositiveNumber($body, 'harga_sewa')
        );

        $status = cleanString($body['status_kamar'] ?? '');
        $namaPenyewa = cleanString($body['nama_penyewa'] ?? '');

        if ($status === 'terisi' && $namaPenyewa === '') {
            $errors['nama_penyewa'] = 'Nama penyewa wajib diisi untuk kamar terisi.';
        }

        if ($errors !== []) {
            errorResponse('Data kamar tidak valid.', 422, $errors);
        }

        return [
            'nomor_kamar' => cleanString($body['nomor_kamar']),
            'nama_penyewa' => $status === 'kosong' ? null : $namaPenyewa,
            'status_kamar' => $status,
            'harga_sewa' => (float) $body['harga_sewa'],
            'status_pembayaran' => $status === 'kosong' ? 'belum_bayar' : cleanString($body['status_pembayaran']),
        ];
    }

    private function ensureKamarExists(PDO $pdo, int $idKamar): void
    {
        $stmt = $pdo->prepare('SELECT id_kamar FROM kamar WHERE id_kamar = :id_kamar LIMIT 1');
        $stmt->execute(['id_kamar' => $idKamar]);

        if (!$stmt->fetch()) {
            errorResponse('Kamar tidak ditemukan.', 404);
        }
    }

    private function ensureNomorAvailable(PDO $pdo, string $nomorKamar, ?int $ignoreId = null): void
    {
        $sql = 'SELECT id_kamar FROM kamar WHERE nomor_kamar = :nomor_kamar';
        $params = ['nomor_kamar' => $nomorKamar];

        if ($ignoreId !== null) {
            $sql .= ' AND id_kamar <> :id_kamar';
            $params['id_kamar'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            errorResponse('Nomor kamar sudah digunakan.', 422, [
                'nomor_kamar' => 'Nomor kamar harus unik.',
            ]);
        }
    }

    private function findKamar(PDO $pdo, int $idKamar): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                id_kamar,
                nomor_kamar,
                nama_penyewa,
                status_kamar,
                harga_sewa,
                status_pembayaran,
                created_at,
                updated_at
             FROM kamar
             WHERE id_kamar = :id_kamar
             LIMIT 1'
        );
        $stmt->execute(['id_kamar' => $idKamar]);

        return $stmt->fetch() ?: [];
    }

    private function monthRange(string $bulan): array
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $bulan . '-01');

        if (!$start) {
            errorResponse('Format bulan tidak valid.', 422, ['bulan' => 'Format bulan tidak valid.']);
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $start->modify('first day of next month')->format('Y-m-d'),
        ];
    }
}
