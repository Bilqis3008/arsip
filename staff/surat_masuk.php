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
$tab = $_GET['tab'] ?? 'pending'; // pending | history

// --- FETCH TASK LIST ---
$query = "SELECT sm.*, 
          sk.id_surat_keluar as reply_id,
          sk.status as reply_status,
          d.nip_penerima,
          u.nama as pemberi_nama,
          p.nama as penerima_nama,
          sk.status as reply_status_sk
          FROM surat_masuk sm
          LEFT JOIN (
              SELECT * FROM disposisi WHERE id_disposisi IN (SELECT MAX(id_disposisi) FROM disposisi WHERE id_seksi = ? GROUP BY id_surat_masuk)
          ) d ON sm.id_surat_masuk = d.id_surat_masuk
          LEFT JOIN users u ON d.nip_pemberi = u.nip
          LEFT JOIN users p ON d.nip_penerima = p.nip
          LEFT JOIN surat_keluar sk ON sm.id_surat_masuk = sk.id_surat_masuk
          WHERE sm.id_seksi = ? AND sm.perlu_balasan = 1 
          AND " . ($tab === 'pending' ? "(sm.status NOT IN ('selesai', 'diarsipkan') AND sk.id_surat_keluar IS NULL)" : "(sm.status IN ('selesai', 'diarsipkan') OR sk.id_surat_keluar IS NOT NULL)") . "
          AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ?)
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
            <div class="header-title">
                <h1>Agenda Surat Tugas</h1>
                <p>Kelola surat yang ditugaskan ke seksi Anda.</p>
            </div>
            <div class="explorer-bar">
                <form method="GET" class="search-field">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <svg class="icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Cari perihal, nomor surat..." value="<?= htmlspecialchars($search) ?>">
                </form>
            </div>
        </header>

        <!-- Tabs Navigation -->
        <div class="tabs-container" style="margin: 0 2rem 1.5rem; display: flex; gap: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 1px;">
            <a href="surat_masuk.php?tab=pending" class="tab-link <?= $tab === 'pending' ? 'active' : '' ?>" style="padding: 0.75rem 1.5rem; text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.9rem; position: relative; transition: all 0.2s;">
                <svg class="icon" style="width:16px; height:16px; margin-right:6px; vertical-align: middle;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Belum Dikerjakan
                <?php if($tab === 'pending'): ?><div style="position: absolute; bottom: -1px; left: 0; right: 0; height: 3px; background: var(--primary); border-radius: 3px;"></div><?php endif; ?>
            </a>
            <a href="surat_masuk.php?tab=history" class="tab-link <?= $tab === 'history' ? 'active' : '' ?>" style="padding: 0.75rem 1.5rem; text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.9rem; position: relative; transition: all 0.2s;">
                <svg class="icon" style="width:16px; height:16px; margin-right:6px; vertical-align: middle;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                Riwayat Selesai
                <?php if($tab === 'history'): ?><div style="position: absolute; bottom: -1px; left: 0; right: 0; height: 3px; background: var(--primary); border-radius: 3px;"></div><?php endif; ?>
            </a>
        </div>

        <style>
            .tab-link.active { color: var(--navy) !important; }
            .tab-link:hover { color: var(--primary) !important; background: #f8fafc; border-radius: 0.5rem 0.5rem 0 0; }
        </style>

        <section class="task-list">
            <?php if (empty($tasks)): ?>
                <div style="text-align: center; padding: 5rem; background: white; border-radius: 2rem; border: 1px solid var(--border);">
                    <svg class="icon" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1.5rem;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    <p style="font-weight: 700; color: var(--text-muted);">
                        <?= $tab === 'pending' ? 'Belum ada surat yang ditugaskan ke seksi ini.' : 'Belum ada riwayat tugas yang tuntas.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $t): 
                    $is_selesai = ($tab === 'history');
                    $is_mine = ($t['nip_penerima'] === $_SESSION['user_nip']);
                ?>
                    <div class="task-item" style="<?= $is_mine ? 'border-left: 5px solid var(--primary);' : '' ?>">
                        <div class="date-box">
                            <div class="day"><?= date('d', strtotime($t['tanggal_terima'])) ?></div>
                            <div class="month"><?= date('M Y', strtotime($t['tanggal_terima'])) ?></div>
                        </div>
                        <div class="task-info">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
                                <?php if ($is_selesai): ?>
                                    <?php if (in_array($t['reply_status_sk'], ['disetujui', 'diarsipkan'])): ?>
                                        <span style="background: #ecfdf5; color: #059669; padding: 0.1rem 0.6rem; border-radius: 1rem; font-size: 0.7rem; font-weight: 800; border: 1px solid #10b981;">✓ SELESAI</span>
                                    <?php else: ?>
                                        <span style="background: #fffbeb; color: #d97706; padding: 0.1rem 0.6rem; border-radius: 1rem; font-size: 0.7rem; font-weight: 800; border: 1px solid #f59e0b;">⏳ SEDANG DIVERIFIKASI</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="task-badge"><?= $is_mine ? 'Tugas Anda' : 'Tugas Seksi' ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="task-perihal" style="margin: 0;"><?= htmlspecialchars($t['perihal']) ?></h3>
                            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">No: <?= htmlspecialchars($t['nomor_surat']) ?></p>
                            <?php if ($t['penerima_nama']): ?>
                                <div style="margin-top: 0.35rem; font-size: 0.75rem; color: var(--primary); font-weight: 700;">
                                    <svg class="icon" style="width: 14px; height: 14px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    Ditugaskan ke: <?= htmlspecialchars($t['penerima_nama']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="sender-box" style="flex: 0 0 200px;">
                            <svg class="icon" style="color: var(--primary); margin-right: 0.5rem;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span style="font-size: 0.9rem;"><?= htmlspecialchars($t['pengirim']) ?></span>
                        </div>
                        <div class="task-actions" style="margin-left: auto;">
                            <a href="tindak_lanjut.php?id=<?= $t['id_surat_masuk'] ?>" class="btn-work" style="<?= $is_selesai ? 'background: var(--navy); color: var(--primary); border: none; padding: 0.75rem 1.5rem;' : '' ?>">
                                <?php if ($is_selesai): ?>
                                    <svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> Lihat Arsip
                                <?php else: ?>
                                    <svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path></svg> Kerjakan
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
