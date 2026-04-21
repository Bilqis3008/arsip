<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$success_msg = $_SESSION['success_msg'] ?? "";
$error_msg = $_SESSION['error_msg'] ?? "";

// Clear messages after reading
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// --- HANDLE FORM SUBMISSION (INPUT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_mail') {
    // Generasi Nomor Agenda Otomatis
    $date_prefix = 'ARS-' . date('Ymd') . '-';
    $stmt_last = $pdo->prepare("SELECT nomor_agenda FROM surat_masuk WHERE nomor_agenda LIKE ? ORDER BY nomor_agenda DESC LIMIT 1");
    $stmt_last->execute([$date_prefix . '%']);
    $last_agenda = $stmt_last->fetchColumn();
    if ($last_agenda) {
        $last_num = (int) substr($last_agenda, -4);
        $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_num = '0001';
    }
    $nomor_agenda = $date_prefix . $new_num;
    $nomor_surat = $_POST['nomor_surat'];
    $tanggal_surat = $_POST['tanggal_surat'];
    $tanggal_terima = $_POST['tanggal_terima'];
    $pengirim = $_POST['pengirim'];
    $perihal = $_POST['perihal'];
    $sifat_surat = $_POST['sifat_surat'];
    $lampiran = (int) $_POST['lampiran'];
    $keterangan = $_POST['keterangan'];

    // File Upload handling
    $file_path = null;
    $upload_error = false;

    if (isset($_FILES['file_surat']) && $_FILES['file_surat']['name'] !== '') {
        if ($_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/surat_masuk/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $file_extension = pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9]/", "_", $perihal) . '.' . $file_extension;
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['file_surat']['tmp_name'], $target_file)) {
                $file_path = 'uploads/surat_masuk/' . $filename;
            }
        } else {
            $upload_error = true;
            if ($_FILES['file_surat']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['file_surat']['error'] === UPLOAD_ERR_FORM_SIZE) {
                $error_msg = "Ukuran file terlalu besar! Silakan perkecil ukuran file atau kompres terlebih dahulu.";
            } else {
                $error_msg = "Terjadi kesalahan saat mengunggah file (Kode: " . $_FILES['file_surat']['error'] . ")";
            }
        }
    }

    if (!$upload_error) {
        try {
            $stmt = $pdo->prepare("INSERT INTO surat_masuk (nomor_agenda, nomor_surat, tanggal_surat, tanggal_terima, pengirim, perihal, sifat_surat, lampiran, file_path, input_by, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'tercatat', ?)");
            $stmt->execute([$nomor_agenda, $nomor_surat, $tanggal_surat, $tanggal_terima, $pengirim, $perihal, $sifat_surat, $lampiran, $file_path, $nip, $keterangan]);
            $_SESSION['success_msg'] = "Surat masuk berhasil disimpan!";
            header("Location: surat_masuk.php");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $_SESSION['error_msg'] = "Nomor Agenda sudah terdaftar dalam sistem!";
            } else {
                $_SESSION['error_msg'] = "Gagal menyimpan surat: " . $e->getMessage();
            }
            header("Location: surat_masuk.php");
            exit;
        }
    } else {
        $_SESSION['error_msg'] = $error_msg;
        header("Location: surat_masuk.php");
        exit;
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    // Only allow delete if status is 'tercatat' (not yet processed)
    $check = $pdo->prepare("SELECT file_path, status FROM surat_masuk WHERE id_surat_masuk = ?");
    $check->execute([$del_id]);
    $del_row = $check->fetch();
    // Allow delete if 'tercatat' or just 'didispokan' (to Kadin) but not yet processed by internal sectors
    if ($del_row && in_array($del_row['status'], ['tercatat', 'didispokan'])) {
        // Delete file if exists
        if ($del_row['file_path'] && file_exists('../' . $del_row['file_path'])) {
            unlink('../' . $del_row['file_path']);
        }
        $pdo->prepare("DELETE FROM surat_masuk WHERE id_surat_masuk = ?")->execute([$del_id]);
        $_SESSION['success_msg'] = "Surat masuk berhasil dihapus.";
    } else {
        $_SESSION['error_msg'] = "Surat yang sudah diproses oleh Bidang tidak dapat dihapus.";
    }
    header("Location: surat_masuk.php");
    exit;
}

// --- HANDLE EDIT (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_mail') {
    $edit_id  = (int)$_POST['edit_id'];
    $nomor_agenda  = $_POST['nomor_agenda'];
    $nomor_surat   = $_POST['nomor_surat'];
    $tanggal_surat = $_POST['tanggal_surat'];
    $tanggal_terima= $_POST['tanggal_terima'];
    $pengirim      = $_POST['pengirim'];
    $perihal       = $_POST['perihal'];
    $sifat_surat   = $_POST['sifat_surat'];
    $lampiran      = (int)$_POST['lampiran'];
    $keterangan    = $_POST['keterangan'];

    // Check if new file uploaded
    $new_file_path = $_POST['existing_file_path'] ?? null;
    if (isset($_FILES['file_surat']) && $_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/surat_masuk/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION);
        $fname = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $perihal) . '.' . $ext;
        if (move_uploaded_file($_FILES['file_surat']['tmp_name'], $upload_dir . $fname)) {
            // Delete old file
            if ($new_file_path && file_exists('../' . $new_file_path)) unlink('../' . $new_file_path);
            $new_file_path = 'uploads/surat_masuk/' . $fname;
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE surat_masuk SET nomor_agenda=?, nomor_surat=?, tanggal_surat=?, tanggal_terima=?, pengirim=?, perihal=?, sifat_surat=?, lampiran=?, keterangan=?, file_path=? WHERE id_surat_masuk=?");
        $stmt->execute([$nomor_agenda, $nomor_surat, $tanggal_surat, $tanggal_terima, $pengirim, $perihal, $sifat_surat, $lampiran, $keterangan, $new_file_path, $edit_id]);
        $_SESSION['success_msg'] = "Data surat berhasil diperbarui!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal memperbarui: " . $e->getMessage();
    }
    header("Location: surat_masuk.php");
    exit;
}

// --- FETCH DATA FOR TABLE (DAFTAR & RIWAYAT) ---
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sifat_filter = $_GET['sifat'] ?? '';

$query = "SELECT * FROM surat_masuk WHERE (perihal LIKE ? OR nomor_surat LIKE ? OR pengirim LIKE ?)";
$params = ["%$search%", "%$search%", "%$search%"];

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($sifat_filter)) {
    $query .= " AND sifat_surat = ?";
    $params[] = $sifat_filter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_mails = $stmt->fetchAll();

$mails = [];
$riwayat_mails = [];
foreach ($all_mails as $m) {
    if ($m['status'] === 'selesai') {
        $riwayat_mails[] = $m;
    } else {
        $mails[] = $m;
    }
}

// Pre-calculate next agenda number for the Add Form display
$date_prefix = 'ARS-' . date('Ymd') . '-';
$stmt_last = $pdo->prepare("SELECT nomor_agenda FROM surat_masuk WHERE nomor_agenda LIKE ? ORDER BY nomor_agenda DESC LIMIT 1");
$stmt_last->execute([$date_prefix . '%']);
$last_agenda = $stmt_last->fetchColumn();
$new_num = $last_agenda ? str_pad(((int) substr($last_agenda, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$next_agenda_number = $date_prefix . $new_num;

// Fetch Admin Data for profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Surat Masuk - Arsip Digital</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/surat_masuk.css">
</head>

<body>
    <!-- Sidebar (Shared from home.php) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px;">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            <h2>ARSIP DIGITAL</h2>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                Dashboard
            </a>

            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item active">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                Surat Masuk
            </a>

            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Manajemen Pengguna
            </a>
            <a href="monitoring_surat.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path>
                    <line x1="12" y1="14" x2="12" y2="19"></line>
                </svg>
                Monitoring Surat
            </a>

            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                Laporan
            </a>
            
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg> Profil Saya</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn">
                <svg class="icon" viewBox="0 0 24 24" style="stroke: #fda4af;">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Keluar Sistem
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Manajemen Surat Masuk</h1>
            </div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr($admin['nama'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Alert Notifications -->
            <?php if ($success_msg): ?>
                <div
                    style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--success); margin-bottom: 1.5rem; font-weight: 600;">
                    <svg class="icon" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 0.5rem;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg> <?= $success_msg ?>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div
                    style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--danger); margin-bottom: 1.5rem; font-weight: 600;">
                    <svg class="icon" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 0.5rem;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg> <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <!-- Module Tabs -->
            <div class="module-tabs">
                <button class="tab-btn active" onclick="switchTab('daftar')">
                    <svg class="icon" style="margin-right: 0.5rem;">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg> Daftar Surat
                </button>
                <button class="tab-btn" onclick="switchTab('riwayat')">
                    <svg class="icon" style="margin-right: 0.5rem;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                        <path d="M3.3 7a10 10 0 1 0 17.4 0"></path>
                    </svg> Riwayat
                </button>
            </div>

            <!-- Section: Daftar Surat -->
            <section id="section-daftar" class="module-section active">
                <div class="card">
                    <div class="table-controls" style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; flex: 1;">
                            <form action="" method="GET" class="search-box" style="flex: 1; min-width: 250px;">
                                <svg class="icon"
                                    style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                <input type="text" name="search" placeholder="Cari perihal atau pengirim..."
                                    value="<?= htmlspecialchars($search) ?>" style="padding-left: 2.75rem; width: 100%;">
                            </form>
                            <form action="" method="GET" class="filter-group">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="tercatat" <?= $status_filter === 'tercatat' ? 'selected' : '' ?>>Tercatat
                                    </option>
                                    <option value="didispokan" <?= $status_filter === 'didispokan' ? 'selected' : '' ?>>
                                        Didispokan</option>
                                    <option value="selesai" <?= $status_filter === 'selesai' ? 'selected' : '' ?>>Selesai
                                    </option>
                                </select>
                                <select name="sifat" onchange="this.form.submit()">
                                    <option value="">Semua Sifat</option>
                                    <option value="biasa" <?= $sifat_filter === 'biasa' ? 'selected' : '' ?>>Biasa</option>
                                    <option value="penting" <?= $sifat_filter === 'penting' ? 'selected' : '' ?>>Penting
                                    </option>
                                    <option value="segera" <?= $sifat_filter === 'segera' ? 'selected' : '' ?>>Segera</option>
                                    <option value="rahasia" <?= $sifat_filter === 'rahasia' ? 'selected' : '' ?>>Rahasia
                                    </option>
                                </select>
                            </form>
                        </div>
                        <button class="btn btn-primary" onclick="openInputModal()" style="display: flex; align-items: center; gap: 0.5rem; justify-content: center; min-width: max-content; padding: 0.75rem 1.5rem;">
                            <svg class="icon" style="width: 20px; height: 20px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Surat
                        </button>
                    </div>

                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No. Agenda</th>
                                    <th>Info Surat</th>
                                    <th>Pengirim</th>
                                    <th>Sifat</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mails)): ?>
                                    <tr>
                                        <td colspan="6"
                                            style="text-align: center; padding: 3rem; color: var(--text-muted);">Tidak ada
                                            data surat masuk.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mails as $mail): ?>
                                        <tr>
                                            <td style="font-weight: 700;"><?= htmlspecialchars($mail['nomor_agenda']) ?></td>
                                            <td>
                                                <div style="font-weight: 600;"><?= htmlspecialchars($mail['perihal']) ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">No:
                                                    <?= htmlspecialchars($mail['nomor_surat']) ?> •
                                                    <?= date('d M Y', strtotime($mail['tanggal_terima'])) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($mail['pengirim']) ?></td>
                                            <td>
                                                <span class="badge-status status-<?= $mail['sifat_surat'] ?>">
                                                    <?= ucfirst($mail['sifat_surat']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status status-<?= $mail['status'] ?>">
                                                    <?= ucfirst($mail['status'] === 'tercatat' ? 'Tercatat' : ($mail['status'] === 'didispokan' ? 'Proses Disposisi' : $mail['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="action-btns">
                                                <button class="action-btn btn-view" title="Lihat Detail" onclick='openViewModal(<?= htmlspecialchars(json_encode($mail)) ?>)'>
                                                    <svg class="icon" style="width: 16px; height: 16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </button>
                                                <?php if ($mail['status'] === 'tercatat'): ?>
                                                <button class="action-btn btn-edit" title="Edit" onclick='openEditModal(<?= htmlspecialchars(json_encode($mail)) ?>)'>
                                                    <svg class="icon" style="width: 16px; height: 16px;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                                                </button>
                                                <a href="?delete_id=<?= $mail['id_surat_masuk'] ?>" class="action-btn btn-delete" title="Hapus" onclick="return confirm('Yakin ingin menghapus surat ini? Tindakan ini tidak dapat dibatalkan.')">
                                                    <svg class="icon" style="width: 16px; height: 16px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Section: Riwayat -->
            <section id="section-riwayat" class="module-section">
                <div class="card">
                    <div class="card-header">
                        <h2>Riwayat Aktivitas Surat (Selesai)</h2>
                        <p>Daftar arsip surat masuk yang telah diproses dan berstatus selesai.</p>
                    </div>
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No. Agenda</th>
                                    <th>Info Surat</th>
                                    <th>Pengirim</th>
                                    <th>Sifat</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($riwayat_mails)): ?>
                                    <tr>
                                        <td colspan="6"
                                            style="text-align: center; padding: 3rem; color: var(--text-muted);">Tidak ada
                                            data surat masuk yang selesai.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($riwayat_mails as $mail): ?>
                                        <tr>
                                            <td style="font-weight: 700;"><?= htmlspecialchars($mail['nomor_agenda']) ?></td>
                                            <td>
                                                <div style="font-weight: 600;"><?= htmlspecialchars($mail['perihal']) ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">No:
                                                    <?= htmlspecialchars($mail['nomor_surat']) ?> •
                                                    <?= date('d M Y', strtotime($mail['tanggal_terima'])) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($mail['pengirim']) ?></td>
                                            <td>
                                                <span class="badge-status status-<?= $mail['sifat_surat'] ?>">
                                                    <?= ucfirst($mail['sifat_surat']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status status-<?= $mail['status'] ?>"><svg class="icon" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 0.25rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Selesai</span>
                                            </td>
                                            <td class="action-btns">
                                                <button class="action-btn btn-view" title="Lihat Detail" onclick='openViewModal(<?= htmlspecialchars(json_encode($mail)) ?>)'>
                                                    <svg class="icon" style="width: 16px; height: 16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- ====== MODAL: INPUT BARU ====== -->
<div id="inputModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); z-index:3000; align-items:center; justify-content:center; padding:2rem;">
    <div style="background:#fff; border-radius:2rem; max-width:800px; width:100%; max-height:90vh; overflow:auto; box-shadow:0 25px 50px rgba(0,0,0,0.4);">
        <div style="padding:1.5rem 2rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="font-size:1.25rem; font-weight:900; color:#0f172a;">Form Input Surat Masuk Baru</h2>
            <button type="button" onclick="closeInputModal()" style="background:none;border:none;cursor:pointer;font-size:1.5rem;color:#64748b;">✕</button>
        </div>
        <div style="padding:2rem;">
            <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_mail">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nomor Agenda (Di-generate Sistem) <span style="color: var(--danger);">*</span></label>
                                <input type="text" name="nomor_agenda" value="<?= $next_agenda_number ?>" readonly style="background:#f1f5f9; cursor:not-allowed; color:#64748b; font-weight:700; border: 1.5px solid #cbd5e1;">
                            </div>
                            <div class="form-group">
                                <label>Nomor Surat <span style="color: var(--danger);">*</span></label>
                                <input type="text" name="nomor_surat" required
                                    placeholder="Sesuai nomor pada fisik surat">
                            </div>
                            <div class="form-group">
                                <label>Tanggal Surat <span style="color: var(--danger);">*</span></label>
                                <input type="date" name="tanggal_surat" required>
                            </div>
                            <div class="form-group">
                                <label>Tanggal Terima <span style="color: var(--danger);">*</span></label>
                                <input type="date" name="tanggal_terima" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="form-group full-width">
                                <label>Pengirim <span style="color: var(--danger);">*</span></label>
                                <input type="text" name="pengirim" required placeholder="Nama instansi atau perorangan">
                            </div>
                            <div class="form-group full-width">
                                <label>Perihal <span style="color: var(--danger);">*</span></label>
                                <input type="text" name="perihal" required placeholder="Topik atau subjek surat">
                            </div>
                            <div class="form-group">
                                <label>Sifat Surat</label>
                                <select name="sifat_surat">
                                    <option value="biasa">Biasa</option>
                                    <option value="penting">Penting</option>
                                    <option value="segera">Segera</option>
                                    <option value="rahasia">Rahasia</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Lampiran (Jumlah Lembar)</label>
                                <input type="number" name="lampiran" min="0" value="0">
                            </div>
                            <div class="form-group full-width">
                                <label>Unggah Digital File (PDF/Image)</label>
                                <div class="file-upload-area" onclick="document.getElementById('file-input').click()">
                                    <svg class="icon"
                                        style="width: 32px; height: 32px; color: var(--text-muted); margin-bottom: 1rem;">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    <p id="file-name">Klik untuk memilih file atau seret ke sini</p>
                                    <p style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.25rem;">Maksimal 10MB (PDF, JPG, PNG)</p>
                                    <input type="file" id="file-input" name="file_surat" hidden
                                        onchange="validateFile(this)">
                                </div>
                            </div>
                            <div class="form-group full-width">
                                <label>Keterangan Tambahan</label>
                                <textarea name="keterangan" placeholder="Catatan singkat mengenai surat..."></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="reset" class="btn btn-ghost">Reset Form</button>
                            <button type="submit" class="btn btn-primary">Simpan Surat Masuk</button>
                        </div>
                    </form>
        </div>
    </div>
</div>

<!-- ====== MODAL: VIEW DETAIL ====== -->
<div id="viewModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); z-index:3000; display:none; align-items:center; justify-content:center; padding:2rem;">
    <div style="background:#fff; border-radius:2rem; max-width:900px; width:100%; max-height:90vh; overflow:auto; box-shadow:0 25px 50px rgba(0,0,0,0.4);">
        <div style="padding:2rem 2.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 id="vPerihal" style="font-size:1.3rem; font-weight:900; color:#0f172a;"></h2>
                <p id="vNo" style="font-size:0.85rem; color:#64748b;"></p>
            </div>
            <button onclick="closeViewModal()" style="background:none;border:none;cursor:pointer;font-size:1.5rem;color:#64748b;">✕</button>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; padding:2rem 2.5rem;">
            <div>
                <p style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:0.5rem;">PENGIRIM</p>
                <p id="vPengirim" style="font-weight:700; color:#0f172a;"></p>
                <p style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; margin:1rem 0 0.5rem;">TANGGAL SURAT</p>
                <p id="vTgl" style="font-weight:700;"></p>
                <p style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; margin:1rem 0 0.5rem;">SIFAT SURAT</p>
                <p id="vSifat" style="font-weight:700;"></p>
                <p style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; margin:1rem 0 0.5rem;">STATUS</p>
                <p id="vStatus" style="font-weight:700;"></p>
                <p style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; margin:1rem 0 0.5rem;">KETERANGAN</p>
                <p id="vKet" style="background:#f8fafc; border:1px solid #e2e8f0; padding:1rem; border-radius:0.75rem; font-size:0.9rem; line-height:1.6;"></p>
            </div>
            <div id="vPreview" style="background:#f8fafc; border-radius:1rem; overflow:hidden; min-height:300px; display:flex; align-items:center; justify-content:center;"></div>
        </div>
    </div>
</div>

<!-- ====== MODAL: EDIT ====== -->
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); z-index:3000; align-items:center; justify-content:center; padding:2rem;">
    <div style="background:#fff; border-radius:2rem; max-width:700px; width:100%; max-height:90vh; overflow:auto; box-shadow:0 25px 50px rgba(0,0,0,0.4);">
        <div style="padding:1.5rem 2rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="font-size:1.1rem; font-weight:900; color:#0f172a;">Edit Surat Masuk</h2>
            <button onclick="closeEditModal()" style="background:none;border:none;cursor:pointer;font-size:1.5rem;color:#64748b;">✕</button>
        </div>
        <form id="editForm" method="POST" enctype="multipart/form-data" style="padding:2rem;">
            <input type="hidden" name="action" value="update_mail">
            <input type="hidden" name="edit_id" id="eId">
            <input type="hidden" name="existing_file_path" id="eFilePath">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">NOMOR AGENDA</label><input type="text" name="nomor_agenda" id="eNomorAgenda" readonly style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem; background:#f1f5f9; cursor:not-allowed; color:#94a3b8;"></div>
                <div><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">NOMOR SURAT</label><input type="text" name="nomor_surat" id="eNomorSurat" style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem;"></div>
                <div><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">TANGGAL SURAT</label><input type="date" name="tanggal_surat" id="eTglSurat" style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem;"></div>
                <div><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">TANGGAL TERIMA</label><input type="date" name="tanggal_terima" id="eTglTerima" style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem;"></div>
                <div style="grid-column:span 2;"><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">PENGIRIM</label><input type="text" name="pengirim" id="ePengirim" style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem;"></div>
                <div style="grid-column:span 2;"><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">PERIHAL</label><input type="text" name="perihal" id="ePerihal" style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem;"></div>
                <div><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">SIFAT SURAT</label>
                    <select name="sifat_surat" id="eSifat" style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem;">
                        <option value="biasa">Biasa</option><option value="penting">Penting</option><option value="segera">Segera</option><option value="rahasia">Rahasia</option>
                    </select></div>
                <div><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">LAMPIRAN</label><input type="number" name="lampiran" id="eLampiran" min="0" style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem;"></div>
                <div style="grid-column:span 2;"><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">KETERANGAN</label><textarea name="keterangan" id="eKet" rows="3" style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem; resize:vertical;"></textarea></div>
                <div style="grid-column:span 2;"><label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.4rem;">GANTI FILE (opsional)</label><input type="file" name="file_surat" style="width:100%; padding:0.75rem; border:1.5px solid #e2e8f0; border-radius:0.75rem;"></div>
            </div>
            <div style="display:flex; gap:1rem; margin-top:1.5rem; justify-content:flex-end;">
                <button type="button" onclick="closeEditModal()" style="padding:0.75rem 1.5rem; border:1.5px solid #e2e8f0; border-radius:0.75rem; cursor:pointer; background:#fff; font-weight:700;">Batal</button>
                <button type="submit" style="padding:0.75rem 1.5rem; background:var(--primary); color:#fff; border:none; border-radius:0.75rem; cursor:pointer; font-weight:800;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

    <script>
        const allMailData = <?= json_encode($mails) ?>;

        function openInputModal() { document.getElementById('inputModal').style.display = 'flex'; }
        function closeInputModal() { document.getElementById('inputModal').style.display = 'none'; }

        function openViewModal(mail) {
            document.getElementById('vPerihal').innerText = mail.perihal;
            document.getElementById('vNo').innerText = 'No: ' + mail.nomor_surat + ' | Agenda: ' + mail.nomor_agenda;
            document.getElementById('vPengirim').innerText = mail.pengirim;
            document.getElementById('vTgl').innerText = mail.tanggal_surat;
            document.getElementById('vSifat').innerText = mail.sifat_surat ? mail.sifat_surat.toUpperCase() : '-';
            document.getElementById('vStatus').innerText = mail.status ? mail.status.toUpperCase() : '-';
            document.getElementById('vKet').innerText = mail.keterangan || 'Tidak ada keterangan.';
            const prev = document.getElementById('vPreview');
            if (mail.file_path) {
                const url = '../' + mail.file_path;
                const isImg = /\.(png|jpg|jpeg)$/i.test(mail.file_path);
                prev.innerHTML = isImg 
                    ? `<img src="${url}" style="max-width:100%; max-height:400px; object-fit:contain;">` 
                    : `<iframe src="${url}" style="width:100%; height:400px; border:none;"></iframe>`;
            } else {
                prev.innerHTML = '<p style="color:#64748b; padding:2rem;">Tidak ada file lampiran.</p>';
            }
            const m = document.getElementById('viewModal');
            m.style.display = 'flex';
        }
        function closeViewModal() { document.getElementById('viewModal').style.display = 'none'; }

        function openEditModal(mail) {
            document.getElementById('eId').value = mail.id_surat_masuk;
            document.getElementById('eNomorAgenda').value = mail.nomor_agenda;
            document.getElementById('eNomorSurat').value = mail.nomor_surat;
            document.getElementById('eTglSurat').value = mail.tanggal_surat;
            document.getElementById('eTglTerima').value = mail.tanggal_terima;
            document.getElementById('ePengirim').value = mail.pengirim;
            document.getElementById('ePerihal').value = mail.perihal;
            document.getElementById('eSifat').value = mail.sifat_surat;
            document.getElementById('eLampiran').value = mail.lampiran;
            document.getElementById('eKet').value = mail.keterangan || '';
            document.getElementById('eFilePath').value = mail.file_path || '';
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.innerText.toLowerCase().includes(tabId)) btn.classList.add('active');
            });
            document.querySelectorAll('.module-section').forEach(sec => {
                sec.classList.remove('active');
            });
            document.getElementById('section-' + tabId).classList.add('active');
        }

        // Close modals on overlay click
        document.getElementById('inputModal').onclick = e => { if (e.target === document.getElementById('inputModal')) closeInputModal(); };
        document.getElementById('viewModal').onclick = e => { if (e.target === document.getElementById('viewModal')) closeViewModal(); };
        document.getElementById('editModal').onclick = e => { if (e.target === document.getElementById('editModal')) closeEditModal(); };

        function validateFile(input) {
            const fileName = input.files[0].name;
            const fileSize = input.files[0].size / 1024 / 1024; // in MB
            const fileLabel = document.getElementById('file-name');
            
            if (fileSize > 10) {
                alert('Ukuran file terlalu besar (' + fileSize.toFixed(2) + ' MB). Maksimal batas unggah adalah 10 MB.');
                input.value = '';
                fileLabel.innerText = 'Klik untuk memilih file atau seret ke sini';
                fileLabel.style.color = 'var(--danger)';
            } else {
                fileLabel.innerText = fileName;
                fileLabel.style.color = 'var(--primary)';
            }
        }

        <?php if ($success_msg || $error_msg): ?>
            switchTab('daftar');
        <?php endif; ?>
    </script>
</body>

</html>