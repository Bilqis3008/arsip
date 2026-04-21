<?php
session_start();
require_once '../config/db.php';

// Auth Check for Admin Bidang
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'admin_bidang') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$tab = $_GET['tab'] ?? 'unread'; // unread | history
$search = $_GET['search'] ?? '';

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang FROM users u LEFT JOIN bidang b ON u.id_bidang = b.id_bidang WHERE u.nip = ?");
$stmt->execute([$nip]);
$admin = $stmt->fetch();

$id_bidang = $admin['id_bidang'];

// --- FETCH LIST ---
if ($tab === 'unread') {
    $query = "SELECT sm.*, d.isi_disposisi as instruksi_kadin, d.tanggal_disposisi as tgl_dispo_kadin
              FROM surat_masuk sm 
              LEFT JOIN disposisi d ON d.id_disposisi = (
                  SELECT MIN(id_disposisi) FROM disposisi 
                  WHERE id_surat_masuk = sm.id_surat_masuk 
                  AND id_bidang = sm.id_bidang
                  AND nip_pemberi IN (SELECT nip FROM users WHERE role = 'kepala_dinas')
              )
              WHERE sm.id_bidang = ? AND sm.status = 'didispokan' 
              AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ?) 
              ORDER BY sm.created_at DESC";
} else {
    $query = "SELECT sm.*, d.isi_disposisi as instruksi_kadin
              FROM surat_masuk sm 
              LEFT JOIN disposisi d ON d.id_disposisi = (
                  SELECT MIN(id_disposisi) FROM disposisi 
                  WHERE id_surat_masuk = sm.id_surat_masuk 
                  AND id_bidang = sm.id_bidang
                  AND nip_pemberi IN (SELECT nip FROM users WHERE role = 'kepala_dinas')
              )
              WHERE sm.id_bidang = ? AND sm.status IN ('diteruskan', 'selesai', 'diarsipkan')
              AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ?) 
              ORDER BY sm.created_at DESC";
}

// --- HANDLE DIRECT ARCHIVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_direct'])) {
    $id_target = $_POST['id_surat'];
    $id_seksi_arsip = $_POST['id_seksi'];
    $stmt = $pdo->prepare("UPDATE surat_masuk SET status = 'selesai', id_seksi = ?, perlu_balasan = 0 WHERE id_surat_masuk = ? AND id_bidang = ?");
    if ($stmt->execute([$id_seksi_arsip, $id_target, $id_bidang])) {
        header("Location: surat_masuk.php?tab=unread");
        exit;
    }
}

// Fetch sections for modal
$stmt = $pdo->prepare("SELECT * FROM seksi WHERE id_bidang = ? ORDER BY nama_seksi ASC");
$stmt->execute([$id_bidang]);
$seksi_list = $stmt->fetchAll();


$stmt = $pdo->prepare($query);
$stmt->execute([$id_bidang, "%$search%", "%$search%"]);
$mails = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Masuk Bidang - Admin Ops</title>
    <link rel="stylesheet" href="../css/admin_perbidang/home.css">
    <link rel="stylesheet" href="../css/admin_perbidang/surat_masuk.css">
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
            <a href="surat_masuk.php" class="menu-item active"><svg class="icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
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
                <h1>Agenda Surat Masuk Bidang</h1>
                <p>Mengelola surat yang diterima dari disposisi pimpinan.</p>
            </div>
            <div class="user-profile">
                <div class="user-info"><span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span><span class="user-role"><?= htmlspecialchars($admin['nama_bidang']) ?></span></div>
                <div class="user-avatar"><?= strtoupper(substr($admin['nama_bidang'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Tabs -->
            <div class="tabs-container">
                <a href="surat_masuk.php?tab=unread" class="tab-btn <?= $tab === 'unread' ? 'active' : '' ?>"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> Belum Ditindaklanjuti</a>
                <a href="surat_masuk.php?tab=history" class="tab-btn <?= $tab === 'history' ? 'active' : '' ?>"><svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> Riwayat Tindak Lanjut</a>
            </div>

            <!-- Search -->
            <div class="explorer-bar">
                <form method="GET" class="search-box">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <svg class="icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Cari perihal, nomor surat, atau instruksi..." value="<?= htmlspecialchars($search) ?>">
                </form>
            </div>

            <!-- List -->
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Tgl Terima</th>
                            <th>Identitas & Instruksi Pimpinan</th>
                            <th>Pengirim</th>
                            <th style="width: 150px;">Status</th>
                            <th style="width: 150px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mails)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">Tidak ada agenda surat untuk ditampilkan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($mails as $m): ?>
                                <tr>
                                    <td><b><?= date('d/m/Y', strtotime($m['tanggal_terima'])) ?></b></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--navy);"><?= htmlspecialchars($m['perihal']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">No: <?= htmlspecialchars($m['nomor_surat']) ?></div>
                                        <?php if ($m['instruksi_kadin']): ?>
                                            <div style="background: var(--bg-body); padding: 0.5rem 0.75rem; border-radius: 0.5rem; border-left: 3px solid var(--primary); font-size: 0.8rem; font-style: italic;">
                                                "<?= htmlspecialchars($m['instruksi_kadin']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($m['pengirim']) ?></td>
                                    <td><span class="badge-status status-<?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span></td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                            <?php if ($m['file_path']): ?>
                                                <a href="../<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn-action" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;" title="Preview Dokumen">
                                                    <svg class="icon" viewBox="0 0 24 24" style="width: 16px; height: 16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($tab === 'unread'): ?>
                                                <a href="disposisi_surat.php?id=<?= $m['id_surat_masuk'] ?>" class="btn-action" title="Teruskan ke Seksi"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> </a>
                                                <button onclick="openArchiveModal(<?= $m['id_surat_masuk'] ?>, '<?= htmlspecialchars(addslashes($m['perihal'])) ?>')" class="btn-action" style="background: var(--navy); color: var(--primary); border: none; cursor: pointer;" title="Arsip Langsung"><svg class="icon"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg> </button>
                                            <?php else: ?>
                                                <a href="monitoring_tindakLanjut.php?id=<?= $m['id_surat_masuk'] ?>" class="btn-action" style="background: #f1f5f9; color: var(--navy); border: 1px solid var(--border);"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Track</a>
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

    <!-- Modal for Arsip Langsung -->
    <div id="archiveModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: #fff; width: 100%; max-width: 400px; border-radius: 1rem; padding: 2rem; position: relative; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
            <button onclick="closeArchiveModal()" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">
                <svg viewBox="0 0 24 24" style="width:24px; height:24px; fill:none; stroke:currentColor; stroke-width:2;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h3 style="margin-bottom: 0.5rem; color: #0f172a; font-size: 1.25rem;">Pengarsipan Langsung</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">Pilih seksi mana surat <strong id="modal-perihal" style="color: #0f172a;"></strong> ini akan diarsipkan.</p>
            
            <form method="POST">
                <input type="hidden" name="id_surat" id="modal-id-surat">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Pilih Seksi Penyimpanan</label>
                    <select name="id_seksi" required style="width: 100%; padding: 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 0.5rem; outline: none; font-size: 0.95rem;">
                        <option value="">-- Pilih Seksi Tujuan --</option>
                        <?php foreach ($seksi_list as $s): ?>
                            <option value="<?= $s['id_seksi'] ?>"><?= htmlspecialchars($s['nama_seksi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeArchiveModal()" style="padding: 0.75rem 1rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; border: none; background: #e2e8f0; color: #475569;">Batal</button>
                    <button type="submit" name="archive_direct" style="padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; border: none; background: #10b981; color: white;">Simpan Arsip</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openArchiveModal(id, perihal) {
            document.getElementById('modal-id-surat').value = id;
            document.getElementById('modal-perihal').textContent = '"' + perihal + '"';
            document.getElementById('archiveModal').style.display = 'flex';
        }
        function closeArchiveModal() {
            document.getElementById('archiveModal').style.display = 'none';
        }
    </script>
</body>
</html>
