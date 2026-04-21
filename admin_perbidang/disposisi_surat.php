<?php
session_start();
require_once '../config/db.php';

// Auth Check for Admin Bidang
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'admin_bidang') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];
$id_surat = $_GET['id'] ?? null;

if (!$id_surat) {
    header('Location: surat_masuk.php');
    exit;
}

// Fetch Admin Bidang Info
$stmt = $pdo->prepare("SELECT u.id_bidang, b.nama_bidang FROM users u JOIN bidang b ON u.id_bidang = b.id_bidang WHERE u.nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();

$id_bidang = $admin['id_bidang'];

// --- FETCH LETTER & KADIN DISPO ---
$stmt = $pdo->prepare("SELECT sm.*, d.isi_disposisi as instruksi_kadin, d.sifat_disposisi, d.tanggal_disposisi as tgl_kadin 
                       FROM surat_masuk sm 
                       LEFT JOIN disposisi d ON sm.id_surat_masuk = d.id_surat_masuk AND d.nip_pemberi IN (SELECT nip FROM users WHERE role = 'kepala_dinas')
                       WHERE sm.id_surat_masuk = ? AND sm.id_bidang = ?");
$stmt->execute([$id_surat, $id_bidang]);
$mail = $stmt->fetch();

if (!$mail) {
    header('Location: surat_masuk.php');
    exit;
}

// --- FETCH SECTIONS IN THIS DEPT ---
$stmt = $pdo->prepare("SELECT * FROM seksi WHERE id_bidang = ? ORDER BY nama_seksi ASC");
$stmt->execute([$id_bidang]);
$seksi_list = $stmt->fetchAll();

// --- FETCH ALL STAFF IN THIS DEPT (for JS filtering) ---
$stmt = $pdo->prepare("SELECT nip, nama, id_seksi FROM users WHERE id_bidang = ? AND role = 'staff' ORDER BY nama ASC");
$stmt->execute([$id_bidang]);
$staff_list = $stmt->fetchAll();

// --- HANDLE INTERNAL DISPOSITION ---
$message = '';
$error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dispro_internal'])) {
    $id_seksi = $_POST['id_seksi'];
    $nip_penerima = $_POST['nip_penerima'] ?: null; // Optional specific person
    $isi_disposisi = $_POST['isi_disposisi'];
    $sifat_disposisi = $mail['sifat_disposisi'];
    $tanggal_disposisi = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        // 1. Insert New Disposisi (Internal) with Receiver NIP
        $stmt = $pdo->prepare("INSERT INTO disposisi (id_surat_masuk, nip_pemberi, id_bidang, id_seksi, nip_penerima, isi_disposisi, sifat_disposisi, tanggal_disposisi) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_surat, $nip_admin, $id_bidang, $id_seksi, $nip_penerima, $isi_disposisi, $sifat_disposisi, $tanggal_disposisi]);

        // 2. Update Surat Masuk Status and current Section
        $stmt = $pdo->prepare("UPDATE surat_masuk SET status = 'diteruskan', id_seksi = ?, perlu_balasan = 1 WHERE id_surat_masuk = ?");
        $stmt->execute([$id_seksi, $id_surat]);

        $pdo->commit();
        $message = "Instruksi internal berhasil diteruskan ke Seksi terkait.";
        $mail['status'] = 'diteruskan';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Gagal memproses disposisi: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disposisi Internal - Admin Bidang</title>
    <link rel="stylesheet" href="../css/admin_perbidang/home.css">
    <link rel="stylesheet" href="../css/admin_perbidang/disposisi_surat.css">
</head>
<body>
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
            <a href="disposisi_surat.php" class="menu-item active"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Disposisi Internal</a>
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
            <div class="header-title"><h1>Meneruskan Disposisi Internal</h1></div>
        </header>

        <div class="content-body">
            <?php if ($message): ?><div style="padding: 1rem; background: #dcfce7; color: #15803d; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $message ?></div><?php endif; ?>
            <?php if ($error): ?><div style="padding: 1rem; background: #fee2e2; color: #b91c1c; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $error ?></div><?php endif; ?>

            <div class="dispo-container">
                <!-- Left: Doc & Kadin Inst -->
                <div class="card-doc">
                    <div class="doc-header">
                        <h2><?= htmlspecialchars($mail['perihal']) ?></h2>
                        <span class="badge"><?= htmlspecialchars($mail['status']) ?></span>
                    </div>

                    <?php if ($mail['instruksi_kadin']): ?>
                        <div class="kadin-instruction">
                            <p style="font-weight: 600; color: #92400e; line-height: 1.6;"><?= nl2br(htmlspecialchars($mail['instruksi_kadin'])) ?></p>
                            <div style="margin-top: 1rem; font-size: 0.7rem; color: #b45309; font-weight: 800;">DITERIMA PADA: <?= date('d M Y H:i', strtotime($mail['tgl_kadin'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="doc-meta-grid">
                        <div class="meta-item"><label>Nomor Surat</label><span><?= htmlspecialchars($mail['nomor_surat']) ?></span></div>
                        <div class="meta-item"><label>Pengirim</label><span><?= htmlspecialchars($mail['pengirim']) ?></span></div>
                        <div class="meta-item"><label>Sifat Surat</label><span style="color: var(--primary); text-transform: uppercase;"><?= ucfirst($mail['sifat_surat']) ?></span></div>
                    </div>
                </div>

                <!-- Right: Internal Form -->
                <div class="card-form">
                    <div class="form-title">
                        <svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path></svg>
                        <h3>Instruksi ke Seksi</h3>
                    </div>
                    <?php if ($mail['status'] === 'didispokan' || isset($_GET['re-dispo'])): ?>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label>Pilih Seksi Pelaksana</label>
                            <select name="id_seksi" id="id_seksi" required onchange="filterStaff()">
                                <option value="">-- Pilih Seksi / Sub Bagian --</option>
                                <?php foreach ($seksi_list as $s): ?>
                                    <option value="<?= $s['id_seksi'] ?>"><?= htmlspecialchars($s['nama_seksi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Pilih Staff Pelaksana (Opsional)</label>
                            <select name="nip_penerima" id="nip_penerima">
                                <option value="">-- Pilih Staff (Pilih Seksi Dulu) --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Catatan Instruksi Bidang (Opsional)</label>
                            <textarea name="isi_disposisi" placeholder="Tulis instruksi tindak lanjut untuk staf seksi..."></textarea>
                        </div>
                        <button type="submit" name="submit_dispro_internal" class="btn-submit">
                            <svg class="icon" style="stroke: var(--primary);"><polyline points="20 6 9 17 4 12"></polyline></svg> Teruskan ke Seksi
                        </button>
                    </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; border-radius: 1rem; background: #f8fafc; border: 1px solid var(--border);">
                            <svg class="icon" style="width: 48px; height: 48px; color: var(--primary); margin-bottom: 1rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 8 12 12 16 14"></polyline></svg>
                            <p style="font-weight: 700; color: var(--navy);">Sudah Diteruskan</p>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">Surat ini sedang dalam proses tindak lanjut oleh seksi terkait.</p>
                            <a href="monitoring_tindakLanjut.php?id=<?= $id_surat ?>" class="btn-submit" style="margin-top: 1.5rem; text-decoration: none;">Cek Progress</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script>
        const staffData = <?= json_encode($staff_list) ?>;
        
        function filterStaff() {
            const seksiId = document.getElementById('id_seksi').value;
            const staffSelect = document.getElementById('nip_penerima');
            
            // Clear current options
            staffSelect.innerHTML = '<option value="">-- Pilih Staff (Opsional) --</option>';
            
            if (seksiId) {
                const filteredStaff = staffData.filter(s => s.id_seksi == seksiId);
                
                if (filteredStaff.length > 0) {
                    filteredStaff.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.nip;
                        opt.textContent = s.nama;
                        staffSelect.appendChild(opt);
                    });
                } else {
                    const opt = document.createElement('option');
                    opt.disabled = true;
                    opt.textContent = 'Belum ada staf di seksi ini';
                    staffSelect.appendChild(opt);
                }
            }
        }
    </script>
</body>
</html>
