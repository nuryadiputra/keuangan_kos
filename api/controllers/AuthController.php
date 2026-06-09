<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

class AuthController
{
    private const DEFAULT_PASSWORD = 'kos_123';
    private const OLD_SEED_PASSWORD_HASH = '$2y$10$Cyo5RcV4ElA3Upq76j96vOC1khrqOpDOeSVViuRy4oriI7F9rqTjy';

    public function login(): void
    {
        $body = readJsonBody();
        $errors = mergeErrors(
            validateRequiredFields($body, ['username', 'password']),
            validateMaxLength($body, 'username', 50),
            validateMaxLength($body, 'password', 255)
        );

        if ($errors !== []) {
            errorResponse('Data login tidak valid.', 422, $errors);
        }

        $username = cleanString($body['username']);
        $password = cleanString($body['password']);

        $stmt = db()->prepare(
            'SELECT id_user, nama, username, password, created_at
             FROM users
             WHERE username = :username
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            errorResponse('Username atau password salah.', 401);
        }

        $storedPassword = (string) $user['password'];
        if (!$this->verifyStoredPassword($password, $storedPassword)) {
            errorResponse('Username atau password salah.', 401);
        }

        if ($this->shouldRehashPassword($storedPassword) || $this->isOldSeedPasswordLogin($password, $storedPassword)) {
            $this->updatePassword((int) $user['id_user'], $password);
        }

        unset($user['password']);

        successResponse([
            'user' => $user,
        ], 'Login berhasil.');
    }

    public function changePassword(): void
    {
        $body = readJsonBody();
        $errors = mergeErrors(
            validateRequiredFields($body, ['password_lama', 'password_baru', 'konfirmasi_password']),
            validateMaxLength($body, 'username', 50),
            validateMaxLength($body, 'username_baru', 50),
            validateMaxLength($body, 'password_lama', 255),
            validateMaxLength($body, 'password_baru', 255),
            validateMaxLength($body, 'konfirmasi_password', 255)
        );

        $hasIdUser   = array_key_exists('id_user', $body) && cleanString($body['id_user']) !== '';
        $hasUsername = array_key_exists('username', $body) && cleanString($body['username']) !== '';

        if ($hasIdUser) {
            $errors = mergeErrors($errors, validateIntId($body, 'id_user'));
        } elseif (!$hasUsername) {
            $errors['username'] = 'Username wajib diisi.';
        }

        $newPassword   = cleanString($body['password_baru']      ?? '');
        $confirmPassword = cleanString($body['konfirmasi_password'] ?? '');
        $usernameBaru  = cleanString($body['username_baru']       ?? '');

        if ($newPassword !== '' && strlen($newPassword) < 6) {
            $errors['password_baru'] = 'Password baru minimal 6 karakter.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors['konfirmasi_password'] = 'Konfirmasi password tidak sama.';
        }

        // Validasi username baru jika diisi
        if ($usernameBaru !== '') {
            if (strlen($usernameBaru) < 3) {
                $errors['username_baru'] = 'Username baru minimal 3 karakter.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $usernameBaru)) {
                $errors['username_baru'] = 'Username hanya boleh huruf, angka, dan underscore (_).';
            } else {
                // Cek apakah username baru sudah dipakai
                $cek = db()->prepare('SELECT id_user FROM users WHERE username = :u LIMIT 1');
                $cek->execute(['u' => $usernameBaru]);
                if ($cek->fetch()) {
                    $errors['username_baru'] = 'Username baru sudah digunakan, pilih yang lain.';
                }
            }
        }

        if ($errors !== []) {
            errorResponse('Data tidak valid.', 422, $errors);
        }

        $user = $hasIdUser
            ? $this->findUserById((int) $body['id_user'])
            : $this->findUserByUsername(cleanString($body['username']));

        if (!$user || !$this->verifyStoredPassword(cleanString($body['password_lama']), (string) $user['password'])) {
            errorResponse('Password lama salah.', 422, ['password_lama' => 'Password lama salah.']);
        }

        $idUser = (int) $user['id_user'];

        // Update password (selalu rehash ke bcrypt terbaru)
        $this->updatePassword($idUser, $newPassword);

        // Update username jika diisi dan berbeda dari yang sekarang
        if ($usernameBaru !== '' && $usernameBaru !== $user['username']) {
            $stmt = db()->prepare('UPDATE users SET username = :username WHERE id_user = :id_user');
            $stmt->execute(['username' => $usernameBaru, 'id_user' => $idUser]);
        }

        $usernameAkhir = ($usernameBaru !== '') ? $usernameBaru : $user['username'];

        successResponse(['username' => $usernameAkhir], 'Username dan password berhasil diubah.');
    }

    public function resetDefaultPassword(): void
    {
        $body = readJsonBody();
        $errors = mergeErrors(
            validateRequiredFields($body, ['id_user']),
            validateIntId($body, 'id_user')
        );

        if ($errors !== []) {
            errorResponse('Data reset password tidak valid.', 422, $errors);
        }

        $idUser = (int) $body['id_user'];
        $user = $this->findUserById($idUser);

        if (!$user) {
            errorResponse('User tidak ditemukan.', 422, ['id_user' => 'User tidak valid.']);
        }

        $this->updatePassword($idUser, self::DEFAULT_PASSWORD);

        successResponse([
            'default_password' => self::DEFAULT_PASSWORD,
        ]);
    }

    private function findUserById(int $idUser): ?array
    {
        $stmt = db()->prepare(
            'SELECT id_user, nama, username, password, created_at
             FROM users
             WHERE id_user = :id_user
             LIMIT 1'
        );
        $stmt->execute(['id_user' => $idUser]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    private function findUserByUsername(string $username): ?array
    {
        $stmt = db()->prepare(
            'SELECT id_user, nama, username, password, created_at
             FROM users
             WHERE username = :username
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    private function updatePassword(int $idUser, string $password): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET password = :password
             WHERE id_user = :id_user'
        );
        $stmt->execute([
            'id_user' => $idUser,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    private function verifyStoredPassword(string $password, string $storedPassword): bool
    {
        // Format sha256: dari seed.sql lama
        if (str_starts_with($storedPassword, 'sha256:')) {
            return hash_equals(substr($storedPassword, 7), hash('sha256', $password));
        }

        // Hash bcrypt dari seed lama yang diketahui
        if ($this->isOldSeedPasswordLogin($password, $storedPassword)) {
            return true;
        }

        // Bcrypt normal
        if (password_verify($password, $storedPassword)) {
            return true;
        }

        // Fallback: plain text (hanya untuk dev/local, password belum pernah di-hash)
        if (hash_equals($storedPassword, $password)) {
            return true;
        }

        return false;
    }

    private function isOldSeedPasswordLogin(string $password, string $storedPassword): bool
    {
        return $password === self::DEFAULT_PASSWORD && hash_equals(self::OLD_SEED_PASSWORD_HASH, $storedPassword);
    }

    private function shouldRehashPassword(string $storedPassword): bool
    {
        return str_starts_with($storedPassword, 'sha256:') || password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
    }
}
