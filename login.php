<?php
// Pastikan session dimulai di bagian paling atas sebelum ada output apa pun
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'koneksi.php';

// Redirect jika sudah login
if (isLogin()) {
    header("Location: index.php");
    exit;
}

// ── MEMBUAT URL LOGIN GOOGLE ──
$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'email profile'
]);

$error_login   = "";
$msg_register  = "";
$show_register = false;

// ── PROSES REGISTRASI (Aman dari SQL Injection via Prepared Statements) ──
if (isset($_POST['register'])) {
    $show_register = true;
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($password)) {
        $msg_register = "Semua kolom wajib diisi!";
    } elseif ($password !== $confirm) {
        $msg_register = "Konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $msg_register = "Password minimal 6 karakter!";
    } else {
        // Cek apakah username atau email sudah ada dengan Prepared Statement
        $stmt_check = $koneksi->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt_check->bind_param("ss", $username, $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $msg_register = "Username atau email sudah terdaftar!";
            $stmt_check->close();
        } else {
            $stmt_check->close();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';

            // Insert user baru dengan Prepared Statement
            $stmt_insert = $koneksi->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $username, $email, $hash, $role);

            if ($stmt_insert->execute()) {
                $new_user_id = $koneksi->insert_id;
                $stmt_insert->close();

                // ── Auto-login setelah registrasi berhasil ──
                session_regenerate_id(true);

                $_SESSION['user_id']  = $new_user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email']    = $email;
                $_SESSION['role']     = $role;
                $_SESSION['foto']     = null;

                header("Location: index.php");
                exit;
            } else {
                $msg_register = "Terjadi kesalahan, coba lagi.";
                $stmt_insert->close();
            }
        }
    }
}

// ── PROSES LOGIN (Aman dari SQL Injection & Mengatasi Bug Session) ──
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Ambil data user menggunakan Prepared Statement
    $stmt_login = $koneksi->prepare("SELECT id, username, email, password, role, foto FROM users WHERE username = ?");
    $stmt_login->bind_param("s", $username);
    $stmt_login->execute();
    $result = $stmt_login->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {

            // Keamanan: Regenerasi ID Session setelah login sukses untuk mencegah Session Hijacking
            session_regenerate_id(true);

            $_SESSION['user_id']  = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['email']    = $row['email'];
            $_SESSION['role']     = $row['role'];
            $_SESSION['foto']     = $row['foto'];

            $stmt_login->close();
            header("Location: " . ($row['role'] === 'admin' ? 'dashboard.php' : 'index.php'));
            exit;
        }
    }
    $stmt_login->close();
    $error_login = "Username atau password salah!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="https://jejakrasa.site.je/gambar/logojr.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk – Jejak Rasa Nusantara</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --gold: #F5A623;
      --gold-dark: #D4891A;
      --dark: #1A1208;
      --cream: #FDF8F0;
    }
    * { box-sizing:border-box; }
    body {
      margin:0;
      font-family:'DM Sans', sans-serif;
      background:var(--cream);
      color:var(--dark);
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:1.5rem;
    }

    /* ── CARD WRAPPER ── */
    .auth-card {
      width:100%;
      max-width:920px;
      background:#fff;
      border-radius:28px;
      overflow:hidden;
      box-shadow:0 24px 60px rgba(26,18,8,.12);
      display:flex;
      min-height:600px;
      position:relative;
    }

    /* ── LEFT BRAND PANEL ── */
    .auth-side{
        flex:1 1 42%;
        background:linear-gradient(135deg,#1A1208 0%,#2C1D0A 100%);
        color:#fff;
        padding:3rem 2.5rem;
        display:flex;
        flex-direction:column;
        justify-content:flex-start;
        gap:50px;
        position:relative;
        overflow:hidden;
    }
    .auth-side::before {
      content:'';
      position:absolute; inset:0;
      background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23F5A623' fill-opacity='0.06'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      pointer-events:none;
    }
    .auth-side-top, 
    .auth-side-bottom { position:relative; z-index:1; }
    .auth-logo { display:flex; align-items:center; gap:.75rem; }
    .auth-logo img { height:46px; }
    .auth-logo span {
      font-family:'Playfair Display', serif;
      font-weight:800; font-size:1.15rem; color:#fff;
    }
    .auth-side-bottom h1 {
      font-family:'Playfair Display', serif;
      font-weight:800; font-size:2rem; line-height:1.25;
      margin:0 0 .75rem;
    }
    .auth-side-bottom h1 span { color:var(--gold); }
    .auth-side-bottom p {
      color:rgba(255,255,255,.55); font-size:.9rem; line-height:1.7; max-width:320px;
    }
    .auth-feature-list { margin-top:1.75rem; display:flex; flex-direction:column; gap:.85rem; }
    .auth-feature {
      display:flex; align-items:center; gap:.75rem;
      font-size:.85rem; color:rgba(255,255,255,.75);
    }
    .auth-feature i {
      width:32px; height:32px; border-radius:10px;
      background:rgba(245,166,35,.15); border:1px solid rgba(245,166,35,.3);
      display:flex; align-items:center; justify-content:center;
      color:var(--gold); font-size:.85rem; flex-shrink:0;
    }

    /* ── RIGHT FORM PANEL ── */
    .auth-form-panel {
      flex:1 1 58%;
      padding:3rem 3rem;
      position:relative;
      display:flex;
      flex-direction:column;
      justify-content:center;
      max-height:100%;
      overflow-y:auto;
    }
    .login-box.hidden { display:none; }

    .close-btn {
      position:absolute; top:1.25rem; right:1.25rem;
      width:36px; height:36px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      background:#F3F0EB; color:#888; text-decoration:none;
      font-size:1.1rem; font-weight:700; line-height:1;
      transition:.2s;
    }
    .close-btn:hover { background:#EDE8E0; color:#444; }

    .auth-mobile-logo { display:none; }

    .auth-title {
      font-family:'Playfair Display', serif;
      font-weight:800; font-size:1.7rem; color:var(--dark);
      margin-bottom:.35rem;
    }
    .auth-subtitle {
      color:#999; font-size:.875rem; margin-bottom:1.75rem;
    }

    /* ── ALERTS ── */
    .alert {
      display:flex; align-items:center; gap:.65rem;
      padding:.8rem 1.1rem; border-radius:12px;
      font-size:.85rem; font-weight:600; margin-bottom:1.25rem;
    }
    .alert-success { background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0; }
    .alert-error   { background:#FEE2E2; color:#991B1B; border:1px solid #FECACA; }

    /* ── INPUTS ── */
    .input-group { margin-bottom:1.1rem; }
    .input-icon-wrap { position:relative; }
    .input-icon-wrap i.field-icon {
      position:absolute; left:1rem; top:50%; transform:translateY(-50%);
      color:#bbb; font-size:.9rem; pointer-events:none;
    }
    .input-field {
      width:100%;
      padding:.85rem 1.1rem .85rem 2.75rem;
      border:2px solid #EDE8E0;
      border-radius:14px;
      font-family:'DM Sans', sans-serif;
      font-size:.95rem;
      color:var(--dark);
      background:#FAFAF8;
      transition:.2s;
      outline:none;
    }
    .input-field:focus {
      border-color:var(--gold); background:#fff;
      box-shadow:0 0 0 4px rgba(245,166,35,.08);
    }
    .input-field::placeholder { color:#bbb; }
    .input-field.has-toggle { padding-right:2.75rem; }
    .toggle-pw {
      position:absolute; right:1rem; top:50%; transform:translateY(-50%);
      color:#bbb; font-size:.9rem; cursor:pointer; transition:.2s;
    }
    .toggle-pw:hover { color:var(--gold-dark); }

    /* ── BUTTONS ── */
    .btn-auth {
      width:100%; padding:.9rem;
      background:linear-gradient(135deg, var(--gold), var(--gold-dark));
      color:#fff; border:none; border-radius:14px;
      font-weight:700; font-size:.95rem; cursor:pointer;
      font-family:'DM Sans', sans-serif; transition:.2s;
      box-shadow:0 4px 16px rgba(245,166,35,.3);
      margin-top:.35rem;
    }
    .btn-auth:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(245,166,35,.4); }

    .btn-google {
      display:flex; align-items:center; justify-content:center; gap:.6rem;
      width:100%; padding:.85rem 1.1rem;
      background:#fff; border:1.5px solid #EDE8E0; border-radius:14px;
      color:#555; font-weight:600; font-size:.9rem;
      text-decoration:none; transition:.2s;
    }
    .btn-google:hover { background:#FAFAF8; border-color:#ddd; }

    .divider {
      display:flex; align-items:center; gap:.85rem;
      margin:1.25rem 0; color:#bbb; font-size:.8rem;
    }
    .divider::before, .divider::after {
      content:''; flex:1; height:1px; background:#EDE8E0;
    }

    .switch-text {
      text-align:center; font-size:.875rem; color:#888;
      margin-top:1.5rem;
    }
    .switch-link {
      color:var(--gold-dark); font-weight:700; text-decoration:none;
    }
    .switch-link:hover { text-decoration:underline; }

    /* ── RESPONSIVE ── */
    @media (max-width:860px) {
      body { padding:0; align-items:flex-start; }
      .auth-card {
        max-width:100%; min-height:100vh; border-radius:0;
        flex-direction:column; box-shadow:none;
      }
      .auth-side { display:none; }
      .auth-mobile-logo {
        display:flex; align-items:center; justify-content:center; gap:.65rem;
        padding:1.75rem 1.5rem .5rem;
      }
      .auth-mobile-logo img { height:42px; }
      .auth-mobile-logo span {
        font-family:'Playfair Display', serif; font-weight:800; font-size:1.05rem; color:var(--dark);
      }
      .auth-form-panel {
        flex:1; padding:1rem 1.5rem 2.5rem; justify-content:flex-start;
        max-height:none; overflow-y:visible;
      }
      .auth-title { font-size:1.45rem; text-align:center; }
      .auth-subtitle { text-align:center; margin-bottom:1.5rem; }
      .close-btn { top:.85rem; right:.85rem; }
    }

    @media (max-width:420px) {
      .auth-form-panel { padding:.75rem 1.1rem 2rem; }
      .input-field { padding:.78rem 1rem .78rem 2.5rem; font-size:.9rem; }
      .input-icon-wrap i.field-icon { left:.9rem; }
      .toggle-pw { right:.9rem; }
      .input-field.has-toggle { padding-right:2.5rem; }
      .btn-auth, .btn-google { padding:.8rem; font-size:.875rem; }
    }
  </style>
</head>
<body>

  <div class="auth-card">

    <!-- ── LEFT BRAND PANEL (desktop only) ── -->
    <div class="auth-side">
      <div class="auth-side-top">
        <div class="auth-logo">
          <img src="https://jejakrasa.site.je/gambar/logoweb.svg" alt="Logo">
          <span>Jejak Rasa Nusantara</span>
        </div>
      </div>
      <div class="auth-side-bottom">
        <h1>Lestarikan <span>Kuliner Nusantara</span> Bersama Kami</h1>
        <p>Jelajahi, simpan, dan bagikan resep tradisional dari seluruh penjuru Indonesia.</p>
        <div class="auth-feature-list">
          <div class="auth-feature"><i class="fas fa-utensils"></i> Koleksi resep otentik dari berbagai daerah</div>
          <div class="auth-feature"><i class="fas fa-heart"></i> Simpan kuliner favoritmu</div>
          <div class="auth-feature"><i class="fas fa-map-marked-alt"></i> Telusuri kuliner berdasarkan daerah</div>
        </div>
      </div>
    </div>

    <!-- ── RIGHT FORM PANEL ── -->
    <div class="auth-form-panel">

      <!-- Mobile logo -->
      <div class="auth-mobile-logo">
        <img src="https://jejakrasa.site.je/gambar/logoweb.svg" alt="Logo">
      </div>

      <!-- ══ LOGIN BOX ══ -->
      <div id="loginBox" class="login-box <?= $show_register ? 'hidden' : '' ?>">
        <a href="index.php" class="close-btn">&times;</a>

        <h2 class="auth-title">Masuk Akun</h2>
        <p class="auth-subtitle">Selamat datang kembali! Silakan masuk untuk melanjutkan.</p>

        <?php if (!empty($error_login)): ?>
          <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_login) ?></div>
        <?php endif; ?>
        <?php if (!$show_register && !empty($msg_register)): ?>
          <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg_register) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
          <div class="input-group">
            <div class="input-icon-wrap">
              <i class="fas fa-user field-icon"></i>
              <input type="text" name="username" placeholder="Username" required class="input-field">
            </div>
          </div>
          <div class="input-group">
            <div class="input-icon-wrap">
              <i class="fas fa-lock field-icon"></i>
              <input type="password" name="password" id="loginPw" placeholder="Password" required class="input-field has-toggle">
              <i class="fas fa-eye toggle-pw" data-target="loginPw"></i>
            </div>
          </div>
          <button type="submit" name="login" class="btn-auth">MASUK</button>
        </form>

        <div class="divider">atau</div>

        <a href="<?= $google_login_url ?>" class="btn-google">
          <svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
          </svg>
          Masuk dengan Google
        </a>

        <p class="switch-text">Belum punya akun?
          <a href="#" id="toRegister" class="switch-link">Daftar sekarang!</a>
        </p>
      </div>

      <!-- ══ REGISTER BOX ══ -->
      <div id="registerBox" class="login-box <?= $show_register ? '' : 'hidden' ?>">
        <a href="index.php" class="close-btn">&times;</a>

        <h2 class="auth-title">Daftar Akun Baru</h2>
        <p class="auth-subtitle">Buat akun untuk mulai menjelajahi kuliner Nusantara.</p>

        <?php if (!empty($msg_register) && $show_register): ?>
          <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($msg_register) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
          <div class="input-group">
            <div class="input-icon-wrap">
              <i class="fas fa-user field-icon"></i>
              <input type="text" name="username" placeholder="Username" required class="input-field">
            </div>
          </div>
          <div class="input-group">
            <div class="input-icon-wrap">
              <i class="fas fa-envelope field-icon"></i>
              <input type="email" name="email" placeholder="Email" required class="input-field">
            </div>
          </div>
          <div class="input-group">
            <div class="input-icon-wrap">
              <i class="fas fa-lock field-icon"></i>
              <input type="password" name="password" id="regPw" placeholder="Password (min. 6)" required class="input-field has-toggle">
              <i class="fas fa-eye toggle-pw" data-target="regPw"></i>
            </div>
          </div>
          <div class="input-group">
            <div class="input-icon-wrap">
              <i class="fas fa-lock field-icon"></i>
              <input type="password" name="confirm_password" id="regConfirmPw" placeholder="Konfirmasi Password" required class="input-field has-toggle">
              <i class="fas fa-eye toggle-pw" data-target="regConfirmPw"></i>
            </div>
          </div>
          <button type="submit" name="register" class="btn-auth">DAFTAR</button>
        </form>

        <div class="divider">atau</div>

        <a href="<?= $google_login_url ?>" class="btn-google">
          <svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
          </svg>
          Daftar dengan Google
        </a>

        <p class="switch-text">Sudah punya akun?
          <a href="#" id="toLogin" class="switch-link">Masuk di sini</a>
        </p>
      </div>

    </div>
  </div>

  <script>
    document.getElementById('toRegister').addEventListener('click', e => {
      e.preventDefault();
      document.getElementById('loginBox').classList.add('hidden');
      document.getElementById('registerBox').classList.remove('hidden');
      window.scrollTo({top: 0, behavior: 'smooth'});
    });
    document.getElementById('toLogin').addEventListener('click', e => {
      e.preventDefault();
      document.getElementById('registerBox').classList.add('hidden');
      document.getElementById('loginBox').classList.remove('hidden');
      window.scrollTo({top: 0, behavior: 'smooth'});
    });

    // ── Toggle show/hide password ──
    document.querySelectorAll('.toggle-pw').forEach(icon => {
      icon.addEventListener('click', () => {
        const target = document.getElementById(icon.dataset.target);
        if (!target) return;
        if (target.type === 'password') {
          target.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        } else {
          target.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      });
    });
  </script>
</body>
</html>