<?php
session_start();
require_once '../config/db.php';

// Auth Check for Staff
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];

// Fetch Staff Info (Bidang & Seksi)
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang, s.nama_seksi FROM users u 
                       LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
                       WHERE u.nip = ?");
$stmt->execute([$nip]);
$user = $stmt->fetch();

$id_seksi = $user['id_seksi'];

// --- STATS ---
// 1. Tugas Belum Selesai (Ada di seksi ini tapi belum ada balasan yang diarsipkan)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_masuk sm 
                       LEFT JOIN surat_keluar sk ON sm.id_surat_masuk = sk.id_surat_masuk
                       WHERE sm.id_seksi = ? AND (sk.status IS NULL OR sk.status != 'diarsipkan')");
$stmt->execute([$id_seksi]);
$count_pending = $stmt->fetchColumn();

// 2. Draft Balasan (Surat Keluar status draft by this staff)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_keluar WHERE uploaded_by = ? AND status = 'draft'");
$stmt->execute([$nip]);
$count_draft = $stmt->fetchColumn();

// 3. Tugas Selesai (Balasan sudah disetujui/diarsipkan)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_keluar sk 
                       JOIN surat_masuk sm ON sk.id_surat_masuk = sm.id_surat_masuk
                       WHERE sm.id_seksi = ? AND sk.status = 'diarsipkan'");
$stmt->execute([$id_seksi]);
$count_finished = $stmt->fetchColumn();

// 4. Total Upload
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_keluar WHERE uploaded_by = ?");
$stmt->execute([$nip]);
$count_uploads = $stmt->fetchColumn();

// --- RECENT INSTRUCTIONS ---
$stmt = $pdo->prepare("SELECT d.*, sm.perihal, u.nama as pemberi_nama 
                       FROM disposisi d 
                       JOIN surat_masuk sm ON d.id_surat_masuk = sm.id_surat_masuk 
                       JOIN users u ON d.nip_pemberi = u.nip
                       WHERE d.id_seksi = ? 
                       ORDER BY d.tanggal_disposisi DESC LIMIT 5");
$stmt->execute([$id_seksi]);
$recent_dispo = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Arsip Kadin</title>
    <link rel="stylesheet" href="../css/staff/home.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px; stroke: var(--primary);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <h2>STAFF PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Dashboard</div>
            <a href="home.php" class="menu-item active"><svg class="icon"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Pekerjaan Saya</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Tugas</a>
            <a href="tindak_lanjut.php" class="menu-item"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Kerjakan Balasan</a>
            <div class="menu-label">Monitoring & Arsip</div>
            <a href="monitoring.php" class="menu-item"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Account</div>
            <a href="profil.php" class="menu-item"><svg class="icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sesi</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Halo, <?= htmlspecialchars($user['nama']) ?></h1>
                <p>Unit Kerja: <?= htmlspecialchars($user['nama_bidang']) ?> / <?= htmlspecialchars($user['nama_seksi']) ?></p>
            </div>
            <div style="background: white; padding: 0.75rem 1.5rem; border-radius: 1rem; border: 1px solid var(--border); box-shadow: var(--shadow-md);">
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted);">TANGGAL HARI INI</div>
                <div style="font-size: 0.9375rem; font-weight: 700; color: var(--primary);"><?= date('d F Y') ?></div>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon amber"><svg class="icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div>
                    <span class="badge badge-teal">Tugas</span>
                </div>
                <div class="stat-value"><?= $count_pending ?></div>
                <div class="stat-label">Sedang Diproses</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon rose"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path></svg></div>
                </div>
                <div class="stat-value"><?= $count_draft ?></div>
                <div class="stat-label">Draft Balasan</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon teal"><svg class="icon"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                </div>
                <div class="stat-value"><?= $count_finished ?></div>
                <div class="stat-label">Selesai Dikerjakan</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue"><svg class="icon"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg></div>
                </div>
                <div class="stat-value"><?= $count_uploads ?></div>
                <div class="stat-label">Surat Diunggah</div>
            </div>
        </section>

        <div class="data-card">
            <div class="card-header">
                <h3>Instruksi Tindak Lanjut Terbaru</h3>
                <a href="surat_masuk.php" style="color: var(--primary); font-size: 0.8rem; font-weight: 800; text-decoration: none;">Lihat Semua &rarr;</a>
            </div>
            <div style="padding: 1rem;">
                <?php if (empty($recent_dispo)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 2rem;">Belum ada instruksi tugas baru.</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                                <th style="padding: 1rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Perihal Surat</th>
                                <th style="padding: 1rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Instruksi Pimpinan</th>
                                <th style="padding: 1rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_dispo as $d): ?>
                                <tr style="border-bottom: 1px solid #f8fafc;">
                                    <td style="padding: 1.25rem 1rem;">
                                        <div style="font-weight: 700; font-size: 0.9375rem; color: var(--primary-dark);"><?= htmlspecialchars($d['perihal']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Dari: <?= htmlspecialchars($d['pemberi_nama']) ?></div>
                                    </td>
                                    <td style="padding: 1.25rem 1rem; font-size: 0.875rem; max-width: 300px; line-height: 1.4;"><?= nl2br(htmlspecialchars($d['isi_disposisi'])) ?></td>
                                    <td style="padding: 1.25rem 1rem; font-size: 0.8125rem; font-weight: 800; color: var(--text-muted);"><?= date('d M Y', strtotime($d['tanggal_disposisi'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
