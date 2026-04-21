<?php
session_start();
require_once '../config/db.php';

// Auth Check for Admin Bidang
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'admin_bidang') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang FROM users u LEFT JOIN bidang b ON u.id_bidang = b.id_bidang WHERE u.nip = ?");
$stmt->execute([$nip]);
$admin = $stmt->fetch();

$id_bidang = $admin['id_bidang'];

// --- STATS QUERIES ---
// 1. Total Surat Masuk Bidang (Current Month)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_masuk WHERE id_bidang = ? AND MONTH(tanggal_terima) = MONTH(CURRENT_DATE)");
$stmt->execute([$id_bidang]);
$total_masuk = $stmt->fetchColumn();

// 2. Belum Ditindaklanjuti (Status = didispokan)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_masuk WHERE id_bidang = ? AND status = 'didispokan'");
$stmt->execute([$id_bidang]);
$pending = $stmt->fetchColumn();

// 3. Sedang Diproses Seksi (Status = diteruskan)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_masuk WHERE id_bidang = ? AND status = 'diteruskan'");
$stmt->execute([$id_bidang]);
$processing = $stmt->fetchColumn();

// 4. Menunggu Persetujuan (New Workflow)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_keluar sk 
                       JOIN surat_masuk sm ON sk.id_surat_masuk = sm.id_surat_masuk 
                       WHERE sm.id_bidang = ? AND sk.status = 'pending_approval'");
$stmt->execute([$id_bidang]);
$waiting_approval = $stmt->fetchColumn();

// 5. Selesai (Status = selesai)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_masuk WHERE id_bidang = ? AND status = 'selesai'");
$stmt->execute([$id_bidang]);
$completed = $stmt->fetchColumn();

// Recent Mail List
$stmt = $pdo->prepare("SELECT * FROM surat_masuk WHERE id_bidang = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$id_bidang]);
$recent_mail = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Bidang - Ops Panel</title>
    <link rel="stylesheet" href="../css/admin_perbidang/home.css">
    <style>
        .chart-container { height: 250px; background: #fff; border-radius: 1.5rem; padding: 2rem; border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 2.5rem; display: flex; align-items: flex-end; gap: 1rem; justify-content: space-around; }
        .bar-group { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; flex: 1; height: 100%; justify-content: flex-end; }
        .bar { width: 100%; background: var(--primary); border-radius: 6px 6px 0 0; transition: var(--transition); min-height: 5px; position: relative; }
        .bar:hover { filter: brightness(1.1); transform: scaleX(1.05); }
        .bar-label { font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
        .bar-val { position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 0.7rem; font-weight: 800; color: var(--navy); }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px; stroke: var(--primary);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>BIDANG OPS</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Dashboard</div>
            <a href="home.php" class="menu-item active"><svg class="icon"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Pengelolaan Surat</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Disposisi Internal</a>
            <a href="monitoring_tindakLanjut.php" class="menu-item"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Monitoring Seksi</a>
            <a href="surat_keluar.php" class="menu-item"><svg class="icon"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Surat Keluar</a>

            <div class="menu-label">Reporting & Account</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <a href="profil.php" class="menu-item"><svg class="icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Logut Panel</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Dashboard <?= htmlspecialchars($admin['nama_bidang']) ?></h1>
                <p>Selamat datang kembali, Admin Bidang.</p>
            </div>
            <div class="user-profile">
                <div class="user-info"><span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span><span class="user-role">Administrator</span></div>
                <div class="user-avatar"><?= strtoupper(substr($admin['nama_bidang'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-num"><?= $total_masuk ?></span>
                    <span class="stat-label">Surat Masuk</span>
                    <span class="badge badge-navy" style="width: fit-content;">Bulan Ini</span>
                </div>
                <div class="stat-card">
                    <span class="stat-num" style="color: var(--primary);"><?= $pending ?></span>
                    <span class="stat-label">Belum Disposisi</span>
                    <span class="badge badge-emerald" style="width: fit-content;">Urgent</span>
                </div>
                <div class="stat-card" style="border-color: var(--accent);">
                    <span class="stat-num" style="color: var(--accent);"><?= $waiting_approval ?></span>
                    <span class="stat-label">Butuh Verifikasi</span>
                    <span class="badge" style="background: var(--accent-light); color: var(--accent); width: fit-content;">Action Required</span>
                </div>
                <div class="stat-card">
                    <span class="stat-num" style="color: #64748b;"><?= $completed ?></span>
                    <span class="stat-label">Total Selesai</span>
                </div>
            </div>

            <!-- Distribution Chart (Simple CSS) -->
            <h3 style="font-weight: 800; color: var(--navy); margin-bottom: 1.5rem;">Distribusi Surat Per Minggu</h3>
            <div class="chart-container">
                <?php 
                $days = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'];
                foreach ($days as $day): 
                    $h = rand(30, 90); // Dummy for now
                ?>
                    <div class="bar-group">
                        <div class="bar" style="height: <?= $h ?>%;">
                            <span class="bar-val"><?= round($h/5) ?></span>
                        </div>
                        <span class="bar-label"><?= $day ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Recent Table -->
            <div class="data-card">
                <div class="card-header"><h3>Agenda Surat Terbaru</h3><a href="surat_masuk.php" style="color: var(--primary); font-weight: 700; font-size: 0.8rem; text-decoration: none;">Lihat Semua &rarr;</a></div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                            <th style="padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Perihal</th>
                            <th style="padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Pengirim</th>
                            <th style="padding: 1rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_mail as $m): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 1rem;">
                                <div style="font-weight: 700; color: var(--navy);"><?= htmlspecialchars($m['perihal']) ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted);">No: <?= htmlspecialchars($m['nomor_surat']) ?></div>
                            </td>
                            <td style="padding: 1rem; font-size: 0.9rem;"><?= htmlspecialchars($m['pengirim']) ?></td>
                            <td style="padding: 1rem;"><span class="badge badge-<?= $m['status'] === 'didispokan' ? 'emerald' : 'navy' ?>"><?= ucfirst($m['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
