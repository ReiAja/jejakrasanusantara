<?php include 'koneksi.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="https://jejakrasa.site.je/gambar/logojr.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Beranda – Jejak Rasa Nusantara</title>
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
      --card-shadow: 0 8px 32px rgba(0,0,0,0.10);
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
      background: linear-gradient(135deg, #1A1208 0%, #2C1D0A 50%, #3D2A0D 100%);
      padding: 5rem 2rem 4rem;
      position: relative;
      z-index: 20;
      overflow: visible;
    }
    .hero::before {
      content:'';
      position:absolute; inset:0;
      background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23F5A623' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .hero-inner { max-width:1280px; margin:auto; display:flex; flex-direction:column; align-items:center; text-align:center; position:relative; gap:2rem; }
    .hero-badge { background:rgba(245,166,35,0.15); border:1px solid rgba(245,166,35,0.4); color:var(--gold); font-size:.8rem; font-weight:600; padding:.35rem 1rem; border-radius:50px; letter-spacing:.5px; text-transform:uppercase; }
    .hero h1 { font-family:'Playfair Display',serif; font-size:clamp(2.6rem,6vw,4.5rem); font-weight:900; color:#fff; line-height:1.12; }
    .hero h1 span { color:var(--gold); }
    .hero p { color:rgba(255,255,255,.6); max-width:540px; line-height:1.75; font-size:1.05rem; }
    .search-bar { display:flex; align-items:center; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.15); border-radius:16px; padding:.5rem .5rem .5rem 1.2rem; width:100%; gap:.5rem; backdrop-filter:blur(8px); }
    .search-bar i { color:rgba(255,255,255,.4); }
    .search-bar input { background:none; border:none; outline:none; color:#fff; flex:1; font-size:1rem; font-family:'DM Sans',sans-serif; }
    .search-bar input::placeholder { color:rgba(255,255,255,.35); }
    .search-bar button { background:var(--gold); color:#fff; border:none; border-radius:10px; padding:.65rem 1.4rem; font-weight:700; font-size:.9rem; cursor:pointer; transition:.2s; white-space:nowrap; }
    .search-bar button:hover { background:var(--gold-dark); }
    /* SEARCH AUTOCOMPLETE */
    .search-wrapper{
      width:100%;
      max-width:620px;
      position:relative;
    }

    .search-suggest{
      position:absolute;
      top:100%;
      left:0;
      right:0;
      margin-top:.6rem;
      background:#1A1208;
      border-radius:16px;
      box-shadow:0 15px 40px rgba(0,0,0,.35);
      display:none;
      z-index:999;
      border:1px solid rgba(255,255,255,.08);
    }

    .search-item{
      display:flex !important;
      align-items:center;
      gap:.9rem;
      padding:.85rem 1rem;
      text-decoration:none;
      transition:.18s;
      border-bottom:1px solid rgba(255,255,255,.05);
      width: 100%;
    }

    .search-item:last-child{
      border-bottom:none;
    }

    .search-item:hover{
      background:rgba(255,255,255,.06);
    }

    .search-item img{
      width:52px;
      height:40px;
      object-fit:cover;
      border-radius:8px;
      flex-shrink:0;
    }

    .search-item-text{
      flex:1;
    }

    .search-item-name{
      color:#fff;
      font-weight:700;
      font-size:.92rem;
    }

    .search-item-cat{
      color:rgba(255,255,255,.55);
      font-size:.78rem;
      margin-top:.15rem;
    }

    .search-empty{
      padding:1rem;
      color:rgba(255,255,255,.5);
      font-size:.85rem;
    }

    /* SECTION */
    .section { max-width:1280px; margin:0 auto; padding:3.5rem 2rem; }
    .section-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:2rem; }
    .section-head h2 { font-family:'Playfair Display',serif; font-size:1.9rem; font-weight:800; }
    .section-head a { color:var(--gold); font-size:.9rem; font-weight:600; text-decoration:none; display:flex; align-items:center; gap:.4rem; }
    .section-head a:hover { color:var(--gold-dark); }

    /* FOOD GRID */
    .food-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:1.6rem; }
    .food-card { position:relative; border-radius:20px; overflow:hidden; aspect-ratio:4/3; cursor:pointer; box-shadow:var(--card-shadow); }
    .food-card img { width:100%; height:100%; object-fit:cover; transition:.5s cubic-bezier(.4,0,.2,1); }
    .food-card:hover img { transform:scale(1.07); }
    .food-card-overlay {
      position:absolute; inset:0;
      background:linear-gradient(to top, rgba(15,8,2,.85) 30%, transparent 70%);
      display:flex; flex-direction:column; justify-content:flex-end; padding:1.4rem;
      transition:.3s;
    }
    .food-card:hover .food-card-overlay { background:linear-gradient(to top, rgba(15,8,2,.92) 40%, rgba(15,8,2,.15) 100%); }
    .food-card-cat { font-size:.7rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:var(--gold); margin-bottom:.3rem; }
    .food-card-name { font-family:'Playfair Display',serif; font-size:1.25rem; font-weight:700; color:#fff; line-height:1.3; }
    .food-card-region { font-size:.8rem; color:rgba(255,255,255,.6); margin-top:.3rem; display:flex; align-items:center; gap:.3rem; }
    .food-card-arrow { position:absolute; top:1rem; right:1rem; background:rgba(255,255,255,.12); border-radius:50%; width:34px; height:34px; display:flex; align-items:center; justify-content:center; color:#fff; opacity:0; transition:.3s; }
    .food-card:hover .food-card-arrow { opacity:1; transform:translateX(0); }

    /* FEATURED STRIP */
    .featured-strip { background:linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); border-radius:24px; padding:2.5rem; display:flex; align-items:center; gap:2rem; margin:0 2rem; max-width:1280px; margin-left:auto; margin-right:auto; }
    .featured-strip-text h3 { font-family:'Playfair Display',serif; font-size:1.6rem; font-weight:800; color:#fff; }
    .featured-strip-text p { color:rgba(255,255,255,.8); margin-top:.4rem; font-size:.95rem; }
    .featured-strip a { margin-left:auto; white-space:nowrap; background:#fff; color:var(--gold-dark); padding:.75rem 1.6rem; border-radius:12px; font-weight:700; text-decoration:none; font-size:.9rem; transition:.2s; flex-shrink:0; }
    .featured-strip a:hover { background:var(--cream); }

    /* FOOTER */
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

    @media (max-width:768px) {
      .nav-links { display:none; }
      .hamburger { display:block; }
      .footer-inner { grid-template-columns:1fr 1fr; }
      .featured-strip { flex-direction:column; }
      .featured-strip a { margin-left:0; align-self:flex-start; }
    }
    @media (max-width:480px) {
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
      <a href="index.php" class="active">Beranda</a>
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
            <a href="profile.php">            <i class="fas fa-user" style="margin-right:.5rem;color:var(--gold);"></i><span>Profile</span></a>
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
    <a href="index.php" class="active">Beranda</a>
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

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">
    <h1>Jejak <span>Rasa</span><br>Nusantara</h1>
    <form action="filter.php" method="GET" style="width:100%;display:flex;justify-content:center;position:relative;">
      <div class="search-wrapper">
        
        <div class="search-bar">
          <i class="fas fa-search"></i>

          <input 
            type="text" 
            name="q" 
            id="searchInput"
            autocomplete="off"
            placeholder="Cari makanan tradisional..."
          >

          <button type="submit">Cari</button>
        </div>

        <!-- HASIL REKOMENDASI -->
        <div class="search-suggest" id="searchSuggest"></div>

      </div>
    </form>
  </div>
</section>

<!-- MAKANAN POPULER -->
<div class="section">
  <div class="section-head">
    <h2>Makanan <span style="color:var(--gold);">Populer</span></h2>
    <a href="filter.php?kategori=Makanan+Utama">Lihat Semua <i class="fas fa-arrow-right"></i></a>
  </div>
  <div class="food-grid">
    <?php
    $q_utama = mysqli_query($koneksi, "SELECT * FROM makanan WHERE kategori='Makanan' ORDER BY id DESC LIMIT 8");
    while ($row = mysqli_fetch_assoc($q_utama)):
    ?>
    <a href="detail.php?id=<?= $row['id'] ?>" style="text-decoration:none;">
      <div class="food-card">
        <img src="<?= htmlspecialchars($row['gambar']) ?>" alt="<?= htmlspecialchars($row['nama']) ?>">
        <div class="food-card-overlay">
          <div class="food-card-cat"><?= htmlspecialchars($row['kategori']) ?></div>
          <div class="food-card-name"><?= htmlspecialchars($row['nama']) ?></div>
          <div class="food-card-region"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($row['daerah']) ?></div>
        </div>
        <div class="food-card-arrow"><i class="fas fa-arrow-right" style="font-size:.75rem;"></i></div>
      </div>
    </a>
    <?php endwhile; ?>
  </div>
</div>

<!-- CTA STRIP -->
<div class="featured-strip">
  <div class="featured-strip-text">
    <h3>Filter Berdasarkan Daerah dan Jenis</h3>
    <p>Temukan kuliner khas daerahmu atau eksplorasi rasa baru dari seluruh Nusantara.</p>
  </div>
  <a href="filter.php">Filter</a>
</div>

<!-- CAMILAN TRADISIONAL -->
<div class="section">
  <div class="section-head">
    <h2>Cemilan <span style="color:var(--red);">Tradisional</span></h2>
    <a href="filter.php?kategori=Camilan" style="color:var(--red);">Lihat Semua <i class="fas fa-arrow-right"></i></a>
  </div>
  <div class="food-grid">
    <?php
    $q_camilan = mysqli_query($koneksi, "SELECT * FROM makanan WHERE kategori='Camilan' ORDER BY id DESC LIMIT 4");
    while ($row = mysqli_fetch_assoc($q_camilan)):
    ?>
    <a href="detail.php?id=<?= $row['id'] ?>" style="text-decoration:none;">
      <div class="food-card">
        <img src="<?= htmlspecialchars($row['gambar']) ?>" alt="<?= htmlspecialchars($row['nama']) ?>">
        <div class="food-card-overlay">
          <div class="food-card-cat"><?= htmlspecialchars($row['kategori']) ?></div>
          <div class="food-card-name"><?= htmlspecialchars($row['nama']) ?></div>
          <div class="food-card-region"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($row['daerah']) ?></div>
        </div>
        <div class="food-card-arrow"><i class="fas fa-arrow-right" style="font-size:.75rem;"></i></div>
      </div>
    </a>
    <?php endwhile; ?>
  </div>
</div>

<!-- FOOTER -->
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

    // AUTOCOMPLETE SEARCH
  const searchInput = document.getElementById('searchInput');
  const searchSuggest = document.getElementById('searchSuggest');

  if(searchInput){

    searchInput.addEventListener('input', async function(){

      const keyword = this.value.trim();

      if(keyword.length < 1){
        searchSuggest.style.display = 'none';
        searchSuggest.innerHTML = '';
        return;
      }

      try {

        const response = await fetch('search_ajax.php?q=' + encodeURIComponent(keyword));
        const data = await response.text();

        searchSuggest.innerHTML = data;
        searchSuggest.style.display = 'block';

      } catch(err){
        console.log(err);
      }

    });

    // klik luar tutup
    document.addEventListener('click', function(e){
      if(!document.querySelector('.search-wrapper').contains(e.target)){
        searchSuggest.style.display = 'none';
      }
    });

  }
</script>
</body>
</html>