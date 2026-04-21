<?php
session_start();
require_once '../config/db.php';

// Auth Check for Admin Bidang
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'admin_bidang') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$tab = $_GET['tab'] ?? 'pending'; // pending | verified
$search = $_GET['search'] ?? '';

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang FROM users u LEFT JOIN bidang b ON u.id_bidang = b.id_bidang WHERE u.nip = ?");
$stmt->execute([$nip]);
$admin = $stmt->fetch();

$id_bidang = $admin['id_bidang'];

// --- HANDLE APPROVAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $id_approve = $_POST['approve_id'];
    $stmt = $pdo->prepare("UPDATE surat_keluar SET status = 'diarsipkan' WHERE id_surat_keluar = ? AND uploaded_by IN (SELECT nip FROM users WHERE id_bidang = ?)");
    if ($stmt->execute([$id_approve, $id_bidang])) {
        header("Location: surat_keluar.php?tab=verified");
        exit;
    }
}

// --- FETCH LIST ---
if ($tab === 'pending') {
    $query = "SELECT sk.*, u.nama as pengirim_staf, s.nama_seksi 
              FROM surat_keluar sk 
              JOIN users u ON sk.uploaded_by = u.nip 
              LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
              WHERE u.id_bidang = ? AND sk.status = 'pending_approval'
              AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ?) 
              ORDER BY sk.created_at DESC";
} else {
    $query = "SELECT sk.*, u.nama as pengirim_staf, s.nama_seksi 
              FROM surat_keluar sk 
              JOIN users u ON sk.uploaded_by = u.nip 
              LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
              WHERE u.id_bidang = ? AND sk.status IN ('disetujui', 'diarsipkan')
              AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ?) 
              ORDER BY sk.created_at DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute([$id_bidang, "%$search%", "%$search%"]);
$mails = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Keluar / Verifikasi Draft - Admin Ops</title>
    <link rel="stylesheet" href="../css/admin_perbidang/home.css">
    <link rel="stylesheet" href="../css/admin_perbidang/surat_masuk.css">
    <style>
        .badge-status.status-pending_approval { background: #fef3c7; color: #b45309; }
        .badge-status.status-disetujui { background: #dcfce7; color: #16a34a; }
        .badge-status.status-diarsipkan { background: #e0e7ff; color: #4338ca; }

        /* Custom Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-out;
        }
        .modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            text-align: center;
            transform: translateY(0);
            animation: slideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .modal-icon {
            width: 70px;
            height: 70px;
            background: #fef3c7;
            color: #d97706;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .modal-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem; }
        .modal-message { font-size: 0.95rem; color: #64748b; line-height: 1.6; margin-bottom: 2rem; }
        .modal-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn-modal { padding: 0.85rem; border-radius: 0.75rem; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-cancel { background: #f1f5f9; color: #64748b; }
        .btn-cancel:hover { background: #e2e8f0; }
        .btn-confirm { background: var(--primary); color: white; }
        .btn-confirm:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px var(--primary-glow); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
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
            <a href="home.php" class="menu-item"><svg class="icon"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Pengelolaan Surat</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Disposisi Internal</a>
            <a href="monitoring_tindakLanjut.php" class="menu-item"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Monitoring Seksi</a>
            <a href="surat_keluar.php" class="menu-item active"><svg class="icon"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Surat Keluar</a>
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
                <h1>Verifikasi Surat Keluar</h1>
                <p>Verifikasi draft surat yang diunggah oleh seksi di bidang ini untuk diarsipkan.</p>
            </div>
            <div class="user-profile">
                <div class="user-info"><span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span><span class="user-role"><?= htmlspecialchars($admin['nama_bidang']) ?></span></div>
                <div class="user-avatar"><?= strtoupper(substr($admin['nama_bidang'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Tabs -->
            <div class="tabs-container">
                <a href="surat_keluar.php?tab=pending" class="tab-btn <?= $tab === 'pending' ? 'active' : '' ?>"><svg class="icon"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Belum Diverifikasi</a>
                <a href="surat_keluar.php?tab=verified" class="tab-btn <?= $tab === 'verified' ? 'active' : '' ?>"><svg class="icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Riwayat Disetujui</a>
            </div>

            <!-- Search -->
            <div class="explorer-bar">
                <form method="GET" class="search-box">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <svg class="icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Cari perihal, nomor surat..." value="<?= htmlspecialchars($search) ?>">
                </form>
            </div>

            <!-- List -->
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Tanggal Surat</th>
                            <th>Identitas & Dokumen Dokumen</th>
                            <th>Penulis Staf / Seksi</th>
                            <th style="width: 150px;">Status</th>
                            <th style="width: 150px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mails)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">Tidak ada draft surat keluar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($mails as $m): ?>
                                <tr>
                                    <td><b><?= date('d/m/Y', strtotime($m['tanggal_surat'])) ?></b></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--navy);"><?= htmlspecialchars($m['perihal']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">No. Surat Keluar: <?= htmlspecialchars($m['nomor_surat_keluar']) ?> • Tujuan: <?= htmlspecialchars($m['tujuan']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($m['pengirim_staf']) ?><br><span style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($m['nama_seksi'] ?: 'Seksi / Sub-Bagian') ?></span></td>
                                    <td>
                                        <?php 
                                            $badgeText = "Diarsipkan";
                                            if($m['status'] == 'pending_approval') $badgeText = "Selesai Draft (Belum Verifikasi)";
                                        ?>
                                        <span class="badge-status status-<?= $m['status'] ?>"><?= $badgeText ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                            <?php if ($m['file_path']): ?>
                                                <a href="../uploads/surat_keluar/<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn-action" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;" title="Preview PDF">
                                                    <svg class="icon" viewBox="0 0 24 24" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($tab === 'pending'): ?>
                                                <div style="flex: 1;">
                                                    <button type="button" 
                                                            onclick="openConfirmModal(<?= $m['id_surat_keluar'] ?>, '<?= htmlspecialchars(addslashes($m['perihal'])) ?>')" 
                                                            class="btn-action" 
                                                            style="background: var(--primary); color: white; border: none; cursor: pointer; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 700; width: 100%; white-space: nowrap; height: 34px;">
                                                        <svg class="icon" style="margin-right:0.25rem;"><polyline points="20 6 9 17 4 12"></polyline></svg> Arsipkan
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <a href="monitoring_tindakLanjut.php?search=<?= urlencode($m['nomor_surat_keluar']) ?>" class="btn-action" style="background: #f1f5f9; color: var(--navy); border: 1px solid var(--border); text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 700; display: inline-flex; align-items: center; white-space: nowrap; height: 34px;"><svg class="icon" style="margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Track</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Hidden Form for submission -->
    <form id="approveForm" method="POST" style="display: none;">
        <input type="hidden" name="approve_id" id="approve_target_id">
    </form>

    <!-- Custom Confirmation Modal -->
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-icon">
                <svg viewBox="0 0 24 24" style="width:32px; height:32px; fill:none; stroke:currentColor; stroke-width:2.5;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <h3 class="modal-title">Konfirmasi Pengarsipan</h3>
            <p class="modal-message">Apakah Anda yakin draft surat <strong id="modal-perihal" style="color:#0f172a;"></strong> ini sudah sesuai? Surat akan diarsipkan secara permanen.</p>
            <div class="modal-actions">
                <button type="button" onclick="closeConfirmModal()" class="btn-modal btn-cancel">Batal</button>
                <button type="button" onclick="submitApprove()" class="btn-modal btn-confirm">Ya, Arsipkan</button>
            </div>
        </div>
    </div>

    <script>
        function openConfirmModal(id, perihal) {
            document.getElementById('approve_target_id').value = id;
            document.getElementById('modal-perihal').innerText = '"' + perihal + '"';
            document.getElementById('confirmModal').style.display = 'flex';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        function submitApprove() {
            document.getElementById('approveForm').submit();
        }

        // Close on escape
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeConfirmModal();
        });
    </script>
</body>
</html>
