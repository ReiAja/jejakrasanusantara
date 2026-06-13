<?php
include 'koneksi.php';

// ── Proses kirim pesan kontak ──────────────────────────────
$pesan_terkirim = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_pesan'])) {
    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS pesan_kontak (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        pesan TEXT NOT NULL,
        sudah_dibaca TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $nama  = mysqli_real_escape_string($koneksi, trim($_POST['nama']));
    $email = mysqli_real_escape_string($koneksi, trim($_POST['email']));
    $pesan = mysqli_real_escape_string($koneksi, trim($_POST['pesan']));
    if (!empty($nama) && !empty($email) && !empty($pesan)) {
        mysqli_query($koneksi, "INSERT INTO pesan_kontak (nama, email, pesan) VALUES ('$nama','$email','$pesan')");
        $pesan_terkirim = true;
    }
}

// ── Ambil konten dari database tentang_kami ───────────────
// Pastikan tabel ada & terisi default
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS tentang_kami (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bagian VARCHAR(50) NOT NULL UNIQUE,
    judul VARCHAR(255) DEFAULT NULL,
    konten TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$bagian_default = ['hero','tentang','visi','misi','nilai1','nilai2','nilai3'];
foreach ($bagian_default as $b) {
    mysqli_query($koneksi, "INSERT IGNORE INTO tentang_kami (bagian) VALUES ('$b')");
}

$tk = [];
$res = mysqli_query($koneksi, "SELECT * FROM tentang_kami");
while ($row = mysqli_fetch_assoc($res)) {
    $tk[$row['bagian']] = $row;
}

// Helper: ambil nilai dengan fallback
function tk_judul($tk, $bagian, $default = '') {
    return htmlspecialchars($tk[$bagian]['judul'] ?? $default);
}
function tk_konten($tk, $bagian, $default = '') {
    return htmlspecialchars($tk[$bagian]['konten'] ?? $default);
}
// Konten misi bisa multi-baris → tampilkan sebagai <li>
function tk_misi_list($tk) {
    $raw = $tk['misi']['konten'] ?? '';
    if (empty($raw)) {
        $items = [
            'Menghubungkan pecinta kuliner dengan khas dari berbagai daerah',
            'Memberikan ruang bagi UMKM kuliner untuk berkembang',
            'Menyediakan ulasan dan rekomendasi autentik',
            'Mendokumentasikan resep tradisional agar tak punah',
        ];
    } else {
        $items = array_filter(array_map('trim', explode("|", $raw)));
    }
    $out = '';
    foreach ($items as $item) {
        $out .= '<li>' . htmlspecialchars($item) . '</li>';
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="https://jejakrasa.site.je/gambar/logojr.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tentang Kami – Jejak Rasa Nusantara</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --gold: #F5A623;
      --gold-dark: #D4891A;
      --red: #C0392B;
      --dark: #1A1208;
      --cream: #FDF8F0;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:var(--cream); color:var(--dark); }

    /* NAVBAR */
    .navbar {
      position:sticky; top:0; z-index:100;
      background:rgba(253,248,240,0.92);
      backdrop-filter:blur(16px);
      border-bottom:1px solid rgba(245,166,35,0.15);
      padding:0 2rem;
    }
    .nav-inner { max-width:1280px; margin:auto; display:flex; align-items:center; justify-content:space-between; height:68px; }
    .nav-logo img { height:52px; }
    .nav-links { display:flex; gap:2.5rem; align-items:center; }
    .nav-links a { font-weight:500; color:#555; text-decoration:none; font-size:.95rem; position:relative; padding-bottom:3px; transition:.2s; }
    .nav-links a::after { content:''; position:absolute; left:0; bottom:0; width:0; height:2px; background:var(--gold); border-radius:2px; transition:.3s; }
    .nav-links a:hover,
    .nav-links a.active { color:var(--gold); }
    .nav-links a:hover::after,
    .nav-links a.active::after { width:100%; }
    .btn-masuk { background:var(--gold)!important; color:#fff!important; padding:.5rem 1.4rem; border-radius:10px; font-weight:600!important; font-size:.9rem; transition:.2s!important; }
    .btn-masuk:hover { background:var(--gold-dark)!important; }
    .btn-masuk::after { display:none!important; }
    .profile-wrap { position:relative; }
    .profile-wrap img { width:38px; height:38px; border-radius:50%; object-fit:cover; border:2.5px solid var(--gold); cursor:pointer; }
    .profile-menu { display:none; position:absolute; right:0; top:calc(100% + 10px); background:#fff; box-shadow:0 12px 40px rgba(0,0,0,0.12); border-radius:14px; overflow:hidden; min-width:168px; border:1px solid #f0e8d8; }
    .profile-menu.open { display:block; }
    .profile-menu a { display:block; padding:.75rem 1.1rem; font-size:.875rem; color:#333; text-decoration:none; }
    .profile-menu a:hover { background:#FFF8EC; }
    .hamburger { display:none; background:none; border:none; font-size:1.4rem; color:#555; cursor:pointer; }
    .mobile-menu { display:none; position:absolute; top:68px; left:0; right:0; background:#fff; border-bottom:1px solid #f0e8d8; padding:1.2rem 2rem; z-index:99; }
    .mobile-menu.open { display:flex; flex-direction:column; gap:1rem; }
    .mobile-menu a { font-weight:500; color:#444; text-decoration:none; padding:.4rem 0; border-left: 3px solid transparent; padding-left: .75rem; transition:.2s; }
    .mobile-menu a:hover { color:var(--gold); border-left-color: var(--gold); }
    .mobile-menu a.active { color:var(--gold); font-weight:700; border-left-color: var(--gold); background: rgba(245,166,35,.07); border-radius: 0 8px 8px 0; }

    /* HERO */
    .hero {
      background:linear-gradient(135deg, #1A1208 0%, #2C1D0A 100%);
      padding:5rem 2rem;
      text-align:center;
      position:relative; overflow:hidden;
    }
    .hero h1 { font-family:'Playfair Display',serif; font-size:clamp(2.2rem,5vw,3.8rem); font-weight:900; color:#fff; line-height:1.15; }
    .hero h1 span { color:var(--gold); }
    .hero p { color:rgba(255,255,255,.55); max-width:560px; margin:.8rem auto 0; line-height:1.75; font-size:1.05rem; }

    /* SECTION */
    .section { max-width:1100px; margin:0 auto; padding:5rem 2rem; }
    .about-grid { display:grid; grid-template-columns:1fr 1fr; gap:4rem; align-items:center; }
    .about-text h2 { font-family:'Playfair Display',serif; font-size:2rem; font-weight:800; margin-bottom:1.25rem; }
    .about-text h2 span { color:var(--gold); }
    .about-text p { color:#555; line-height:1.85; font-size:1rem; }
    .about-img { border-radius:24px; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,0.15); }
    .about-img img { width:100%; height:380px; object-fit:cover; display:block; }

    /* MISI & VISI */
    .mv-wrap { background:linear-gradient(135deg, #1A1208 0%, #2C1D0A 100%); padding:5rem 2rem; }
    .mv-inner { max-width:1100px; margin:auto; }
    .mv-title { font-family:'Playfair Display',serif; font-size:2rem; font-weight:800; color:#fff; text-align:center; margin-bottom:3rem; }
    .mv-title span { color:var(--gold); }
    .mv-grid { display:grid; grid-template-columns:1fr 1fr; gap:2rem; }
    .mv-card { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); border-radius:20px; padding:2.5rem; }
    .mv-card-icon { font-size:2.5rem; margin-bottom:1.2rem; }
    .mv-card h3 { font-family:'Playfair Display',serif; color:#fff; font-size:1.4rem; font-weight:700; margin-bottom:1rem; }
    .mv-card p, .mv-card li { color:rgba(255,255,255,.6); font-size:.95rem; line-height:1.8; }
    .mv-card ul { list-style:none; }
    .mv-card li { padding:.3rem 0; display:flex; align-items:flex-start; gap:.6rem; }
    .mv-card li::before { content:'✦'; color:var(--gold); font-size:.7rem; margin-top:.35rem; flex-shrink:0; }

    /* VALUES */
    .values-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem; }
    .value-card { background:#fff; border-radius:20px; padding:2rem; box-shadow:0 4px 20px rgba(0,0,0,0.07); border:1px solid #f0e8d8; text-align:center; transition:.3s; }
    .value-card:hover { transform:translateY(-4px); box-shadow:0 12px 40px rgba(0,0,0,0.12); }
    .value-icon { width:64px; height:64px; border-radius:16px; background:linear-gradient(135deg, rgba(245,166,35,.15), rgba(245,166,35,.05)); display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 1.2rem; }
    .value-card h4 { font-family:'Playfair Display',serif; font-size:1.15rem; font-weight:700; margin-bottom:.5rem; }
    .value-card p { color:#777; font-size:.88rem; line-height:1.7; }

    /* CONTACT */
    .contact-wrap { background:#fff; }
    .contact-inner { max-width:700px; margin:0 auto; padding:5rem 2rem; text-align:center; }
    .contact-inner h2 { font-family:'Playfair Display',serif; font-size:2rem; font-weight:800; margin-bottom:.5rem; }
    .contact-inner h2 span { color:var(--gold); }
    .contact-inner > p { color:#777; margin-bottom:2.5rem; font-size:.95rem; }
    .contact-form { text-align:left; }
    .form-group { margin-bottom:1.2rem; }
    .form-group label { display:block; font-weight:600; font-size:.875rem; color:#444; margin-bottom:.5rem; }
    .form-input { width:100%; padding:.9rem 1.1rem; border:2px solid #E8E0D5; border-radius:12px; font-family:'DM Sans',sans-serif; font-size:.95rem; color:var(--dark); background:#FAFAF8; transition:.2s; }
    .form-input:focus { outline:none; border-color:var(--gold); background:#fff; }
    .form-submit { width:100%; margin-top:.5rem; padding:1rem; background:var(--gold); color:#fff; border:none; border-radius:12px; font-size:1rem; font-weight:700; cursor:pointer; transition:.2s; font-family:'DM Sans',sans-serif; }
    .form-submit:hover { background:var(--gold-dark); }

    /* POPUP */
    .popup-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; align-items:center; justify-content:center; }
    .popup-overlay.show { display:flex; }
    .popup-box { background:#fff; padding:2.5rem; border-radius:20px; text-align:center; max-width:380px; width:90%; box-shadow:0 24px 80px rgba(0,0,0,.2); }
    .popup-box .icon { font-size:3rem; margin-bottom:1rem; }
    .popup-box h3 { font-family:'Playfair Display',serif; font-size:1.5rem; font-weight:800; margin-bottom:.5rem; }
    .popup-box p { color:#777; font-size:.9rem; margin-bottom:1.5rem; }
    .popup-close { background:var(--gold); color:#fff; border:none; padding:.7rem 2rem; border-radius:10px; font-weight:700; cursor:pointer; font-family:'DM Sans',sans-serif; }

    /* FOOTER */
    footer { background:#1A1208; padding:3rem 2rem 1.5rem; margin-top:0; }
    .footer-inner { max-width:1280px; margin:auto; display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:2rem; padding-bottom:2rem; border-bottom:1px solid rgba(255,255,255,.08); }
    .footer-brand p { color:rgba(255,255,255,.45); font-size:.875rem; margin-top:.75rem; line-height:1.7; max-width:220px; }
    .footer-col h5 { font-family:'Playfair Display',serif; color:#fff; font-size:1rem; margin-bottom:1rem; }
    .footer-col a { display:block; color:rgba(255,255,255,.45); font-size:.875rem; text-decoration:none; margin-bottom:.5rem; transition:.2s; }
    .footer-col a:hover { color:var(--gold); }
    .footer-social { display:flex; gap:.75rem; margin-top:.5rem; }
    .footer-social a { width:36px; height:36px; border-radius:50%; border:1px solid rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.5); font-size:.85rem; transition:.2s; }
    .footer-social a:hover { background:var(--gold); border-color:var(--gold); color:#fff; }
    .footer-copy { max-width:1280px; margin:1.2rem auto 0; text-align:center; color:rgba(255,255,255,.3); font-size:.8rem; }

    @media(max-width:768px) {
      .nav-links { display:none; }
      .hamburger { display:block; }
      .about-grid, .mv-grid { grid-template-columns:1fr; }
      .values-grid { grid-template-columns:1fr; }
      .footer-inner { grid-template-columns:1fr 1fr; }
    }
    @media(max-width:480px) {
      .footer-inner { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="nav-inner">
    <a class="nav-logo" href="index.php"><img src="https://jejakrasa.site.je/gambar/logoweb.svg" alt="Logo"></a>
    <div class="nav-links">
      <a href="index.php">Beranda</a>
      <a href="filter.php">Filter</a>
      <a href="tentangkami.php" class="active">Tentang Kami</a>
    </div>
    <div class="nav-links">
      <?php if (isLogin()): ?>
        <?php if (isAdmin()): ?>
          <a href="dashboard.php">Admin</a>
        <?php endif; ?>
        <div class="profile-wrap" id="profileWrap">
          <img src="<?= $_SESSION['foto'] ? 'uploads/'.$_SESSION['foto'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png' ?>" id="profileBtn">
          <div class="profile-menu" id="profileMenu">
            <a href="profile.php"><i class="fas fa-user" style="margin-right:.5rem;color:var(--gold);"></i>Profile</a>
            <hr style="border-color:#f5eee0;">
            <a href="logout.php" style="color:#e53e3e;"><i class="fas fa-sign-out-alt" style="margin-right:.5rem;"></i>Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="btn-masuk">Masuk</a>
      <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
  </div>
  <div class="mobile-menu" id="mobileMenu">
    <a href="index.php">Beranda</a>
    <a href="filter.php">Filter</a>
    <a href="tentangkami.php" class="active">Tentang Kami</a>
    <?php if (isLogin()): ?>
      <a href="profile.php">Profil Saya</a>
      <a href="logout.php" style="color:#e53e3e;">Logout</a>
    <?php else: ?>
      <a href="login.php" style="background:var(--gold);color:#fff;padding:.6rem 1rem;border-radius:10px;font-weight:600;text-align:center;">Masuk</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO — data dari DB -->
<section class="hero">
  <?php
    $heroJudul  = $tk['hero']['judul']  ?? 'Melestarikan Warisan Kuliner Bangsa';
    $heroKonten = $tk['hero']['konten'] ?? 'Kami percaya setiap sajian tradisional menyimpan cerita, kenangan, dan identitas budaya yang tak ternilai.';
    // Highlight kata pertama dalam judul sebagai <span>
    $heroWords = explode(' ', $heroJudul, 2);
    $heroDisplay = count($heroWords) === 2
      ? htmlspecialchars($heroWords[0]) . ' <span>' . htmlspecialchars($heroWords[1]) . '</span>'
      : htmlspecialchars($heroJudul);
  ?>
  <h1><?= $heroDisplay ?></h1>
  <p><?= htmlspecialchars($heroKonten) ?></p>
</section>

<!-- TENTANG -->
<div class="section">
  <div class="about-grid">
    <div class="about-text">
      <?php
        $tentangJudul  = $tk['tentang']['judul']  ?? 'Selamat datang di Jejak Rasa Nusantara';
        $tentangKonten = $tk['tentang']['konten'] ?? 'Kami adalah platform digital yang didedikasikan untuk melestarikan kuliner tradisional Indonesia.';
        // Split judul pada kata terakhir untuk span
        $tWords = explode(' ', $tentangJudul);
        $lastWord = array_pop($tWords);
        $tentangDisplay = htmlspecialchars(implode(' ', $tWords)) . ' <span>' . htmlspecialchars($lastWord) . '</span>';
      ?>
      <h2><?= $tentangDisplay ?></h2>
      <?php foreach (explode("|", $tentangKonten) as $para): if (trim($para)): ?>
        <p style="margin-top:.8rem;"><?= htmlspecialchars(trim($para)) ?></p>
      <?php endif; endforeach; ?>
    </div>
    <div class="about-img">
      <img src="https://images.pexels.com/photos/28647406/pexels-photo-28647406.jpeg" alt="Kuliner Nusantara">
    </div>
  </div>
</div>

<!-- MISI & VISI -->
<div class="mv-wrap">
  <div class="mv-inner">
    <h2 class="mv-title">Visi dan <span>Misi</span> Kami</h2>
    <div class="mv-grid">
      <div class="mv-card">
        <div class="mv-card-icon">🎯</div>
        <h3><?= tk_judul($tk, 'visi', 'Visi') ?></h3>
        <p><?= tk_konten($tk, 'visi', 'Menjadi platform kuliner terdepan yang menghubungkan pecinta makanan dengan keanekaragaman cita rasa tradisional Indonesia.') ?></p>
      </div>
      <div class="mv-card">
        <div class="mv-card-icon">🚀</div>
        <h3><?= tk_judul($tk, 'misi', 'Misi') ?></h3>
        <ul>
          <?= tk_misi_list($tk) ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- VALUES -->
<div class="section">
  <h2 style="font-family:'Playfair Display',serif;font-size:2rem;font-weight:800;text-align:center;margin-bottom:2.5rem;">
    Nilai <span style="color:var(--gold);">yang Kami Pegang</span>
  </h2>
  <div class="values-grid">
    <?php
    $nilai_icons = ['nilai1' => '🤝', 'nilai2' => '🌱', 'nilai3' => '💛'];
    foreach (['nilai1','nilai2','nilai3'] as $n):
      $icon    = $nilai_icons[$n];
      $njudul  = $tk[$n]['judul']  ?? '';
      $nkonten = $tk[$n]['konten'] ?? '';
    ?>
    <div class="value-card">
      <div class="value-icon"><?= $icon ?></div>
      <h4><?= htmlspecialchars($njudul ?: 'Nilai') ?></h4>
      <p><?= htmlspecialchars($nkonten ?: '—') ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- KONTAK -->
<div class="contact-wrap">
  <div class="contact-inner">
    <h2>Hubungi <span>Kami</span></h2>
    <p>Kami terbuka untuk kolaborasi, saran, atau pertanyaan.<br>Kirim pesan dan kami akan segera merespons.</p>
    <form class="contact-form" method="POST" action="">
      <div class="form-group">
        <label>Nama Anda</label>
        <input type="text" name="nama" class="form-input" placeholder="Masukkan nama lengkap" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-input" placeholder="nama@email.com" required>
      </div>
      <div class="form-group">
        <label>Pesan</label>
        <textarea name="pesan" class="form-input" rows="4" placeholder="Tulis pesan Anda di sini..." required style="resize:none;"></textarea>
      </div>
      <button type="submit" name="kirim_pesan" class="form-submit">
        <i class="fas fa-paper-plane" style="margin-right:.5rem;"></i>Kirim Pesan
      </button>
    </form>
  </div>
</div>

<!-- POPUP SUKSES -->
<div class="popup-overlay <?= $pesan_terkirim ? 'show' : '' ?>" id="successPopup">
  <div class="popup-box">
    <div class="icon">✅</div>
    <h3>Pesan Terkirim!</h3>
    <p>Terima kasih sudah menghubungi kami. Kami akan segera merespons pesan Anda.</p>
    <button class="popup-close" onclick="document.getElementById('successPopup').classList.remove('show')">Tutup</button>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <div class="footer-brand">
      <div style="display:flex;align-items:center;gap:.8rem;">
        <img src="gambar/logoweb.svg" alt="Logo" style="height:48px;">
        <span style="font-weight:800;font-size:1.15rem;color:#fff;font-family:'Playfair Display',serif;">Jejak Rasa Nusantara</span>
      </div>
      <p>Platform digital pelestarian kuliner tradisional dari seluruh penjuru Indonesia.</p>
    </div>
    <div class="footer-col">
      <h5>Navigasi</h5>
      <a href="index.php">Beranda</a>
      <a href="filter.php">Filter</a>
      <a href="tentangkami.php">Tentang Kami</a>
    </div>
    <div class="footer-col">
      <h5>Legal</h5>
      <a href="#">Persyaratan Layanan</a>
      <a href="#">Kebijakan Privasi</a>
    </div>
    <div class="footer-col">
      <h5>Ikuti Kami</h5>
      <div class="footer-social">
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
      </div>
    </div>
  </div>
  <p class="footer-copy">&copy; 2025 Jejak Rasa Nusantara.</p>
</footer>

<script>
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  hamburger.addEventListener('click', () => mobileMenu.classList.toggle('open'));

  const profileBtn = document.getElementById('profileBtn');
  if (profileBtn) {
    profileBtn.addEventListener('click', () => document.getElementById('profileMenu').classList.toggle('open'));
    document.addEventListener('click', e => {
      if (!document.getElementById('profileWrap').contains(e.target))
        document.getElementById('profileMenu').classList.remove('open');
    });
  }

  <?php if ($pesan_terkirim): ?>
  setTimeout(() => document.getElementById('successPopup').classList.remove('show'), 4000);
  <?php endif; ?>
</script>
</body>
</html>