<?php
session_start();

// Memanggil file konfigurasi private
include 'config.php';

// Membuat koneksi ke database menggunakan konstanta dari config.php
$koneksi = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($koneksi, "utf8mb4");


// ── HELPER FUNCTION ─────────────────

// Helper: cek apakah sudah login
function isLogin() {
    return isset($_SESSION['user_id']);
}

// Helper: cek apakah admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Helper: redirect jika belum login
function requireLogin() {
    if (!isLogin()) {
        header("Location: login.php");
        exit;
    }
}

// Helper: redirect jika bukan admin
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: index.php");
        exit;
    }
}
?>