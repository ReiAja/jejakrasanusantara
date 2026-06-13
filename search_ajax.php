<?php
include 'koneksi.php';

$q = trim($_GET['q'] ?? '');

if ($q == '') exit;

$q = mysqli_real_escape_string($koneksi, $q);

$data = mysqli_query($koneksi,"
  SELECT * FROM makanan 
  WHERE nama LIKE '%$q%' 
     OR daerah LIKE '%$q%'
     OR kategori LIKE '%$q%'
  ORDER BY nama ASC
  LIMIT 6
");

if(mysqli_num_rows($data) == 0){
    echo '<div class="search-empty">Tidak ada hasil ditemukan</div>';
    exit;
}

while($row = mysqli_fetch_assoc($data)):
?>

<a href="detail.php?id=<?= $row['id'] ?>" class="search-item">

  <img src="<?= htmlspecialchars($row['gambar']) ?>">

  <div class="search-item-text">
    <div class="search-item-name">
      <?= htmlspecialchars($row['nama']) ?>
    </div>

    <div class="search-item-cat">
      <?= htmlspecialchars($row['kategori']) ?> • <?= htmlspecialchars($row['daerah']) ?>
    </div>
  </div>

</a>

<?php endwhile; ?>