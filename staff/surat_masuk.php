<?php
session_start();
require_once '../config/db.php';

// Auth Check for Staff
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$id_seksi = $_SESSION['user_seksi'] ?? null;
if (!$id_seksi) {
    // If not in session, fetch it
    $stmt = $pdo->prepare("SELECT id_seksi FROM users WHERE nip = ?");
    $stmt->execute([$_SESSION['user_nip']]);
    $id_seksi = $stmt->fetchColumn();
    $_SESSION['user_seksi'] = $id_seksi;
}

$search = $_GET['search'] ?? '';

// --- FETCH TASK LIST ---
$query = "SELECT sm.*, 
          d.isi_disposisi as instruksi_admin, 
          d.tanggal_disposisi as tgl_dispo,
          d.nip_penerima,
          u.nama as pemberi_nama,
          p.nama as penerima_nama,
          sk.status as reply_status
          FROM surat_masuk sm
          LEFT JOIN (
              SELECT * FROM disposisi WHERE id_disposisi IN (SELECT MAX(id_disposisi) FROM disposisi WHERE id_seksi = ? GROUP BY id_surat_masuk)
          ) d ON sm.id_surat_masuk = d.id_surat_masuk
          LEFT JOIN users u ON d.nip_pemberi = u.nip
          LEFT JOIN users p ON d.nip_penerima = p.nip
          LEFT JOIN surat_keluar sk ON sm.id_surat_masuk = sk.id_surat_masuk
          WHERE sm.id_seksi = ? AND sm.perlu_balasan = 1 AND sm.status NOT IN ('selesai', 'diarsipkan') AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ?)
          ORDER BY sm.tanggal_terima DESC LIMIT 50";

$stmt = $pdo->prepare($query);
$stmt->execute([$id_seksi, $id_seksi, "%$search%", "%$search%"]);
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Surat Tugas - Staff Operational</title>
    <link rel="stylesheet" href="../css/staff/home.css">
    <link rel="stylesheet" href="../css/staff/surat_masuk.css">
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
            <a href="surat_masuk.php" class="menu-item active"><svg class="icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Tugas</a>
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
            <div class="header-title"><h1>Daftar Surat Tugas (Seksi)</h1></div>
            <div class="explorer-bar">
                <form method="GET" class="search-field">
                    <svg class="icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Cari perihal, nomor surat, atau pengirim..." value="<?= htmlspecialchars($search) ?>">
                </form>
            </div>
        </header>

        <section class="task-list">
            <?php if (empty($tasks)): ?>
                <div style="text-align: center; padding: 5rem; background: white; border-radius: 2rem; border: 1px solid var(--border);">
                    <svg class="icon" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1.5rem;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    <p style="font-weight: 700; color: var(--text-muted);">Belum ada surat yang ditugaskan ke seksi ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $t): 
                    // Task is only COMPLETE for staff if their REPLY is archived
                    $is_selesai = ($t['reply_status'] === 'diarsipkan');
                    $is_mine = ($t['nip_penerima'] === $_SESSION['user_nip']);
                ?>
                    <div class="task-item" style="<?= $is_mine ? 'border-left: 5px solid var(--primary);' : '' ?>">
                        <div class="date-box">
                            <div class="day"><?= date('d', strtotime($t['tanggal_terima'])) ?></div>
                            <div class="month"><?= date('M Y', strtotime($t['tanggal_terima'])) ?></div>
                        </div>
                        <div class="task-info">
                            <?php if ($t['status'] === 'selesai' || $t['status'] === 'diarsipkan'): ?>
                                <span class="badge-status b-finished">Selesai</span>
                            <?php elseif ($t['reply_status'] === 'pending_approval'): ?>
                                <span class="badge-status" style="background: #fef3c7; color: #d97706; padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">Menunggu Verifikasi</span>
                            <?php else: ?>
                                <span class="badge-status b-pending"><?= $is_mine ? 'Tugas Anda' : 'Tugas Seksi' ?></span>
                            <?php endif; ?>
                            
                            <h4><?= htmlspecialchars($t['perihal']) ?></h4>
                            <p>No: <?= htmlspecialchars($t['nomor_surat']) ?></p>
                            <?php if ($t['penerima_nama']): ?>
                                <div style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--primary); font-weight: 700;">
                                    <svg class="icon" style="width: 14px; height: 14px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    Ditugaskan ke: <?= htmlspecialchars($t['penerima_nama']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="sender-box">
                            <svg class="icon" style="color: var(--primary);"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span><?= htmlspecialchars($t['pengirim']) ?></span>
                        </div>
                        <div style="text-align: right;">
                            <a href="tindak_lanjut.php?id=<?= $t['id_surat_masuk'] ?>" class="btn-work">
                                <svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> 
                                <?= $is_selesai ? 'Lihat Arsip' : 'Kerjakan' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
