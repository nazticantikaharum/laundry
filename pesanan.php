
<?php
session_start();
require_once "koneksi.php";
date_default_timezone_set("Asia/Jakarta");

if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ",", ".");
}

// =====================================
// WHATSAPP
// =====================================
function bukaWhatsApp($nomor, $pesan) {
    $nomor = preg_replace('/[^0-9]/', '', $nomor);

    if (substr($nomor, 0, 1) == "0") {
        $nomor = "62" . substr($nomor, 1);
    } elseif (substr($nomor, 0, 2) != "62") {
        $nomor = "62" . $nomor;
    }

    if ($nomor == "") {
        return false;
    }

    header("Location: https://wa.me/" . $nomor . "?text=" . urlencode($pesan));
    exit;
}

$pesan = "";

// =====================================
// TAMBAH PESANAN
// =====================================
if (isset($_POST["tambah"])) {

    $id_pelanggan = (int)($_POST["id_pelanggan"] ?? 0);
    $id_layanan = (int)($_POST["id_layanan"] ?? 0);
    $berat = (float)($_POST["berat_kg"] ?? 0);

    if ($id_pelanggan <= 0 || $id_layanan <= 0 || $berat <= 0) {
        $pesan = "Lengkapi data pesanan dengan benar.";
    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT harga_per_kg, estimasi_hari
             FROM layanan WHERE id_layanan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_layanan);
        mysqli_stmt_execute($stmt);
        $layanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        $cek = mysqli_prepare(
            $conn,
            "SELECT id_pelanggan, nama, no_hp
             FROM pelanggan WHERE id_pelanggan = ?"
        );

        mysqli_stmt_bind_param($cek, "i", $id_pelanggan);
        mysqli_stmt_execute($cek);
        $pelangganAda = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

        if (!$layanan || !$pelangganAda) {
            $pesan = "Pelanggan atau layanan tidak ditemukan.";
        } else {

            $harga = (float)$layanan["harga_per_kg"];
            $estimasi = (int)$layanan["estimasi_hari"];
            $total = $harga * $berat;

            $tanggalMasuk = date("Y-m-d H:i:s");
            $tanggalSelesai = date(
                "Y-m-d",
                strtotime("+$estimasi days")
            );

            $kode = "TN-" . date("YmdHis") . "-" . random_int(100, 999);
            $id_user = (int)$_SESSION["id_user"];
            $status = "Diproses";

            mysqli_begin_transaction($conn);

            try {

                $sql = "INSERT INTO pesanan
                    (kode_pesanan, id_pelanggan, id_user, id_layanan,
                     tanggal_masuk, tanggal_estimasi_selesai,
                     berat_kg, total_harga, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "siiissdds",
                    $kode,
                    $id_pelanggan,
                    $id_user,
                    $id_layanan,
                    $tanggalMasuk,
                    $tanggalSelesai,
                    $berat,
                    $total,
                    $status
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Pesanan gagal disimpan.");
                }

                $id_pesanan = mysqli_insert_id($conn);

                $stmtRiwayat = mysqli_prepare(
                    $conn,
                    "INSERT INTO riwayat_status
                     (id_pesanan, id_user, status, waktu)
                     VALUES (?, ?, ?, NOW())"
                );

                mysqli_stmt_bind_param(
                    $stmtRiwayat,
                    "iis",
                    $id_pesanan,
                    $id_user,
                    $status
                );

                if (!mysqli_stmt_execute($stmtRiwayat)) {
                    throw new Exception("Riwayat status gagal disimpan.");
                }

                mysqli_commit($conn);

                $pesanWA = "Halo " . $pelangganAda["nama"] . ",\n\n" .
                    "Pesanan laundry Anda sudah kami terima.\n" .
                    "Kode Pesanan: " . $kode . "\n" .
                    "Status: DIPROSES\n\n" .
                    "Terima kasih telah menggunakan Teman.Nyuci.";

                bukaWhatsApp($pelangganAda["no_hp"], $pesanWA);

            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $pesan = $e->getMessage();
            }
        }
    }
}


// =====================================
// UBAH STATUS PESANAN
// =====================================
if (isset($_POST["ubah_status"])) {

    $id = (int)($_POST["id_pesanan"] ?? 0);
    $statusBaru = $_POST["status"] ?? "";

    $statusValid = ["Diproses", "Selesai"];

    if ($id > 0 && in_array($statusBaru, $statusValid, true)) {

        $stmtCek = mysqli_prepare(
            $conn,
            "SELECT p.status, p.kode_pesanan, pl.nama, pl.no_hp
             FROM pesanan p
             JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
             WHERE p.id_pesanan = ?"
        );

        mysqli_stmt_bind_param($stmtCek, "i", $id);
        mysqli_stmt_execute($stmtCek);

        $dataLama = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtCek)
        );

        if (!$dataLama) {
            $pesan = "Pesanan tidak ditemukan.";
        } else {

            $statusSebelumnya = $dataLama["status"];

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE pesanan SET status = ? WHERE id_pesanan = ?"
            );

            mysqli_stmt_bind_param($stmt, "si", $statusBaru, $id);

            if (mysqli_stmt_execute($stmt)) {

                if ($statusSebelumnya !== $statusBaru) {

                    $id_user = (int)$_SESSION["id_user"];

                    $stmtRiwayat = mysqli_prepare(
                        $conn,
                        "INSERT INTO riwayat_status
                         (id_pesanan, id_user, status, waktu)
                         VALUES (?, ?, ?, NOW())"
                    );

                    mysqli_stmt_bind_param(
                        $stmtRiwayat,
                        "iis",
                        $id,
                        $id_user,
                        $statusBaru
                    );

                    mysqli_stmt_execute($stmtRiwayat);
                }

                if (
                    $statusBaru == "Selesai" &&
                    $statusSebelumnya != "Selesai"
                ) {

                    $pesanWA = "Halo " . $dataLama["nama"] . ",\n\n" .
                        "Laundry Anda sudah SELESAI dan siap diambil.\n" .
                        "Kode Pesanan: " . $dataLama["kode_pesanan"] . "\n\n" .
                        "Silakan datang ke Teman.Nyuci untuk mengambil laundry Anda.\n" .
                        "Terima kasih telah menggunakan layanan kami.";

                    bukaWhatsApp($dataLama["no_hp"], $pesanWA);
                }

                header("Location: pesanan.php?status=ubah");
                exit;

            } else {
                $pesan = "Status pesanan gagal diubah.";
            }
        }

    } else {
        $pesan = "Status pesanan tidak valid.";
    }
}


// =====================================
// HAPUS PESANAN
// =====================================
if (isset($_POST["hapus"])) {

    $id = (int)($_POST["id_pesanan"] ?? 0);

    mysqli_begin_transaction($conn);

    try {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_pembayaran FROM pembayaran WHERE id_pesanan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $adaPembayaran = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        if ($adaPembayaran) {
            throw new Exception("Pesanan sudah memiliki pembayaran.");
        }

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM riwayat_status WHERE id_pesanan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM notifikasi_wa WHERE id_pesanan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM pesanan WHERE id_pesanan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) < 1) {
            throw new Exception("Pesanan tidak ditemukan.");
        }

        mysqli_commit($conn);

        header("Location: pesanan.php?status=hapus");
        exit;

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $pesan = "Pesanan tidak dapat dihapus. " . $e->getMessage();
    }
}


// =====================================
// NOTIFIKASI
// =====================================
if (isset($_GET["status"])) {

    if ($_GET["status"] == "tambah") {
        $pesan = "Pesanan berhasil ditambahkan.";
    } elseif ($_GET["status"] == "ubah") {
        $pesan = "Status pesanan berhasil diperbarui.";
    } elseif ($_GET["status"] == "hapus") {
        $pesan = "Pesanan berhasil dihapus.";
    }
}


// =====================================
// DATA PELANGGAN DAN LAYANAN
// =====================================
$daftarPelanggan = mysqli_query(
    $conn,
    "SELECT * FROM pelanggan ORDER BY nama ASC"
);

$daftarLayanan = mysqli_query(
    $conn,
    "SELECT * FROM layanan ORDER BY nama_layanan ASC"
);


// =====================================
// PENCARIAN PESANAN
// =====================================
$cari = trim($_GET["cari"] ?? "");

$sql = "SELECT
            p.*,
            pl.nama AS nama_pelanggan,
            l.nama_layanan,
            l.jenis_waktu,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM pembayaran pb
                    WHERE pb.id_pesanan = p.id_pesanan
                    AND pb.status_bayar = 'Berhasil'
                )
                THEN 'Lunas'
                ELSE 'Belum Lunas'
            END AS status_pembayaran
        FROM pesanan p
        JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
        JOIN layanan l ON p.id_layanan = l.id_layanan";

if ($cari != "") {
    $sql .= " WHERE p.kode_pesanan LIKE ?
              OR pl.nama LIKE ?
              OR p.status LIKE ?";
}

$sql .= " ORDER BY p.id_pesanan DESC";

if ($cari != "") {

    $stmt = mysqli_prepare($conn, $sql);
    $keyword = "%" . $cari . "%";

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $keyword,
        $keyword,
        $keyword
    );

    mysqli_stmt_execute($stmt);
    $pesanan = mysqli_stmt_get_result($stmt);

} else {
    $pesanan = mysqli_query($conn, $sql);
}


// =====================================
// DATA AKUN
// =====================================
$namaUser = $_SESSION["nama"] ?? "Pengguna";
$roleUser = $_SESSION["role"] ?? "Petugas";
$inisial = strtoupper(substr($namaUser, 0, 1));
$isAdmin = strtolower($roleUser) === "admin";
$labelRole = $isAdmin ? "Administrator" : "Petugas";

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Pesanan | Teman.Nyuci</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f3f7fc;
    color: #34445a;
}

/* =====================================
   SIDEBAR
===================================== */

.sidebar {
    width: 235px;
    height: 100vh;
    background: #ffffff;
    position: fixed;
    left: 0;
    top: 0;
    padding: 25px 17px;
    border-right: 1px solid #e7eef6;
    z-index: 10;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.brand {
    font-size: 23px;
    font-weight: 750;
    color: #64a9e5;
    padding: 5px 12px;
    margin-bottom: 38px;
    flex-shrink: 0;
}

.brand span {
    color: #34445a;
}

.menu-label {
    font-size: 11px;
    color: #a0adbc;
    letter-spacing: 1px;
    padding: 0 12px;
    margin-bottom: 13px;
}

.sidebar > a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    margin-bottom: 7px;
    text-decoration: none;
    color: #748399;
    font-size: 13px;
    border-radius: 9px;
    transition: .2s;
    flex-shrink: 0;
}

.sidebar > a i {
    width: 18px;
    font-size: 15px;
    text-align: center;
}

.sidebar > a:hover,
.sidebar > a.active {
    background: #e6f3ff;
    color: #559bd5;
    font-weight: 600;
}

/* PROFIL SIDEBAR */

.account-menu {
    position: sticky;
    bottom: 0;
    margin-top: auto;
    padding-top: 12px;
    background: white;
    flex-shrink: 0;
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 13px 5px;
    background: white;
    border: none;
    border-top: 1px solid #edf1f5;
    cursor: pointer;
    text-align: left;
}

.user-avatar {
    width: 39px;
    height: 39px;
    border-radius: 10px;
    background: #8fc5f3;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.user-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.user-info strong {
    font-size: 12px;
    color: #34445a;
}

.user-info span {
    font-size: 11px;
    color: #9aa8b7;
}

.arrow {
    color: #9aa8b7;
}

.account-dropdown {
    display: none;
    position: absolute;
    bottom: 72px;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e5edf5;
    border-radius: 10px;
    padding: 7px;
    box-shadow: 0 5px 20px rgba(70,110,150,.12);
}

.account-dropdown.show {
    display: block;
}

.account-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px;
    text-decoration: none;
    color: #748399;
    font-size: 13px;
    border-radius: 7px;
}

.account-dropdown a:hover {
    background: #e6f3ff;
    color: #559bd5;
}

.account-dropdown .logout-menu {
    color: #df7474;
}

/* =====================================
   KONTEN UTAMA
===================================== */

.main {
    margin-left: 235px;
    padding: 30px 32px;
    min-height: 100vh;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    font-size: 22px;
    font-weight: 650;
}

.header p {
    color: #96a3b2;
    font-size: 12px;
    margin-top: 5px;
}

.date-now {
    background: white;
    border: 1px solid #e7eef6;
    padding: 11px 15px;
    border-radius: 9px;
    font-size: 12px;
    color: #718197;
}

/* KARTU */

.card {
    background: white;
    border: 1px solid #e8eef5;
    border-radius: 12px;
    padding: 23px;
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(75,110,150,.035);
}

.card h3 {
    font-size: 15px;
    font-weight: 650;
    color: #43556a;
    margin-bottom: 20px;
}

/* FORM */

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 17px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #718197;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #e0e8f0;
    border-radius: 8px;
    font-size: 13px;
    background: white;
    color: #53677d;
    outline: none;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #83b9e9;
}

.form-group input[readonly] {
    background: #f6f9fc;
}

/* TOMBOL */

.btn {
    border: none;
    padding: 10px 14px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
    display: inline-block;
    white-space: nowrap;
    font-family: inherit;
}

.btn-primary {
    background: #83b9e9;
    color: white;
}

.btn-primary:hover {
    background: #65a9e5;
}

.btn-delete {
    background: #fff0f0;
    color: #c66c6c;
}

.btn-status {
    background: #eaf3fc;
    color: #559bd5;
}

.btn-nota {
    background: #e8f3fc;
    color: #559bd5;
}

.form-button {
    margin-top: 20px;
}

/* PENCARIAN */

.table-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 19px;
}

.table-top h3 {
    margin-bottom: 0;
}

.search {
    display: flex;
    gap: 7px;
}

.search input {
    padding: 10px 12px;
    border: 1px solid #e0e8f0;
    border-radius: 7px;
    width: 230px;
    font-size: 12px;
}

/* TABEL */

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 1200px;
}

th {
    background: #f6f9fc;
    color: #8494a6;
    text-align: left;
    padding: 14px 10px;
    font-size: 11px;
    font-weight: 600;
}

td {
    padding: 13px 10px;
    border-bottom: 1px solid #edf1f5;
    color: #617286;
    vertical-align: middle;
}

tbody tr:hover {
    background: #f9fcff;
}

.status-form {
    display: flex;
    gap: 5px;
    align-items: center;
}

.status-form select {
    padding: 7px;
    border: 1px solid #e0e8f0;
    border-radius: 6px;
    font-size: 11px;
}

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
    white-space: nowrap;
}

.lunas {
    background: #e7f7f1;
    color: #58a88c;
}

.belum-lunas {
    background: #fff3e4;
    color: #d49a4d;
}

.aksi {
    display: flex;
    align-items: center;
    gap: 7px;
}

/* ALERT */

.alert {
    background: #eaf5ee;
    color: #277344;
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
}

.alert.error {
    background: #fff0f0;
    color: #b83232;
}

.empty {
    text-align: center;
    color: #9aa8b7;
    padding: 30px;
}

/* RESPONSIVE */

@media(max-width: 1200px) {
    .form-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 700px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        max-height: none;
    }

    .account-menu {
        position: relative;
        margin-top: 15px;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }

    .header,
    .table-top {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .search {
        width: 100%;
    }

    .search input {
        width: 100%;
    }
}

</style>
</head>

<body>

<!-- =====================================
     SIDEBAR
===================================== -->

<div class="sidebar">

    <div class="brand">
        Teman<span>.Nyuci</span>
    </div>

    <div class="menu-label">MENU UTAMA</div>

    <a href="dashboard.php">
        <i class="fa-solid fa-house"></i>
        Dashboard
    </a>

    <a href="pelanggan.php">
        <i class="fa-solid fa-users"></i>
        Data Pelanggan
    </a>

    <a href="layanan.php">
        <i class="fa-solid fa-shirt"></i>
        Data Layanan
    </a>

    <a href="pesanan.php" class="active">
        <i class="fa-solid fa-box"></i>
        Data Pesanan
    </a>

    <a href="konfirmasi_online.php">
        <i class="fa-solid fa-globe"></i>
        Pesanan Online
    </a>

    <a href="pembayaran.php">
        <i class="fa-solid fa-wallet"></i>
        Pembayaran
    </a>

    <a href="laporan.php">
        <i class="fa-solid fa-file-lines"></i>
        Laporan
    </a>

    <a href="faq.php">
        <i class="fa-solid fa-circle-question"></i>
        FAQ Pelanggan
    </a>

    <a href="kelola_faq.php">
        <i class="fa-solid fa-book-open"></i>
        Kelola FAQ
    </a>

    <?php if ($isAdmin): ?>
        <a href="user.php">
            <i class="fa-solid fa-user-gear"></i>
            Manajemen User
        </a>
    <?php endif; ?>

    <div class="account-menu">

        <button class="sidebar-user"
                type="button"
                onclick="toggleAccount()">

            <div class="user-avatar">
                <?= aman($inisial) ?>
            </div>

            <div class="user-info">
                <strong><?= aman($namaUser) ?></strong>
                <span><?= aman($labelRole) ?></span>
            </div>

            <span class="arrow" id="accountArrow">⌃</span>

        </button>

        <div class="account-dropdown" id="accountDropdown">

            <a href="profil.php">
                <i class="fa-solid fa-user"></i>
                Profil Akun
            </a>

            <a href="logout.php" class="logout-menu">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </div>

    </div>

</div>


<!-- =====================================
     KONTEN UTAMA
===================================== -->

<div class="main">

    <div class="header">

        <div>
            <h1>Data Pesanan</h1>
            <p>Kelola dan pantau seluruh pesanan laundry pelanggan</p>
        </div>

        <div class="date-now">
            <i class="fa-regular fa-calendar"></i>
            <?= date("d F Y") ?>
        </div>

    </div>


    <?php if ($pesan != ""): ?>

        <div class="alert <?= (
            strpos($pesan, "gagal") !== false ||
            strpos($pesan, "tidak dapat") !== false ||
            strpos($pesan, "sudah memiliki") !== false
        ) ? "error" : "" ?>">

            <?= aman($pesan) ?>

        </div>

    <?php endif; ?>


    <!-- TAMBAH PESANAN -->

    <div class="card">

        <h3>
            <i class="fa-solid fa-plus-circle"></i>
            Tambah Pesanan Baru
        </h3>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>Pelanggan</label>

                    <select name="id_pelanggan" required>

                        <option value="">Pilih pelanggan</option>

                        <?php while ($pl = mysqli_fetch_assoc($daftarPelanggan)): ?>

                            <option value="<?= (int)$pl["id_pelanggan"] ?>">

                                <?= aman($pl["nama"]) ?> -
                                <?= aman($pl["no_hp"]) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Layanan</label>

                    <select name="id_layanan"
                            id="layanan"
                            onchange="hitungTotal()"
                            required>

                        <option value="">Pilih layanan</option>

                        <?php while ($l = mysqli_fetch_assoc($daftarLayanan)): ?>

                            <option
                                value="<?= (int)$l["id_layanan"] ?>"
                                data-harga="<?= aman($l["harga_per_kg"]) ?>"
                                data-estimasi="<?= (int)$l["estimasi_hari"] ?>">

                                <?= aman($l["nama_layanan"]) ?>
                                (<?= aman($l["jenis_waktu"]) ?>)

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Berat Cucian (Kg)</label>

                    <input
                        type="number"
                        name="berat_kg"
                        id="berat"
                        min="0.1"
                        step="0.1"
                        placeholder="Contoh: 3.5"
                        oninput="hitungTotal()"
                        required>

                </div>


                <div class="form-group">

                    <label>Harga per Kg</label>

                    <input
                        type="text"
                        id="harga"
                        readonly
                        placeholder="Otomatis">

                </div>


                <div class="form-group">

                    <label>Total Harga</label>

                    <input
                        type="text"
                        id="total"
                        readonly
                        placeholder="Otomatis">

                </div>


                <div class="form-group">

                    <label>Estimasi Selesai</label>

                    <input
                        type="text"
                        id="estimasi"
                        readonly
                        placeholder="Otomatis">

                </div>

            </div>

            <div class="form-button">

                <button type="submit"
                        name="tambah"
                        class="btn btn-primary">

                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan Pesanan

                </button>

            </div>

        </form>

    </div>


    <!-- DAFTAR PESANAN -->

    <div class="card">

        <div class="table-top">

            <h3>
                <i class="fa-solid fa-list"></i>
                Daftar Pesanan
            </h3>

            <form method="GET" class="search">

                <input
                    type="text"
                    name="cari"
                    placeholder="Cari kode, nama, atau status..."
                    value="<?= aman($cari) ?>">

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-search"></i>
                    Cari
                </button>

            </form>

        </div>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>No</th>
                        <th>Kode Pesanan</th>
                        <th>Pelanggan</th>
                        <th>Layanan</th>
                        <th>Berat</th>
                        <th>Total Harga</th>
                        <th>Tanggal Masuk</th>
                        <th>Estimasi Selesai</th>
                        <th>Status Pesanan</th>
                        <th>Status Pembayaran</th>
                        <th>Aksi</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($pesanan && mysqli_num_rows($pesanan) > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($pesanan)): ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td>
                                <?= aman($row["kode_pesanan"]) ?>
                            </td>

                            <td>
                                <?= aman($row["nama_pelanggan"]) ?>
                            </td>

                            <td>
                                <?= aman($row["nama_layanan"]) ?>
                                (<?= aman($row["jenis_waktu"]) ?>)
                            </td>

                            <td>
                                <?= number_format(
                                    (float)$row["berat_kg"],
                                    2,
                                    ",",
                                    "."
                                ) ?> Kg
                            </td>

                            <td>
                                <?= rupiah($row["total_harga"]) ?>
                            </td>

                            <td>
                                <?= date(
                                    "d-m-Y H:i",
                                    strtotime($row["tanggal_masuk"])
                                ) ?>
                            </td>

                            <td>
                                <?= date(
                                    "d-m-Y",
                                    strtotime($row["tanggal_estimasi_selesai"])
                                ) ?>
                            </td>


                            <!-- STATUS PESANAN -->

                            <td>

                                <form method="POST" class="status-form">

                                    <input
                                        type="hidden"
                                        name="id_pesanan"
                                        value="<?= (int)$row["id_pesanan"] ?>">

                                    <select name="status">

                                        <option
                                            value="Diproses"
                                            <?= $row["status"] != "Selesai" ? "selected" : "" ?>>

                                            Diproses

                                        </option>

                                        <option
                                            value="Selesai"
                                            <?= $row["status"] == "Selesai" ? "selected" : "" ?>>

                                            Selesai

                                        </option>

                                    </select>

                                    <button
                                        type="submit"
                                        name="ubah_status"
                                        class="btn btn-status">

                                        Simpan

                                    </button>

                                </form>

                            </td>


                            <!-- STATUS PEMBAYARAN -->

                            <td>

                                <?php if ($row["status_pembayaran"] == "Lunas"): ?>

                                    <span class="badge lunas">
                                        Lunas
                                    </span>

                                <?php else: ?>

                                    <span class="badge belum-lunas">
                                        Belum Lunas
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- AKSI -->

                            <td>

                                <div class="aksi">

                                    <a
                                        href="nota.php?id=<?= (int)$row["id_pesanan"] ?>"
                                        class="btn btn-nota"
                                        target="_blank">

                                        <i class="fa-solid fa-receipt"></i>
                                        Nota

                                    </a>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus pesanan ini?')">

                                        <input
                                            type="hidden"
                                            name="id_pesanan"
                                            value="<?= (int)$row["id_pesanan"] ?>">

                                        <button
                                            type="submit"
                                            name="hapus"
                                            class="btn btn-delete">

                                            <i class="fa-solid fa-trash"></i>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="11" class="empty">
                            <i class="fa-solid fa-box-open"></i>
                            <br><br>
                            Belum ada data pesanan.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- =====================================
     JAVASCRIPT
===================================== -->

<script>

// DROPDOWN PROFIL
function toggleAccount() {

    const dropdown = document.getElementById("accountDropdown");
    const arrow = document.getElementById("accountArrow");

    dropdown.classList.toggle("show");

    arrow.innerHTML =
        dropdown.classList.contains("show") ? "⌄" : "⌃";
}

document.addEventListener("click", function(event) {

    const menu = document.querySelector(".account-menu");
    const dropdown = document.getElementById("accountDropdown");
    const arrow = document.getElementById("accountArrow");

    if (!menu.contains(event.target)) {
        dropdown.classList.remove("show");
        arrow.innerHTML = "⌃";
    }

});


// HITUNG TOTAL HARGA OTOMATIS
function hitungTotal() {

    const pilihan = document.getElementById("layanan");

    const berat = parseFloat(
        document.getElementById("berat").value
    ) || 0;

    const hargaInput = document.getElementById("harga");
    const totalInput = document.getElementById("total");
    const estimasiInput = document.getElementById("estimasi");

    const opsi = pilihan.options[pilihan.selectedIndex];

    if (!opsi.value) {

        hargaInput.value = "";
        totalInput.value = "";
        estimasiInput.value = "";

        return;
    }

    const harga = parseFloat(opsi.dataset.harga);
    const estimasi = parseInt(opsi.dataset.estimasi);
    const total = harga * berat;

    const formatRupiah = angka =>
        "Rp " + angka.toLocaleString("id-ID");

    hargaInput.value = formatRupiah(harga);
    totalInput.value = formatRupiah(total);
    estimasiInput.value = estimasi + " Hari";

}

</script>

</body>
</html>
