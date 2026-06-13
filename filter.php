<?php
include 'koneksi.php';

// Mengubah default kategori menjadi 'Semua' agar saat pertama dibuka muncul semua makanan & camilan
$kategori = $_GET['kategori'] ?? 'Semua';
$daerah   = $_GET['daerah']   ?? 'Semua';
$urutan   = $_GET['urutan']   ?? 'terbaru';
$q        = trim($_GET['q']   ?? '');

$daerah_list = [];
$res_daerah  = mysqli_query($koneksi, "SELECT DISTINCT daerah FROM makanan ORDER BY daerah ASC");
while ($d = mysqli_fetch_assoc($res_daerah)) $daerah_list[] = $d['daerah'];

$where = ["1=1"];
if ($kategori !== 'Semua') $where[] = "kategori = '" . mysqli_real_escape_string($koneksi, $kategori) . "'";
if ($daerah   !== 'Semua') $where[] = "daerah   = '" . mysqli_real_escape_string($koneksi, $daerah) . "'";
if ($q !== '')              $where[] = "(nama LIKE '%" . mysqli_real_escape_string($koneksi, $q) . "%' OR daerah LIKE '%" . mysqli_real_escape_string($koneksi, $q) . "%')";

$order = $urutan === 'abjad' ? "nama ASC" : "id DESC";
$sql   = "SELECT * FROM makanan WHERE " . implode(' AND ', $where) . " ORDER BY $order";
$foods = mysqli_query($koneksi, $sql);

$rows  = [];
while ($r = mysqli_fetch_assoc($foods)) $rows[] = $r;
$count = count($rows);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="https://jejakrasa.site.je/gambar/logojr.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Filter Makanan – Jejak Rasa Nusantara</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root { --gold:#F5A623; --gold-dark:#D4891A; --red:#C0392B; --dark:#1A1208; --cream:#FDF8F0; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:var(--cream); color:var(--dark); min-height:100vh; }

    /* ── NAVBAR ── */
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
    .nav-links a:hover, .nav-links a.active { color:var(--gold); }
    .nav-links a:hover::after, .nav-links a.active::after { width:100%; }
    .btn-masuk { background:var(--gold)!important; color:#fff!important; padding:.5rem 1.4rem; border-radius:10px; font-weight:600!important; font-size:.9rem; transition:.2s!important; }
    .btn-masuk:hover { background:var(--gold-dark)!important; }
    .btn-masuk::after { display:none!important; }
    .profile-wrap { position:relative; }
    .profile-wrap img { width:38px; height:38px; border-radius:50%; object-fit:cover; border:2.5px solid var(--gold); cursor:pointer; }
    .profile-menu { display:none; position:absolute; right:0; top:calc(100%+10px); background:#fff; box-shadow:0 12px 40px rgba(0,0,0,0.12); border-radius:14px; overflow:hidden; min-width:168px; border:1px solid #f0e8d8; }
    .profile-menu.open { display:block; }
    .profile-menu a { display:block; padding:.75rem 1.1rem; font-size:.875rem; color:#333; text-decoration:none; }
    .profile-menu a:hover { background:#FFF8EC; }
    .hamburger { display:none; background:none; border:none; font-size:1.4rem; color:#555; cursor:pointer; }
    .mobile-menu { display:none; position:absolute; top:68px; left:0; right:0; background:#fff; border-bottom:1px solid #f0e8d8; padding:1.2rem 2rem; z-index:99; }
    .mobile-menu.open { display:flex; flex-direction:column; gap:1rem; }
    .mobile-menu a { font-weight:500; color:#444; text-decoration:none; padding:.4rem 0; border-left: 3px solid transparent; padding-left: .75rem; transition:.2s; }
    .mobile-menu a:hover { color:var(--gold); border-left-color: var(--gold); }
    .mobile-menu a.active { color:var(--gold); font-weight:700; border-left-color: var(--gold); background: rgba(245,166,35,.07); border-radius: 0 8px 8px 0; }

    /* ── PAGE HEADER ── */
    .page-header {
      background:linear-gradient(135deg, #1A1208 0%, #2C1D0A 100%);
      padding:4.5rem 2rem 7rem;
      text-align:center; position:relative; overflow:hidden;
    }
    .page-header::before {
      content:'';
      position:absolute; inset:0;
      background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23F5A623' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .page-header-inner { position:relative; }
    .page-badge { display:inline-block; background:rgba(245,166,35,.15); border:1px solid rgba(245,166,35,.4); color:var(--gold); font-size:.78rem; font-weight:700; padding:.35rem 1rem; border-radius:50px; letter-spacing:.5px; text-transform:uppercase; margin-bottom:1.2rem; }
    .page-header h1 { font-family:'Playfair Display',serif; font-size:clamp(2rem,5vw,3.2rem); font-weight:900; color:#fff; }
    .page-header h1 span { color:var(--gold); }
    .page-header p { color:rgba(255,255,255,.55); margin-top:.6rem; font-size:.95rem; }

    /* ── FILTER CARD ── */
    .filter-outer { max-width:920px; margin:-4.5rem auto 0; padding:0 2rem; position:relative; z-index:10; }
    .filter-card {
      background:#fff; border-radius:24px;
      box-shadow:0 24px 72px rgba(0,0,0,0.14);
      padding:2.5rem; border:1px solid rgba(245,166,35,0.1);
    }

    /* 3-kolom: Kategori | Daerah | Urutkan */
    .filter-row { display:grid; grid-template-columns:1fr 1fr 1fr; gap:2rem; align-items:start; }
    .filter-group { display:flex; flex-direction:column; gap:.7rem; }
    .filter-label { font-size:.72rem; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:#aaa; }

    /* Tombol Kategori */
    .type-btns { display:flex; gap:.6rem; }
    .type-btn {
      flex:1; padding:1rem 1rem;
      border-radius:14px; border:2px solid #EDE8DF;
      background:#FAFAF8; cursor:pointer;
      font-weight:600; font-size:.88rem; color:#777;
      transition:all .25s cubic-bezier(.4,0,.2,1);
      font-family:'DM Sans',sans-serif;
      display:flex; flex-direction:column; align-items:center; gap:.4rem;
      position:relative; overflow:hidden;
    }
    .type-btn .btn-emoji { font-size:1.6rem; transition:.3s; }
    .type-btn .btn-text { font-size:.82rem; font-weight:700; letter-spacing:.2px; }
    .type-btn:hover {
      border-color:var(--gold); color:var(--gold-dark);
      background:#FFFBF3;
      transform:translateY(-3px);
      box-shadow:0 8px 24px rgba(245,166,35,.18);
    }
    .type-btn.active {
      background:linear-gradient(145deg, var(--gold) 0%, var(--gold-dark) 100%);
      border-color:transparent; color:#fff;
      box-shadow:0 10px 30px rgba(245,166,35,.4);
      transform:translateY(-3px);
    }
    .type-btn.active .btn-emoji { transform:scale(1.2); }
    .type-btn.active .btn-text { color:#fff; }
    .type-btn.active::before {
      content:'\f058';
      font-family:'Font Awesome 6 Free'; font-weight:900;
      position:absolute; top:.5rem; right:.6rem;
      font-size:.7rem; color:rgba(255,255,255,.7);
    }

    /* Select */
    .filter-select {
      width:100%; padding:.95rem 1.1rem; border-radius:14px; border:2px solid #EDE8DF;
      background:#FAFAF8; font-size:.93rem; color:#333;
      font-family:'DM Sans',sans-serif; cursor:pointer; transition:.2s;
      appearance:none;
      background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
      background-repeat:no-repeat; background-position:right 1rem center;
    }
    .filter-select:focus { outline:none; border-color:var(--gold); background-color:#fff; box-shadow:0 0 0 3px rgba(245,166,35,.1); }

    /* Tombol Urutan */
    .sort-btns { display:flex; flex-direction:column; gap:.5rem; }
    .sort-btn {
      padding:.75rem 1rem;
      border-radius:12px; border:2px solid #EDE8DF;
      background:#FAFAF8; cursor:pointer;
      font-weight:600; font-size:.85rem; color:#777;
      transition:all .22s; font-family:'DM Sans',sans-serif;
      display:flex; align-items:center; gap:.6rem;
      text-align:left;
    }
    .sort-btn i { width:16px; text-align:center; }
    .sort-btn:hover { border-color:var(--gold); color:var(--gold-dark); background:#FFFBF3; }
    .sort-btn.active {
      background:linear-gradient(145deg, var(--gold) 0%, var(--gold-dark) 100%);
      border-color:transparent; color:#fff;
      box-shadow:0 6px 20px rgba(245,166,35,.35);
    }

    /* ── RESULTS ── */
    .results-outer { max-width:1280px; margin:4rem auto; padding:0 2rem; }
    .results-meta { display:flex; align-items:center; justify-content:space-between; margin-bottom:2rem; flex-wrap:wrap; gap:1rem; }
    .results-meta h3 { font-family:'Playfair Display',serif; font-size:1.7rem; font-weight:800; }
    .results-count { background:rgba(245,166,35,.1); color:var(--gold-dark); font-weight:700; font-size:.83rem; padding:.38rem 1.1rem; border-radius:50px; border:1px solid rgba(245,166,35,.3); }

    .food-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:1.6rem; }
    .food-card { position:relative; border-radius:20px; overflow:hidden; aspect-ratio:4/3; cursor:pointer; box-shadow:0 4px 20px rgba(0,0,0,0.08); border:1px solid #f0e8d8; transition:.35s cubic-bezier(.4,0,.2,1); }
    .food-card img { width:100%; height:100%; object-fit:cover; display:block; transition:.45s; }
    .food-card:hover { transform:translateY(-6px); box-shadow:0 16px 48px rgba(0,0,0,0.16); border-color:rgba(245,166,35,.3); }
    .food-card:hover img { transform:scale(1.07); }
    .food-card-overlay { position:absolute; inset:0; background:linear-gradient(to top, rgba(15,8,2,.85) 30%, transparent 70%); display:flex; flex-direction:column; justify-content:flex-end; padding:1.4rem; transition:.3s; }
    .food-card:hover .food-card-overlay { background:linear-gradient(to top, rgba(15,8,2,.92) 40%, rgba(15,8,2,.15) 100%); }
    .food-card-cat { font-size:.7rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:var(--gold); margin-bottom:.3rem; }
    .food-card-name { font-family:'Playfair Display',serif; font-size:1.2rem; font-weight:700; color:#fff; line-height:1.3; }
    .food-card-region { font-size:.8rem; color:rgba(255,255,255,.6); margin-top:.3rem; display:flex; align-items:center; gap:.3rem; }
    .food-card-arrow { position:absolute; top:1rem; right:1rem; background:rgba(255,255,255,.12); backdrop-filter:blur(4px); border-radius:50%; width:34px; height:34px; display:flex; align-items:center; justify-content:center; color:#fff; opacity:0; transition:.3s; }
    .food-card:hover .food-card-arrow { opacity:1; }

    /* Empty */
    .empty-state { text-align:center; padding:5rem 2rem; }
    .empty-icon { font-size:4rem; color:#D4C9B8; margin-bottom:1rem; }
    .empty-state h3 { font-family:'Playfair Display',serif; font-size:1.5rem; color:#888; margin-bottom:.5rem; }
    .empty-state p { color:#aaa; font-size:.9rem; }

    /* Footer */
    footer { background:#1A1208; padding:3rem 2rem 1.5rem; margin-top:5rem; }
    .footer-inner { max-width:1280px; margin:auto; display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:2rem; padding-bottom:2rem; border-bottom:1px solid rgba(255,255,255,.08); }
    .footer-brand p { color:rgba(255,255,255,.45); font-size:.875rem; margin-top:.75rem; line-height:1.7; max-width:220px; }
    .footer-col h5 { font-family:'Playfair Display',serif; color:#fff; font-size:1rem; margin-bottom:1rem; }
    .footer-col a { display:block; color:rgba(255,255,255,.45); font-size:.875rem; text-decoration:none; margin-bottom:.5rem; transition:.2s; }
    .footer-col a:hover { color:var(--gold); }
    .footer-social { display:flex; gap:.75rem; margin-top:.5rem; }
    .footer-social a { width:36px; height:36px; border-radius:50%; border:1px solid rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.5); font-size:.85rem; transition:.2s; }
    .footer-social a:hover { background:var(--gold); border-color:var(--gold); color:#fff; }
    .footer-copy { max-width:1280px; margin:1.2rem auto 0; text-align:center; color:rgba(255,255,255,.3); font-size:.8rem; }

    /* ── SEARCH BAR ── */
    .search-bar { display:flex; align-items:center; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.15); border-radius:16px; padding:.5rem .5rem .5rem 1.2rem; width:100%; gap:.5rem; backdrop-filter:blur(8px); }
    .search-bar i { color:rgba(255,255,255,.4); }
    .search-bar input { background:none; border:none; outline:none; color:#fff; flex:1; font-size:1rem; font-family:'DM Sans',sans-serif; }
    .search-bar input::placeholder { color:rgba(255,255,255,.35); }
    .search-bar button { background:var(--gold); color:#fff; border:none; border-radius:10px; padding:.65rem 1.4rem; font-weight:700; font-size:.9rem; cursor:pointer; transition:.2s; white-space:nowrap; }
    .search-bar button:hover { background:var(--gold-dark); }
    .search-wrapper { width:100%; max-width:620px; position:relative; }
    .search-suggest { position:absolute; top:100%; left:0; right:0; margin-top:.6rem; background:#1A1208; border-radius:16px; box-shadow:0 15px 40px rgba(0,0,0,.35); display:none; z-index:999; border:1px solid rgba(255,255,255,.08); }
    .search-item { display:flex !important; align-items:center; gap:.9rem; padding:.85rem 1rem; text-decoration:none; transition:.18s; border-bottom:1px solid rgba(255,255,255,.05); width:100%; }
    .search-item:last-child { border-bottom:none; }
    .search-item:hover { background:rgba(255,255,255,.06); }
    .search-item img { width:52px; height:40px; object-fit:cover; border-radius:8px; flex-shrink:0; }
    .search-item-text { flex:1; }
    .search-item-name { color:#fff; font-weight:700; font-size:.92rem; }
    .search-item-cat { color:rgba(255,255,255,.55); font-size:.78rem; margin-top:.15rem; }
    .search-empty { padding:1rem; color:rgba(255,255,255,.5); font-size:.85rem; }

    @media(max-width:900px) {
      .filter-row { grid-template-columns:1fr 1fr; }
    }
    @media(max-width:768px) {
      .nav-links { display:none; }
      .hamburger { display:block; }
      .filter-row { grid-template-columns:1fr; }
      .type-btns { gap:.5rem; }
      .footer-inner { grid-template-columns:1fr 1fr; }
    }
    @media(max-width:480px) { .footer-inner { grid-template-columns:1fr; } }
  </style>
</head>
<body>

<nav class="navbar">
  <div class="nav-inner">
    <a class="nav-logo" href="index.php"><img src="https://jejakrasa.site.je/gambar/logoweb.svg" alt="Logo"></a>
    <div class="nav-links">
      <a href="index.php">Beranda</a>
      <a href="filter.php" class="active">Filter</a>
      <a href="tentangkami.php">Tentang Kami</a>
    </div>
    <div class="nav-links">
      <?php if (isLogin()): ?>
        <?php if (isAdmin()): ?>
          <a href="dashboard.php">Admin</a>
        <?php endif; ?>
        <div class="profile-wrap" id="profileWrap">
          <img src="<?= $_SESSION['foto'] ? 'uploads/'.$_SESSION['foto'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png' ?>" id="profileBtn" alt="Profil">
          <div class="profile-menu" id="profileMenu">
            <a href="profile.php"><i class="fas fa-user" style="color:var(--gold);margin-right:.5rem;"></i><?= htmlspecialchars($_SESSION['username']) ?></a>
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
    <a href="filter.php" class="active">Filter</a>
    <a href="tentangkami.php">Tentang Kami</a>
    <?php if (isLogin()): ?>
      <a href="profile.php">Profil Saya</a>
      <a href="logout.php" style="color:#e53e3e;">Logout</a>
    <?php else: ?>
      <a href="login.php" style="background:var(--gold);color:#fff;padding:.6rem 1rem;border-radius:10px;font-weight:600;text-align:center;">Masuk</a>
    <?php endif; ?>
  </div>
</nav>

<div class="page-header">
  <div class="page-header-inner">
    <?php if ($q !== ''): ?>
      <h1>Kata: <span><?= htmlspecialchars($q) ?></span></h1>
      <p>Gunakan filter di bawah untuk mempersempit hasil pencarian</p>
    <?php else: ?>
      <h1>Filter <span>Makanan</span></h1>
      <p>Temukan kuliner tradisional berdasarkan jenis dan daerah asal</p>
    <?php endif; ?>
    <form action="filter.php" method="GET" style="width:100%;display:flex;justify-content:center;position:relative;margin-top:1.5rem;">
      <?php if ($kategori !== 'Semua'): ?><input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori) ?>"><?php endif; ?>
      <?php if ($daerah !== 'Semua'): ?><input type="hidden" name="daerah" value="<?= htmlspecialchars($daerah) ?>"><?php endif; ?>
      <?php if ($urutan !== 'terbaru'): ?><input type="hidden" name="urutan" value="<?= htmlspecialchars($urutan) ?>"><?php endif; ?>
      <div class="search-wrapper">
        <div class="search-bar">
          <i class="fas fa-search"></i>
          <input type="text" name="q" id="searchInput" autocomplete="off" placeholder="Cari makanan tradisional..." value="<?= htmlspecialchars($q) ?>">
          <button type="submit">Cari</button>
        </div>
        <div class="search-suggest" id="searchSuggest"></div>
      </div>
    </form>
  </div>
</div>

<div class="filter-outer">
  <div class="filter-card">
    <div class="filter-row">

      <div class="filter-group">
        <span class="filter-label">Jenis Makanan</span>
        <div class="type-btns">
          <button type="button" class="type-btn <?= $kategori === 'Semua' ? 'active' : '' ?>"
                  onclick="setFilter('kategori','Semua',this)">
            <span class="btn-emoji">🍱</span>
            <span class="btn-text">Semua</span>
          </button>
          <button type="button" class="type-btn <?= $kategori === 'Makanan' ? 'active' : '' ?>"
                  onclick="setFilter('kategori','Makanan',this)">
            <span class="btn-emoji">🍲</span>
            <span class="btn-text">Makanan</span>
          </button>
          <button type="button" class="type-btn <?= $kategori === 'Camilan' ? 'active' : '' ?>"
                  onclick="setFilter('kategori','Camilan',this)">
            <span class="btn-emoji">🍡</span>
            <span class="btn-text">Camilan</span>
          </button>
        </div>
      </div>

      <div class="filter-group">
        <span class="filter-label">Daerah Asal</span>
        <select class="filter-select" onchange="setSelectFilter('daerah',this.value)">
          <option value="Semua" <?= $daerah === 'Semua' ? 'selected' : '' ?>>🗺 Semua Daerah</option>
          <?php foreach ($daerah_list as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= $daerah === $d ? 'selected' : '' ?>>
              📍 <?= htmlspecialchars($d) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <span class="filter-label">Urutkan</span>
        <div class="sort-btns">
          <button type="button" class="sort-btn <?= $urutan === 'terbaru' ? 'active' : '' ?>"
                  onclick="setFilter('urutan','terbaru',this,'sort-btn')">
            <i class="fas fa-clock"></i> Terbaru
          </button>
          <button type="button" class="sort-btn <?= $urutan === 'abjad' ? 'active' : '' ?>"
                  onclick="setFilter('urutan','abjad',this,'sort-btn')">
            <i class="fas fa-sort-alpha-down"></i> A – Z
          </button>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="results-outer">
  <div class="results-meta">
    <h3>Hasil <span style="color:var(--gold);"><?= $q !== '' ? 'Pencarian' : 'Filter' ?></span></h3>
    <div style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;">
      <?php if ($q !== ''): ?>
        <span id="qBadge" title="Klik untuk ubah kata pencarian"
              style="background:rgba(245,166,35,.12);border:1.5px solid rgba(245,166,35,.45);color:var(--gold-dark);font-weight:700;font-size:.8rem;padding:.35rem .9rem;border-radius:50px;display:inline-flex;align-items:center;gap:.4rem;cursor:text;transition:.2s;position:relative;">
          <i class="fas fa-search" style="pointer-events:none;"></i>
          <!-- tampilan teks -->
          <span id="qText">"<?= htmlspecialchars($q) ?>"</span>
          <!-- input edit tersembunyi -->
          <input id="qInput" type="text" value="<?= htmlspecialchars($q) ?>"
                 style="display:none;border:none;outline:none;background:transparent;font-weight:700;font-size:.8rem;color:var(--gold-dark);font-family:'DM Sans',sans-serif;min-width:60px;max-width:180px;padding:0;">
          <i class="fas fa-pencil" id="qEditIcon" style="font-size:.65rem;opacity:.5;margin-left:.1rem;pointer-events:none;"></i>
        </span>
        <a href="filter.php?kategori=<?= urlencode($kategori) ?>&daerah=<?= urlencode($daerah) ?>&urutan=<?= urlencode($urutan) ?>"
           style="background:#fee2e2;color:#c0392b;font-weight:700;font-size:.8rem;padding:.35rem 1rem;border-radius:50px;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
          <i class="fas fa-times"></i> Hapus
        </a>
      <?php endif; ?>
      <span class="results-count"><i class="fas fa-utensils" style="margin-right:.4rem;"></i><?= $count ?> makanan ditemukan</span>
    </div>
  </div>

  <?php if ($count === 0): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fas fa-bowl-food"></i></div>
    <h3>Tidak Ada Hasil</h3>
    <p>Coba ubah filter atau pilih daerah lain</p>
  </div>
  <?php else: ?>
  <div class="food-grid">
    <?php foreach ($rows as $row): ?>
    <div class="food-card" onclick="location.href='detail.php?id=<?= $row['id'] ?>'">
      <img src="<?= htmlspecialchars($row['gambar']) ?>" alt="<?= htmlspecialchars($row['nama']) ?>">
      <div class="food-card-overlay">
        <div class="food-card-cat"><?= htmlspecialchars($row['kategori']) ?></div>
        <div class="food-card-name"><?= htmlspecialchars($row['nama']) ?></div>
        <div class="food-card-region"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($row['daerah']) ?></div>
      </div>
      <div class="food-card-arrow"><i class="fas fa-arrow-right" style="font-size:.75rem;"></i></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<footer>
  <div class="footer-inner">

    <div class="footer-brand">

      <div style="display:flex;align-items:center;gap:.8rem;">
        <img src="gambar/logoweb.svg" alt="Logo" style="height:48px;">

        <span style="font-weight:800;font-size:1.15rem;color:#fff;font-family:'Playfair Display',serif;">
          Jejak Rasa Nusantara
        </span>
      </div>

      <p>
        Platform digital pelestarian kuliner tradisional dari seluruh penjuru Indonesia.
      </p>

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
  // ── State filter (diambil dari PHP) ──
  const state = {
    kategori: '<?= addslashes($kategori) ?>',
    daerah:   '<?= addslashes($daerah) ?>',
    urutan:   '<?= addslashes($urutan) ?>',
    q:        '<?= addslashes($q) ?>'
  };

  function navigate() {
    const p = new URLSearchParams(state);
    if (!state.q) p.delete('q');
    window.location.href = 'filter.php?' + p.toString();
  }

  // Untuk type-btn (kategori) dan sort-btn (urutan)
  function setFilter(key, val, el, btnClass) {
    btnClass = btnClass || 'type-btn';
    // Hanya toggle active dalam kelompok tombol yang sama
    el.closest('.' + (btnClass === 'sort-btn' ? 'sort-btns' : 'type-btns'))
      .querySelectorAll('.' + btnClass)
      .forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    state[key] = val;
    navigate();
  }

  // Untuk select daerah
  function setSelectFilter(key, val) {
    state[key] = val;
    navigate();
  }

  // ── Inline edit badge keyword ──
  const qBadge = document.getElementById('qBadge');
  if (qBadge) {
    const qText      = document.getElementById('qText');
    const qInput     = document.getElementById('qInput');
    const qEditIcon  = document.getElementById('qEditIcon');

    function startEdit() {
      qText.style.display     = 'none';
      qEditIcon.style.display = 'none';
      qInput.style.display    = 'inline-block';
      qInput.style.width      = Math.max(qInput.value.length * 9, 60) + 'px';
      qBadge.style.borderColor = 'var(--gold)';
      qBadge.style.background  = 'rgba(245,166,35,.2)';
      qInput.focus();
      qInput.select();
    }

    function commitEdit() {
      const newQ = qInput.value.trim();
      if (newQ && newQ !== state.q) {
        state.q = newQ;
        navigate();
      } else if (!newQ) {
        state.q = '';
        navigate();
      } else {
        qText.style.display     = '';
        qEditIcon.style.display = '';
        qInput.style.display    = 'none';
        qBadge.style.borderColor = '';
        qBadge.style.background  = '';
      }
    }

    qBadge.addEventListener('click', () => {
      if (qInput.style.display === 'none') startEdit();
    });

    qInput.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); commitEdit(); }
      if (e.key === 'Escape') {
        qInput.value = state.q;
        qText.style.display = '';
        qEditIcon.style.display = '';
        qInput.style.display = 'none';
        qBadge.style.borderColor = '';
        qBadge.style.background  = '';
      }
      setTimeout(() => { qInput.style.width = Math.max(qInput.value.length * 9, 60) + 'px'; }, 0);
    });

    qInput.addEventListener('blur', commitEdit);

    qBadge.addEventListener('mouseenter', () => {
      if (qInput.style.display === 'none') qBadge.style.borderColor = 'var(--gold)';
    });
    qBadge.addEventListener('mouseleave', () => {
      if (qInput.style.display === 'none') qBadge.style.borderColor = '';
    });
  }

  // AUTOCOMPLETE SEARCH
  const searchInput = document.getElementById('searchInput');
  const searchSuggest = document.getElementById('searchSuggest');
  if(searchInput){
    searchInput.addEventListener('input', async function(){
      const keyword = this.value.trim();
      if(keyword.length < 1){ searchSuggest.style.display='none'; searchSuggest.innerHTML=''; return; }
      try {
        const response = await fetch('search_ajax.php?q=' + encodeURIComponent(keyword));
        const data = await response.text();
        searchSuggest.innerHTML = data;
        searchSuggest.style.display = 'block';
      } catch(err){ console.log(err); }
    });
    document.addEventListener('click', function(e){
      if(!document.querySelector('.search-wrapper').contains(e.target))
        searchSuggest.style.display = 'none';
    });
  }

  // Navbar
  document.getElementById('hamburger').addEventListener('click', () =>
    document.getElementById('mobileMenu').classList.toggle('open'));

  const pb = document.getElementById('profileBtn');
  if (pb) {
    pb.addEventListener('click', () => document.getElementById('profileMenu').classList.toggle('open'));
    document.addEventListener('click', e => {
      if (!document.getElementById('profileWrap').contains(e.target))
        document.getElementById('profileMenu').classList.remove('open');
    });
  }
</script>
</body>
</html>