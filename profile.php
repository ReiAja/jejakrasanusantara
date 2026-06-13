<?php
include 'koneksi.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$msg     = '';
$msg_type = '';

// Ambil data user terbaru
$res  = mysqli_query($koneksi, "SELECT * FROM users WHERE id=$user_id");
$user = mysqli_fetch_assoc($res);

// ── HAPUS FOTO PROFIL ──
if (isset($_POST['hapus_foto'])) {

    // Hapus file lama jika ada
    if (!empty($user['foto']) && file_exists('uploads/' . $user['foto'])) {
        @unlink('uploads/' . $user['foto']);
    }

    // Kosongkan foto di database
    mysqli_query($koneksi, "UPDATE users SET foto=NULL WHERE id=$user_id");

    // Update session
    $_SESSION['foto'] = null;

    // Refresh data user
    $res  = mysqli_query($koneksi, "SELECT * FROM users WHERE id=$user_id");
    $user = mysqli_fetch_assoc($res);

    $msg = "Foto profil berhasil dihapus!";
    $msg_type = 'success';
}

// Proses update profil
if (isset($_POST['update'])) {
    $username = $user['username'];
    $email = $user['email'];
    $current_password = $_POST['current_password'] ?? '';
    $password = $_POST['password'];
    $foto_val = $user['foto'];

    $cek = mysqli_query($koneksi, "SELECT id FROM users WHERE (username='$username' OR email='$email') AND id != $user_id");
    if (mysqli_num_rows($cek) > 0) {
        $msg = "Username atau email sudah dipakai akun lain!";
        $msg_type = 'error';
    } else {
        // Upload foto via base64 (hasil crop)
        if (!empty($_POST['foto_crop_data'])) {
            $data = $_POST['foto_crop_data'];
            if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/i', $data, $m)) {
                $imgData   = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $data));
                $ext       = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                $filename  = 'foto_'.$user_id.'_'.time().'.'.$ext;
                $upload_to = 'uploads/' . $filename;
                if (file_put_contents($upload_to, $imgData)) {
                    if ($user['foto'] && file_exists('uploads/'.$user['foto'])) {
                        @unlink('uploads/'.$user['foto']);
                    }
                    $foto_val = $filename;
                } else {
                    $msg = "Gagal menyimpan foto!";
                    $msg_type = 'error';
                }
            } else {
                $msg = "Format foto tidak valid!";
                $msg_type = 'error';
            }
        }
        // Upload foto biasa (fallback)
        elseif (!empty($_FILES['foto']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed)) {
                $filename  = 'foto_'.$user_id.'_'.time().'.'.$ext;
                $upload_to = 'uploads/' . $filename;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_to)) {
                    if ($user['foto'] && file_exists('uploads/'.$user['foto'])) {
                        @unlink('uploads/'.$user['foto']);
                    }
                    $foto_val = $filename;
                }
            } else {
                $msg = "Format foto harus JPG, PNG, atau WebP!";
                $msg_type = 'error';
            }
        }

        if (empty($msg)) {
            if (!empty($password)) {
                if (empty($current_password)) {
                    $msg = "Masukkan password lama untuk mengganti password!";
                    $msg_type = 'error';
                } elseif (!password_verify($current_password, $user['password'])) {
                    $msg = "Password lama yang Anda masukkan salah!";
                    $msg_type = 'error';
                } elseif (strlen($password) < 6) {
                    $msg = "Password baru minimal 6 karakter!";
                    $msg_type = 'error';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    mysqli_query($koneksi, "UPDATE users SET username='$username', email='$email', password='$hash', foto='$foto_val' WHERE id=$user_id");
                    $msg = "Profil berhasil diperbarui!";
                    $msg_type = 'success';
                }
            } else {
                mysqli_query($koneksi, "UPDATE users SET username='$username', email='$email', foto='$foto_val' WHERE id=$user_id");
                $msg = "Profil berhasil diperbarui!";
                $msg_type = 'success';
            }

            if ($msg_type === 'success') {
                $_SESSION['username'] = $username;
                $_SESSION['email']    = $email;
                $_SESSION['foto']     = $foto_val;
                $res  = mysqli_query($koneksi, "SELECT * FROM users WHERE id=$user_id");
                $user = mysqli_fetch_assoc($res);
            }
        }
    }
}

$foto_src = $user['foto'] ? 'uploads/'.$user['foto'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png';

// Ambil resep tersimpan milik user
$fav_res = mysqli_query($koneksi,
    "SELECT m.* FROM favorit f
     JOIN makanan m ON f.makanan_id = m.id
     WHERE f.user_id = $user_id
     ORDER BY f.created_at DESC");
$fav_list = [];
while ($row = mysqli_fetch_assoc($fav_res)) $fav_list[] = $row;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="https://jejakrasa.site.je/gambar/logojr.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Saya – Jejak Rasa Nusantara</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <!-- Cropper.js -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
  <style>
    :root {
      --gold: #F5A623;
      --gold-dark: #D4891A;
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
    .nav-links a:hover { color:var(--gold); }
    .nav-links a:hover::after { width:100%; }
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

    /* PAGE LAYOUT */
    .page-wrap { max-width:780px; margin:3rem auto; padding:0 1.5rem 5rem; }

    /* PROFILE HEADER CARD */
    .profile-header {
      background:linear-gradient(135deg, #1A1208 0%, #2C1D0A 100%);
      border-radius:24px;
      padding:2.5rem 2.5rem 5rem;
      position:relative;
      margin-bottom:0;
      overflow:hidden;
    }
    .profile-header::before {
      content:'';
      position:absolute; inset:0;
      background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23F5A623' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .profile-header-inner { position:relative; display:flex; align-items:center; gap:1.5rem; }
    .profile-header h2 { font-family:'Playfair Display',serif; font-size:1.6rem; font-weight:800; color:#fff; }
    .profile-header p { color:rgba(255,255,255,.45); font-size:.85rem; margin-top:.25rem; }
    .role-badge { display:inline-block; background:rgba(245,166,35,.2); border:1px solid rgba(245,166,35,.4); color:var(--gold); font-size:.72rem; font-weight:700; padding:.2rem .75rem; border-radius:50px; letter-spacing:.5px; text-transform:uppercase; margin-top:.5rem; }

    /* AVATAR */
    .avatar-section {
      display:flex; flex-direction:column; align-items:center;
      margin-top:-72px; margin-bottom:1.75rem;
      position:relative; z-index:10;
    }
    .avatar-ring {
      width:130px; height:130px; border-radius:50%; padding:4px;
      background:linear-gradient(135deg, var(--gold), var(--gold-dark));
      box-shadow:0 8px 32px rgba(245,166,35,.35);
      cursor:pointer; position:relative;
    }
    .avatar-ring img {
      width:100%; height:100%; border-radius:50%;
      object-fit:cover; border:4px solid var(--cream); display:block;
    }
    .avatar-overlay {
      position:absolute; inset:0; border-radius:50%;
      background:rgba(0,0,0,.45);
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      opacity:0; transition:.25s; cursor:pointer;
    }
    .avatar-ring:hover .avatar-overlay { opacity:1; }
    .avatar-overlay i { color:#fff; font-size:1.4rem; }
    .avatar-overlay span { color:#fff; font-size:.72rem; font-weight:600; margin-top:.3rem; }
    .avatar-name { font-family:'Playfair Display',serif; font-size:1.25rem; font-weight:800; color:var(--dark); margin-top:.85rem; }
    .avatar-email { color:#888; font-size:.85rem; margin-top:.2rem; }

    /* FORM CARD */
    .form-card {
      background:#fff; border-radius:24px;
      box-shadow:0 4px 24px rgba(0,0,0,.07);
      border:1px solid #f0e8d8; padding:2.5rem;
    }

    /* INPUT GROUP */
    .input-group { margin-bottom:1.4rem; }
    .input-group label {
      display:block; font-size:.8rem; font-weight:700; color:#555;
      letter-spacing:.4px; text-transform:uppercase; margin-bottom:.5rem;
    }
    .input-field {
      width:100%; padding:.85rem 1.1rem;
      border:2px solid #EDE8E0; border-radius:14px;
      font-family:'DM Sans',sans-serif; font-size:.95rem;
      color:var(--dark); background:#FAFAF8;
      transition:.2s; outline:none;
    }
    .input-field:focus { border-color:var(--gold); background:#fff; box-shadow:0 0 0 4px rgba(245,166,35,.08); }
    .input-field::placeholder { color:#bbb; }

    /* INPUT WITH ICON */
    .input-icon-wrap { position:relative; }
    .input-icon-wrap > i.fa-lock,
    .input-icon-wrap > i.fa-user,
    .input-icon-wrap > i.fa-envelope {
      position:absolute; left:1rem; top:50%;
      transform:translateY(-50%); color:#bbb;
      font-size:.9rem; pointer-events:none; z-index:2;
    }
    .input-icon-wrap .input-field { padding-left:2.75rem; }
    .input-icon-wrap .input-field.has-toggle { padding-right:3rem; }

    /* ── TOGGLE PASSWORD BUTTON (PERBAIKAN) ── */
    .toggle-pw-btn {
      position: absolute;
      right: .75rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #bbb;
      font-size: 1rem;
      padding: .35rem .4rem;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color .2s;
      z-index: 5;
      border-radius: 6px;
      line-height: 1;
    }
    .toggle-pw-btn:hover { color: var(--gold-dark); background: rgba(245,166,35,.08); }
    .toggle-pw-btn.active { color: var(--gold-dark); }

    /* SECTION DIVIDER */
    .section-label {
      font-size:.72rem; font-weight:800; letter-spacing:1px;
      text-transform:uppercase; color:#bbb;
      display:flex; align-items:center; gap:.75rem;
      margin:2rem 0 1.25rem;
    }
    .section-label::after { content:''; flex:1; height:1px; background:#EDE8E0; }

    /* ALERT */
    .alert { display:flex; align-items:center; gap:.75rem; padding:.9rem 1.2rem; border-radius:12px; font-size:.875rem; font-weight:600; margin-bottom:1.75rem; }
    .alert-success { background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0; }
    .alert-error   { background:#FEE2E2; color:#991B1B; border:1px solid #FECACA; }

    /* BUTTONS */
    .btn-row { display:flex; gap:1rem; margin-top:2rem; }
    .btn-save {
      flex:1; padding:.9rem;
      background:linear-gradient(135deg, var(--gold), var(--gold-dark));
      color:#fff; border:none; border-radius:14px;
      font-weight:700; font-size:.95rem; cursor:pointer;
      font-family:'DM Sans',sans-serif; transition:.2s;
      box-shadow:0 4px 16px rgba(245,166,35,.3);
    }
    .btn-save:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(245,166,35,.4); }
    .btn-cancel {
      padding:.9rem 1.5rem; background:#F3F0EB; color:#666;
      border:none; border-radius:14px; font-weight:600; font-size:.95rem;
      cursor:pointer; font-family:'DM Sans',sans-serif; transition:.2s;
      text-decoration:none; display:flex; align-items:center; justify-content:center;
    }
    .btn-cancel:hover { background:#EDE8E0; color:#444; }

    /* FOTO ACTIONS */
    .photo-actions {
      display:flex; align-items:center; gap:.75rem;
      margin-top:.75rem; flex-wrap:wrap; justify-content:center;
    }
    .btn-change-photo,
    .btn-delete-photo {
      display:flex; align-items:center; justify-content:center; gap:.55rem;
      height:40px; min-width:160px; padding:0 1.3rem; border-radius:999px;
      font-weight:700; font-size:.85rem; cursor:pointer; transition:.2s; box-sizing:border-box;
    }
    .btn-change-photo {
      background:rgba(245,166,35,.1); border:1.5px solid rgba(245,166,35,.3); color:var(--gold-dark);
    }
    .btn-change-photo:hover { background:rgba(245,166,35,.2); border-color:var(--gold); }
    .btn-delete-photo {
      background:#FEE2E2; border:1.5px solid #FCA5A5; color:#B91C1C;
    }
    .btn-delete-photo:hover { background:#FECACA; }

    /* TAB */
    .tab-bar {
      display:flex; gap:.5rem; margin-bottom:1.75rem;
      border-bottom:2px solid #EDE8E0; padding-bottom:0;
    }
    .tab-btn {
      padding:.65rem 1.25rem; border:none; background:none; cursor:pointer;
      font-family:'DM Sans',sans-serif; font-size:.9rem; font-weight:600;
      color:#999; border-bottom:3px solid transparent; margin-bottom:-2px; transition:.2s;
    }
    .tab-btn.active { color:var(--gold-dark); border-bottom-color:var(--gold); }
    .tab-pane { display:none; }
    .tab-pane.active { display:block; }

    /* FAVORIT */
    .fav-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1.2rem; }
    .fav-card { position:relative; border-radius:16px; overflow:hidden; aspect-ratio:4/3; box-shadow:0 4px 16px rgba(0,0,0,.08); text-decoration:none; display:block; }
    .fav-card img { width:100%; height:100%; object-fit:cover; transition:.4s; }
    .fav-card:hover img { transform:scale(1.06); }
    .fav-card-overlay { position:absolute; inset:0; background:linear-gradient(to top,rgba(15,8,2,.85) 30%,transparent 70%); display:flex; flex-direction:column; justify-content:flex-end; padding:1rem; }
    .fav-card-name { font-family:'Playfair Display',serif; font-size:1rem; font-weight:800; color:#fff; }
    .fav-card-region { font-size:.75rem; color:rgba(255,255,255,.6); margin-top:.2rem; }
    .fav-remove {
      position:absolute; top:.6rem; right:.6rem; width:30px; height:30px;
      border-radius:50%; background:rgba(0,0,0,.55); border:none; cursor:pointer;
      color:#fff; font-size:.8rem; display:flex; align-items:center; justify-content:center;
      transition:.2s; z-index:10;
    }
    .fav-remove:hover { background:#C0392B; }
    .fav-empty { text-align:center; padding:3rem 1rem; color:#bbb; font-size:.95rem; }

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

    /* RESPONSIVE */
    @media(max-width:768px) {
      .nav-links { display:none; }
      .hamburger { display:block; }
      .page-wrap { margin:2rem auto; padding:0 1rem 4rem; }
      .profile-header { padding:2rem 1.25rem 4.5rem; border-radius:18px; }
      .profile-header h2 { font-size:1.35rem; }
      .form-card { padding:1.5rem; border-radius:18px; }
      .btn-row { flex-direction:column; }
      .btn-cancel { text-align:center; }
      .avatar-section { margin-top:-60px; }
      .avatar-ring { width:108px; height:108px; }
      .photo-actions { width:100%; flex-direction:column; align-items:stretch; }
      .btn-change-photo, .btn-delete-photo { width:100%; min-width:0; }
      .footer-inner { grid-template-columns:1fr; gap:2rem; }
      .footer-brand p { max-width:100%; }
      .tab-bar { overflow-x:auto; -webkit-overflow-scrolling:touch; flex-wrap:nowrap; }
      .tab-btn { white-space:nowrap; padding:.6rem 1rem; }
      .crop-modal { width:94vw; max-height:92vh; }
      .crop-canvas-wrap { max-height:300px; }
      .crop-canvas-wrap img { max-height:300px; }
      .crop-toolbar { justify-content:center; gap:.5rem; }
      .crop-tool-sep { display:none; }
      .crop-zoom-slider { width:80px; }
      .crop-btn-cancel, .crop-btn-apply { flex:1 1 auto; justify-content:center; }
    }

    @media(max-width:480px) {
      .page-wrap { padding:0 .75rem 3rem; }
      .profile-header { padding:1.75rem 1rem 4rem; }
      .avatar-section { margin-top:-52px; }
      .avatar-ring { width:92px; height:92px; }
      .avatar-name { font-size:1.05rem; }
      .avatar-email { font-size:.78rem; }
      .form-card { padding:1.1rem; }
      .section-label { font-size:.68rem; margin:1.5rem 0 1rem; }
      .input-field { padding:.75rem 1rem; font-size:.9rem; }
      .input-icon-wrap .input-field { padding-left:2.5rem; }
      .input-icon-wrap .input-field.has-toggle { padding-right:2.75rem; }
      .tab-btn { font-size:.82rem; padding:.55rem .85rem; }
      .crop-toolbar { padding:.85rem 1rem; }
      .crop-tool-btn { width:34px; height:34px; font-size:.8rem; }
      .crop-zoom-slider { width:100%; order:1; flex-basis:100%; }
      .crop-btn-cancel, .crop-btn-apply { order:2; }
    }

    /* ── CROP MODAL ── */
    .crop-modal-backdrop {
      display:none; position:fixed; inset:0; z-index:1000;
      background:rgba(15,8,2,.75); backdrop-filter:blur(6px);
      align-items:center; justify-content:center;
    }
    .crop-modal-backdrop.open { display:flex; }
    .crop-modal {
      background:#fff; border-radius:24px; width:min(520px, 95vw);
      max-height:90vh; overflow:hidden;
      box-shadow:0 32px 80px rgba(0,0,0,.35);
      display:flex; flex-direction:column;
    }
    .crop-modal-head {
      padding:1.25rem 1.5rem; border-bottom:1px solid #EDE8E0;
      display:flex; align-items:center; justify-content:space-between;
    }
    .crop-modal-head h3 { font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:800; color:var(--dark); }
    .crop-modal-head p { font-size:.78rem; color:#999; margin-top:.1rem; }
    .crop-modal-close {
      width:34px; height:34px; border-radius:50%;
      border:none; background:#F3F0EB; color:#666;
      cursor:pointer; font-size:.9rem; transition:.2s;
      display:flex; align-items:center; justify-content:center;
    }
    .crop-modal-close:hover { background:#EDE8E0; color:#333; }
    .crop-canvas-wrap { flex:1; overflow:hidden; background:#1A1208; max-height:380px; position:relative; }
    .crop-canvas-wrap img { display:block; max-width:100%; max-height:380px; }
    .crop-toolbar { padding:1rem 1.5rem; border-top:1px solid #EDE8E0; display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; }
    .crop-tool-btn {
      width:38px; height:38px; border-radius:10px;
      border:1.5px solid #EDE8E0; background:#FAFAF8;
      color:#666; cursor:pointer; font-size:.9rem;
      transition:.2s; display:flex; align-items:center; justify-content:center;
    }
    .crop-tool-btn:hover { border-color:var(--gold); color:var(--gold-dark); background:#FFFBF3; }
    .crop-tool-sep { flex:1; }
    .crop-btn-cancel {
      padding:.65rem 1.2rem; border-radius:12px;
      border:1.5px solid #EDE8E0; background:#F3F0EB;
      color:#666; font-weight:600; font-size:.875rem;
      cursor:pointer; transition:.2s; font-family:'DM Sans',sans-serif;
    }
    .crop-btn-cancel:hover { background:#EDE8E0; }
    .crop-btn-apply {
      padding:.65rem 1.4rem; border-radius:12px;
      border:none; background:linear-gradient(135deg, var(--gold), var(--gold-dark));
      color:#fff; font-weight:700; font-size:.875rem;
      cursor:pointer; transition:.2s; font-family:'DM Sans',sans-serif;
      box-shadow:0 4px 14px rgba(245,166,35,.3);
      display:flex; align-items:center; gap:.45rem;
    }
    .crop-btn-apply:hover { transform:translateY(-1px); box-shadow:0 8px 20px rgba(245,166,35,.4); }
    .crop-zoom-slider {
      -webkit-appearance:none; width:90px; height:4px;
      border-radius:4px; background:#EDE8E0; outline:none; cursor:pointer;
    }
    .crop-zoom-slider::-webkit-slider-thumb {
      -webkit-appearance:none; width:16px; height:16px;
      border-radius:50%; background:var(--gold); cursor:pointer;
      box-shadow:0 2px 6px rgba(245,166,35,.4);
    }
  </style>
</head>
<body>

<nav class="navbar">
  <div class="nav-inner">
    <a class="nav-logo" href="index.php"><img src="https://jejakrasa.site.je/gambar/logoweb.svg" alt="Logo"></a>
    <div class="nav-links">
      <a href="index.php">Beranda</a>
      <a href="filter.php">Filter</a>
      <a href="tentangkami.php">Tentang Kami</a>
    </div>
    <div class="nav-links">
      <?php if (isAdmin()): ?>
        <a href="dashboard.php">Admin</a>
      <?php endif; ?>
      <div class="profile-wrap" id="profileWrap">
        <img src="<?= $foto_src ?>" id="profileBtn" alt="foto">
        <div class="profile-menu" id="profileMenu">
          <a href="profile.php" style="color:var(--gold);font-weight:600;background:#FFF8EC;">
            <i class="fas fa-user" style="margin-right:.5rem;color:var(--gold);"></i><span>Profile</span>
          </a>
          <hr style="border-color:#f5eee0;">
          <a href="logout.php" style="color:#e53e3e;">
            <i class="fas fa-sign-out-alt" style="margin-right:.5rem;"></i>Logout
          </a>
        </div>
      </div>
    </div>
    <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
  </div>
  <div class="mobile-menu" id="mobileMenu">
    <a href="index.php">Beranda</a>
    <a href="filter.php">Filter</a>
    <a href="tentangkami.php">Tentang Kami</a>
    <a href="profile.php" class="active">Profil Saya</a>
    <a href="logout.php" style="color:#e53e3e;">Logout</a>
  </div>
</nav>

<!-- ── CROP MODAL ── -->
<div class="crop-modal-backdrop" id="cropModalBackdrop">
  <div class="crop-modal">
    <div class="crop-modal-head">
      <div>
        <h3><i class="fas fa-crop-alt" style="color:var(--gold);margin-right:.5rem;"></i>Sesuaikan Foto</h3>
        <p>Geser, zoom, dan putar untuk mendapatkan tampilan terbaik</p>
      </div>
      <button class="crop-modal-close" id="cropModalClose"><i class="fas fa-times"></i></button>
    </div>
    <div class="crop-canvas-wrap">
      <img id="cropImage" src="" alt="crop">
    </div>
    <div class="crop-toolbar">
      <button class="crop-tool-btn" id="cropRotateL" title="Putar kiri"><i class="fas fa-rotate-left"></i></button>
      <button class="crop-tool-btn" id="cropRotateR" title="Putar kanan"><i class="fas fa-rotate-right"></i></button>
      <button class="crop-tool-btn" id="cropFlipH" title="Balik horizontal"><i class="fas fa-left-right"></i></button>
      <button class="crop-tool-btn" id="cropFlipV" title="Balik vertikal"><i class="fas fa-up-down"></i></button>
      <input type="range" class="crop-zoom-slider" id="cropZoomSlider" min="0" max="3" step="0.01" value="0">
      <span class="crop-tool-sep"></span>
      <button class="crop-btn-cancel" id="cropBtnCancel">Batal</button>
      <button class="crop-btn-apply" id="cropBtnApply">
        <i class="fas fa-check"></i> Terapkan
      </button>
    </div>
  </div>
</div>


<div class="page-wrap">

  <div class="profile-header">
    <div class="profile-header-inner">
      <div>
        <h2>Profile</h2>
        <p>Kelola informasi akun dan foto profil Anda</p>
        <span class="role-badge"><?= htmlspecialchars($user['role']) ?></span>
      </div>
    </div>
  </div>

  <div class="avatar-section">
    <label for="inputFoto" style="cursor:pointer;">
      <div class="avatar-ring" id="avatarRing">
        <img id="previewFoto" src="<?= $foto_src ?>" alt="Foto Profil">
        <div class="avatar-overlay">
          <i class="fas fa-camera"></i>
          <span>Ganti Foto</span>
        </div>
      </div>
    </label>

    <div class="photo-actions">
      <label for="inputFoto" class="btn-change-photo">
        <i class="fas fa-camera"></i>
        Ganti Foto
      </label>

      <?php if (!empty($user['foto'])): ?>
      <form method="POST" onsubmit="return confirm('Yakin ingin menghapus foto profil?')">
        <button type="submit" name="hapus_foto" class="btn-delete-photo">
          <i class="fas fa-trash"></i>
          Hapus Foto
        </button>
      </form>
      <?php endif; ?>
    </div>
    <p class="avatar-name"><?= htmlspecialchars($user['username']) ?></p>
    <p class="avatar-email"><?= htmlspecialchars($user['email']) ?></p>
  </div>

  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('edit-profil', this)">
      <i class="fas fa-user-edit mr-1"></i> Edit Profil
    </button>
    <button class="tab-btn" onclick="switchTab('resep-favorit', this)">
      <i class="fas fa-heart mr-1"></i> Favorit Saya (<?= count($fav_list) ?>)
    </button>
  </div>

  <div id="edit-profil" class="tab-pane active">
    <div class="form-card">
      <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= $msg_type ?>">
        <?php if ($msg_type === 'success'): ?>
          <i class="fas fa-check-circle"></i>
        <?php else: ?>
          <i class="fas fa-exclamation-circle"></i>
        <?php endif; ?>
        <?= htmlspecialchars($msg) ?>
      </div>
      <?php endif; ?>

      <form action="" method="POST" enctype="multipart/form-data">
        <input type="file" name="foto" accept="image/*" class="hidden" id="inputFoto">
        <input type="hidden" name="foto_crop_data" id="fotoCropData">

        <div class="section-label">Informasi Akun</div>

        <div class="input-group">
          <label>Username</label>
          <div class="input-icon-wrap">
            <i class="fas fa-user"></i>
            <input type="text" class="input-field" value="<?= htmlspecialchars($user['username']) ?>" readonly style="background:#F3F0EB;color:#888;cursor:not-allowed;">
          </div>
        </div>

        <div class="input-group">
          <label>Email</label>
          <div class="input-icon-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" class="input-field" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background:#F3F0EB;color:#888;cursor:not-allowed;">
          </div>
        </div>

        <div class="section-label">Keamanan</div>

        <!-- ── PASSWORD LAMA ── -->
        <div class="input-group">
          <label>
            Password Lama
            <span style="color:#bbb;font-size:.75rem;text-transform:none;letter-spacing:0;">
              (wajib diisi jika ingin ganti password)
            </span>
          </label>
          <div class="input-icon-wrap" style="position:relative;">
            <i class="fas fa-lock"></i>
            <input
              type="password"
              name="current_password"
              id="currentPwInput"
              class="input-field has-toggle"
              placeholder="Masukkan password saat ini"
              autocomplete="current-password"
            >
            <button type="button" class="toggle-pw-btn" onclick="togglePw('currentPwInput', this)" title="Tampilkan/sembunyikan password">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- ── PASSWORD BARU ── -->
        <div class="input-group">
          <label>
            Password Baru
            <span style="color:#bbb;font-size:.75rem;text-transform:none;letter-spacing:0;">
              (kosongkan jika tidak ingin ganti)
            </span>
          </label>
          <div class="input-icon-wrap" style="position:relative;">
            <i class="fas fa-lock"></i>
            <input
              type="password"
              name="password"
              id="pwInput"
              class="input-field has-toggle"
              placeholder="Minimal 6 karakter"
              autocomplete="new-password"
            >
            <button type="button" class="toggle-pw-btn" onclick="togglePw('pwInput', this)" title="Tampilkan/sembunyikan password">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="btn-row">
          <a href="index.php" class="btn-cancel">
            <i class="fas fa-arrow-left" style="margin-right:.4rem;"></i> Batal
          </a>
          <button type="submit" name="update" class="btn-save">
            <i class="fas fa-save" style="margin-right:.5rem;"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="resep-favorit" class="tab-pane">
    <div class="form-card">
      <div class="section-label" style="margin-top:0;">Daftar Kuliner Tersimpan</div>

      <?php if (empty($fav_list)): ?>
        <div class="fav-empty">
          <i class="fas fa-heart-broken" style="font-size:2.5rem;color:#D4C9B8;margin-bottom:1rem;display:block;"></i>
          Belum ada makanan favorit yang disimpan.
        </div>
      <?php else: ?>
        <div class="fav-grid">
          <?php foreach ($fav_list as $fav): ?>
            <a href="detail.php?id=<?= $fav['id'] ?>" class="fav-card">
              <img src="<?= htmlspecialchars($fav['gambar']) ?>" alt="<?= htmlspecialchars($fav['nama']) ?>">
              <div class="fav-card-overlay">
                <div class="fav-card-name"><?= htmlspecialchars($fav['nama']) ?></div>
                <div class="fav-card-region">
                  <i class="fas fa-map-marker-alt" style="margin-right:.25rem;"></i>
                  <?= htmlspecialchars($fav['daerah']) ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

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
  // ── Navbar ──
  document.getElementById('hamburger').addEventListener('click', () => {
    document.getElementById('mobileMenu').classList.toggle('open');
  });
  const profileBtn = document.getElementById('profileBtn');
  if (profileBtn) {
    profileBtn.addEventListener('click', () => document.getElementById('profileMenu').classList.toggle('open'));
    document.addEventListener('click', e => {
      if (!document.getElementById('profileWrap').contains(e.target))
        document.getElementById('profileMenu').classList.remove('open');
    });
  }

  // ── Toggle Show/Hide Password ──
  function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (!input) return;

    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('fa-eye', 'fa-eye-slash');
      btn.classList.add('active');
    } else {
      input.type = 'password';
      icon.classList.replace('fa-eye-slash', 'fa-eye');
      btn.classList.remove('active');
    }
  }

  // ── Crop foto ──
  let cropper = null;
  let scaleX = 1, scaleY = 1;

  const inputFoto      = document.getElementById('inputFoto');
  const cropBackdrop   = document.getElementById('cropModalBackdrop');
  const cropImage      = document.getElementById('cropImage');
  const cropBtnApply   = document.getElementById('cropBtnApply');
  const cropBtnCancel  = document.getElementById('cropBtnCancel');
  const cropModalClose = document.getElementById('cropModalClose');
  const cropRotateL    = document.getElementById('cropRotateL');
  const cropRotateR    = document.getElementById('cropRotateR');
  const cropFlipH      = document.getElementById('cropFlipH');
  const cropFlipV      = document.getElementById('cropFlipV');
  const cropZoomSlider = document.getElementById('cropZoomSlider');
  const fotoCropData   = document.getElementById('fotoCropData');
  const previewFoto    = document.getElementById('previewFoto');

  inputFoto.addEventListener('change', function () {
    if (!this.files || !this.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
      cropImage.src = e.target.result;
      openCropper();
    };
    reader.readAsDataURL(this.files[0]);
    this.value = '';
  });

  function openCropper() {
    scaleX = 1; scaleY = 1;
    cropZoomSlider.value = 0;
    cropBackdrop.classList.add('open');
    cropImage.onload = function () {
      if (cropper) { cropper.destroy(); cropper = null; }
      cropper = new Cropper(cropImage, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 0.85,
        restore: false,
        guides: true,
        center: true,
        highlight: true,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
        ready() { cropZoomSlider.value = 0; },
        zoom(e) { cropZoomSlider.value = e.detail.ratio; }
      });
    };
    if (cropImage.complete) cropImage.onload();
  }

  function closeCropper() {
    cropBackdrop.classList.remove('open');
    if (cropper) { cropper.destroy(); cropper = null; }
  }

  cropBtnCancel.addEventListener('click', closeCropper);
  cropModalClose.addEventListener('click', closeCropper);
  cropBackdrop.addEventListener('click', e => { if (e.target === cropBackdrop) closeCropper(); });

  cropBtnApply.addEventListener('click', () => {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingQuality: 'high' });
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    previewFoto.src = dataUrl;
    fotoCropData.value = dataUrl;
    closeCropper();
  });

  cropRotateL.addEventListener('click', () => cropper && cropper.rotate(-90));
  cropRotateR.addEventListener('click', () => cropper && cropper.rotate(90));
  cropFlipH.addEventListener('click', () => {
    if (!cropper) return;
    scaleX = -scaleX;
    cropper.scaleX(scaleX);
  });
  cropFlipV.addEventListener('click', () => {
    if (!cropper) return;
    scaleY = -scaleY;
    cropper.scaleY(scaleY);
  });
  cropZoomSlider.addEventListener('input', () => {
    if (cropper) cropper.zoomTo(parseFloat(cropZoomSlider.value));
  });

  // ── Switch Tab ──
  function switchTab(tabId, el) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    el.classList.add('active');
  }
</script>
</body>
</html>