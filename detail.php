<?php
include 'koneksi.php';

// ── HANDLE TOGGLE FAVORIT (form POST biasa, tanpa AJAX) ──
if (isset($_POST['toggle_favorit']) && isLogin()) {
    $fav_makanan_id = (int)($_POST['makanan_id'] ?? 0);
    $fav_user_id    = (int)$_SESSION['user_id'];
    $fav_status     = 'error';
    if ($fav_makanan_id > 0) {
        $cek = mysqli_query($koneksi, "SELECT id FROM favorit WHERE user_id=$fav_user_id AND makanan_id=$fav_makanan_id");
        if (mysqli_num_rows($cek) > 0) {
            mysqli_query($koneksi, "DELETE FROM favorit WHERE user_id=$fav_user_id AND makanan_id=$fav_makanan_id");
            $fav_status = 'removed';
        } else {
            mysqli_query($koneksi, "INSERT INTO favorit (user_id, makanan_id) VALUES ($fav_user_id, $fav_makanan_id)");
            $fav_status = 'saved';
        }
    }
    $slug = isset($_POST['slug']) && $_POST['slug'] ? '/' . $_POST['slug'] : '/';
    header("Location: {$slug}?fav={$fav_status}");
    exit;
}

// ── URL SLUG: support ?nama=nasi-goreng ATAU fallback ?id=14 ──
function slugify($str) {
    $str = mb_strtolower(trim($str), 'UTF-8');
    $str = preg_replace('/\s+/', '-', $str);
    $str = preg_replace('/[^a-z0-9\-]/', '', $str);
    $str = preg_replace('/-+/', '-', $str);
    return $str;
}

$makanan = null;
if (isset($_GET['nama']) && $_GET['nama'] !== '') {
    $all = mysqli_query($koneksi, "SELECT * FROM makanan");
    while ($row = mysqli_fetch_assoc($all)) {
        if (slugify($row['nama']) === slugify($_GET['nama'])) {
            $makanan = $row;
            break;
        }
    }
} elseif (isset($_GET['id'])) {
    $id_fb = (int)$_GET['id'];
    $res_fb = mysqli_query($koneksi, "SELECT * FROM makanan WHERE id=$id_fb");
    $makanan = mysqli_fetch_assoc($res_fb);
    if ($makanan) {
        $slug = slugify($makanan['nama']);
        header("Location: /" . $slug);
        exit;
    }
}
if (!$makanan) { header("Location: index.php"); exit; }
$id = (int)$makanan['id'];

// ── TOXIC FILTER ──
$toxic_words = [
    'anjing','anjg','anjir','anjrit','anjeng','anying','ajg',
    'babi','bb','bepet',
    'bangsat','bgst','bangsad',
    'bajingan','bjgn','bajing',
    'keparat','kprt',
    'goblok','goblog','gblg','gblk','g0bl0k',
    'bego','bg',
    'tolol','tll','t0l01',
    'idiot','idt',
    'bodoh','bdh',
    'dungu',
    'monyet','mnytk',
    'kampret','kmprt',
    'memek','mmk','m3m3k',
    'kontol','kntl','k0nt0l',
    'jancok','jancuk','cnk','jancoq','jncok','cok','cuk',
    'asu','asw',
    'tai','taik','ty',
    'setan','stn',
    'iblis',
    'laknat',
    'sialan','sln',
    'mampus','mps',
    'brengsek','brngsk',
    'kampungan',
    'payah',
    'sampah','smph',
    'najis','njs',
    'peler','plr',
    'perek','prk',
    'lonte','lnt',
    'fuck','fck','fking','fk','f*ck',
    'shit','sht','sh1t',
    'bitch','btch','bch',
    'damn','dmn',
    'ass','as5',
    'asshole','ashl',
    'bastard','bstrd',
    'crap',
    'dick','dck',
    'idiot',
    'stupid','stpd',
    'dumb',
    'moron',
    'loser','lsr',
    'ugly',
    'trash',
    'garbage',
    'suck','sucks','sk',
    'wtf','stfu','lmao','omg'
];

function containsToxic($text, $toxic_words) {
    $lower = mb_strtolower($text, 'UTF-8');
    foreach ($toxic_words as $word) {
        if (strpos($lower, $word) !== false) return true;
    }
    return false;
}

// Proses tambah ulasan
$msg_ulasan = '';
$msg_toxic  = '';
if (isset($_POST['submit_ulasan']) && isLogin()) {
    $rating       = (int)$_POST['rating'];
    $komentar_raw = trim($_POST['komentar']);
    $uid          = $_SESSION['user_id'];
    if ($rating >= 1 && $rating <= 5) {
        if (containsToxic($komentar_raw, $toxic_words)) {
            $msg_toxic = 'Ulasan Anda mengandung kata yang tidak pantas dan tidak dapat dipublikasikan. Harap gunakan bahasa yang sopan.';
        } else {
            $komentar = mysqli_real_escape_string($koneksi, $komentar_raw);
            $cek = mysqli_query($koneksi, "SELECT id FROM ulasan WHERE makanan_id=$id AND user_id=$uid");
            if (mysqli_num_rows($cek) > 0) {
                mysqli_query($koneksi, "UPDATE ulasan SET rating=$rating, komentar='$komentar' WHERE makanan_id=$id AND user_id=$uid");
            } else {
                mysqli_query($koneksi, "INSERT INTO ulasan (makanan_id, user_id, rating, komentar) VALUES ($id, $uid, $rating, '$komentar')");
            }
            $msg_ulasan = "ok";
        }
    }
}

// Ambil semua ulasan
$ulasan_res = mysqli_query($koneksi,
    "SELECT u.*, us.username, us.foto FROM ulasan u
     JOIN users us ON u.user_id = us.id
     WHERE u.makanan_id = $id AND u.is_hidden = 0 ORDER BY u.created_at DESC");

// Rata-rata rating
$avg_res = mysqli_query($koneksi, "SELECT AVG(rating) as avg, COUNT(*) as total FROM ulasan WHERE makanan_id=$id AND is_hidden = 0");
$avg_row = mysqli_fetch_assoc($avg_res);
$avg_rating   = round($avg_row['avg'], 1);
$total_ulasan = $avg_row['total'];

// Cek apakah ulasan user sendiri disembunyikan admin
$ulasan_user_hidden = false;
if (isLogin()) {
    $uid_chk = (int)$_SESSION['user_id'];
    $res_chk  = mysqli_query($koneksi, "SELECT is_hidden FROM ulasan WHERE makanan_id=$id AND user_id=$uid_chk LIMIT 1");
    if ($res_chk && $row_chk = mysqli_fetch_assoc($res_chk)) {
        $ulasan_user_hidden = (bool)$row_chk['is_hidden'];
    }
}

// Pisah bahan & cara buat
$bahan_list = array_filter(explode('|', $makanan['bahan']));
$cara_list  = array_filter(explode('|', $makanan['cara_buat']));

// Cek favorit
$is_favorit = false;
if (isLogin()) {
    $uid_fav = (int)$_SESSION['user_id'];
    $cek_fav = mysqli_query($koneksi, "SELECT id FROM favorit WHERE user_id=$uid_fav AND makanan_id=$id");
    $is_favorit = mysqli_num_rows($cek_fav) > 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <link rel="icon" type="image/png" href="https://jejakrasa.site.je/gambar/logojr.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($makanan['nama']) ?> – Jejak Rasa Nusantara</title>
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

    /* HERO IMAGE */
    .hero-img { max-width:900px; margin:0 auto; padding:0 2rem; }
    .hero-img img { width:100%; height:420px; object-fit:cover; border-radius:24px; box-shadow:0 16px 48px rgba(0,0,0,0.14); display:block; }

    /* MAIN CONTENT */
    .detail-wrap { max-width:900px; margin:0 auto; padding:2rem 2rem 4rem; }

    /* META CARD */
    .meta-card { display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:1.5rem; margin:2rem 0; }
    .cat-badge { display:inline-block; background:rgba(245,166,35,.15); color:var(--gold-dark); font-size:.72rem; font-weight:700; padding:.3rem .9rem; border-radius:50px; letter-spacing:.5px; text-transform:uppercase; margin-bottom:.75rem; }
    .food-title { font-family:'Playfair Display',serif; font-size:2.4rem; font-weight:900; line-height:1.15; color:var(--dark); }
    .food-region { display:flex; align-items:center; gap:.4rem; color:#888; font-size:.9rem; margin-top:.6rem; }
    .food-region i { color:var(--gold); }

    /* RATING BOX */
    .rating-box { background:linear-gradient(135deg, #1A1208, #2C1D0A); border-radius:20px; padding:1.5rem 2rem; text-align:center; min-width:140px; flex-shrink:0; }
    .rating-num { font-family:'Playfair Display',serif; font-size:3rem; font-weight:900; color:var(--gold); line-height:1; }
    .stars-gold { color:var(--gold); font-size:1.1rem; letter-spacing:2px; margin:.3rem 0; }
    .rating-count { font-size:.78rem; color:rgba(255,255,255,.4); margin-top:.25rem; }

    /* SECTION CARDS */
    .info-card { background:#fff; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.06); border:1px solid #f0e8d8; padding:2rem; margin-bottom:1.5rem; }
    .info-card-title { display:flex; align-items:center; gap:.65rem; font-family:'Playfair Display',serif; font-size:1.2rem; font-weight:800; color:var(--dark); margin-bottom:1.25rem; }
    .info-card-title i { color:var(--gold); font-size:1rem; }

    /* BAHAN LIST */
    .bahan-item { display:flex; align-items:center; gap:.75rem; padding:.5rem 0; border-bottom:1px solid #f9f3e8; font-size:.95rem; color:#444; }
    .bahan-item:last-child { border-bottom:none; }
    .bahan-dot { width:8px; height:8px; border-radius:50%; background:var(--gold); flex-shrink:0; }

    /* LANGKAH */
    .langkah-item { display:flex; align-items:flex-start; gap:1rem; padding:.75rem 0; border-bottom:1px solid #f9f3e8; }
    .langkah-item:last-child { border-bottom:none; }
    .langkah-num { min-width:34px; height:34px; border-radius:50%; background:var(--gold); color:#fff; font-weight:800; font-size:.95rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 4px 12px rgba(245,166,35,.3); }
    .langkah-text { color:#444; line-height:1.75; font-size:.95rem; padding-top:.3rem; }

    /* ULASAN FORM */
    .star-inp { display:none; }
    .star-inp + label { font-size:2rem; color:#ddd; cursor:pointer; transition:.15s; }
    .star-inp:checked ~ label,
    .star-inp + label:hover ~ label,
    .star-inp + label:hover { color:var(--gold); }
    .ulasan-stars { color:var(--gold); font-size:.95rem; letter-spacing:1px; }

    /* =============================================
       ULASAN SLIDER — RESPONSIVE FIX
       ============================================= */
    .ulasan-slider-wrap {
      position: relative;
      overflow: hidden;           /* clip cards yang keluar */
      width: 100%;
      padding: .5rem 0 1.5rem;   /* NO horizontal padding */
    }
    .ulasan-track {
      display: flex;
      gap: 12px;                  /* gap konsisten dgn JS */
      transition: transform .55s cubic-bezier(.4,0,.2,1);
      will-change: transform;
    }
    .ulasan-card {
      /* Lebar dihitung JS, flex hanya sebagai fallback */
      flex: 0 0 calc((100% - 24px) / 3); /* desktop 3 card */
      min-width: 0;
      box-sizing: border-box;            /* KUNCI: cegah overflow */
      background: rgba(255,255,255,0.55);
      backdrop-filter: blur(14px) saturate(160%);
      -webkit-backdrop-filter: blur(14px) saturate(160%);
      border: 1px solid rgba(245,166,35,0.22);
      border-radius: 18px;
      padding: 1.25rem;
      box-shadow:
        0 4px 24px rgba(245,166,35,0.13),
        0 1.5px 8px rgba(0,0,0,0.07),
        inset 0 1px 0 rgba(255,255,255,0.7);
      transition: transform .25s, box-shadow .25s;
    }
    .ulasan-card:hover {
      transform: translateY(-4px);
      box-shadow:
        0 10px 36px rgba(245,166,35,0.22),
        0 4px 14px rgba(0,0,0,0.10),
        inset 0 1px 0 rgba(255,255,255,0.8);
    }

    /* Dots */
    .ulasan-dots {
      display: flex;
      justify-content: center;
      gap: .45rem;
      margin-top: .75rem;
    }
    .ulasan-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: rgba(245,166,35,.25);
      transition: background .3s, transform .3s;
      cursor: pointer;
    }
    .ulasan-dot.active {
      background: var(--gold);
      transform: scale(1.3);
    }

    /* Nav arrows — hanya desktop */
    .ulasan-nav {
      position: absolute;
      top: 0; bottom: 0;
      width: 60px;
      background: transparent;
      border: none;
      cursor: pointer;
      z-index: 5;
      opacity: 0;
    }
    .ulasan-nav.prev { left: 0; }
    .ulasan-nav.next { right: 0; }
    .ulasan-nav i { display: none; }

    /* Tablet: 2 card */
    @media(max-width: 768px) {
      .ulasan-card { flex: 0 0 calc((100% - 12px) / 2); }
    }
    /* Mobile: 1 card full width */
    @media(max-width: 520px) {
      .ulasan-card {
        flex: 0 0 100%;
        width: 100%;
      }
      .ulasan-nav { display: none; }
    }

    /* ULASAN DISEMBUNYIKAN */
    .ulasan-hidden-notice {
      display:flex; align-items:center; gap:.75rem;
      background:#FFF8EC; border:1.5px solid rgba(245,166,35,.35);
      border-radius:14px; padding:1rem 1.25rem; margin-bottom:1.25rem;
      font-size:.875rem; color:#92400E;
    }
    .ulasan-hidden-notice .icon-hidden {
      width:36px; height:36px; border-radius:50%;
      background:rgba(245,166,35,.15); display:flex;
      align-items:center; justify-content:center;
      flex-shrink:0; font-size:1rem; color:var(--gold);
    }
    .btn-why {
      display:inline-flex; align-items:center; gap:.35rem;
      padding:.3rem .75rem; border-radius:50px;
      border:1.5px solid rgba(245,166,35,.5);
      background:#fff; color:var(--gold-dark);
      font-size:.75rem; font-weight:700; cursor:pointer;
      font-family:'DM Sans',sans-serif; transition:.2s;
      margin-left:auto; flex-shrink:0;
    }
    .btn-why:hover { background:rgba(245,166,35,.1); }

    /* MODAL WHY */
    .why-modal-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:9998;
      align-items:center; justify-content:center;
    }
    .why-modal-overlay.open { display:flex; }
    .why-modal {
      background:#fff; border-radius:20px; padding:2rem;
      max-width:400px; width:90%; box-shadow:0 24px 64px rgba(0,0,0,.2);
      animation:popIn .25s cubic-bezier(.4,0,.2,1);
    }
    @keyframes popIn { from{transform:scale(.9);opacity:0} to{transform:scale(1);opacity:1} }
    .why-modal h4 { font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:800; color:var(--dark); margin-bottom:.75rem; display:flex;align-items:center;gap:.5rem; }
    .why-modal p  { font-size:.875rem; color:#555; line-height:1.75; }
    .why-modal ul { padding-left:1.2rem; margin:.5rem 0; }
    .why-modal ul li { font-size:.85rem; color:#555; line-height:1.9; }
    .why-modal-close { display:block; margin-top:1.25rem; padding:.65rem 1.5rem; background:var(--gold); color:#fff; border:none; border-radius:10px; font-weight:700; font-size:.875rem; cursor:pointer; font-family:'DM Sans',sans-serif; width:100%; transition:.2s; }
    .why-modal-close:hover { background:var(--gold-dark); }

    /* BACK LINK */
    .back-link { display:inline-flex; align-items:center; gap:.5rem; color:var(--gold); font-weight:600; text-decoration:none; font-size:.9rem; margin-top:1.5rem; transition:.2s; }
    .back-link:hover { color:var(--gold-dark); }

    /* SIMPAN RESEP */
    .btn-simpan {
      display:inline-flex; align-items:center; gap:.55rem;
      padding:.7rem 1.5rem; border-radius:12px;
      border:2px solid rgba(245,166,35,.4);
      background:rgba(245,166,35,.08);
      color:var(--gold-dark);
      font-weight:700; font-size:.88rem;
      cursor:pointer; transition:.25s;
      font-family:'DM Sans',sans-serif;
    }
    .btn-simpan:hover { background:rgba(245,166,35,.18); border-color:var(--gold); }
    .btn-simpan.saved {
      background:var(--gold); border-color:var(--gold-dark);
      color:#fff; box-shadow:0 4px 16px rgba(245,166,35,.35);
    }
    .btn-simpan.saved:hover { background:var(--gold-dark); }

    /* Toast */
    #toastFav {
      position:fixed; bottom:2rem; left:50%; transform:translateX(-50%) translateY(20px);
      background:#1A1208; color:#fff; padding:.75rem 1.5rem;
      border-radius:50px; font-size:.875rem; font-weight:600;
      box-shadow:0 8px 32px rgba(0,0,0,.25);
      opacity:0; transition:.3s; z-index:9999; pointer-events:none;
      display:flex; align-items:center; gap:.5rem;
    }
    #toastFav.show { opacity:1; transform:translateX(-50%) translateY(0); }

    /* FOOTER */
    footer { background:#1A1208; padding:3rem 2rem 1.5rem; margin-top:3rem; }
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
      .hero-img img { height:260px; }
      .food-title { font-size:1.8rem; }
      .meta-card { flex-direction:column; }
      .rating-box { width:100%; }
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
      <a href="tentangkami.php">Tentang Kami</a>
    </div>
    <div class="nav-links">
      <?php if (isLogin()): ?>
        <?php if (isAdmin()): ?>
          <a href="dashboard.php">Admin</a>
        <?php endif; ?>
        <div class="profile-wrap" id="profileWrap">
          <img src="<?= $_SESSION['foto'] ? 'uploads/'.$_SESSION['foto'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png' ?>" id="profileBtn">
          <div class="profile-menu" id="profileMenu">
            <a href="profile.php"><i class="fas fa-user" style="margin-right:.5rem;color:var(--gold);"></i><span>Profile</span></a>
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
    <a href="tentangkami.php">Tentang Kami</a>
    <?php if (isLogin()): ?>
      <a href="profile.php">Profil Saya</a>
      <a href="logout.php" style="color:#e53e3e;">Logout</a>
    <?php else: ?>
      <a href="login.php" style="background:var(--gold);color:#fff;padding:.6rem 1rem;border-radius:10px;font-weight:600;text-align:center;">Masuk</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO IMAGE -->
<div class="hero-img">
  <img src="<?= htmlspecialchars($makanan['gambar']) ?>" alt="<?= htmlspecialchars($makanan['nama']) ?>">
</div>

<!-- DETAIL CONTENT -->
<div class="detail-wrap">

  <!-- META -->
  <div class="meta-card">
    <div class="meta-left">
      <span class="cat-badge"><?= htmlspecialchars($makanan['kategori']) ?></span>
      <h1 class="food-title"><?= htmlspecialchars($makanan['nama']) ?></h1>
      <div class="food-region">
        <i class="fas fa-map-marker-alt"></i>
        <?= htmlspecialchars($makanan['daerah']) ?>
      </div>
      <div style="margin-top:1rem;">
        <?php if (isLogin()): ?>
          <form method="POST" action="" style="display:inline;">
            <input type="hidden" name="toggle_favorit" value="1">
            <input type="hidden" name="makanan_id" value="<?= $id ?>">
            <input type="hidden" name="slug" value="<?= slugify($makanan['nama']) ?>">
            <button type="submit" class="btn-simpan <?= $is_favorit ? 'saved' : '' ?>">
              <i class="<?= $is_favorit ? 'fas' : 'far' ?> fa-heart"></i>
              <span><?= $is_favorit ? 'Difavoritkan' : 'Favorit' ?></span>
            </button>
          </form>
        <?php else: ?>
          <a href="login.php" class="btn-simpan">
            <i class="far fa-heart"></i>
            <span>Favorit</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="rating-box">
      <div class="rating-num"><?= $avg_rating ?: '–' ?></div>
      <div class="stars-gold">
        <?php for ($i=1; $i<=5; $i++) echo $i <= round($avg_rating) ? '★' : '☆'; ?>
      </div>
      <div class="rating-count"><?= $total_ulasan ?> ulasan</div>
    </div>
  </div>

  <!-- DESKRIPSI -->
  <div class="info-card">
    <div class="info-card-title"><i class="fas fa-info-circle"></i> Tentang</div>
    <p style="color:#555;line-height:1.85;font-size:.95rem;"><?= nl2br(htmlspecialchars($makanan['deskripsi'])) ?></p>
  </div>

  <!-- BAHAN -->
  <div class="info-card">
    <div class="info-card-title"><i class="fas fa-list-ul"></i> Bahan-Bahan</div>
    <?php foreach ($bahan_list as $bahan): ?>
    <div class="bahan-item">
      <span class="bahan-dot"></span>
      <?= htmlspecialchars(trim($bahan)) ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- CARA MEMBUAT -->
  <div class="info-card">
    <div class="info-card-title"><i class="fas fa-utensils"></i> Cara Membuat</div>
    <?php $no = 1; foreach ($cara_list as $langkah): ?>
    <div class="langkah-item">
      <span class="langkah-num"><?= $no++ ?></span>
      <span class="langkah-text"><?= htmlspecialchars(trim($langkah)) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ULASAN -->
  <div class="info-card">
    <div class="info-card-title"><i class="fas fa-star"></i> Ulasan Pengguna</div>

    <?php if (isLogin()): ?>
    <?php if ($ulasan_user_hidden): ?>
    <div class="ulasan-hidden-notice">
      <div class="icon-hidden"><i class="fas fa-eye-slash"></i></div>
      <div style="flex:1;min-width:0;">
        <strong style="display:block;margin-bottom:.2rem;">Ulasan kamu disembunyikan</strong>
        <span style="color:#B45309;font-size:.8rem;">Ulasan ini tidak tampil untuk pengguna lain saat ini.</span>
      </div>
      <button class="btn-why" onclick="document.getElementById('whyModal').classList.add('open')">
        <i class="fas fa-question-circle"></i> Kenapa?
      </button>
    </div>
    <?php endif; ?>

    <!-- Form Ulasan -->
    <form action="" method="POST" style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:16px;padding:1.5rem;margin-bottom:1.75rem;">
      <?php if ($msg_ulasan === 'ok'): ?>
      <div style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:10px;padding:.75rem 1rem;font-size:.875rem;font-weight:500;margin-bottom:1rem;">
        ✅ Ulasan berhasil disimpan!
      </div>
      <?php elseif ($msg_toxic): ?>
      <div style="background:#FEE2E2;color:#991B1B;border:1px solid #FECACA;border-radius:10px;padding:.75rem 1rem;font-size:.875rem;font-weight:500;margin-bottom:1rem;">
        🚫 <?= htmlspecialchars($msg_toxic) ?>
      </div>
      <?php endif; ?>
      <p style="font-weight:700;font-size:.9rem;color:var(--dark);margin-bottom:.75rem;">Beri Rating:</p>
      <div style="display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:.25rem;margin-bottom:1rem;">
        <?php for ($i=5; $i>=1; $i--): ?>
          <input type="radio" name="rating" id="star<?=$i?>" value="<?=$i?>" class="star-inp" required>
          <label for="star<?=$i?>">★</label>
        <?php endfor; ?>
      </div>
      <textarea name="komentar" rows="3" placeholder="Tulis komentar Anda..."
        style="width:100%;padding:.85rem 1rem;border:2px solid #E8E0D5;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:.9rem;resize:none;background:#fafaf8;color:var(--dark);outline:none;transition:.2s;"
        onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='#E8E0D5'"></textarea>
      <button type="submit" name="submit_ulasan"
        style="margin-top:.75rem;padding:.75rem 2rem;background:var(--gold);color:#fff;border:none;border-radius:12px;font-weight:700;font-size:.9rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s;"
        onmouseover="this.style.background='var(--gold-dark)'" onmouseout="this.style.background='var(--gold)'">
        Kirim Ulasan
      </button>
    </form>
    <?php else: ?>
    <div style="background:rgba(245,166,35,.06);border:1px solid rgba(245,166,35,.2);border-radius:16px;padding:1.25rem;text-align:center;margin-bottom:1.75rem;">
      <p style="color:#666;font-size:.9rem;">
        <a href="login.php" style="color:var(--gold);font-weight:700;text-decoration:none;">Masuk</a> untuk menulis ulasan.
      </p>
    </div>
    <?php endif; ?>

    <!-- Daftar Ulasan Slider -->
    <?php if ($total_ulasan === 0): ?>
      <p style="text-align:center;color:#aaa;padding:2rem 0;font-size:.9rem;">Belum ada ulasan. Jadilah yang pertama!</p>
    <?php else: ?>
    <div class="ulasan-slider-wrap" id="ulasanWrap">
      <button class="ulasan-nav prev" id="ulasanPrev" aria-label="Sebelumnya"><i class="fas fa-chevron-left"></i></button>
      <div class="ulasan-track" id="ulasanTrack">
        <?php
        $shown_ulasan = 0;
        while ($ul = mysqli_fetch_assoc($ulasan_res)):
          if (containsToxic($ul['komentar'], $toxic_words)) continue;
          $shown_ulasan++;
        ?>
        <div class="ulasan-card">
          <div style="display:flex;align-items:center;gap:.85rem;margin-bottom:.75rem;">
            <img src="<?= $ul['foto'] ? 'uploads/'.$ul['foto'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png' ?>"
                 style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid rgba(245,166,35,.35);flex-shrink:0;">
            <div style="flex:1;min-width:0;">
              <p style="font-weight:700;font-size:.875rem;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($ul['username']) ?></p>
              <div class="ulasan-stars">
                <?php for ($i=1; $i<=5; $i++) echo $i <= $ul['rating'] ? '★' : '☆'; ?>
              </div>
            </div>
            <span style="font-size:.72rem;color:#aaa;flex-shrink:0;"><?= date('d M Y', strtotime($ul['created_at'])) ?></span>
          </div>
          <?php if ($ul['komentar']): ?>
            <p style="color:#555;font-size:.875rem;line-height:1.75;margin:0;word-break:break-word;overflow-wrap:break-word;"><?= nl2br(htmlspecialchars($ul['komentar'])) ?></p>
          <?php endif; ?>
        </div>
        <?php endwhile;
          if ($shown_ulasan === 0): ?>
            <p style="text-align:center;color:#aaa;padding:1rem 0;font-size:.875rem;">Belum ada ulasan yang tampil.</p>
          <?php endif; ?>
      </div>
      <button class="ulasan-nav next" id="ulasanNext" aria-label="Berikutnya"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div class="ulasan-dots" id="ulasanDots"></div>
    <?php endif; ?>
  </div>

  <a href="index.php" class="back-link">
    <i class="fas fa-arrow-left"></i> Kembali ke Beranda
  </a>

</div>

<!-- MODAL WHY -->
<div class="why-modal-overlay" id="whyModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="why-modal">
    <h4><i class="fas fa-eye-slash" style="color:var(--gold);font-size:1rem;"></i> Kenapa ulasan disembunyikan?</h4>
    <p>Ulasan kamu tidak tampil karena moderator menemukan salah satu dari kondisi berikut:</p>
    <ul>
      <li>Mengandung kata-kata tidak sopan atau kasar</li>
      <li>Berisi konten yang tidak relevan atau spam</li>
      <li>Melanggar panduan komunitas Jejak Rasa</li>
    </ul>
    <p style="margin-top:.75rem;">Kamu masih bisa mengedit dan mengirim ulasan baru. Pastikan menggunakan bahasa yang sopan dan sesuai topik.</p>
    <button class="why-modal-close" onclick="document.getElementById('whyModal').classList.remove('open')">Mengerti</button>
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

<div id="toastFav"></div>

<script>
  /* Navbar */
  document.getElementById('hamburger').addEventListener('click', () =>
    document.getElementById('mobileMenu').classList.toggle('open'));

  const profileBtn = document.getElementById('profileBtn');
  if (profileBtn) {
    profileBtn.addEventListener('click', () =>
      document.getElementById('profileMenu').classList.toggle('open'));
    document.addEventListener('click', e => {
      if (!document.getElementById('profileWrap').contains(e.target))
        document.getElementById('profileMenu').classList.remove('open');
    });
  }

  /* ── ULASAN SLIDER — RESPONSIVE FIX ── */
  (function () {
    const track   = document.getElementById('ulasanTrack');
    const dotsBox = document.getElementById('ulasanDots');
    const prev    = document.getElementById('ulasanPrev');
    const next    = document.getElementById('ulasanNext');
    if (!track) return;

    const GAP   = 12;  /* px — harus sama dengan CSS gap: 12px */
    const cards = Array.from(track.children);
    const total = cards.length;
    let current = 0;
    let timer;

    function getVisible() {
      const w = track.parentElement.offsetWidth;
      if (w <= 520) return 1;
      if (w <= 768) return 2;
      return 3;
    }

    function maxIndex() { return Math.max(0, total - getVisible()); }

    /* Hitung lebar card berdasarkan lebar container aktual */
    function cardWidth() {
      const vis = getVisible();
      return (track.parentElement.offsetWidth - GAP * (vis - 1)) / vis;
    }

    /* Set lebar setiap card secara eksplisit agar tidak meluber */
    function resizeCards() {
      const w = cardWidth();
      cards.forEach(c => { c.style.width = w + 'px'; c.style.flexShrink = '0'; });
    }

    function buildDots() {
      dotsBox.innerHTML = '';
      const pages = maxIndex() + 1;
      for (let i = 0; i < pages; i++) {
        const d = document.createElement('span');
        d.className = 'ulasan-dot' + (i === current ? ' active' : '');
        d.addEventListener('click', () => goTo(i));
        dotsBox.appendChild(d);
      }
    }

    function updateDots() {
      dotsBox.querySelectorAll('.ulasan-dot')
        .forEach((d, i) => d.classList.toggle('active', i === current));
    }

    function goTo(idx) {
      current = Math.max(0, Math.min(idx, maxIndex()));
      const offset = current * (cardWidth() + GAP);
      track.style.transform = `translateX(-${offset}px)`;
      updateDots();
    }

    function autoNext() { goTo(current >= maxIndex() ? 0 : current + 1); }
    function startAuto() { timer = setInterval(autoNext, 4000); }
    function stopAuto()  { clearInterval(timer); }

    if (prev) prev.addEventListener('click', () => { stopAuto(); goTo(current - 1); startAuto(); });
    if (next) next.addEventListener('click', () => { stopAuto(); goTo(current + 1); startAuto(); });

    track.parentElement.addEventListener('mouseenter', stopAuto);
    track.parentElement.addEventListener('mouseleave', startAuto);

    /* Touch swipe */
    let touchX = 0;
    track.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; stopAuto(); }, {passive:true});
    track.addEventListener('touchend',   e => {
      const diff = touchX - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 40) goTo(diff > 0 ? current + 1 : current - 1);
      startAuto();
    }, {passive:true});

    /* Resize: recalculate card width & reposition */
    window.addEventListener('resize', () => {
      resizeCards();
      current = Math.min(current, maxIndex());
      goTo(current);
      buildDots();
    });

    /* Init */
    resizeCards();
    buildDots();
    goTo(0);
    startAuto();
  })();

  /* Toast favorit */
  const toast = document.getElementById('toastFav');
  function showToast(icon, msg) {
    toast.innerHTML = `<i class="${icon}"></i> ${msg}`;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2500);
  }
  (function(){
    const params = new URLSearchParams(location.search);
    if (params.get('fav') === 'saved') {
      showToast('fas fa-heart', 'Ditambahkan ke favorit!');
      history.replaceState(null,'',location.pathname);
    } else if (params.get('fav') === 'removed') {
      showToast('far fa-heart', 'Dihapus dari favorit.');
      history.replaceState(null,'',location.pathname);
    }
  })();
</script>
</body>
</html>