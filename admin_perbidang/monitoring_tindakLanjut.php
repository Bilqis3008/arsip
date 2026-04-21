<?php
session_start();
require_once '../config/db.php';

// Auth Check for Admin Bidang
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'admin_bidang') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang FROM users u LEFT JOIN bidang b ON u.id_bidang = b.id_bidang WHERE u.nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();

$id_bidang = $admin['id_bidang'];
$kadin = $pdo->query("SELECT nama FROM users WHERE role='kepala_dinas' LIMIT 1")->fetchColumn() ?: 'Kepala Dinas';

// Fetch mappings of Admin Bidang by id_bidang
$stmt_admin = $pdo->query("SELECT id_bidang, nama FROM users WHERE role = 'admin_bidang'");
$admin_bidang_list = [];
while ($row = $stmt_admin->fetch()) {
    $admin_bidang_list[$row['id_bidang']] = $row['nama'];
}

$search = $_GET['search'] ?? '';

// --- MAIL MASUK (Unfinished) ---
$query_m = "SELECT sm.*, d.tanggal_disposisi, d.status_disposisi, b.nama_bidang, s.nama_seksi, u_in.nama as nama_sekretariat, u_tujuan.nama as nama_admin_bidang 
      FROM surat_masuk sm
      LEFT JOIN users u_in ON sm.input_by = u_in.nip 
      LEFT JOIN (
          SELECT d1.* FROM disposisi d1
          INNER JOIN (
              SELECT id_surat_masuk, MAX(id_disposisi) as max_id 
              FROM disposisi 
              GROUP BY id_surat_masuk
          ) d2 ON d1.id_disposisi = d2.max_id
      ) d ON sm.id_surat_masuk = d.id_surat_masuk
      LEFT JOIN bidang b ON d.id_bidang = b.id_bidang
      LEFT JOIN seksi s ON d.id_seksi = s.id_seksi
      LEFT JOIN users u_tujuan ON d.nip_tujuan = u_tujuan.nip 
      WHERE sm.status NOT IN ('selesai', 'diarsipkan') AND sm.id_bidang = ?";

$params_m = [$id_bidang];
if ($search) {
    $query_m .= " AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ? OR sm.pengirim LIKE ?)";
    array_push($params_m, "%$search%", "%$search%", "%$search%");
}
$stmt_m = $pdo->prepare($query_m);
$stmt_m->execute($params_m);
$mails_m = $stmt_m->fetchAll();
foreach ($mails_m as &$m) { $m['tipe'] = 'masuk'; }
unset($m);

// --- MAIL KELUAR (Unfinished) ---
$query_k = "SELECT sk.*, u.nama as pengirim_user, u.id_bidang, s.nama_seksi, b.nama_bidang 
      FROM surat_keluar sk 
      LEFT JOIN users u ON sk.uploaded_by = u.nip 
      LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
      LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
      WHERE sk.status != 'diarsipkan' AND u.id_bidang = ?";

$params_k = [$id_bidang];
if ($search) {
    $query_k .= " AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ? OR sk.tujuan LIKE ?)";
    array_push($params_k, "%$search%", "%$search%", "%$search%");
}
$stmt_k = $pdo->prepare($query_k);
$stmt_k->execute($params_k);
$mails_k = $stmt_k->fetchAll();
foreach ($mails_k as &$k) { $k['tipe'] = 'keluar'; }
unset($k);

// Merge & Sort newest first
$mails = array_merge($mails_m, $mails_k);
usort($mails, function($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Surat - Bidang Ops</title>
    <link rel="stylesheet" href="../css/admin_perbidang/home.css">
    <link rel="stylesheet" href="../css/sekretariat/monitoring_surat.css">
    <style>
        .type-badge { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem; display: inline-block; }
        .type-masuk { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .type-keluar { background: rgba(16, 185, 129, 0.1); color: #10b981; }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px); }
        .modal-content { background: #fff; width: 100%; max-width: 500px; border-radius: 1rem; padding: 2rem; position: relative; max-height: 90vh; overflow-y: auto; }
        .modal-close { position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer; }
        .timeline { position: relative; margin-top: 1rem; padding-left: 20px; border-left: 2px solid #e2e8f0; }
        .timeline-item { position: relative; padding-bottom: 1.5rem; }
        .timeline-item::before { content: ''; position: absolute; left: -26px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: #fff; border: 2px solid #cbd5e1; }
        .timeline-item.done::before { background: #10b981; border-color: #10b981; }
        .timeline-item.active::before { background: #3b82f6; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
        
        .timeline-content h4 { margin: 0 0 0.25rem; color: #0f172a; font-size: 0.95rem; }
        .timeline-content p { margin: 0; color: #64748b; font-size: 0.85rem; line-height: 1.4; }
        .timeline-time { font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem; }
    </style>
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
            <a href="disposisi_surat.php" class="menu-item"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Disposisi Internal</a>
            <a href="monitoring_tindakLanjut.php" class="menu-item active"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Monitoring Surat</a>
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
            <div class="header-title"><h1>Monitoring Surat Dalam Proses (Belum Selesai)</h1></div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span>
                    <span class="user-role">Admin <?= htmlspecialchars($admin['nama_bidang'] ?? 'Bidang Terkait') ?></span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr($admin['nama_bidang'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body" style="padding-top: 1rem;">
            <!-- Monitoring Card -->
            <div class="card">
                <div class="table-controls">
                    <form method="GET" class="search-box">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="search" placeholder="Cari perihal, nomor surat..." value="<?= htmlspecialchars($search) ?>">
                    </form>
                </div>

                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Info Surat & Tipe</th>
                                <th>Tahap Posisi Saat Ini</th>
                                <th>Aksi Tracker</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mails)): ?>
                                <tr><td colspan="3" style="text-align: center; padding: 4rem; color: var(--text-muted);">Tidak ada berkas yang sedang diproses.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mails as $m): ?>
                                <tr>
                                    <td>
                                        <div class="info-cell">
                                            <span class="type-badge <?= $m['tipe'] == 'masuk' ? 'type-masuk' : 'type-keluar' ?>">Surat <?= $m['tipe'] ?></span><br>
                                            <b style="font-size: 1rem; color: #0f172a;"><?= htmlspecialchars($m['perihal']) ?></b>
                                            <span>No: <?= htmlspecialchars($m['tipe'] === 'masuk' ? $m['nomor_surat'] : $m['nomor_surat_keluar']) ?></span>
                                            <span><?= $m['tipe'] === 'masuk' ? 'Pengirim' : 'Tujuan' ?>: <?= htmlspecialchars($m['tipe'] === 'masuk' ? $m['pengirim'] : $m['tujuan']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($m['tipe'] === 'masuk'): ?>
                                            <?php if ($m['status'] === 'tercatat'): ?>
                                                <div style="font-weight: 700; color: #b45309;"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> Menunggu Disposisi (Meja Kadin)</div>
                                            <?php elseif ($m['status'] === 'didispokan'): ?>
                                                <div style="font-weight: 700; color: #d97706;"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Surat Masuk / Belum Ditindaklanjuti</div>
                                                <div style="font-size: 0.8rem; color: #64748b; margin-top:2px;">Target: <?= htmlspecialchars($m['nama_bidang'] ?: 'Bidang Anda') ?></div>
                                            <?php elseif ($m['status'] === 'diteruskan'): ?>
                                                <div style="font-weight: 700; color: #2563eb;"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Proses Internal Seksi</div>
                                                <div style="font-size: 0.8rem; color: #64748b; margin-top:2px;">Disahkan ke: <?= htmlspecialchars($m['nama_seksi'] ?: 'Staf Seksi') ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($m['status'] === 'draft'): ?>
                                                <div style="font-weight: 700; color: #b45309;"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;margin-right:4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Draft Awal (Staf Seksi)</div>
                                            <?php elseif ($m['status'] === 'pending_approval'): ?>
                                                <div style="font-weight: 700; color: #d97706;"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Menunggu Validasi (Meja Anda)</div>
                                            <?php elseif ($m['status'] === 'disetujui'): ?>
                                                <div style="font-weight: 700; color: #059669;"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;margin-right:4px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Disetujui (Distribusi / Tunggu Arsip)</div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; background: #e0e7ff; color: #4338ca; border: none; cursor: pointer;" onclick="showTracker('<?= $m['tipe'] ?>', <?= $m['tipe'] === 'masuk' ? $m['id_surat_masuk'] : $m['id_surat_keluar'] ?>)">
                                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Tracker
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Tracking Modal -->
    <div class="modal-overlay" id="tracker-modal" onclick="closeTracker()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeTracker()">
                <svg viewBox="0 0 24 24" style="width:24px; height:24px; fill:none; stroke:currentColor; stroke-width:2;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h3 style="margin-bottom: 0.5rem; color: #0f172a; font-size: 1.25rem;">Live Tracking Alur Surat</h3>
            <p id="tracker-subtitle" style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;"></p>
            
            <div id="tracker-mail-info" style="background: #f8fafc; padding: 1.25rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; display: none;"></div>

            <div id="tracker-details">
                <div class="timeline" id="timeline-box"></div>
            </div>
        </div>
    </div>

    <script>
        const suratMasukData = <?= json_encode($mails_m) ?>;
        const suratKeluarData = <?= json_encode($mails_k) ?>;
        const namaKadin = <?= json_encode($kadin) ?>;
        const adminBidangDict = <?= json_encode($admin_bidang_list) ?>;

        function showTracker(tipe, id) {
            const timeline = document.getElementById('timeline-box');
            timeline.innerHTML = '';
            
            const infoBox = document.getElementById('tracker-mail-info');
            infoBox.style.display = 'block';

            if (tipe === 'masuk') {
                const mail = suratMasukData.find(m => m.id_surat_masuk == id);
                document.getElementById('tracker-subtitle').textContent = `Surat Masuk #${mail.nomor_surat}`;

                const tgl = new Date(mail.tanggal_terima).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'});
                infoBox.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.85rem;">
                        <div style="grid-column: span 2;">
                            <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Perihal</span>
                            <strong style="color: #0f172a; font-size: 0.95rem;">${mail.perihal}</strong>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Pengirim</span>
                            <strong style="color: #0f172a;">${mail.pengirim}</strong>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Tanggal Terima</span>
                            <strong style="color: #0f172a;">${tgl}</strong>
                        </div>
                    </div>
                `;

                const sekreName = mail.nama_sekretariat || 'Staf Sekretariat';
                addTimelineItem(`${sekreName} (Sekretariat)`, 'Resepsionis/Sekretariat mencatat agenda baru.', mail.created_at, 'done');

                const kadinFull = `${namaKadin} (Kepala Dinas)`;
                if (mail.status === 'tercatat') {
                    addTimelineItem(kadinFull, 'Menunggu Keputusan / Disposisi', null, 'active');
                } else {
                    addTimelineItem(kadinFull, 'Memberikan arah dan disposisi kepada unit bersangkutan.', mail.tanggal_disposisi, 'done');
                }

                if (mail.status === 'didispokan') {
                    const adminBidangName = mail.nama_admin_bidang || 'Admin Bidang';
                    const deskripsiBidang = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
                    addTimelineItem(`${adminBidangName} ${deskripsiBidang}`, `Perlu respon dan tindak lanjut/disposisi internal.`, null, 'active');
                } else if (mail.status === 'diteruskan') {
                    const adminBidangName = mail.nama_admin_bidang || 'Admin Bidang';
                    const deskripsiBidang = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
                    addTimelineItem(`${adminBidangName} ${deskripsiBidang}`, `Telah dikonfirmasi/Tindak Lanjut Admin Bidang.`, null, 'done');

                    const seksiTargetName = mail.nama_seksi || 'Staf Sub-Seksi';
                    addTimelineItem(`${seksiTargetName}`, `Sedang ditindaklanjuti secara internal dalam seksi.`, null, 'active');
                }

            } else { // 'keluar'
                const mail = suratKeluarData.find(m => m.id_surat_keluar == id);
                document.getElementById('tracker-subtitle').textContent = `Surat Keluar #${mail.nomor_surat_keluar}`;

                const tgl = new Date(mail.tanggal_surat).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'});
                infoBox.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.85rem;">
                        <div style="grid-column: span 2;">
                            <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Perihal</span>
                            <strong style="color: #0f172a; font-size: 0.95rem;">${mail.perihal}</strong>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Tujuan</span>
                            <strong style="color: #0f172a;">${mail.tujuan}</strong>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Tanggal Surat</span>
                            <strong style="color: #0f172a;">${tgl}</strong>
                        </div>
                    </div>
                `;

                const senderAdmin = mail.pengirim_user || 'Staf Penulis';
                const senderUnit = mail.nama_seksi || mail.nama_bidang || '';
                const senderFull = senderUnit ? `${senderAdmin} (Staf ${senderUnit})` : senderAdmin;
                
                const adminReviewer = adminBidangDict[mail.id_bidang] || 'Admin Perbidang';
                const reviewerUnit = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
                const reviewerFull = `${adminReviewer} ${reviewerUnit}`;

                addTimelineItem(senderFull, 'Staf pengusul membuat draft surat.', mail.created_at, 'done');

                if (mail.status === 'draft' || mail.status === 'pending_approval') {
                    addTimelineItem(reviewerFull, 'Menunggu persetujuan / verifikasi draft surat dari meja Anda.', null, 'active');
                } else if (mail.status === 'disetujui') {
                    addTimelineItem(reviewerFull, 'Telah di tinjau dan disetujui (Verifikasi passed).', null, 'done');
                    addTimelineItem('Sekretariat / Distribusi', 'Menunggu finalisasi arsip dan pemberian stempel/nomor rilis.', null, 'active');
                }
            }

            document.getElementById('tracker-modal').style.display = 'flex';
        }

        function addTimelineItem(title, desc, time, type) {
            const box = document.getElementById('timeline-box');
            const item = document.createElement('div');
            item.className = 'timeline-item ' + type;
            item.innerHTML = `
                <div class="timeline-content">
                    <h4>${title}</h4>
                    <p>${desc}</p>
                    ${time ? `<div class="timeline-time">${new Date(time).toLocaleString('id-ID')}</div>` : ''}
                </div>
            `;
            box.appendChild(item);
        }

        function closeTracker() {
            document.getElementById('tracker-modal').style.display = 'none';
        }
    </script>
</body>
</html>
