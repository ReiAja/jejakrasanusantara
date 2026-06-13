<?php
include 'koneksi.php';
header('Content-Type: application/json');

// 1. Pastikan user sudah login
if (!isLogin()) {
    echo json_encode(['status' => 'login_required']);
    exit;
}

// 2. Ambil data input POST dari JavaScript dan pastikan tipenya Integer
$makanan_id = isset($_POST['makanan_id']) ? (int)$_POST['makanan_id'] : 0;
$user_id    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// 3. Validasi ID jika kosong atau tidak valid
if ($makanan_id <= 0 || $user_id <= 0) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid ID. Makanan ID: ' . $makanan_id . ', User ID: ' . $user_id
    ]);
    exit;
}

// 4. Cek apakah sudah disimpan ke favorit
$stmt_cek = mysqli_prepare($koneksi, "SELECT id FROM favorit WHERE user_id = ? AND makanan_id = ?");
mysqli_stmt_bind_param($stmt_cek, "ii", $user_id, $makanan_id);
mysqli_stmt_execute($stmt_cek);
mysqli_stmt_store_result($stmt_cek);

if (mysqli_stmt_num_rows($stmt_cek) > 0) {
    // Sudah ada -> hapus dari favorit (toggle off)
    mysqli_stmt_close($stmt_cek);
    
    $stmt_del = mysqli_prepare($koneksi, "DELETE FROM favorit WHERE user_id = ? AND makanan_id = ?");
    mysqli_stmt_bind_param($stmt_del, "ii", $user_id, $makanan_id);
    mysqli_stmt_execute($stmt_del);
    mysqli_stmt_close($stmt_del);
    
    echo json_encode(['status' => 'removed']);
} else {
    // Belum ada -> simpan ke favorit (toggle on)
    mysqli_stmt_close($stmt_cek);
    
    $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO favorit (user_id, makanan_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt_ins, "ii", $user_id, $makanan_id);
    mysqli_stmt_execute($stmt_ins);
    mysqli_stmt_close($stmt_ins);
    
    echo json_encode(['status' => 'saved']);
}
exit;
?>