<?php
session_start();
require_once '../config/db.php';

// Auth Check for Staff
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$success_msg = "";
$error_msg = "";

// --- HANDLE PASSWORD UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass === $confirm_pass) {
        if (strlen($new_pass) >= 8) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE nip = ?");
            if ($stmt->execute([$hashed, $nip])) {
                $success_msg = "Password berhasil diperbarui!";
            } else {
                $error_msg = "Gagal mengubah password.";
            }
        } else {
            $error_msg = "Password minimal 8 karakter.";
        }
    } else {
        $error_msg = "Konfirmasi password tidak cocok.";
    }
}

// Fetch Staff Data
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang, s.nama_seksi 
                       FROM users u 
                       LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
                       WHERE u.nip = ?");
$stmt->execute([$nip]);
$user_data = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Staff - Panel Operasional</title>
    <link rel="stylesheet" href="../css/staff/home.css">
    <link rel="stylesheet" href="../css/staff/profil.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px; stroke: var(--primary);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <h2>STAFF PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Dashboard</div>
            <a href="home.php" class="menu-item"><svg class="icon"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Pekerjaan Saya</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Tugas</a>
            <a href="tindak_lanjut.php" class="menu-item"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Kerjakan Balasan</a>
            <div class="menu-label">Monitoring & Arsip</div>
            <a href="monitoring.php" class="menu-item"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Account</div>
            <a href="profil.php" class="menu-item active"><svg class="icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sesi</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Akun Pengguna</h1></div>
        </header>

        <section class="profil-wrapper">
            <!-- Sidebar Details -->
            <div class="card-profile-header">
                <div class="avatar-staff"><?= strtoupper(substr($user_data['nama'], 0, 1)) ?></div>
                <h3><?= htmlspecialchars($user_data['nama']) ?></h3>
                <p>NIP: <?= htmlspecialchars($user_data['nip']) ?></p>
            </div>

            <!-- Main Form -->
            <div class="card-profile-main">
                <?php if ($success_msg): ?><div style="padding: 1rem; background: #f0fdf4; color: #16a34a; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $success_msg ?></div><?php endif; ?>
                <?php if ($error_msg): ?><div style="padding: 1rem; background: #fff1f2; color: #e11d48; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $error_msg ?></div><?php endif; ?>

                <div class="p-section-title"><svg class="icon" style="color: var(--primary);"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> INFORMASI PERSONAL</div>
                <div class="detail-p-row"><span class="detail-p-label">NAMA LENGKAP</span><span class="detail-p-val"><?= htmlspecialchars($user_data['nama']) ?></span></div>
                <div class="detail-p-row"><span class="detail-p-label">EMAIL DINAS</span><span class="detail-p-val"><?= htmlspecialchars($user_data['email']) ?></span></div>
                <div class="detail-p-row"><span class="detail-p-label">JABATAN / SEKSI</span><span class="detail-p-val"><?= htmlspecialchars($user_data['nama_seksi']) ?></span></div>
                <div class="detail-p-row" style="border: none;"><span class="detail-p-label">UNIT KERJA (BIDANG)</span><span class="detail-p-val"><?= htmlspecialchars($user_data['nama_bidang']) ?></span></div>

                <div class="p-section-title" style="margin-top: 3.5rem;"><svg class="icon" style="color: var(--accent);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> PENGATURAN KEAMANAN</div>
                <form action="" method="POST">
                    <div class="p-form-group">
                        <label>Password Baru</label>
                        <input type="password" name="new_password" class="p-input" placeholder="Masukkan password minimal 8 karakter..." required>
                    </div>
                    <div class="p-form-group">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="p-input" placeholder="Ulangi password baru Anda..." required>
                    </div>
                    <button type="submit" name="update_password" class="btn-update-profil">Perbarui Kode Keamanan</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
