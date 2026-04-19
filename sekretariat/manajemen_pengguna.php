<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];
$success_msg = "";
$error_msg = "";

// --- HANDLE FORM SUBMISSION (CREATE/UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $nip = $_POST['nip'];
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $no_hp = $_POST['no_hp'];
    $jabatan = $_POST['jabatan'];
    $role = $_POST['role'];
    $id_bidang = !empty($_POST['id_bidang']) ? $_POST['id_bidang'] : null;
    $id_seksi = !empty($_POST['id_seksi']) ? $_POST['id_seksi'] : null;

    if ($action === 'save_user') {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (nip, nama, email, password, no_hp, jabatan, role, id_bidang, id_seksi, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')");
            $stmt->execute([$nip, $nama, $email, $password, $no_hp, $jabatan, $role, $id_bidang, $id_seksi]);
            $success_msg = "Pengguna baru berhasil ditambahkan!";
        } catch (PDOException $e) {
            $error_msg = "Gagal menambah pengguna: " . $e->getMessage();
        }
    } elseif ($action === 'update_user') {
        try {
            $sql = "UPDATE users SET nama = ?, email = ?, no_hp = ?, jabatan = ?, role = ?, id_bidang = ?, id_seksi = ? WHERE nip = ?";
            $params = [$nama, $email, $no_hp, $jabatan, $role, $id_bidang, $id_seksi, $nip];

            // Update password if provided
            if (!empty($_POST['password'])) {
                $sql = "UPDATE users SET nama = ?, email = ?, no_hp = ?, jabatan = ?, role = ?, id_bidang = ?, id_seksi = ?, password = ? WHERE nip = ?";
                $params = [$nama, $email, $no_hp, $jabatan, $role, $id_bidang, $id_seksi, password_hash($_POST['password'], PASSWORD_DEFAULT), $nip];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success_msg = "Data pengguna berhasil diperbarui!";
        } catch (PDOException $e) {
            $error_msg = "Gagal memperbarui pengguna: " . $e->getMessage();
        }
    }
}

// --- HANDLE STATUS TOGGLE ---
if (isset($_GET['toggle_status']) && isset($_GET['nip'])) {
    $nip = $_GET['nip'];
    $current_status = $_GET['toggle_status'];
    $new_status = ($current_status === 'aktif') ? 'nonaktif' : 'aktif';

    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE nip = ?");
        $stmt->execute([$new_status, $nip]);
        $success_msg = "Status akun pengguna berhasil diubah menjadi " . $new_status . "!";
    } catch (PDOException $e) {
        $error_msg = "Gagal mengubah status: " . $e->getMessage();
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete_nip'])) {
    $nip_to_delete = $_GET['delete_nip'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE nip = ?");
        $stmt->execute([$nip_to_delete]);
        $success_msg = "Akun pengguna berhasil dihapus!";
    } catch (PDOException $e) {
        $error_msg = "Gagal menghapus pengguna: " . $e->getMessage();
    }
}

// --- FETCH DATA ---
// Fetch All Users
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$query = "SELECT u.*, b.nama_bidang, s.nama_seksi FROM users u 
          LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
          LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
          WHERE (u.nama LIKE ? OR u.nip LIKE ? OR u.email LIKE ?)";
$params = ["%$search%", "%$search%", "%$search%"];

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

$query .= " ORDER BY u.nama ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Group users dynamically by role
$usersByRole = [];
foreach ($users as $u) {
    if (!isset($usersByRole[$u['role']])) {
        $usersByRole[$u['role']] = [];
    }
    $usersByRole[$u['role']][] = $u;
}

// Fetch Bidang list
$bidang_list = $pdo->query("SELECT * FROM bidang ORDER BY nama_bidang ASC")->fetchAll();

// Fetch Seksi list (for JS mapping)
$seksi_list = $pdo->query("SELECT * FROM seksi ORDER BY nama_seksi ASC")->fetchAll();

// Fetch Admin Data for profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();

// --- PRE-FILL EDIT FORM ---
// DiHapus: form edit sekarang menggunakan pop up modal dengan javascript
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Arsip Digital</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../css/sekretariat/manajemen_pengguna.css?v=<?= time() ?>">
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon">
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
            <a href="surat_masuk.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                Surat Masuk
            </a>

            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item active">
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
                <svg class="icon" viewBox="0 0 24 24">
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
                <h1>Manajemen Pengguna</h1>
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
            <!-- Alerts -->
            <?php if ($success_msg): ?>
                <div class="alert-message alert-success">
                    <svg class="icon alert-icon">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg> <?= $success_msg ?>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-message alert-danger">
                    <svg class="icon alert-icon">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg> <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="module-tabs">
                <button class="tab-btn active" onclick="switchTab('daftar')"><svg class="icon">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg> Daftar Pengguna</button>
                <button class="tab-btn" onclick="switchTab('tambah')"><svg class="icon">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg> Tambah Baru</button>
            </div>

            <!-- Section: Daftar -->
            <section id="section-daftar" class="module-section active">
                <div class="card">
                    <div class="table-controls">
                        <form method="GET" class="search-box">
                            <input type="text" name="search" placeholder="Cari NIP, nama, atau email..."
                                value="<?= htmlspecialchars($search) ?>">
                        </form>
                        <form method="GET" class="filter-group">
                            <select name="role" onchange="this.form.submit()">
                                <option value="">Semua Role</option>
                                <option value="sekretariat" <?= $role_filter === 'sekretariat' ? 'selected' : '' ?>>Sekretariat</option>
                                <option value="kepala_dinas" <?= $role_filter === 'kepala_dinas' ? 'selected' : '' ?>>Kepala Dinas</option>
                                <option value="admin_bidang" <?= $role_filter === 'admin_bidang' ? 'selected' : '' ?>>Admin Bidang</option>
                                <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
                            </select>
                        </form>
                    </div>

                    <?php if (empty($usersByRole)): ?>
                        <div class="empty-state">
                            <svg class="icon empty-state-icon"><path d="M12 2A10 10 0 1 0 22 12 10 10 0 0 0 12 2Z"></path><path d="M15 9.4 9 15.4"></path><path d="M9 9.4l6 6"></path></svg>
                            <p class="empty-state-text">Belum ada data pengguna</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($usersByRole as $roleKey => $roleUsers): ?>
                            <?php 
                                $roleName = ucwords(str_replace('_', ' ', $roleKey)); 
                                $showBidang = !in_array($roleKey, ['sekretariat', 'kepala_dinas']);
                                $showSeksi = !in_array($roleKey, ['sekretariat', 'kepala_dinas', 'admin_bidang']);
                            ?>
                            <div class="role-group">
                                <div class="role-header">
                                    <div class="role-title-wrapper">
                                        <div class="role-icon">
                                            <svg class="icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                        </div>
                                        <h3><?= htmlspecialchars($roleName) ?></h3>
                                    </div>
                                    <span class="badge badge-role"><?= count($roleUsers) ?> Pengguna</span>
                                </div>
                                <div class="data-table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr class="table-header">
                                                <th>NIP</th>
                                                <th>Nama</th>
                                                <th>Jabatan</th>
                                                <?php if ($showBidang): ?><th>Bidang</th><?php endif; ?>
                                                <?php if ($showSeksi): ?><th>Seksi Bidang</th><?php endif; ?>
                                                <th>Role</th>
                                                <th class="text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($roleUsers as $u): ?>
                                                <tr>
                                                    <td class="nip-cell"><?= htmlspecialchars($u['nip']) ?></td>
                                                    <td>
                                                        <div class="user-info-cell">
                                                            <div class="user-avatar"><?= strtoupper(substr($u['nama'], 0, 1)) ?></div>
                                                            <div style="font-weight: 600;"><?= htmlspecialchars($u['nama']) ?></div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="jabatan-text"><?= htmlspecialchars($u['jabatan'] ?? '-') ?></div>
                                                    </td>
                                                    <?php if ($showBidang): ?>
                                                        <td><div class="bidang-text"><?= htmlspecialchars($u['nama_bidang'] ?? '-') ?></div></td>
                                                    <?php endif; ?>
                                                    <?php if ($showSeksi): ?>
                                                        <td><div class="seksi-text"><?= htmlspecialchars($u['nama_seksi'] ?? '-') ?></div></td>
                                                    <?php endif; ?>
                                                    <td><span class="badge badge-role"><?= htmlspecialchars($roleName) ?></span></td>
                                                    <td class="action-btns">
                                                        <button type="button" onclick='viewDetail(<?= htmlspecialchars(json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)' class="action-btn btn-view" title="Detail">
                                                            <svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                        </button>
                                                        <button type="button" onclick='openEditModal(<?= htmlspecialchars(json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)' class="action-btn btn-edit" title="Edit">
                                                            <svg class="icon"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                                                        </button>
                                                        <button type="button" onclick="confirmDelete('<?= $u['nip'] ?>')" class="action-btn btn-delete" title="Hapus">
                                                            <svg class="icon"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </section>

            <!-- Section: Tambah/Edit -->
            <section id="section-tambah" class="module-section">
                <div class="card">
                    <div class="card-header">
                        <h2>Tambah Pengguna Baru</h2>
                        <p>Buat akun baru untuk pegawai sesuai dengan role dan unit kerja.</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_user">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>NIP Pegawai <span class="text-danger">*</span></label>
                                <input type="text" name="nip" required placeholder="Contoh: 19880101...">
                            </div>
                            <div class="form-group">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" required placeholder="Nama serta gelar (jika ada)">
                            </div>
                            <div class="form-group">
                                <label>Email Institusi <span class="text-danger">*</span></label>
                                <input type="email" name="email" required placeholder="email@kemendikbud.go.id">
                            </div>
                            <div class="form-group">
                                <label>Nomor HP/WhatsApp</label>
                                <input type="text" name="no_hp" placeholder="08xxxxxx">
                            </div>
                            <div class="form-group">
                                <label>Role Sistem <span class="text-danger">*</span></label>
                                <select name="role" id="add-role" required onchange="toggleFieldsVisibility(this.value, 'add')">
                                    <option value="kepala_dinas">Kepala Dinas</option>
                                    <option value="admin_bidang">Admin Bidang</option>
                                    <option value="staff">Staff Pelaksana</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Jabatan</label>
                                <input type="text" name="jabatan" placeholder="Contoh: Analis Kebijakan Ahli Muda">
                            </div>
                            <div class="form-group d-none" id="add-container-bidang">
                                <label>Bidang / Bagian</label>
                                <select name="id_bidang" id="add-select-bidang" onchange="updateSeksiOptions(this.value, 'add')">
                                    <option value="">-- Pilih Bidang --</option>
                                    <?php foreach ($bidang_list as $b): ?>
                                        <option value="<?= $b['id_bidang'] ?>"><?= htmlspecialchars($b['nama_bidang']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group d-none" id="add-container-seksi">
                                <label>Seksi / Sub Bagian</label>
                                <select name="id_seksi" id="add-select-seksi">
                                    <option value="">-- Pilih Seksi --</option>
                                    <!-- Options loaded via JS -->
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label>Password Akun *</label>
                                <input type="password" name="password" required placeholder="Minimal 8 karakter">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="reset" class="btn btn-ghost">Reset</button>
                            <button type="submit" class="btn btn-primary">Buat Akun Pengguna</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <!-- Detail Modal Overlay -->
    <div id="detailModal" class="modal-overlay">
        <div class="modal-card w-500">
            <div class="modal-header">
                <h3 class="modal-title">Detail Pengguna</h3>
                <button onclick="closeModal()" class="btn-close"><svg class="icon"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
            </div>
            <div id="modalContent" class="modal-body">
                <!-- Content handled by JS -->
            </div>
        </div>
    </div>

    <!-- Edit Modal Overlay -->
    <div id="editModal" class="modal-overlay z-1010">
        <div class="modal-card w-650">
            <div class="modal-header">
                <h3 class="modal-title">Edit Data Pengguna</h3>
                <button type="button" onclick="closeEditModal()" class="btn-close"><svg class="icon"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_user">
                <div class="form-grid">
                    <div class="form-group">
                        <label>NIP Pegawai</label>
                        <input type="text" name="nip" id="edit_nip" required readonly>
                    </div>
                    <div class="form-group">
                        <label>Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="edit_nama" required>
                    </div>
                    <div class="form-group">
                        <label>Email Institusi <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label>Nomor HP/WhatsApp</label>
                        <input type="text" name="no_hp" id="edit_no_hp">
                    </div>
                    <div class="form-group">
                        <label>Role Sistem <span class="text-danger">*</span></label>
                        <select name="role" id="edit_role" required onchange="toggleFieldsVisibility(this.value, 'edit')">
                            <option value="sekretariat">Sekretariat</option>
                            <option value="kepala_dinas">Kepala Dinas</option>
                            <option value="admin_bidang">Admin Bidang</option>
                            <option value="bagian_perencanaan">Perencanaan</option>
                            <option value="bagian_keuangan">Keuangan</option>
                            <option value="staff">Staff Pelaksana</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jabatan</label>
                        <input type="text" name="jabatan" id="edit_jabatan">
                    </div>
                    <div class="form-group" id="edit_container_bidang">
                        <label>Bidang / Bagian</label>
                        <select name="id_bidang" id="edit_id_bidang" onchange="updateSeksiOptions(this.value, 'edit')">
                            <option value="">-- Pilih Bidang --</option>
                            <?php foreach ($bidang_list as $b): ?>
                                <option value="<?= $b['id_bidang'] ?>"><?= htmlspecialchars($b['nama_bidang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="edit_container_seksi">
                        <label>Seksi / Sub Bagian</label>
                        <select name="id_seksi" id="edit_id_seksi">
                            <option value="">-- Pilih Seksi --</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Ganti Password (Kosongkan jika tidak diubah)</label>
                        <input type="password" name="password" placeholder="Minimal 8 karakter">
                    </div>
                </div>
                <div class="form-actions modal-footer">
                    <button type="button" onclick="closeEditModal()" class="btn btn-ghost">Batal Edit</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal Overlay -->
    <div id="deleteModal" class="modal-overlay z-1010">
        <div class="modal-card w-400">
            <div class="delete-icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="delete-text">Hapus Pengguna</h3>
            <p class="delete-subtext">Apakah Anda yakin ingin menghapus pengguna ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="modal-footer center">
                <button type="button" onclick="closeDeleteModal()" class="btn btn-ghost">Batal</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Ya, Hapus</a>
            </div>
        </div>
    </div>

    <script>
        const seksiData = <?= json_encode($seksi_list) ?>;

        function updateSeksiOptions(bidangId, mode = 'add', preselectedSeksi = null) {
            const seksiSelect = document.getElementById(mode === 'edit' ? 'edit_id_seksi' : 'add-select-seksi');
            const seksiContainer = document.getElementById(mode === 'edit' ? 'edit_container_seksi' : 'add-container-seksi');
            seksiSelect.innerHTML = '<option value="">-- Pilih Seksi --</option>';

            if (!bidangId) return;

            const filtered = seksiData.filter(s => s.id_bidang == bidangId);

            if (filtered.length > 0) {
                // If the role allows seksi logic, show it
                const roleValue = document.getElementById(mode === 'edit' ? 'edit_role' : 'add-role').value;
                if (roleValue !== 'admin_bidang' && roleValue !== 'sekretariat' && roleValue !== 'kepala_dinas') {
                    seksiContainer.classList.remove('d-none');
                }
                
                filtered.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id_seksi;
                    opt.textContent = s.nama_seksi;
                    if (preselectedSeksi && s.id_seksi == preselectedSeksi) opt.selected = true;
                    seksiSelect.appendChild(opt);
                });
            } else {
                seksiContainer.classList.add('d-none');
            }
        }

        function toggleFieldsVisibility(role, mode = 'add') {
            const bidangContainer = document.getElementById(mode === 'edit' ? 'edit_container_bidang' : 'add-container-bidang');
            const seksiContainer = document.getElementById(mode === 'edit' ? 'edit_container_seksi' : 'add-container-seksi');
            const bidangSelect = document.getElementById(mode === 'edit' ? 'edit_id_bidang' : 'add-select-bidang');
            const seksiSelect = document.getElementById(mode === 'edit' ? 'edit_id_seksi' : 'add-select-seksi');

            if (role === 'sekretariat' || role === 'kepala_dinas') {
                bidangContainer.classList.add('d-none');
                seksiContainer.classList.add('d-none');
                bidangSelect.value = '';
                seksiSelect.value = '';
                seksiSelect.innerHTML = '<option value="">-- Pilih Seksi --</option>';
            } else if (role === 'admin_bidang') {
                bidangContainer.classList.remove('d-none');
                seksiContainer.classList.add('d-none');
                seksiSelect.value = '';
                seksiSelect.innerHTML = '<option value="">-- Pilih Seksi --</option>';
            } else {
                bidangContainer.classList.remove('d-none');
                seksiContainer.classList.add('d-none');
                if (bidangSelect.value) {
                    updateSeksiOptions(bidangSelect.value, mode, seksiSelect.value);
                }
            }
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => {
                const btnText = btn.innerText.toLowerCase();
                if (btnText.includes('daftar') && tabId === 'daftar') btn.classList.add('active');
                if (btnText.includes('tambah') && tabId === 'tambah') btn.classList.add('active');
            });
            document.querySelectorAll('.module-section').forEach(sec => sec.classList.remove('active'));
            const targetSec = document.getElementById('section-' + tabId);
            if (targetSec) targetSec.classList.add('active');
        }

        function viewDetail(user) {
            const modal = document.getElementById('detailModal');
            const content = document.getElementById('modalContent');
            
            // Format role name
            const roleName = user.role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

            content.innerHTML = `
                <div class="detail-user-header">
                    <div class="detail-avatar">
                        ${user.nama.substring(0,1).toUpperCase()}
                    </div>
                    <div>
                        <div class="detail-user-name">${user.nama}</div>
                        <div class="detail-user-email">${user.email || '-'}</div>
                    </div>
                </div>
                <div class="detail-info-grid">
                    <div class="detail-label">NIP</div>
                    <div class="detail-value">${user.nip}</div>
                    
                    <div class="detail-label">Nomor HP</div>
                    <div class="detail-value">${user.no_hp || '-'}</div>
                    
                    <div class="detail-label">Role</div>
                    <div><span class="badge badge-role">${roleName}</span></div>
                    
                    <div class="detail-label">Jabatan</div>
                    <div class="detail-value">${user.jabatan || '-'}</div>
                    
                    <div class="detail-label">Bidang</div>
                    <div class="detail-value">${user.nama_bidang || '-'}</div>
                    
                    <div class="detail-label">Seksi</div>
                    <div class="detail-value">${user.nama_seksi || '-'}</div>

                    <div class="detail-label">Status Akun</div>
                    <div><span class="badge badge-${user.status} detail-status">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span></div>
                </div>
            `;
            
            modal.classList.add('active');
        }

        function openEditModal(user) {
            const modal = document.getElementById('editModal');
            
            // Fill fields gracefully handling nulls
            document.getElementById('edit_nip').value = user.nip;
            document.getElementById('edit_nama').value = user.nama;
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_no_hp').value = user.no_hp || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_jabatan').value = user.jabatan || '';
            document.getElementById('edit_id_bidang').value = user.id_bidang || '';
            
            // Adjust visibility
            toggleFieldsVisibility(user.role, 'edit');
            
            // Fill seksi options if related
            if (user.id_bidang) {
                updateSeksiOptions(user.id_bidang, 'edit', user.id_seksi);
            } else {
                document.getElementById('edit_id_seksi').innerHTML = '<option value="">-- Pilih Seksi --</option>';
            }

            modal.classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('active');
        }

        function confirmDelete(nip) {
            const modal = document.getElementById('deleteModal');
            document.getElementById('confirmDeleteBtn').href = '?delete_nip=' + nip;
            modal.classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        // Initialize visibility on page load for Add Tab
        toggleFieldsVisibility(document.getElementById('add-role').value, 'add');
    </script>
</body>

</html>