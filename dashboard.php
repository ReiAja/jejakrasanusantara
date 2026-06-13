<?php
include 'koneksi.php';
// Matikan cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Jika bukan admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
requireAdmin();

$msg = '';
$edit_data = null;
$edit_user = null;
$edit_kategori = null;
$tab = $_GET['tab'] ?? 'overview';

// ── KATEGORI CRUD ──
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL UNIQUE,
    deskripsi TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
// Seed default if empty
$kc = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM kategori"));
if ($kc['c'] == 0) {
    mysqli_query($koneksi,"INSERT IGNORE INTO kategori (nama) VALUES ('Makanan'),('Camilan')");
}

if (isset($_POST['tambah_kategori'])) {
    $knama = mysqli_real_escape_string($koneksi, trim($_POST['knama']));
    $kdesc = mysqli_real_escape_string($koneksi, trim($_POST['kdesc']));
    if ($knama) {
        if (mysqli_query($koneksi,"INSERT INTO kategori (nama,deskripsi) VALUES ('$knama','$kdesc')")) {
            catatLog($koneksi,'TAMBAH',"Menambahkan kategori: $knama");
            $msg = '<div class="alert alert-success">✅ Kategori berhasil ditambahkan!</div>';
        } else {
            $msg = '<div class="alert alert-error">❌ Kategori sudah ada!</div>';
        }
    }
    $tab = 'kategori';
}
if (isset($_POST['edit_kategori_save'])) {
    $kid   = (int)$_POST['kid'];
    $knama = mysqli_real_escape_string($koneksi, trim($_POST['knama']));
    $kdesc = mysqli_real_escape_string($koneksi, trim($_POST['kdesc']));
    $old_k = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT nama FROM kategori WHERE id=$kid"));
    $knama_lama = $old_k ? mysqli_real_escape_string($koneksi, $old_k['nama']) : '';
    mysqli_query($koneksi,"UPDATE kategori SET nama='$knama',deskripsi='$kdesc' WHERE id=$kid");
    if ($knama_lama && $knama_lama !== $knama) {
        mysqli_query($koneksi,"UPDATE makanan SET kategori='$knama' WHERE kategori='$knama_lama'");
    }
    catatLog($koneksi,'EDIT',"Mengedit kategori ID $kid: $knama");
    $msg = '<div class="alert alert-success">✅ Kategori diperbarui & disinkronkan ke makanan!</div>';
    $tab = 'kategori';
}
if (isset($_GET['hapus_kategori'])) {
    $kid = (int)$_GET['hapus_kategori'];
    $kn  = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT nama FROM kategori WHERE id=$kid"));
    if ($kn) {
        $knama_cek = mysqli_real_escape_string($koneksi, $kn['nama']);
        $jml_cek = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM makanan WHERE kategori='$knama_cek'"))['c'];
        if ($jml_cek == 0) {
            mysqli_query($koneksi,"DELETE FROM kategori WHERE id=$kid");
            catatLog($koneksi,'HAPUS',"Menghapus kategori: {$kn['nama']}");
        }
    }
    header("Location: dashboard.php?tab=kategori&msg=hapus_ok"); exit;
}
if (isset($_GET['hapus_makanan_dari_kat'])) {
    $mid = (int)$_GET['hapus_makanan_dari_kat'];
    $kid = (int)($_GET['kid'] ?? 0);
    $nm  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama, kategori FROM makanan WHERE id=$mid"));
    if ($nm) {
        // Hanya lepas dari kategori (kosongkan), TIDAK hapus makanan
        mysqli_query($koneksi, "UPDATE makanan SET kategori='' WHERE id=$mid");
        catatLog($koneksi, 'EDIT', "Melepas makanan '{$nm['nama']}' dari kategori '{$nm['kategori']}'");
    }
    header("Location: dashboard.php?tab=kategori&edit_kategori=$kid&msg=lepas_ok"); exit;
}
if (isset($_GET['edit_kategori'])) {
    $kid = (int)$_GET['edit_kategori'];
    $edit_kategori = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT * FROM kategori WHERE id=$kid"));
    $tab = 'kategori';
}

// ── TENTANG KAMI CRUD ──
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS tentang_kami (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bagian VARCHAR(50) NOT NULL UNIQUE,
    judul VARCHAR(255) DEFAULT NULL,
    konten TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$bagian_default = ['hero','tentang','visi','misi','nilai1','nilai2','nilai3'];
foreach ($bagian_default as $b) {
    mysqli_query($koneksi,"INSERT IGNORE INTO tentang_kami (bagian) VALUES ('$b')");
}
if (isset($_POST['simpan_tentang'])) {
    foreach ($_POST['tentang'] as $bagian => $data) {
        $b    = mysqli_real_escape_string($koneksi, $bagian);
        $judul = mysqli_real_escape_string($koneksi, trim($data['judul'] ?? ''));
        $konten = mysqli_real_escape_string($koneksi, trim($data['konten'] ?? ''));
        mysqli_query($koneksi,"UPDATE tentang_kami SET judul='$judul',konten='$konten' WHERE bagian='$b'");
    }
    catatLog($koneksi,'EDIT','Memperbarui konten Tentang Kami');
    $msg = '<div class="alert alert-success">✅ Tentang Kami berhasil diperbarui!</div>';
    $tab = 'tentang';
}

// ── MAKANAN CRUD ──
if (isset($_POST['tambah_makanan'])) {
    $nama      = mysqli_real_escape_string($koneksi, trim($_POST['nama']));
    $kategori  = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $daerah    = mysqli_real_escape_string($koneksi, trim($_POST['daerah']));
    $deskripsi = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi']));
    $bahan     = mysqli_real_escape_string($koneksi, trim($_POST['bahan']));
    $cara_buat = mysqli_real_escape_string($koneksi, trim($_POST['cara_buat']));
    $gambar    = mysqli_real_escape_string($koneksi, trim($_POST['gambar']));
    if (!empty($_FILES['gambar_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['gambar_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $fname = 'food_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['gambar_file']['tmp_name'], 'uploads/'.$fname))
                $gambar = 'uploads/'.$fname;
        }
    }
    mysqli_query($koneksi, "INSERT INTO makanan (nama,kategori,daerah,deskripsi,bahan,cara_buat,gambar) VALUES ('$nama','$kategori','$daerah','$deskripsi','$bahan','$cara_buat','$gambar')");
    catatLog($koneksi, 'TAMBAH', "Menambahkan makanan baru: $nama ($kategori, $daerah)");
    $msg = '<div class="alert alert-success">✅ Makanan berhasil ditambahkan!</div>';
    $tab = 'makanan';
}

if (isset($_POST['edit_makanan'])) {
    $id        = (int)$_POST['id'];
    $nama      = mysqli_real_escape_string($koneksi, trim($_POST['nama']));
    $kategori  = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $daerah    = mysqli_real_escape_string($koneksi, trim($_POST['daerah']));
    $deskripsi = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi']));
    $bahan     = mysqli_real_escape_string($koneksi, trim($_POST['bahan']));
    $cara_buat = mysqli_real_escape_string($koneksi, trim($_POST['cara_buat']));
    $gambar    = mysqli_real_escape_string($koneksi, trim($_POST['gambar']));
    if (!empty($_FILES['gambar_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['gambar_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $fname = 'food_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['gambar_file']['tmp_name'], 'uploads/'.$fname))
                $gambar = 'uploads/'.$fname;
        }
    }
    mysqli_query($koneksi, "UPDATE makanan SET nama='$nama',kategori='$kategori',daerah='$daerah',deskripsi='$deskripsi',bahan='$bahan',cara_buat='$cara_buat',gambar='$gambar' WHERE id=$id");
    catatLog($koneksi, 'EDIT', "Mengedit makanan ID $id: $nama");
    $msg = '<div class="alert alert-success">✅ Makanan berhasil diperbarui!</div>';
    $tab = 'makanan';
}

if (isset($_GET['hapus_makanan'])) {
    $id = (int)$_GET['hapus_makanan'];
    $nm = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama FROM makanan WHERE id=$id"));
    $namaHapus = $nm ? $nm['nama'] : "ID $id";
    mysqli_query($koneksi, "DELETE FROM makanan WHERE id=$id");
    catatLog($koneksi, 'HAPUS', "Menghapus makanan: $namaHapus (ID $id)");
    header("Location: dashboard.php?tab=makanan&msg=hapus_ok"); exit;
}

if (isset($_GET['edit_makanan'])) {
    $id = (int)$_GET['edit_makanan'];
    $res = mysqli_query($koneksi, "SELECT * FROM makanan WHERE id=$id");
    $edit_data = mysqli_fetch_assoc($res);
    $tab = 'makanan';
}

// ── USER CRUD ──
// TAMBAH USER
if (isset($_POST['tambah_user'])) {
    $username = trim(mysqli_real_escape_string($koneksi, $_POST['username']));
    $email    = trim(mysqli_real_escape_string($koneksi, $_POST['email']));
    $password = $_POST['password'];
    $role     = $_POST['role'] === 'admin' ? 'admin' : 'user';
    $tab = 'users';

    if (empty($username) || empty($email) || empty($password)) {
        $msg = '<div class="alert alert-error">❌ Semua kolom wajib diisi!</div>';
    } elseif (strlen($password) < 6) {
        $msg = '<div class="alert alert-error">❌ Password minimal 6 karakter!</div>';
    } else {
        $cek = mysqli_query($koneksi, "SELECT id FROM users WHERE username='$username' OR email='$email'");
        if (mysqli_num_rows($cek) > 0) {
            $msg = '<div class="alert alert-error">❌ Username atau email sudah terdaftar!</div>';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            mysqli_query($koneksi, "INSERT INTO users (username,email,password,role) VALUES ('$username','$email','$hash','$role')");
            catatLog($koneksi, 'TAMBAH', "Menambahkan pengguna baru: $username ($role)");
            $msg = '<div class="alert alert-success">✅ Pengguna berhasil ditambahkan!</div>';
        }
    }
}

// EDIT USER SAVE
if (isset($_POST['edit_user'])) {
    $id       = (int)$_POST['user_id'];
    $username = trim(mysqli_real_escape_string($koneksi, $_POST['username']));
    $email    = trim(mysqli_real_escape_string($koneksi, $_POST['email']));
    $password = $_POST['password'];
    $tab = 'users';

    $role = $_POST['role'] === 'admin' ? 'admin' : 'user';

    $cek = mysqli_query($koneksi, "SELECT id FROM users WHERE (username='$username' OR email='$email') AND id != $id");
    if (mysqli_num_rows($cek) > 0) {
        $msg = '<div class="alert alert-error">❌ Username atau email sudah dipakai!</div>';
        $res = mysqli_query($koneksi, "SELECT * FROM users WHERE id=$id");
        $edit_user = mysqli_fetch_assoc($res);
    } else {
        if (!empty($password) && strlen($password) >= 6) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            mysqli_query($koneksi, "UPDATE users SET username='$username',email='$email',role='$role',password='$hash' WHERE id=$id");
        } else {
            mysqli_query($koneksi, "UPDATE users SET username='$username',email='$email',role='$role' WHERE id=$id");
        }
        catatLog($koneksi, 'EDIT', "Mengedit pengguna ID $id: $username ($role)");
        $msg = '<div class="alert alert-success">✅ Pengguna berhasil diperbarui!</div>';
        if ($id == $_SESSION['user_id']) {
            $_SESSION['username'] = $username;
            $_SESSION['email']    = $email;
            $_SESSION['role']     = $role;
        }
    }
}

// HAPUS USER
if (isset($_GET['hapus_user'])) {
    $id = (int)$_GET['hapus_user'];
    if ($id !== $_SESSION['user_id']) {
        $un = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT username FROM users WHERE id=$id"));
        $unameHapus = $un ? $un['username'] : "ID $id";
        mysqli_query($koneksi, "DELETE FROM users WHERE id=$id");
        catatLog($koneksi, 'HAPUS', "Menghapus pengguna: $unameHapus (ID $id)");
        header("Location: dashboard.php?tab=users&msg=hapus_ok"); exit;
    } else {
        header("Location: dashboard.php?tab=users"); exit;
    }
}

// LOAD EDIT USER
if (isset($_GET['edit_user'])) {
    $id = (int)$_GET['edit_user'];
    $res = mysqli_query($koneksi, "SELECT * FROM users WHERE id=$id");
    $loaded = mysqli_fetch_assoc($res);
    if ($loaded) {
        $edit_user = $loaded;
        $tab = 'users';
    }
}

// Pastikan kolom is_hidden ada di tabel ulasan
mysqli_query($koneksi, "ALTER TABLE ulasan ADD COLUMN IF NOT EXISTS is_hidden TINYINT(1) DEFAULT 0");

// HAPUS ULASAN
if (isset($_GET['hapus_ulasan'])) {
    $id = (int)$_GET['hapus_ulasan'];
    catatLog($koneksi, 'HAPUS', "Menghapus ulasan ID $id");
    mysqli_query($koneksi, "DELETE FROM ulasan WHERE id=$id");
    header("Location: dashboard.php?tab=ulasan"); exit;
}

// TOGGLE HIDE/SHOW ULASAN
if (isset($_GET['toggle_ulasan'])) {
    $id = (int)$_GET['toggle_ulasan'];
    $row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT is_hidden FROM ulasan WHERE id=$id"));
    if ($row) {
        $new = $row['is_hidden'] ? 0 : 1;
        mysqli_query($koneksi, "UPDATE ulasan SET is_hidden=$new WHERE id=$id");
        $aksi = $new ? 'Menyembunyikan' : 'Menampilkan kembali';
        catatLog($koneksi, 'UPDATE', "$aksi ulasan ID $id");
    }
    header("Location: dashboard.php?tab=ulasan"); exit;
}

// ── PESAN KONTAK ──
// Buat tabel jika belum ada
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS pesan_kontak (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    pesan TEXT NOT NULL,
    sudah_dibaca TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ── LOG AKTIVITAS (Admin Only) ──
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS log_aktivitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(100) DEFAULT 'System',
    aksi VARCHAR(50) NOT NULL,
    keterangan TEXT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Helper: catat log aktivitas admin
function catatLog($koneksi, $aksi, $keterangan) {
    $user_id  = isset($_SESSION['user_id'])  ? (int)$_SESSION['user_id']  : 0;
    $username = isset($_SESSION['username']) ? mysqli_real_escape_string($koneksi, $_SESSION['username']) : 'System';
    $aksi     = mysqli_real_escape_string($koneksi, $aksi);
    $ket      = mysqli_real_escape_string($koneksi, $keterangan);
    $ip       = mysqli_real_escape_string($koneksi, $_SERVER['REMOTE_ADDR'] ?? '');
    mysqli_query($koneksi, "INSERT INTO log_aktivitas (user_id, username, aksi, keterangan, ip_address)
                            VALUES ($user_id, '$username', '$aksi', '$ket', '$ip')");
}

// Tandai sudah dibaca
if (isset($_GET['baca_pesan'])) {
    $id = (int)$_GET['baca_pesan'];
    mysqli_query($koneksi, "UPDATE pesan_kontak SET sudah_dibaca=1 WHERE id=$id");
    header("Location: dashboard.php?tab=pesan"); exit;
}

// Hapus pesan
if (isset($_GET['hapus_pesan'])) {
    $id = (int)$_GET['hapus_pesan'];
    $pm = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama FROM pesan_kontak WHERE id=$id"));
    $pengirim = $pm ? $pm['nama'] : "ID $id";
    catatLog($koneksi, 'HAPUS', "Menghapus pesan dari: $pengirim (ID $id)");
    mysqli_query($koneksi, "DELETE FROM pesan_kontak WHERE id=$id");
    header("Location: dashboard.php?tab=pesan&msg=hapus_ok"); exit;
}

// DATA
$makanan_list = mysqli_query($koneksi, "SELECT * FROM makanan ORDER BY id DESC");
$users_list   = mysqli_query($koneksi, "SELECT * FROM users ORDER BY id DESC");
$ulasan_list  = mysqli_query($koneksi,
    "SELECT u.*, us.username, m.nama as nama_makanan FROM ulasan u
     JOIN users us ON u.user_id=us.id JOIN makanan m ON u.makanan_id=m.id ORDER BY u.created_at DESC");
$pesan_list   = mysqli_query($koneksi, "SELECT * FROM pesan_kontak ORDER BY created_at DESC");
$total_makanan = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM makanan"))['c'];
$total_users   = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM users"))['c'];
$total_ulasan  = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM ulasan"))['c'];
$total_pesan   = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM pesan_kontak"))['c'];
$belum_dibaca  = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM pesan_kontak WHERE sudah_dibaca=0"))['c'];
$total_log     = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM log_aktivitas"))['c'];
$log_list      = mysqli_query($koneksi,"SELECT * FROM log_aktivitas ORDER BY created_at DESC LIMIT 200");

// Kategori list
$kategori_list = mysqli_query($koneksi,"SELECT * FROM kategori ORDER BY id ASC");
$kategori_arr  = [];
$temp = mysqli_query($koneksi,"SELECT nama FROM kategori ORDER BY id ASC");
while($kr = mysqli_fetch_assoc($temp)) $kategori_arr[] = $kr['nama'];

// Tentang Kami data
$tentang_data = [];
$tr = mysqli_query($koneksi,"SELECT * FROM tentang_kami");
while ($td = mysqli_fetch_assoc($tr)) $tentang_data[$td['bagian']] = $td;

// Favorit chart data — top 8 makanan paling difavoritkan
$fav_chart_res = mysqli_query($koneksi,
    "SELECT m.nama, COUNT(f.id) as total FROM favorit f
     JOIN makanan m ON f.makanan_id=m.id
     GROUP BY f.makanan_id ORDER BY total DESC LIMIT 8");
$fav_chart_labels = [];
$fav_chart_data   = [];
if ($fav_chart_res) {
    while ($fc = mysqli_fetch_assoc($fav_chart_res)) {
        $fav_chart_labels[] = $fc['nama'];
        $fav_chart_data[]   = (int)$fc['total'];
    }
}
$total_favorit = array_sum($fav_chart_data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="https://jejakrasa.site.je/gambar/logojr.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin – Jejak Rasa</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root { --gold:#F5A623; --gold-dark:#D4891A; --dark:#1A1208; --sidebar:#111827; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:#F3F4F6; }

    /* SIDEBAR */
    .sidebar { width:260px; background:var(--sidebar); min-height:100vh; display:flex; flex-direction:column; position:fixed; z-index:50; }
    .sidebar-brand { padding:1.75rem 1.5rem 1.5rem; border-bottom:1px solid rgba(255,255,255,.07); }
    .sidebar-brand h1 { font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--gold); font-weight:800; }
    .sidebar-brand p { font-size:.75rem; color:rgba(255,255,255,.35); margin-top:.25rem; }
    .sidebar-nav { flex:1; padding:1rem; overflow-y:auto; }
    .nav-group-label { font-size:.7rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:rgba(255,255,255,.25); padding:.75rem .75rem .4rem; }
    .nav-item { display:flex; align-items:center; gap:.85rem; padding:.7rem 1rem; border-radius:10px; color:rgba(255,255,255,.55); font-size:.9rem; font-weight:500; text-decoration:none; transition:.2s; margin-bottom:.25rem; cursor:pointer; }
    .nav-item:hover { background:rgba(255,255,255,.07); color:#fff; }
    .nav-item.active { background:var(--gold); color:#fff; font-weight:600; }
    .nav-item .icon { width:18px; text-align:center; }
    .sidebar-footer { padding:1rem; border-top:1px solid rgba(255,255,255,.07); }
    .sidebar-footer a { display:flex; align-items:center; gap:.7rem; padding:.65rem .9rem; border-radius:8px; color:rgba(255,255,255,.4); font-size:.875rem; text-decoration:none; transition:.2s; }
    .sidebar-footer a:hover { color:#fff; }
    .sidebar-footer a.danger { color:rgba(239,68,68,.7); }
    .sidebar-footer a.danger:hover { color:#ef4444; }

    /* MAIN */
    .main { margin-left:260px; min-height:100vh; }
    .topbar { background:#fff; border-bottom:1px solid #E5E7EB; padding:.9rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:40; }
    .topbar-title { font-family:'Playfair Display',serif; font-size:1.5rem; font-weight:800; color:#111; }
    .topbar-user { display:flex; align-items:center; gap:.75rem; }
    .topbar-user img { width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid var(--gold); }
    .topbar-user span { font-size:.875rem; font-weight:600; color:#444; }
    .content { padding:2rem; }

    /* ALERTS */
    .alert { padding:.9rem 1.2rem; border-radius:10px; font-size:.9rem; font-weight:500; margin-bottom:1.5rem; }
    .alert-success { background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0; }
    .alert-error   { background:#FEE2E2; color:#991B1B; border:1px solid #FECACA; }

    /* STAT CARDS */
    .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1.25rem; margin-bottom:2rem; }
    .stat-card { background:#fff; border-radius:16px; padding:1.5rem; display:flex; align-items:center; gap:1.2rem; box-shadow:0 2px 8px rgba(0,0,0,.05); }
    .stat-icon { width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
    .stat-num { font-size:2rem; font-weight:800; color:#111; line-height:1; }
    .stat-label { font-size:.8rem; color:#888; margin-top:.25rem; }

    /* QUICK ACTIONS */
    .quick-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .quick-card { background:#fff; border-radius:14px; padding:1.4rem; display:flex; align-items:center; gap:1rem; text-decoration:none; border:2px solid transparent; box-shadow:0 2px 8px rgba(0,0,0,.04); transition:.25s; }
    .quick-card:hover { border-color:var(--gold); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.08); }
    .quick-icon { font-size:1.6rem; }
    .quick-card h4 { font-weight:700; color:#111; font-size:.95rem; }
    .quick-card p { font-size:.8rem; color:#888; }

    /* DATA TABLE */
    .card { background:#fff; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,.05); overflow:hidden; margin-bottom:1.5rem; }
    .card-header { padding:1.2rem 1.5rem; border-bottom:1px solid #F3F4F6; display:flex; align-items:center; justify-content:space-between; }
    .card-header h3 { font-weight:700; color:#111; font-size:1rem; }
    .card-header .badge { background:rgba(245,166,35,.12); color:var(--gold-dark); font-size:.78rem; font-weight:700; padding:.25rem .75rem; border-radius:50px; }
    table { width:100%; border-collapse:collapse; font-size:.875rem; }
    thead th { background:#F9FAFB; padding:.8rem 1.2rem; text-align:left; font-size:.72rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#6B7280; white-space:nowrap; }
    thead th .sort-icon { display:inline; margin-left:.3rem; font-style:normal; }
    tbody tr { border-top:1px solid #F3F4F6; transition:.15s; }
    tbody tr:hover { background:#FAFAFA; }
    td { padding:.8rem 1.2rem; color:#374151; vertical-align:middle; }
    .badge-cat { display:inline-block; background:rgba(245,166,35,.12); color:var(--gold-dark); font-size:.72rem; font-weight:700; padding:.2rem .65rem; border-radius:50px; letter-spacing:.3px; }
    .badge-admin { background:rgba(59,130,246,.12); color:#1D4ED8; }
    .badge-user  { background:rgba(16,185,129,.12); color:#065F46; }
    .action-btn { display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .8rem; border-radius:7px; font-size:.78rem; font-weight:600; text-decoration:none; border:none; cursor:pointer; transition:.15s; font-family:'DM Sans',sans-serif; }
    .btn-view  { background:#F3F4F6; color:#374151; } .btn-view:hover  { background:#E5E7EB; }
    .btn-edit  { background:#EFF6FF; color:#1D4ED8; } .btn-edit:hover  { background:#DBEAFE; }
    .btn-del   { background:#FEF2F2; color:#DC2626; } .btn-del:hover   { background:#FEE2E2; }
    .btn-save  { background:var(--gold); color:#fff; } .btn-save:hover  { background:var(--gold-dark); }

    /* FORMS */
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .form-full { grid-column:1/-1; }
    .form-group label { display:block; font-size:.8rem; font-weight:600; color:#555; margin-bottom:.4rem; }
    .form-input { width:100%; padding:.75rem 1rem; border:2px solid #E5E7EB; border-radius:10px; font-size:.9rem; font-family:'DM Sans',sans-serif; color:#111; background:#FAFAFA; transition:.2s; min-height:42px; box-sizing:border-box; }
    .form-input:focus { outline:none; border-color:var(--gold); background:#fff; }
    textarea.form-input { resize:vertical; }
    select.form-input { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .8rem center; }
    .form-actions { display:flex; gap:.75rem; margin-top:.5rem; }
    .btn-primary { background:var(--gold); color:#fff; border:none; border-radius:10px; padding:.75rem 1.5rem; font-size:.9rem; font-weight:700; cursor:pointer; transition:.2s; font-family:'DM Sans',sans-serif; }
    .btn-primary:hover { background:var(--gold-dark); }
    .btn-secondary { background:#E5E7EB; color:#374151; border:none; border-radius:10px; padding:.75rem 1.5rem; font-size:.9rem; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; font-family:'DM Sans',sans-serif; transition:.2s; }
    .btn-secondary:hover { background:#D1D5DB; }

    /* DIVIDER LABEL */
    .section-title { font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:800; color:#111; margin-bottom:1.2rem; display:flex; align-items:center; gap:.6rem; }
    .section-title span { font-size:.85rem; }

    /* FORM TOGGLE */
    .form-toggle-header { display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:1.2rem 1.5rem;border-bottom:1px solid #F3F4F6;user-select:none; }
    .form-toggle-header h3 { font-weight:700;color:#111;font-size:1rem; }
    .form-toggle-body { display:none;padding:1.5rem; }
    .form-toggle-body.open { display:block; }
    .toggle-icon { transition:.25s;color:#9CA3AF;font-size:.85rem; }
    .toggle-icon.rotated { transform:rotate(180deg); }

    /* QUICK MENU SECTION HEADER */
    .quick-section-label { font-family:'Playfair Display',serif; font-size:1rem; font-weight:800; color:#374151; margin-bottom:.85rem; display:flex; align-items:center; gap:.5rem; padding:.5rem 0; border-bottom:2px solid rgba(245,166,35,.2); }

    @media(max-width:768px) {
      .sidebar { display:none; }
      .main { margin-left:0; }
      .stats-grid, .form-grid, .quick-grid { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>Jejak Rasa</h1>
    <p>Admin Dashboard</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-group-label">Menu</div>
    <a href="?tab=overview" class="nav-item <?= $tab==='overview'?'active':'' ?>">
      <i class="fas fa-chart-bar icon"></i> Overview
    </a>
    <a href="?tab=makanan" class="nav-item <?= $tab==='makanan'?'active':'' ?>">
      <i class="fas fa-utensils icon"></i> Data Makanan
    </a>
    <a href="?tab=users" class="nav-item <?= $tab==='users'?'active':'' ?>">
      <i class="fas fa-users icon"></i> Data Pengguna
    </a>
    <a href="?tab=ulasan" class="nav-item <?= $tab==='ulasan'?'active':'' ?>">
      <i class="fas fa-star icon"></i> Ulasan
    </a>
    <a href="?tab=pesan" class="nav-item <?= $tab==='pesan'?'active':'' ?>" style="position:relative;">
      <i class="fas fa-envelope icon"></i> Pesan Masuk
      <?php if ($belum_dibaca > 0): ?>
        <span style="margin-left:auto;background:#EF4444;color:#fff;font-size:.65rem;font-weight:800;padding:.15rem .5rem;border-radius:50px;"><?= $belum_dibaca ?></span>
      <?php endif; ?>
    </a>
    <a href="?tab=log" class="nav-item <?= $tab==='log'?'active':'' ?>">
      <i class="fas fa-history icon"></i> Log Aktivitas
    </a>
    <div class="nav-group-label" style="margin-top:.5rem;">Konten</div>
    <a href="?tab=kategori" class="nav-item <?= $tab==='kategori'?'active':'' ?>">
      <i class="fas fa-tags icon"></i> Kelola Kategori
    </a>
    <a href="?tab=tentang" class="nav-item <?= $tab==='tentang'?'active':'' ?>">
      <i class="fas fa-info-circle icon"></i> Tentang Kami
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="index.php"><i class="fas fa-home"></i> Ke Beranda</a>
    <a href="logout.php" class="danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title">
      <?php $titles=['overview'=>'Overview','makanan'=>'Data Makanan','users'=>'Data Pengguna','ulasan'=>'Ulasan','pesan'=>'Pesan Masuk','log'=>'Log Aktivitas','kategori'=>'Kelola Kategori','tentang'=>'Tentang Kami'];
        echo $titles[$tab] ?? 'Dashboard'; ?>
    </div>
    <div class="topbar-user">
      <a href="profile.php">
        <img src="<?= $_SESSION['foto'] ? 'uploads/'.$_SESSION['foto'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png' ?>">
      </a>
      <span>Halo, <?= htmlspecialchars($_SESSION['username']) ?> 👋</span>
    </div>
  </div>

  <div class="content">
    <?= $msg ?>
    <?php if (isset($_GET['msg']) && $_GET['msg']==='hapus_ok'): ?>
      <div class="alert alert-success">✅ Data berhasil dihapus.</div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg']==='lepas_ok'): ?>
      <div class="alert alert-success">✅ Makanan berhasil dilepas dari kategori.</div>
    <?php endif; ?>

    <!-- ══ OVERVIEW ══ -->
    <?php if ($tab === 'overview'): ?>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,166,35,.12);color:var(--gold);"><i class="fas fa-utensils"></i></div>
        <div><div class="stat-num"><?= $total_makanan ?></div><div class="stat-label">Total Makanan</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#EFF6FF;color:#3B82F6;"><i class="fas fa-users"></i></div>
        <div><div class="stat-num"><?= $total_users ?></div><div class="stat-label">Total Pengguna</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#FEF3C7;color:#D97706;"><i class="fas fa-star"></i></div>
        <div><div class="stat-num"><?= $total_ulasan ?></div><div class="stat-label">Total Ulasan</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#FEF2F2;color:#EF4444;"><i class="fas fa-envelope"></i></div>
        <div>
          <div class="stat-num"><?= $total_pesan ?></div>
          <div class="stat-label">Pesan Masuk <?php if($belum_dibaca>0): ?><span style="color:#EF4444;font-weight:700;">(<?= $belum_dibaca ?> baru)</span><?php endif; ?></div>
        </div>
      </div>
    </div>

    <!-- GRAFIK FAVORIT -->
    <?php if (!empty($fav_chart_labels)): ?>
    <div class="card" style="margin-bottom:2rem;">
      <div class="card-header">
        <h3>❤️ Grafik Favorit Makanan</h3>
        <span class="badge"><?= $total_favorit ?> total favorit</span>
      </div>
      <div style="padding:1.5rem;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;align-items:center;">
          <div style="position:relative;height:260px;">
            <canvas id="chartFavBar"></canvas>
          </div>
          <div style="position:relative;height:260px;max-width:260px;margin:auto;">
            <canvas id="chartFavDoughnut"></canvas>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="card" style="margin-bottom:2rem;padding:1.5rem 2rem;color:#9CA3AF;font-size:.9rem;">
      <i class="fas fa-heart" style="margin-right:.5rem;"></i> Belum ada data favorit. Data akan muncul setelah pengguna menyimpan makanan favorit.
    </div>
    <?php endif; ?>

    <!-- QUICK MENU -->
    <div class="quick-section-label"><i class="fas fa-bolt" style="color:var(--gold);"></i> Quick Menu</div>
    <div class="quick-grid">
      <a href="?tab=makanan" class="quick-card">
        <div class="quick-icon">🍽️</div>
        <div><h4>Tambah Makanan Baru</h4><p>Kelola data resep dan kuliner</p></div>
      </a>
      <a href="?tab=users" class="quick-card">
        <div class="quick-icon">👥</div>
        <div><h4>Kelola Pengguna</h4><p>Tambah, edit, dan hapus pengguna</p></div>
      </a>
      <a href="?tab=ulasan" class="quick-card">
        <div class="quick-icon">⭐</div>
        <div><h4>Moderasi Ulasan</h4><p>Pantau dan hapus ulasan</p></div>
      </a>
      <a href="?tab=pesan" class="quick-card">
        <div class="quick-icon">📩</div>
        <div><h4>Pesan Masuk</h4><p><?= $belum_dibaca ?> pesan belum dibaca</p></div>
      </a>
      <a href="?tab=kategori" class="quick-card">
        <div class="quick-icon">🏷️</div>
        <div><h4>Kelola Kategori</h4><p>Tambah dan edit kategori makanan</p></div>
      </a>
      <a href="?tab=tentang" class="quick-card">
        <div class="quick-icon">📄</div>
        <div><h4>Tentang Kami</h4><p>Edit konten halaman tentang kami</p></div>
      </a>
      <a href="index.php" class="quick-card">
        <div class="quick-icon">🌐</div>
        <div><h4>Lihat Website</h4><p>Buka halaman utama publik</p></div>
      </a>
    </div>

    <!-- ══ MAKANAN ══ -->
    <?php elseif ($tab === 'makanan'): ?>
    <div class="card" style="margin-bottom:1.5rem;">
      <div class="form-toggle-header" onclick="toggleForm('formMakanan',this)" id="toggleMakananHeader">
        <h3><?= $edit_data ? '✏️ Edit Makanan: '.htmlspecialchars($edit_data['nama']) : '➕ Tambah Makanan Baru' ?></h3>
        <div style="display:flex;align-items:center;gap:.75rem;">
          <?php if ($edit_data): ?><a href="?tab=makanan" class="action-btn btn-view" onclick="event.stopPropagation()">Batal</a><?php endif; ?>
          <i class="fas fa-chevron-down toggle-icon <?= $edit_data ? 'rotated' : '' ?>" id="iconMakanan"></i>
        </div>
      </div>
      <div class="form-toggle-body <?= $edit_data ? 'open' : '' ?>" id="formMakanan">
        <form action="?tab=makanan" method="POST" enctype="multipart/form-data">
          <?php if ($edit_data): ?><input type="hidden" name="id" value="<?= $edit_data['id'] ?>"><?php endif; ?>
          <div class="form-grid">
            <div class="form-group">
              <label>Nama Makanan *</label>
              <input type="text" name="nama" required class="form-input" value="<?= htmlspecialchars($edit_data['nama'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Kategori *</label>
              <select name="kategori" class="form-input">
                <?php foreach ($kategori_arr as $kat): ?>
                <option value="<?= htmlspecialchars($kat) ?>" <?= ($edit_data['kategori'] ?? '') === $kat ? 'selected' : '' ?>><?= htmlspecialchars($kat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Daerah Asal *</label>
              <input 
                type="text"
                name="daerah"
                required
                class="form-input"
                placeholder="Masukkan daerah asal"
                value="<?= htmlspecialchars($edit_data['daerah'] ?? '') ?>"
              >
            </div>
            <div class="form-group">
              <label>URL Gambar</label>
              <input type="text" name="gambar" class="form-input" value="<?= htmlspecialchars($edit_data['gambar'] ?? '') ?>" placeholder="https://...">
            </div>
            <div class="form-group form-full">
              <label>Upload Gambar <span style="color:#aaa;font-weight:400;">(opsional, override URL)</span></label>
              <input type="file" name="gambar_file" accept="image/*" class="form-input" style="padding:.5rem;">
            </div>
            <div class="form-group form-full">
              <label>Deskripsi</label>
              <textarea name="deskripsi" rows="3" class="form-input"><?= htmlspecialchars($edit_data['deskripsi'] ?? '') ?></textarea>
            </div>
            <div class="form-group form-full">
              <label>Bahan-bahan <span style="color:#aaa;font-weight:400;">(pisahkan dengan | )</span></label>
              <textarea name="bahan" rows="3" class="form-input" placeholder="Bawang merah 5 siung|Santan 200ml|Garam secukupnya"><?= htmlspecialchars($edit_data['bahan'] ?? '') ?></textarea>
            </div>
            <div class="form-group form-full">
              <label>Cara Membuat <span style="color:#aaa;font-weight:400;">(pisahkan setiap langkah dengan | )</span></label>
              <textarea name="cara_buat" rows="4" class="form-input" placeholder="Cuci bahan.|Tumis bumbu.|Masukkan daging."><?= htmlspecialchars($edit_data['cara_buat'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="form-actions">
            <?php if ($edit_data): ?>
              <button type="submit" name="edit_makanan" class="btn-primary">Simpan Perubahan</button>
              <a href="?tab=makanan" class="btn-secondary">Batal</a>
            <?php else: ?>
              <button type="submit" name="tambah_makanan" class="btn-primary">Tambah Makanan</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="card-header" style="gap:.75rem;flex-wrap:wrap;">
      <h3>Daftar Makanan</h3>
      <div style="display:flex;align-items:center;gap:.6rem;margin-left:auto;flex-wrap:wrap;">
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:.8rem;"></i>
          <input type="text" id="searchMakanan" placeholder="Cari makanan..." class="form-input" style="padding-left:2.2rem;width:220px;">
        </div>
        <select id="filterKategoriMakanan" class="form-input" style="width:175px;">
          <option value="">Semua Kategori</option>
          <?php mysqli_data_seek($kategori_list,0); while($kf=mysqli_fetch_assoc($kategori_list)): ?>
          <option value="<?= htmlspecialchars($kf['nama']) ?>"><?= htmlspecialchars($kf['nama']) ?></option>
          <?php endwhile; mysqli_data_seek($kategori_list,0); ?>
        </select>
        <select id="pageSizeMakanan" class="form-input" style="width:130px;">
          <option value="10">10/halaman</option>
          <option value="25">25/halaman</option>
          <option value="50">50/halaman</option>
          <option value="0">Semua</option>
        </select>
        <span class="badge" id="badgeMakanan"><?= $total_makanan ?> data</span>
      </div>
    </div>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th style="width:42px;text-align:center;" data-table="makanan">#</th>
              <th>Gambar</th>
              <th style="cursor:pointer;" data-sort="nama" data-table="makanan">Nama <span class="sort-icon">↕</span></th>
              <th style="cursor:pointer;" data-sort="kategori" data-table="makanan">Kategori <span class="sort-icon">↕</span></th>
              <th style="cursor:pointer;" data-sort="daerah" data-table="makanan">Daerah <span class="sort-icon">↕</span></th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody id="tableMakanan">
            <?php mysqli_data_seek($makanan_list, 0); while ($m = mysqli_fetch_assoc($makanan_list)): ?>
            <tr>
              <td style="color:#9CA3AF;font-size:.8rem;text-align:center;" class="row-no"></td>
              <td><img src="<?= htmlspecialchars($m['gambar']) ?>" style="width:52px;height:40px;object-fit:cover;border-radius:8px;"></td>
              <td style="font-weight:600;color:#111;"><?= htmlspecialchars($m['nama']) ?></td>
              <td><span class="badge-cat"><?= htmlspecialchars($m['kategori']) ?></span></td>
              <td style="color:#6B7280;"><?= htmlspecialchars($m['daerah']) ?></td>
              <td style="text-align:center;">
                <div style="display:flex;gap:.4rem;justify-content:center;">
                  <a href="detail.php?id=<?= $m['id'] ?>" target="_blank" class="action-btn btn-view"><i class="fas fa-eye"></i> Lihat</a>
                  <a href="?tab=makanan&edit_makanan=<?= $m['id'] ?>" class="action-btn btn-edit"><i class="fas fa-pen"></i> Edit</a>
                  <a href="?tab=makanan&hapus_makanan=<?= $m['id'] ?>" onclick="return confirm('Hapus <?= addslashes($m['nama']) ?>?')" class="action-btn btn-del"><i class="fas fa-trash"></i> Hapus</a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <div id="paginationMakanan" style="display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.5rem;border-top:1px solid #F3F4F6;font-size:.8rem;color:#6B7280;flex-wrap:wrap;gap:.5rem;">
        <span id="infoMakanan"></span>
        <div id="pagesMakanan" style="display:flex;gap:.3rem;"></div>
      </div>
    </div>

    <!-- ══ USERS ══ -->
    <?php elseif ($tab === 'users'): ?>
    <!-- Form Tambah / Edit User -->
    <div class="card" style="margin-bottom:1.5rem;">
      <div class="form-toggle-header" onclick="toggleForm('formUser',this)">
        <h3><?= $edit_user ? '✏️ Edit Pengguna: '.htmlspecialchars($edit_user['username']) : '➕ Tambah Pengguna Baru' ?></h3>
        <div style="display:flex;align-items:center;gap:.75rem;">
          <?php if ($edit_user): ?><a href="?tab=users" class="action-btn btn-view" onclick="event.stopPropagation()">Batal</a><?php endif; ?>
          <i class="fas fa-chevron-down toggle-icon <?= $edit_user ? 'rotated' : '' ?>"></i>
        </div>
      </div>
      <div class="form-toggle-body <?= $edit_user ? 'open' : '' ?>" id="formUser">
        <form action="?tab=users" method="POST">
          <?php if ($edit_user): ?><input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>"><?php endif; ?>
          <div class="form-grid">
            <div class="form-group">
              <label>Username *</label>
              <input type="text" name="username" required class="form-input" value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>" placeholder="Masukkan username">
            </div>
            <div class="form-group">
              <label>Email *</label>
              <input type="email" name="email" required class="form-input" value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>" placeholder="nama@email.com">
            </div>
            <div class="form-group">
              <label>Password <?= $edit_user ? '<span style="color:#aaa;font-weight:400;">(kosongkan jika tidak ganti)</span>' : '*' ?></label>
              <input type="password" name="password" <?= $edit_user ? '' : 'required' ?> class="form-input" placeholder="<?= $edit_user ? 'Kosongkan jika tidak ganti' : 'Minimal 6 karakter' ?>">
            </div>
            <div class="form-group">
              <label>Role *</label>
              <select name="role" class="form-input">
                  <option value="user"  <?= ($edit_user['role'] ?? 'user') === 'user'  ? 'selected' : '' ?>>User</option>
                  <option value="admin" <?= ($edit_user['role'] ?? '')      === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
          </div>
          <div class="form-actions">
            <?php if ($edit_user): ?>
              <button type="submit" name="edit_user" class="btn-primary">Simpan Perubahan</button>
              <a href="?tab=users" class="btn-secondary">Batal</a>
            <?php else: ?>
              <button type="submit" name="tambah_user" class="btn-primary">Tambah Pengguna</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabel Users -->
    <div class="card">
    <div class="card-header" style="gap:.75rem;flex-wrap:wrap;">
      <h3>Daftar Pengguna</h3>
      <div style="display:flex;align-items:center;gap:.6rem;margin-left:auto;flex-wrap:wrap;">
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:.8rem;"></i>
          <input type="text" id="searchUsers" placeholder="Cari pengguna..." class="form-input" style="padding-left:2.2rem;width:220px;">
        </div>
        <select id="filterRoleUsers" class="form-input" style="width:145px;">
          <option value="">Semua Role</option>
          <option value="admin">Admin</option>
          <option value="user">User</option>
        </select>
        <select id="pageSizeUsers" class="form-input" style="width:130px;">
          <option value="10">10/halaman</option>
          <option value="25">25/halaman</option>
          <option value="50">50/halaman</option>
          <option value="0">Semua</option>
        </select>
        <span class="badge"><?= $total_users ?> pengguna</span>
      </div>
    </div>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th style="width:42px;text-align:center;" data-table="users">#</th>
              <th>Foto</th>
              <th style="cursor:pointer;" data-sort="username" data-table="users">Username <span class="sort-icon">↕</span></th>
              <th style="cursor:pointer;" data-sort="email" data-table="users">Email <span class="sort-icon">↕</span></th>
              <th style="text-align:center;cursor:pointer;" data-sort="role" data-table="users">Role <span class="sort-icon">↕</span></th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody id="tableUsers">
            <?php while ($u = mysqli_fetch_assoc($users_list)): ?>
            <tr data-role="<?= $u['role'] ?>">
              <td style="color:#9CA3AF;font-size:.8rem;text-align:center;" class="row-no"></td>
              <td>
                <img src="<?= $u['foto'] ? 'uploads/'.$u['foto'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png' ?>"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #FDE68A;">
              </td>
              <td style="font-weight:600;color:#111;">
                <?= htmlspecialchars($u['username']) ?>
                <?php if ($u['id'] == $_SESSION['user_id']): ?><span style="font-size:.7rem;color:var(--gold);margin-left:.3rem;">(Anda)</span><?php endif; ?>
              </td>
              <td style="color:#6B7280;"><?= htmlspecialchars($u['email']) ?></td>
              <td style="text-align:center;">
                <span class="badge-cat <?= $u['role']==='admin' ? 'badge-admin' : 'badge-user' ?>">
                  <?= $u['role'] ?>
                </span>
              </td>
              <td style="text-align:center;">
                <div style="display:flex;gap:.4rem;justify-content:center;">
                  <?php
                    $canEdit = true;
                  ?>
                  <?php if ($canEdit): ?>
                  <a href="?tab=users&edit_user=<?= $u['id'] ?>" class="action-btn btn-edit"><i class="fas fa-pen"></i> Edit</a>
                  <?php endif; ?>
                  <?php if ($u['id'] != $_SESSION['user_id']): ?>
                  <a href="?tab=users&hapus_user=<?= $u['id'] ?>" onclick="return confirm('Hapus pengguna <?= addslashes($u['username']) ?>?')" class="action-btn btn-del"><i class="fas fa-trash"></i> Hapus</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <div id="paginationUsers" style="display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.5rem;border-top:1px solid #F3F4F6;font-size:.8rem;color:#6B7280;flex-wrap:wrap;gap:.5rem;">
        <span id="infoUsers"></span>
        <div id="pagesUsers" style="display:flex;gap:.3rem;"></div>
      </div>
    </div>

    <!-- ══ ULASAN ══ -->
    <?php elseif ($tab === 'ulasan'): ?>
    <div class="card">
    <div class="card-header" style="gap:.75rem;flex-wrap:wrap;">
      <h3>Semua Ulasan</h3>
      <div style="display:flex;align-items:center;gap:.6rem;margin-left:auto;flex-wrap:wrap;">
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:.8rem;"></i>
          <input type="text" id="searchUlasan" placeholder="Cari ulasan..." class="form-input" style="padding-left:2.2rem;width:220px;">
        </div>
        <select id="filterRatingUlasan" class="form-input" style="width:155px;">
          <option value="">Semua Rating</option>
          <option value="5">★★★★★ (5)</option>
          <option value="4">★★★★☆ (4)</option>
          <option value="3">★★★☆☆ (3)</option>
          <option value="2">★★☆☆☆ (2)</option>
          <option value="1">★☆☆☆☆ (1)</option>
        </select>
        <select id="pageSizeUlasan" class="form-input" style="width:130px;">
          <option value="10">10/halaman</option>
          <option value="25">25/halaman</option>
          <option value="50">50/halaman</option>
          <option value="0">Semua</option>
        </select>
        <span class="badge"><?= $total_ulasan ?> ulasan</span>
      </div>
    </div>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th style="width:42px;text-align:center;" data-table="ulasan">#</th>
              <th style="cursor:pointer;" data-sort="username" data-table="ulasan">Pengguna <span class="sort-icon">↕</span></th>
              <th style="cursor:pointer;" data-sort="makanan" data-table="ulasan">Makanan <span class="sort-icon">↕</span></th>
              <th style="text-align:center;cursor:pointer;" data-sort="rating" data-table="ulasan">Rating <span class="sort-icon">↕</span></th>
              <th>Komentar</th>
              <th style="cursor:pointer;" data-sort="tanggal" data-table="ulasan">Tanggal <span class="sort-icon">↕</span></th>
              <th style="text-align:center;">Status</th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody id="tableUlasan">
            <?php while ($ul = mysqli_fetch_assoc($ulasan_list)): ?>
            <tr data-rating="<?= $ul['rating'] ?>" data-hidden="<?= $ul['is_hidden'] ?? 0 ?>" <?= ($ul['is_hidden'] ?? 0) ? 'style="opacity:.45;background:#fafafa;"' : '' ?>>
              <td style="color:#9CA3AF;font-size:.8rem;text-align:center;" class="row-no"></td>
              <td style="font-weight:600;"><?= htmlspecialchars($ul['username']) ?></td>
              <td style="color:#6B7280;"><?= htmlspecialchars($ul['nama_makanan']) ?></td>
              <td style="text-align:center;color:#F59E0B;letter-spacing:2px;">
                <?php for($i=1;$i<=5;$i++) echo $i<=$ul['rating']?'★':'☆'; ?>
              </td>
              <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#6B7280;"><?= htmlspecialchars($ul['komentar']) ?></td>
              <td style="color:#9CA3AF;font-size:.8rem;"><?= date('d M Y', strtotime($ul['created_at'])) ?></td>
              <td style="text-align:center;">
                <?php if ($ul['is_hidden'] ?? 0): ?>
                  <span style="font-size:.72rem;font-weight:600;padding:.25rem .7rem;border-radius:50px;background:#FEE2E2;color:#991B1B;">Disembunyikan</span>
                <?php else: ?>
                  <span style="font-size:.72rem;font-weight:600;padding:.25rem .7rem;border-radius:50px;background:#D1FAE5;color:#065F46;">Tampil</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;white-space:nowrap;">
                <a href="?tab=ulasan&toggle_ulasan=<?= $ul['id'] ?>"
                   title="<?= ($ul['is_hidden'] ?? 0) ? 'Tampilkan' : 'Sembunyikan' ?>"
                   class="action-btn"
                   style="<?= ($ul['is_hidden'] ?? 0) ? 'background:#D1FAE5;color:#065F46;' : 'background:#FEF3C7;color:#92400E;' ?>"
                   onclick="return confirm('<?= ($ul['is_hidden'] ?? 0) ? 'Tampilkan ulasan ini?' : 'Sembunyikan ulasan ini dari publik?' ?>')">
                  <i class="fas fa-<?= ($ul['is_hidden'] ?? 0) ? 'eye' : 'eye-slash' ?>"></i>
                </a>
                <a href="?tab=ulasan&hapus_ulasan=<?= $ul['id'] ?>" onclick="return confirm('Hapus ulasan ini permanen?')" class="action-btn btn-del"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <div id="paginationUlasan" style="display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.5rem;border-top:1px solid #F3F4F6;font-size:.8rem;color:#6B7280;flex-wrap:wrap;gap:.5rem;">
        <span id="infoUlasan"></span>
        <div id="pagesUlasan" style="display:flex;gap:.3rem;"></div>
      </div>
    </div>

    <!-- ══ PESAN MASUK ══ -->
    <?php elseif ($tab === 'pesan'): ?>
    <div class="card">
      <div class="card-header" style="gap:.75rem;flex-wrap:wrap;">
        <h3>📩 Pesan Masuk</h3>
        <div style="display:flex;align-items:center;gap:.6rem;margin-left:auto;flex-wrap:wrap;">
          <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:.8rem;"></i>
            <input type="text" id="searchPesan" placeholder="Cari nama/email/pesan..." class="form-input" style="padding-left:2.2rem;width:230px;">
          </div>
          <select id="filterStatusPesan" class="form-input" style="width:155px;">
            <option value="">Semua Status</option>
            <option value="0">Belum Dibaca</option>
            <option value="1">Sudah Dibaca</option>
          </select>
          <select id="pageSizePesan" class="form-input" style="width:130px;">
            <option value="10">10/halaman</option>
            <option value="25">25/halaman</option>
            <option value="50">50/halaman</option>
            <option value="0">Semua</option>
          </select>
          <span class="badge"><?= $total_pesan ?> pesan<?= $belum_dibaca > 0 ? ' &nbsp;·&nbsp; <span style="color:#EF4444;">'.$belum_dibaca.' baru</span>' : '' ?></span>
        </div>
      </div>
      <?php if ($total_pesan == 0): ?>
        <div style="text-align:center;padding:4rem 2rem;color:#9CA3AF;">
          <i class="fas fa-inbox" style="font-size:3rem;opacity:.3;display:block;margin-bottom:1rem;"></i>
          <p>Belum ada pesan yang masuk.</p>
        </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th style="width:40px;text-align:center;" data-table="pesan">#</th>
              <th style="cursor:pointer;" data-sort="nama" data-table="pesan">Nama <span class="sort-icon">↕</span></th>
              <th style="cursor:pointer;" data-sort="email" data-table="pesan">Email <span class="sort-icon">↕</span></th>
              <th>Pesan</th>
              <th style="cursor:pointer;" data-sort="tanggal" data-table="pesan">Tanggal <span class="sort-icon">↕</span></th>
              <th style="text-align:center;cursor:pointer;" data-sort="status" data-table="pesan">Status <span class="sort-icon">↕</span></th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody id="tablePesan">
            <?php mysqli_data_seek($pesan_list,0); while ($pm = mysqli_fetch_assoc($pesan_list)): ?>
            <tr data-status="<?= $pm['sudah_dibaca'] ?>" style="<?= !$pm['sudah_dibaca'] ? 'background:#FFFBF0;' : '' ?>">
              <td style="text-align:center;color:#9CA3AF;font-size:.8rem;" class="row-no"></td>
              <td style="font-weight:600;color:#111;">
                <?= htmlspecialchars($pm['nama']) ?>
                <?php if (!$pm['sudah_dibaca']): ?>
                  <span style="display:inline-block;background:#EF4444;color:#fff;font-size:.6rem;font-weight:700;padding:.1rem .4rem;border-radius:50px;margin-left:.3rem;">BARU</span>
                <?php endif; ?>
              </td>
              <td style="color:#6B7280;font-size:.85rem;"><?= htmlspecialchars($pm['email']) ?></td>
              <td style="max-width:280px;color:#374151;font-size:.85rem;">
                <div style="white-space:pre-wrap;max-height:60px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(mb_strimwidth($pm['pesan'],0,120,'…')) ?></div>
              </td>
              <td style="color:#9CA3AF;font-size:.8rem;white-space:nowrap;"><?= date('d M Y', strtotime($pm['created_at'])) ?><br><span style="font-size:.75rem;"><?= date('H:i', strtotime($pm['created_at'])) ?></span></td>
              <td style="text-align:center;">
                <?php if ($pm['sudah_dibaca']): ?>
                  <span style="font-size:.75rem;color:#10B981;font-weight:600;"><i class="fas fa-check-circle"></i> Dibaca</span>
                <?php else: ?>
                  <span style="font-size:.75rem;color:#F59E0B;font-weight:600;"><i class="fas fa-clock"></i> Belum</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <div style="display:flex;gap:.3rem;justify-content:center;flex-wrap:wrap;">
                  <?php if (!$pm['sudah_dibaca']): ?>
                  <a href="?tab=pesan&baca_pesan=<?= $pm['id'] ?>" class="action-btn btn-edit" style="font-size:.72rem;" title="Tandai Dibaca">
                    <i class="fas fa-check"></i>
                  </a>
                  <?php endif; ?>
                  <a href="mailto:<?= htmlspecialchars($pm['email']) ?>?subject=Re:%20Pesan%20Jejak%20Rasa" class="action-btn btn-save" style="font-size:.72rem;" title="Balas">
                    <i class="fas fa-reply"></i>
                  </a>
                  <a href="?tab=pesan&hapus_pesan=<?= $pm['id'] ?>"
                     onclick="return confirm('Hapus pesan dari <?= addslashes(htmlspecialchars($pm['nama'])) ?>?')"
                     class="action-btn btn-del" style="font-size:.72rem;" title="Hapus">
                    <i class="fas fa-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <div id="paginationPesan" style="display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.5rem;border-top:1px solid #F3F4F6;font-size:.8rem;color:#6B7280;flex-wrap:wrap;gap:.5rem;">
        <span id="infoPesan"></span>
        <div id="pagesPesan" style="display:flex;gap:.3rem;"></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ LOG AKTIVITAS ══ -->
    <?php elseif ($tab === 'log'): ?>
    <div class="card">
      <div class="card-header" style="gap:.75rem;flex-wrap:wrap;">
        <h3>📋 Log Aktivitas Admin</h3>
        <div style="display:flex;align-items:center;gap:.6rem;margin-left:auto;flex-wrap:wrap;">
          <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:.8rem;"></i>
            <input type="text" id="searchLog" placeholder="Cari log..." class="form-input" style="padding-left:2.2rem;width:220px;">
          </div>
          <select id="filterAksiLog" class="form-input" style="width:145px;">
            <option value="">Semua Aksi</option>
            <option value="TAMBAH">TAMBAH</option>
            <option value="EDIT">EDIT</option>
            <option value="HAPUS">HAPUS</option>
          </select>
          <select id="pageSizeLog" class="form-input" style="width:130px;">
            <option value="10">10/halaman</option>
            <option value="25">25/halaman</option>
            <option value="50">50/halaman</option>
            <option value="0">Semua</option>
          </select>
          <span class="badge" id="badgeLog"><?= $total_log ?> entri</span>
        </div>
      </div>

      <?php if ($total_log == 0): ?>
        <div style="text-align:center;padding:4rem 2rem;color:#9CA3AF;">
          <i class="fas fa-history" style="font-size:3rem;opacity:.3;display:block;margin-bottom:1rem;"></i>
          <p style="font-size:.95rem;">Belum ada aktivitas yang tercatat.</p>
        </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th style="width:42px;text-align:center;" data-table="log">#</th>
              <th style="cursor:pointer;" data-sort="waktu" data-table="log">Waktu <span class="sort-icon">↕</span></th>
              <th style="cursor:pointer;" data-sort="admin" data-table="log">Admin <span class="sort-icon">↕</span></th>
              <th style="text-align:center;cursor:pointer;" data-sort="aksi" data-table="log">Aksi <span class="sort-icon">↕</span></th>
              <th>Keterangan</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody id="tableLog">
            <?php while ($log = mysqli_fetch_assoc($log_list)):
              $aksiColor = match($log['aksi']) {
                'TAMBAH' => 'background:#D1FAE5;color:#065F46;',
                'EDIT'   => 'background:#DBEAFE;color:#1D4ED8;',
                'HAPUS'  => 'background:#FEE2E2;color:#991B1B;',
                default  => 'background:#F3F4F6;color:#374151;',
              };
            ?>
            <tr data-aksi="<?= $log['aksi'] ?>">
              <td style="color:#9CA3AF;font-size:.8rem;text-align:center;" class="row-no"></td>
              <td style="color:#6B7280;font-size:.8rem;white-space:nowrap;">
                <?= date('d M Y', strtotime($log['created_at'])) ?><br>
                <span style="color:#9CA3AF;"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:.5rem;">
                  <div style="width:30px;height:30px;border-radius:50%;background:rgba(245,166,35,.15);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:.8rem;flex-shrink:0;">
                    <i class="fas fa-user-shield"></i>
                  </div>
                  <span style="font-weight:600;color:#111;font-size:.85rem;"><?= htmlspecialchars($log['username']) ?></span>
                </div>
              </td>
              <td style="text-align:center;">
                <span style="display:inline-block;<?= $aksiColor ?>font-size:.72rem;font-weight:700;padding:.2rem .7rem;border-radius:50px;letter-spacing:.4px;">
                  <?= htmlspecialchars($log['aksi']) ?>
                </span>
              </td>
              <td style="color:#374151;font-size:.875rem;max-width:280px;"><?= htmlspecialchars($log['keterangan']) ?></td>
              <td style="color:#9CA3AF;font-size:.8rem;font-family:monospace;"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <div id="paginationLog" style="display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.5rem;border-top:1px solid #F3F4F6;font-size:.8rem;color:#6B7280;flex-wrap:wrap;gap:.5rem;">
        <span id="infoLog"></span>
        <div id="pagesLog" style="display:flex;gap:.3rem;"></div>
      </div>
      <div style="padding:1rem 1.5rem;border-top:1px solid #F3F4F6;font-size:.78rem;color:#9CA3AF;display:flex;align-items:center;gap:.4rem;">
        <i class="fas fa-lock" style="color:#D1D5DB;"></i>
        Log aktivitas bersifat <strong style="color:#6B7280;">read-only</strong> — tidak dapat diubah atau dihapus untuk menjaga integritas audit.
      </div>
      <?php endif; ?>
    </div>
    <!-- ══ KATEGORI ══ -->
    <?php elseif ($tab === 'kategori'): ?>
    <div class="card" style="margin-bottom:1.5rem;">
      <div class="form-toggle-header" onclick="toggleForm('formKategori',this)">
        <h3><?= $edit_kategori ? '✏️ Edit Kategori: '.htmlspecialchars($edit_kategori['nama']) : '➕ Tambah Kategori Baru' ?></h3>
        <div style="display:flex;align-items:center;gap:.75rem;">
          <?php if ($edit_kategori): ?><a href="?tab=kategori" class="action-btn btn-view" onclick="event.stopPropagation()">Batal</a><?php endif; ?>
          <i class="fas fa-chevron-down toggle-icon <?= $edit_kategori ? 'rotated' : '' ?>"></i>
        </div>
      </div>
      <div class="form-toggle-body <?= $edit_kategori ? 'open' : '' ?>" id="formKategori">
        <form action="?tab=kategori" method="POST">
          <?php if ($edit_kategori): ?><input type="hidden" name="kid" value="<?= $edit_kategori['id'] ?>"><?php endif; ?>
          <div class="form-grid">
            <div class="form-group">
              <label>Nama Kategori *</label>
              <input type="text" name="knama" required class="form-input" value="<?= htmlspecialchars($edit_kategori['nama'] ?? '') ?>" placeholder="contoh: Minuman Tradisional">
            </div>
            <div class="form-group">
              <label>Deskripsi <span style="color:#aaa;font-weight:400;">(opsional)</span></label>
              <input type="text" name="kdesc" class="form-input" value="<?= htmlspecialchars($edit_kategori['deskripsi'] ?? '') ?>" placeholder="Deskripsi singkat kategori">
            </div>
          </div>
          <div class="form-actions">
            <?php if ($edit_kategori): ?>
              <button type="submit" name="edit_kategori_save" class="btn-primary">Simpan Perubahan</button>
              <a href="?tab=kategori" class="btn-secondary">Batal</a>
            <?php else: ?>
              <button type="submit" name="tambah_kategori" class="btn-primary">Tambah Kategori</button>
            <?php endif; ?>
          </div>
        </form>

        <?php if ($edit_kategori): ?>
        <?php
          $kn_esc = mysqli_real_escape_string($koneksi, $edit_kategori['nama']);
          $mak_list_kat = mysqli_query($koneksi, "SELECT * FROM makanan WHERE kategori='$kn_esc' ORDER BY nama ASC");
          $mak_count_kat = mysqli_num_rows($mak_list_kat);
        ?>
        <div style="margin-top:1.5rem;border-top:2px solid #F3F4F6;padding-top:1.2rem;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
            <h4 style="font-weight:700;color:#111;font-size:.95rem;display:flex;align-items:center;gap:.5rem;">
              <i class="fas fa-utensils" style="color:var(--gold);"></i>
              Makanan dalam kategori ini
              <span style="background:rgba(245,166,35,.12);color:var(--gold-dark);font-size:.75rem;font-weight:700;padding:.2rem .65rem;border-radius:50px;"><?= $mak_count_kat ?> makanan</span>
            </h4>
            <a href="?tab=makanan" class="action-btn btn-save" style="font-size:.8rem;">
              <i class="fas fa-plus"></i> Tambah Makanan Baru
            </a>
          </div>
          <?php if ($mak_count_kat == 0): ?>
          <div style="text-align:center;padding:2rem;color:#9CA3AF;font-size:.875rem;background:#FAFAFA;border-radius:12px;">
            <i class="fas fa-bowl-food" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.5rem;"></i>
            Belum ada makanan di kategori ini.
          </div>
          <?php else: ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.75rem;">
            <?php while ($mk = mysqli_fetch_assoc($mak_list_kat)): ?>
            <div style="display:flex;align-items:center;gap:.75rem;background:#FAFAFA;border:1px solid #F0F0F0;border-radius:12px;padding:.7rem .9rem;transition:.2s;" onmouseover="this.style.borderColor='#FDE68A'" onmouseout="this.style.borderColor='#F0F0F0'">
              <img src="<?= htmlspecialchars($mk['gambar']) ?>" style="width:48px;height:38px;object-fit:cover;border-radius:8px;flex-shrink:0;">
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;color:#111;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($mk['nama']) ?></div>
                <div style="font-size:.75rem;color:#9CA3AF;margin-top:.1rem;"><?= htmlspecialchars($mk['daerah']) ?></div>
              </div>
              <div style="display:flex;gap:.3rem;flex-shrink:0;">
                <a href="?tab=makanan&edit_makanan=<?= $mk['id'] ?>" class="action-btn btn-edit" style="font-size:.72rem;padding:.3rem .55rem;" title="Edit"><i class="fas fa-pen"></i></a>
                <a href="?tab=kategori&hapus_makanan_dari_kat=<?= $mk['id'] ?>&kid=<?= $edit_kategori['id'] ?>"
                   onclick="return confirm('Lepas \"<?= addslashes($mk['nama']) ?>\" dari kategori ini? Makanan tidak dihapus, hanya dikeluarkan dari kategori.')"
                   class="action-btn btn-del" style="font-size:.72rem;padding:.3rem .55rem;background:#FEF3C7;color:#D97706;" title="Lepas dari kategori"><i class="fas fa-times"></i></a>
              </div>
            </div>
            <?php endwhile; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <div class="card">
      <div class="card-header" style="gap:.75rem;flex-wrap:wrap;">
        <h3>Daftar Kategori</h3>
        <div style="display:flex;align-items:center;gap:.6rem;margin-left:auto;flex-wrap:wrap;">
          <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:.8rem;"></i>
            <input type="text" id="searchKategori" placeholder="Cari kategori..." class="form-input" style="padding-left:2rem;width:200px;">
          </div>
          <select id="pageSizeKategori" class="form-input" style="width:100px;">
            <option value="10">10/halaman</option>
            <option value="25">25/halaman</option>
            <option value="0">Semua</option>
          </select>
          <span class="badge" id="badgeKategori"><?= mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM kategori"))['c'] ?> kategori</span>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th style="width:42px;text-align:center;" data-table="kategori">#</th>
              <th style="cursor:pointer;" data-sort="nama_kat" data-table="kategori">Nama Kategori <span class="sort-icon">↕</span></th>
              <th>Deskripsi</th>
              <th style="text-align:center;cursor:pointer;" data-sort="jml" data-table="kategori">Jumlah Makanan <span class="sort-icon">↕</span></th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody id="tableKategori">
            <?php mysqli_data_seek($kategori_list,0); while ($kat=mysqli_fetch_assoc($kategori_list)): 
              $jml = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as c FROM makanan WHERE kategori='".mysqli_real_escape_string($koneksi,$kat['nama'])."'"))['c'];
            ?>
            <tr data-jml="<?= $jml ?>">
              <td style="text-align:center;color:#9CA3AF;font-size:.8rem;" class="row-no"></td>
              <td style="font-weight:600;color:#111;">
                <span class="badge-cat"><?= htmlspecialchars($kat['nama']) ?></span>
              </td>
              <td style="color:#6B7280;font-size:.875rem;"><?= htmlspecialchars($kat['deskripsi'] ?: '—') ?></td>
              <td style="text-align:center;font-weight:700;color:var(--gold);"><?= $jml ?></td>
              <td style="text-align:center;">
                <div style="display:flex;gap:.4rem;justify-content:center;">
                  <a href="?tab=kategori&edit_kategori=<?= $kat['id'] ?>" class="action-btn btn-edit"><i class="fas fa-pen"></i> Edit</a>
                  <?php if ($jml == 0): ?>
                  <a href="?tab=kategori&hapus_kategori=<?= $kat['id'] ?>" onclick="return confirm('Hapus kategori <?= addslashes($kat['nama']) ?>?')" class="action-btn btn-del"><i class="fas fa-trash"></i> Hapus</a>
                  <?php else: ?>
                  <span class="action-btn" style="background:#F9FAFB;color:#D1D5DB;cursor:not-allowed;" title="Ada <?= $jml ?> makanan di kategori ini"><i class="fas fa-lock"></i></span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <div id="paginationKategori" style="display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.5rem;border-top:1px solid #F3F4F6;font-size:.8rem;color:#6B7280;flex-wrap:wrap;gap:.5rem;">
        <span id="infoKategori"></span>
        <div id="pagesKategori" style="display:flex;gap:.3rem;"></div>
      </div>
      <div style="padding:.9rem 1.5rem;border-top:1px solid #F3F4F6;font-size:.78rem;color:#9CA3AF;">
        <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
        Kategori yang memiliki makanan tidak dapat dihapus. Hapus atau pindahkan makanan terlebih dahulu.
      </div>
    </div>

    <!-- ══ TENTANG KAMI ══ -->
    <?php elseif ($tab === 'tentang'): ?>

    <?php
    $tk_meta = [
      'hero'   => ['label'=>'Hero / Banner Utama',  'emoji'=>'🦸', 'accent'=>'rgba(245,166,35,.13)', 'desc'=>'Judul besar & tagline halaman Tentang Kami', 'judul_ph'=>'contoh: Mengenal Warisan Kuliner Nusantara', 'konten_ph'=>'contoh: Platform terlengkap untuk menemukan & melestarikan resep tradisional Indonesia.', 'rows'=>3],
      'tentang'=> ['label'=>'Bagian Tentang Kami',  'emoji'=>'📖', 'accent'=>'rgba(59,130,246,.13)',  'desc'=>'Paragraf deskripsi utama platform',            'judul_ph'=>'contoh: Tentang Jejak Rasa',              'konten_ph'=>'contoh: Jejak Rasa adalah platform digital yang hadir untuk mendokumentasikan kekayaan kuliner Nusantara...', 'rows'=>5],
      'visi'   => ['label'=>'Visi',                 'emoji'=>'🎯', 'accent'=>'rgba(16,185,129,.13)',  'desc'=>'Pernyataan visi jangka panjang platform',     'judul_ph'=>'contoh: Visi Kami',                      'konten_ph'=>'contoh: Menjadi platform kuliner tradisional Indonesia terpercaya dan terlengkap.', 'rows'=>3],
      'misi'   => ['label'=>'Misi',                 'emoji'=>'🚀', 'accent'=>'rgba(139,92,246,.13)',  'desc'=>'Poin-poin misi — satu misi per baris',        'judul_ph'=>'contoh: Misi Kami',                      'konten_ph'=>"contoh:\nMendokumentasikan resep dari seluruh Nusantara.\nMemperkenalkan kuliner kepada generasi muda.", 'rows'=>5],
      'nilai1' => ['label'=>'Nilai 1',              'emoji'=>'💛', 'accent'=>'rgba(245,166,35,.13)', 'desc'=>'Nilai inti pertama platform',                 'judul_ph'=>'contoh: Keaslian',                       'konten_ph'=>'contoh: Kami berkomitmen menyajikan resep yang autentik bersumber dari komunitas lokal.', 'rows'=>3],
      'nilai2' => ['label'=>'Nilai 2',              'emoji'=>'🌱', 'accent'=>'rgba(16,185,129,.13)',  'desc'=>'Nilai inti kedua platform',                   'judul_ph'=>'contoh: Keberlanjutan',                  'konten_ph'=>'contoh: Melestarikan warisan kuliner untuk generasi mendatang adalah inti dari langkah kami.', 'rows'=>3],
      'nilai3' => ['label'=>'Nilai 3',              'emoji'=>'🤝', 'accent'=>'rgba(59,130,246,.13)',  'desc'=>'Nilai inti ketiga platform',                  'judul_ph'=>'contoh: Komunitas',                      'konten_ph'=>'contoh: Bersama komunitas pecinta kuliner di seluruh Indonesia, kami tumbuh dan berbagi.', 'rows'=>3],
    ];
    ?>

    <form action="?tab=tentang" method="POST" id="formTentang">

    <?php foreach ($tk_meta as $bagian => $meta):
      $td      = $tentang_data[$bagian] ?? ['judul'=>'','konten'=>'','updated_at'=>null];
      $judul   = htmlspecialchars($td['judul']  ?? '');
      $konten  = htmlspecialchars($td['konten'] ?? '');
      $isFilled = trim($td['judul'] ?? '') !== '' || trim($td['konten'] ?? '') !== '';
      $updAt   = $td['updated_at'] ?? null;
    ?>

    <div class="card" style="margin-bottom:1rem;border:2px solid <?= $isFilled ? 'rgba(245,166,35,.3)' : 'transparent' ?>;transition:border-color .2s;" id="tkCard_<?= $bagian ?>">

      <!-- Header Toggle — mirip panel Edit Makanan -->
      <div class="form-toggle-header" onclick="tkToggle('tkBody_<?= $bagian ?>',this,'tkChv_<?= $bagian ?>')" style="padding:1.1rem 1.5rem;">
        <div style="display:flex;align-items:center;gap:.9rem;">
          <!-- Badge icon berwarna -->
          <div style="width:38px;height:38px;border-radius:10px;background:<?= $meta['accent'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
            <?= $meta['emoji'] ?>
          </div>
          <div>
            <h3 style="font-weight:700;color:#111;font-size:.95rem;margin:0;"><?= $meta['label'] ?></h3>
            <div style="font-size:.75rem;color:#9CA3AF;margin-top:.1rem;"><?= $meta['desc'] ?></div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;">
          <?php if ($isFilled && $judul): ?>
            <span style="font-size:.72rem;font-weight:600;padding:.2rem .75rem;border-radius:50px;background:rgba(245,166,35,.12);color:var(--gold-dark);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= $judul ?>"><?= $judul ?></span>
          <?php elseif (!$isFilled): ?>
            <span style="font-size:.72rem;color:#D1D5DB;font-style:italic;">Belum diisi</span>
          <?php endif; ?>
          <i class="fas fa-chevron-down toggle-icon" id="tkChv_<?= $bagian ?>"></i>
        </div>
      </div>

      <!-- Form Body -->
      <div class="form-toggle-body" id="tkBody_<?= $bagian ?>">
        <div class="form-grid">

          <!-- Judul -->
          <div class="form-group">
            <label>Judul <span style="font-weight:400;color:#aaa;">— heading bagian</span></label>
            <input
              type="text"
              name="tentang[<?= $bagian ?>][judul]"
              class="form-input"
              value="<?= $judul ?>"
              placeholder="<?= htmlspecialchars($meta['judul_ph']) ?>"
              oninput="tkRefreshPill('<?= $bagian ?>',this.value)"
            >
          </div>

          <!-- Info terakhir diperbarui -->
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <div style="width:100%;padding-bottom:.05rem;">
              <label style="color:transparent;user-select:none;font-size:.8rem;">–</label>
              <?php if ($updAt): ?>
              <div style="display:inline-flex;align-items:center;gap:.4rem;font-size:.75rem;color:#9CA3AF;">
                <i class="fas fa-clock" style="color:#D1D5DB;"></i>
                Diperbarui: <strong style="color:#6B7280;"><?= date('d M Y, H:i', strtotime($updAt)) ?></strong>
              </div>
              <?php else: ?>
              <div style="font-size:.75rem;color:#D1D5DB;font-style:italic;display:flex;align-items:center;gap:.35rem;">
                <i class="fas fa-circle" style="font-size:.45rem;"></i> Belum pernah disimpan
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Konten (full width) -->
          <div class="form-group form-full">
            <label>Konten</label>
            <textarea
              name="tentang[<?= $bagian ?>][konten]"
              rows="<?= $meta['rows'] ?>"
              class="form-input"
              placeholder="<?= htmlspecialchars($meta['konten_ph']) ?>"
              oninput="tkCount(this,'tkCnt_<?= $bagian ?>')"
              id="tkTxt_<?= $bagian ?>"
            ><?= $konten ?></textarea>
            <div style="font-size:.72rem;color:#9CA3AF;text-align:right;margin-top:.25rem;" id="tkCnt_<?= $bagian ?>">
              <?= mb_strlen($td['konten'] ?? '') ?> karakter
            </div>
          </div>

        </div><!-- /.form-grid -->
      </div><!-- /.form-toggle-body -->

    </div><!-- /.card -->
    <?php endforeach; ?>

    <!-- Action Bar -->
    <div class="card" style="margin-top:.5rem;">
      <div style="padding:1.2rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
        <div style="font-size:.8rem;color:#9CA3AF;display:flex;align-items:center;gap:.5rem;">
          <i class="fas fa-info-circle" style="color:#D1D5DB;"></i>
          Klik bagian di atas untuk membuka & edit, lalu simpan semua sekaligus.
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
          <button type="button" onclick="tkBukaSmua()" class="btn-secondary">
            <i class="fas fa-expand-alt" style="margin-right:.4rem;"></i>Buka Semua
          </button>
          <a href="tentangkami.php" target="_blank" class="btn-secondary">
            <i class="fas fa-eye" style="margin-right:.4rem;"></i>Lihat Halaman
          </a>
          <button type="submit" name="simpan_tentang" class="btn-primary">
            <i class="fas fa-save" style="margin-right:.4rem;"></i>Simpan Semua Perubahan
          </button>
        </div>
      </div>
    </div>

    </form>

    <script>
    // Toggle buka/tutup
    function tkToggle(bodyId, hdrEl, chvId) {
      var body = document.getElementById(bodyId);
      var chv  = document.getElementById(chvId);
      if (!body) return;
      var open = body.classList.contains('open');
      body.classList.toggle('open', !open);
      if (chv) chv.classList.toggle('rotated', !open);
      if (hdrEl) hdrEl.classList.toggle('open', !open);
    }
    // Buka semua
    function tkBukaSmua() {
      <?php foreach (array_keys($tk_meta) as $b): ?>
      document.getElementById('tkBody_<?= $b ?>')?.classList.add('open');
      document.getElementById('tkChv_<?= $b ?>')?.classList.add('rotated');
      <?php endforeach; ?>
    }
    // Update pill judul di header
    function tkRefreshPill(bagian, val) {
      var card = document.getElementById('tkCard_' + bagian);
      if (!card) return;
      var pill = card.querySelector('.tk-pill');
      var empty = card.querySelector('.tk-empty');
      if (!pill) {
        pill = document.createElement('span');
        pill.className = 'tk-pill';
        pill.style.cssText = 'font-size:.72rem;font-weight:600;padding:.2rem .75rem;border-radius:50px;background:rgba(245,166,35,.12);color:var(--gold-dark);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
        var right = card.querySelector('.form-toggle-header > div:last-child');
        if (right) right.prepend(pill);
      }
      if (val.trim()) {
        pill.style.display = '';
        pill.textContent = val;
        if (empty) empty.style.display = 'none';
        card.style.borderColor = 'rgba(245,166,35,.3)';
      } else {
        pill.style.display = 'none';
        if (empty) empty.style.display = '';
        card.style.borderColor = 'transparent';
      }
    }
    // Hitung karakter
    function tkCount(el, cntId) {
      var el2 = document.getElementById(cntId);
      if (el2) el2.textContent = el.value.length + ' karakter';
    }
    // Init: char count + buka semua jika ada yg kosong
    document.addEventListener('DOMContentLoaded', function () {
      <?php foreach (array_keys($tk_meta) as $b): ?>
      (function(){
        var tx = document.getElementById('tkTxt_<?= $b ?>');
        if (tx) tkCount(tx, 'tkCnt_<?= $b ?>');
      })();
      <?php endforeach; ?>

      // Buka semua jika ada yang belum diisi
      var anyEmpty = document.querySelectorAll('.tk-empty');
      var hasEmpty = Array.from(anyEmpty).some(function(e){ return e.style.display !== 'none'; });
      if (hasEmpty) tkBukaSmua();

      // Guard perubahan belum disimpan
      var changed = false;
      document.getElementById('formTentang').addEventListener('input', function(){ changed = true; });
      window.addEventListener('beforeunload', function(e){ if(changed){ e.preventDefault(); e.returnValue=''; } });
      document.getElementById('formTentang').addEventListener('submit', function(){ changed = false; });
    });
    </script>

    <?php endif; ?>
  </div>
</div>

<script>
/* ═══ FORM TOGGLE ═══ */
function toggleForm(id, header) {
  const body = document.getElementById(id);
  const icon = header ? header.querySelector('.toggle-icon') : null;
  if (!body) return;
  body.classList.toggle('open');
  if (icon) icon.classList.toggle('rotated');
}

/* ═══ FAVORIT CHART ═══ */
<?php if (!empty($fav_chart_labels)): ?>
(function() {
  const labels = <?= json_encode($fav_chart_labels) ?>;
  const data   = <?= json_encode($fav_chart_data) ?>;
  const colors = ['#F5A623','#E07B00','#FFD580','#D4891A','#FFAE42','#B36200','#FFC266','#8B4513'];

  const ctxBar = document.getElementById('chartFavBar');
  if (ctxBar) {
    new Chart(ctxBar, {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Favorit', data, backgroundColor: colors, borderRadius: 8, borderSkipped: false }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` ${c.raw} favorit` } } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: '#F3F4F6' } },
          x: { ticks: { font: { size: 10 }, maxRotation: 30 }, grid: { display: false } }
        }
      }
    });
  }
  const ctxDn = document.getElementById('chartFavDoughnut');
  if (ctxDn) {
    new Chart(ctxDn, {
      type: 'doughnut',
      data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: { legend: { position: 'right', labels: { font: { size: 11 }, padding: 10, boxWidth: 14 } }, tooltip: { callbacks: { label: c => ` ${c.raw} favorit` } } }
      }
    });
  }
})();
<?php endif; ?>

/* ═══════════════════════════════════════════════════
   TABLE ENGINE — numbering, sort, filter, pagination
   ═══════════════════════════════════════════════════ */

const tables = {
  makanan: {
    tbodyId    : 'tableMakanan',
    searchId   : 'searchMakanan',
    pageSizeId : 'pageSizeMakanan',
    infoId     : 'infoMakanan',
    pagesId    : 'pagesMakanan',
    filterSelectors: [
      { id: 'filterKategoriMakanan', colIndex: 3 }
    ],
    currentPage : 1,
    sortCol     : null,
    sortDir     : 'asc',
  },
  users: {
    tbodyId    : 'tableUsers',
    searchId   : 'searchUsers',
    pageSizeId : 'pageSizeUsers',
    infoId     : 'infoUsers',
    pagesId    : 'pagesUsers',
    filterSelectors: [
      { id: 'filterRoleUsers', attr: 'data-role' }
    ],
    currentPage : 1,
    sortCol     : null,
    sortDir     : 'asc',
  },
  ulasan: {
    tbodyId    : 'tableUlasan',
    searchId   : 'searchUlasan',
    pageSizeId : 'pageSizeUlasan',
    infoId     : 'infoUlasan',
    pagesId    : 'pagesUlasan',
    filterSelectors: [
      { id: 'filterRatingUlasan', attr: 'data-rating' }
    ],
    currentPage : 1,
    sortCol     : null,
    sortDir     : 'asc',
  },
  log: {
    tbodyId    : 'tableLog',
    searchId   : 'searchLog',
    pageSizeId : 'pageSizeLog',
    infoId     : 'infoLog',
    pagesId    : 'pagesLog',
    filterSelectors: [
      { id: 'filterAksiLog', attr: 'data-aksi' }
    ],
    currentPage : 1,
    sortCol     : null,
    sortDir     : 'asc',
  },
  pesan: {
    tbodyId    : 'tablePesan',
    searchId   : 'searchPesan',
    pageSizeId : 'pageSizePesan',
    infoId     : 'infoPesan',
    pagesId    : 'pagesPesan',
    filterSelectors: [
      { id: 'filterStatusPesan', attr: 'data-status' }
    ],
    currentPage : 1,
    sortCol     : null,
    sortDir     : 'asc',
  },
  kategori: {
    tbodyId    : 'tableKategori',
    searchId   : 'searchKategori',
    pageSizeId : 'pageSizeKategori',
    infoId     : 'infoKategori',
    pagesId    : 'pagesKategori',
    filterSelectors: [],
    currentPage : 1,
    sortCol     : null,
    sortDir     : 'asc',
  },
};

function getRows(name) {
  const tbody = document.getElementById(tables[name].tbodyId);
  return tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
}

function applyTable(name) {
  const cfg    = tables[name];
  const tbody  = document.getElementById(cfg.tbodyId);
  if (!tbody) return;

  const allRows  = Array.from(tbody.querySelectorAll('tr'));
  const keyword  = (document.getElementById(cfg.searchId)?.value || '').toLowerCase();
  const pageSize = parseInt(document.getElementById(cfg.pageSizeId)?.value || '10');

  // ── FILTER ──
  let visible = allRows.filter(row => {
    // text search
    if (keyword && !row.innerText.toLowerCase().includes(keyword)) return false;
    // dropdown filters
    for (const f of cfg.filterSelectors) {
      const el = document.getElementById(f.id);
      if (!el || !el.value) continue;
      if (f.attr) {
        if (row.getAttribute(f.attr) !== el.value) return false;
      } else if (f.colIndex !== undefined) {
        const cell = row.cells[f.colIndex];
        if (!cell || !cell.innerText.trim().toLowerCase().includes(el.value.toLowerCase())) return false;
      }
    }
    return true;
  });

  // ── SORT ──
  if (cfg.sortCol !== null && cfg.sortCol >= 0) {
    visible.sort((a, b) => {
      const ca = a.cells[cfg.sortCol]?.innerText.trim() || '';
      const cb = b.cells[cfg.sortCol]?.innerText.trim() || '';
      const na = parseFloat(ca), nb = parseFloat(cb);
      const cmp = (!isNaN(na) && !isNaN(nb)) ? na - nb : ca.localeCompare(cb, 'id');
      return cfg.sortDir === 'asc' ? cmp : -cmp;
    });
    visible.forEach(r => tbody.appendChild(r));
    allRows.filter(r => !visible.includes(r)).forEach(r => tbody.appendChild(r));
  }

  // ── PAGINATION ──
  const total = visible.length;
  const perPage = pageSize === 0 ? total : pageSize;
  const totalPages = perPage > 0 ? Math.ceil(total / perPage) : 1;
  if (cfg.currentPage > totalPages) cfg.currentPage = 1;

  const start = (cfg.currentPage - 1) * perPage;
  const end   = pageSize === 0 ? total : Math.min(start + perPage, total);

  // hide all, show page slice
  allRows.forEach(r => r.style.display = 'none');
  visible.forEach((r, i) => {
    r.style.display = (i >= start && i < end) ? '' : 'none';
  });

  // re-number ALL visible rows so sorting updates numbers correctly
  visible.forEach((r, i) => {
    const noCell = r.querySelector('.row-no');
    if (noCell) noCell.textContent = i + 1;
  });

  // ── INFO TEXT ──
  const infoEl = document.getElementById(cfg.infoId);
  if (infoEl) {
    infoEl.textContent = total === 0
      ? 'Tidak ada data'
      : `Menampilkan ${start + 1}–${end} dari ${total} data`;
  }

  // ── PAGE BUTTONS ──
  const pagesEl = document.getElementById(cfg.pagesId);
  if (pagesEl) {
    pagesEl.innerHTML = '';
    if (totalPages <= 1) return;

    const btnStyle = (active) =>
      `display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;padding:0 .5rem;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid ${active ? 'var(--gold)' : '#E5E7EB'};background:${active ? 'var(--gold)' : '#fff'};color:${active ? '#fff' : '#374151'};transition:.15s;`;

    // Prev
    if (cfg.currentPage > 1) {
      const b = document.createElement('button');
      b.innerHTML = '‹'; b.style.cssText = btnStyle(false);
      b.onclick = () => { cfg.currentPage--; applyTable(name); };
      pagesEl.appendChild(b);
    }

    // Page numbers (max 7 buttons with ellipsis)
    let pages = [];
    if (totalPages <= 7) {
      for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
      const cur = cfg.currentPage;
      pages = [1];
      if (cur > 3) pages.push('…');
      for (let i = Math.max(2, cur - 1); i <= Math.min(totalPages - 1, cur + 1); i++) pages.push(i);
      if (cur < totalPages - 2) pages.push('…');
      pages.push(totalPages);
    }

    pages.forEach(p => {
      const b = document.createElement('button');
      b.textContent = p;
      if (p === '…') { b.style.cssText = btnStyle(false); b.style.cursor = 'default'; }
      else {
        b.style.cssText = btnStyle(p === cfg.currentPage);
        b.onclick = () => { cfg.currentPage = p; applyTable(name); };
      }
      pagesEl.appendChild(b);
    });

    // Next
    if (cfg.currentPage < totalPages) {
      const b = document.createElement('button');
      b.innerHTML = '›'; b.style.cssText = btnStyle(false);
      b.onclick = () => { cfg.currentPage++; applyTable(name); };
      pagesEl.appendChild(b);
    }
  }
}


// ── WIRE UP SORT HEADERS ──
document.querySelectorAll('th[data-sort]').forEach(th => {
  th.addEventListener('click', function() {
    const name = this.dataset.table;
    const cfg  = tables[name];
    if (!cfg) return;
    const ths  = Array.from(this.closest('tr').querySelectorAll('th'));
    const col  = ths.indexOf(this);
    if (this.dataset.sort === 'no') {
      if (cfg.sortCol === -1) cfg.sortDir = cfg.sortDir === 'asc' ? 'desc' : 'asc';
      else { cfg.sortCol = -1; cfg.sortDir = 'asc'; }
    } else {
      if (cfg.sortCol === col) cfg.sortDir = cfg.sortDir === 'asc' ? 'desc' : 'asc';
      else { cfg.sortCol = col; cfg.sortDir = 'asc'; }
    }
    // update sort icons
    document.querySelectorAll(`th[data-table="${name}"] .sort-icon`).forEach(i => i.textContent = '↕');
    this.querySelector('.sort-icon').textContent = cfg.sortDir === 'asc' ? '↑' : '↓';
    cfg.currentPage = 1;
    applyTable(name);
  });
});

// ── INIT origIndex on each row ──
['makanan','users','ulasan','log','pesan','kategori'].forEach(name => {
  const cfg2 = tables[name];
  const tbody2 = document.getElementById(cfg2.tbodyId);
  if (!tbody2) return;
  Array.from(tbody2.querySelectorAll('tr')).forEach((r, i) => r.dataset.origIndex = i);
});

// ── WIRE UP SEARCH / FILTER / PAGE SIZE ──
['makanan','users','ulasan','log','pesan','kategori'].forEach(name => {
  const cfg = tables[name];

  const si = document.getElementById(cfg.searchId);
  if (si) si.addEventListener('input', () => { cfg.currentPage = 1; applyTable(name); });

  const ps = document.getElementById(cfg.pageSizeId);
  if (ps) ps.addEventListener('change', () => { cfg.currentPage = 1; applyTable(name); });

  cfg.filterSelectors.forEach(f => {
    const el = document.getElementById(f.id);
    if (el) el.addEventListener('change', () => { cfg.currentPage = 1; applyTable(name); });
  });

  // initial render
  applyTable(name);
});
</script>
</body>
</html>