<?php
session_start();
require_once '../config/db.php';

// Auth Check for Staff
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$id_surat = $_GET['id'] ?? null;
if (!$id_surat) {
    header('Location: surat_masuk.php');
    exit;
}

$nip_staff = $_SESSION['user_nip'];

// --- FETCH TASK & INSTRUCTIONS ---
$stmt = $pdo->prepare("SELECT sm.*, b.nama_bidang, s.nama_seksi 
                       FROM surat_masuk sm 
                       LEFT JOIN bidang b ON sm.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON sm.id_seksi = s.id_seksi 
                       WHERE sm.id_surat_masuk = ?");
$stmt->execute([$id_surat]);
$mail = $stmt->fetch();

if (!$mail) {
    header('Location: surat_masuk.php');
    exit;
}

// Fetch Chain of Command (Disposisi)
$stmt = $pdo->prepare("SELECT d.*, u.nama, u.role 
                       FROM disposisi d 
                       JOIN users u ON d.nip_pemberi = u.nip 
                       WHERE d.id_surat_masuk = ? 
                       ORDER BY d.tanggal_disposisi ASC");
$stmt->execute([$id_surat]);
$instructions = $stmt->fetchAll();

// Check if already has a pending or approved reply
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_keluar WHERE id_surat_masuk = ? AND status IN ('pending_approval', 'diarsipkan')");
$stmt->execute([$id_surat]);
$has_reply = $stmt->fetchColumn() > 0;

// --- HANDLE FULFILLMENT (UPLOAD REPLY) ---
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_fulfillment']) && !$has_reply) {
    $nomor_surat_keluar = $_POST['nomor_surat_keluar'];
    $tujuan = $_POST['tujuan'];
    $perihal = "Balasan: " . $mail['perihal'];
    $tanggal_surat = date('Y-m-d');
    
    // File Upload
    $file_name = null;
    if (isset($_FILES['file_reply']) && $_FILES['file_reply']['error'] === 0) {
        $ext = pathinfo($_FILES['file_reply']['name'], PATHINFO_EXTENSION);
        $file_name = "REPLY_" . time() . "_" . uniqid() . "." . $ext;
        $target_path = "../uploads/surat_keluar/" . $file_name;
        
        if (!is_dir("../uploads/surat_keluar/")) {
            mkdir("../uploads/surat_keluar/", 0777, true);
        }
        
        if (move_uploaded_file($_FILES['file_reply']['tmp_name'], $target_path)) {
            try {
                // Insert into surat_keluar with pending_approval status
                $stmt = $pdo->prepare("INSERT INTO surat_keluar (nomor_surat_keluar, tanggal_surat, perihal, id_surat_masuk, tujuan, file_path, uploaded_by, status) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_approval')");
                $stmt->execute([$nomor_surat_keluar, $tanggal_surat, $perihal, $id_surat, $tujuan, $file_name, $nip_staff]);
                
                $success = "Draft balasan berhasil diunggah! Menunggu verifikasi dari Admin Bidang.";
                $has_reply = true;
            } catch (PDOException $e) {
                $error = "Kesalahan Database: " . $e->getMessage();
            }
        } else {
            $error = "Gagal mengunggah file.";
        }
    } else {
        $error = "File balasan wajib diunggah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyelesaian Tugas - Staff Operational</title>
    <link rel="stylesheet" href="../css/staff/home.css">
    <link rel="stylesheet" href="../css/staff/tindak_lanjut.css">
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
            <a href="tindak_lanjut.php" class="menu-item active"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Kerjakan Balasan</a>
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
        <div class="content-header">
            <div class="header-title"><h1>Tindak Lanjut & Balasan Surat</h1></div>
        </div>

        <?php if ($success): ?><div style="padding: 1rem; background: #f0fdf4; color: #16a34a; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div style="padding: 1rem; background: #fff1f2; color: #e11d48; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $error ?></div><?php endif; ?>

        <div class="fulfillment-grid">
            <!-- Left: Command Trace -->
            <div class="card-trace">
                <div class="trace-header">
                    <h2><?= htmlspecialchars($mail['perihal']) ?></h2>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">STATUS: <b style="color: var(--primary);"><?= strtoupper($mail['status']) ?></b></p>
                </div>

                <div class="trace-timeline">
                    <!-- Original Mail -->
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <span class="timeline-label">SURAT ASLI (PENGIRIM: <?= htmlspecialchars($mail['pengirim']) ?>)</span>
                            <div class="timeline-body"><?= htmlspecialchars($mail['nomor_surat']) ?></div>
                            <div style="margin-top: 0.5rem;"><a href="../<?= $mail['file_path'] ?>" target="_blank" style="font-size: 0.75rem; color: var(--primary); font-weight: 800; text-decoration: none;">Download Surat Masuk &rarr;</a></div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <?php foreach ($instructions as $ins): ?>
                        <div class="timeline-item active">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-label">INSTRUKSI DARI: <?= strtoupper($ins['role']) ?> (<?= htmlspecialchars($ins['nama']) ?>)</span>
                                <div class="timeline-body"><?= nl2br(htmlspecialchars($ins['isi_disposisi'])) ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem; font-weight: 800;"><?= date('d M Y H:i', strtotime($ins['tanggal_disposisi'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Fulfillment Form -->
            <div class="card-fulfillment">
                <?php if ($mail['perlu_balasan'] == 1 && !$has_reply): ?>
                    <div class="form-title">
                        <svg class="icon" style="stroke: var(--primary);"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <h3>Unggah Surat Balasan</h3>
                    </div>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Nomor Surat Balasan</label>
                            <input type="text" name="nomor_surat_keluar" placeholder="Contoh: 004/DISDIK/IV/2026" required>
                        </div>
                        <div class="form-group">
                            <label>Tujuan / Penerima Balasan</label>
                            <input type="text" name="tujuan" value="<?= htmlspecialchars($mail['pengirim']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>File Surat Balasan (PDF)</label>
                            <input type="file" name="file_reply" accept=".pdf" required>
                        </div>
                        <div class="form-group">
                            <label>Keterangan Tambahan</label>
                            <textarea name="keterangan" placeholder="Catatan proses penyelesaian..."></textarea>
                        </div>
                        <button type="submit" name="submit_fulfillment" class="btn-finish">Kirim Untuk Verifikasi</button>
                    </form>
                <?php elseif ($has_reply && $mail['status'] !== 'selesai'): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <svg class="icon" style="width: 60px; height: 60px; color: var(--accent); margin-bottom: 1.5rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <h3 style="font-weight: 800; color: var(--navy);">Menunggu Verifikasi</h3>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0.75rem;">Balasan telah diunggah. Tugas akan ditandai selesai setelah disetujui oleh Admin Bidang.</p>
                        <a href="surat_masuk.php" class="btn-finish" style="margin-top: 2rem; display: block; text-decoration: none; background: var(--navy);">Kembali ke Daftar Tugas</a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem;">
                        <svg class="icon" style="width: 60px; height: 60px; color: var(--primary); margin-bottom: 1.5rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <h3 style="font-weight: 800; color: var(--navy);">Selesai Dikerjakan</h3>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0.75rem;">Surat ini telah disetujui dan diarsipkan secara resmi.</p>
                        </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
