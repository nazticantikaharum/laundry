
<?php
session_start();
require_once "koneksi.php";
date_default_timezone_set("Asia/Jakarta");

// CEK LOGIN
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

// IDENTITAS PENGGUNA
$namaUser = $_SESSION["nama"] ?? "Pengguna";
$roleUser = $_SESSION["role"] ?? "Petugas";
$inisial = strtoupper(substr($namaUser, 0, 1));
$isAdmin = strtolower($roleUser) === "admin";
$labelRole = ucfirst($roleUser);

// FUNGSI
function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ",", ".");
}

$pesan = "";
$error = "";

// =====================================
// SIMPAN PEMBAYARAN
// =====================================
if (isset($_POST["bayar"])) {

    $id_pesanan = (int)($_POST["id_pesanan"] ?? 0);
    $metode = $_POST["metode"] ?? "";

    if ($id_pesanan <= 0 || !in_array($metode, ["Tunai", "QRIS"], true)) {
        $error = "Data pembayaran tidak valid.";
    } else {

        $sql = "SELECT total_harga FROM pesanan
                WHERE id_pesanan = ?
                AND status != 'Dibatalkan'";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
        mysqli_stmt_execute($stmt);
        $dataPesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$dataPesanan) {
            $error = "Pesanan tidak ditemukan atau sudah dibatalkan.";
        } else {

            $sql = "SELECT id_pembayaran FROM pembayaran
                    WHERE id_pesanan = ?
                    AND status_bayar IN ('Menunggu', 'Berhasil')";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
            mysqli_stmt_execute($stmt);
            $pembayaranAda = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($pembayaranAda) {
                $error = "Pesanan ini sudah memiliki transaksi pembayaran.";
            } else {

                $jumlah = (float)$dataPesanan["total_harga"];
                $tanggal = date("Y-m-d");
                $status = ($metode == "QRIS") ? "Menunggu" : "Berhasil";

                $sql = "INSERT INTO pembayaran
                        (id_pesanan, tanggal_bayar, jumlah_bayar, metode, status_bayar)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "isdss",
                    $id_pesanan,
                    $tanggal,
                    $jumlah,
                    $metode,
                    $status
                );

                if (mysqli_stmt_execute($stmt)) {

                    $idBaru = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);

                    if ($metode == "QRIS") {
                        header("Location: midtrans_qris.php?id=" . $idBaru);
                        exit;
                    } else {
                        header("Location: pembayaran.php?status=tambah");
                        exit;
                    }

                } else {
                    $error = "Pembayaran gagal disimpan.";
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// =====================================
// HAPUS RIWAYAT
// =====================================
if (isset($_POST["hapus_riwayat"])) {

    $id = (int)($_POST["id_pembayaran"] ?? 0);

    if ($id > 0) {

        $sql = "DELETE FROM pembayaran WHERE id_pembayaran = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (
            mysqli_stmt_execute($stmt) &&
            mysqli_stmt_affected_rows($stmt) > 0
        ) {
            mysqli_stmt_close($stmt);
            header("Location: pembayaran.php?status=hapus");
            exit;
        } else {
            $error = "Riwayat pembayaran gagal dihapus.";
        }

        mysqli_stmt_close($stmt);

    } else {
        $error = "ID pembayaran tidak valid.";
    }
}

// =====================================
// NOTIFIKASI
// =====================================
if (isset($_GET["status"])) {
    switch ($_GET["status"]) {
        case "tambah":
            $pesan = "Pembayaran tunai berhasil disimpan.";
            break;

        case "hapus":
            $pesan = "Riwayat pembayaran berhasil dihapus.";
            break;

        case "qris":
            $pesan = "Transaksi QRIS berhasil dibuat. Silakan lakukan pembayaran.";
            break;

        case "cek_berhasil":
            $pesan = "Pembayaran Midtrans berhasil dikonfirmasi.";
            break;

        case "cek_menunggu":
            $pesan = "Pembayaran masih menunggu penyelesaian.";
            break;

        case "cek_gagal":
            $pesan = "Pembayaran gagal, dibatalkan, atau kedaluwarsa.";
            break;
    }
}

// =====================================
// DATA QRIS
// =====================================
$dataQris = null;

if (
    isset($_GET["status"]) &&
    in_array($_GET["status"], [
        "qris",
        "cek_berhasil",
        "cek_menunggu",
        "cek_gagal"
    ], true)
) {

    $idQris = (int)($_GET["id"] ?? 0);

    $sql = "SELECT pb.*, p.kode_pesanan, pl.nama
            FROM pembayaran pb
            JOIN pesanan p ON pb.id_pesanan = p.id_pesanan
            JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
            WHERE pb.id_pembayaran = ?
            AND pb.metode = 'QRIS'";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $idQris);
    mysqli_stmt_execute($stmt);
    $dataQris = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// =====================================
// PESANAN BELUM DIBAYAR
// =====================================
$sqlBelumBayar = "
    SELECT p.id_pesanan, p.kode_pesanan, p.total_harga, pl.nama
    FROM pesanan p
    JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    WHERE p.status != 'Dibatalkan'
    AND NOT EXISTS (
        SELECT 1 FROM pembayaran pb
        WHERE pb.id_pesanan = p.id_pesanan
        AND pb.status_bayar IN ('Menunggu', 'Berhasil')
    )
    ORDER BY p.id_pesanan DESC
";

$pesananBelumBayar = mysqli_query($conn, $sqlBelumBayar);

// =====================================
// PENCARIAN RIWAYAT
// =====================================
$cari = trim($_GET["cari"] ?? "");

$sqlRiwayat = "
    SELECT pb.*, p.kode_pesanan, pl.nama
    FROM pembayaran pb
    JOIN pesanan p ON pb.id_pesanan = p.id_pesanan
    JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
";

if ($cari != "") {
    $sqlRiwayat .= "
        WHERE p.kode_pesanan LIKE ?
        OR pl.nama LIKE ?
        OR pb.metode LIKE ?
        OR pb.status_bayar LIKE ?
    ";
}

$sqlRiwayat .= " ORDER BY pb.id_pembayaran DESC";

if ($cari != "") {

    $stmt = mysqli_prepare($conn, $sqlRiwayat);
    $keyword = "%" . $cari . "%";

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $keyword,
        $keyword,
        $keyword,
        $keyword
    );

    mysqli_stmt_execute($stmt);
    $riwayat = mysqli_stmt_get_result($stmt);

} else {
    $riwayat = mysqli_query($conn, $sqlRiwayat);
}

// =====================================
// RINGKASAN
// =====================================
$resultTotal = mysqli_query(
    $conn,
    "SELECT SUM(jumlah_bayar) AS total
     FROM pembayaran WHERE status_bayar = 'Berhasil'"
);

$dataTotal = mysqli_fetch_assoc($resultTotal);
$totalPendapatan = $dataTotal["total"] ?? 0;

$resultJumlah = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS jumlah
     FROM pembayaran WHERE status_bayar = 'Berhasil'"
);

$dataJumlah = mysqli_fetch_assoc($resultJumlah);
$jumlahTransaksi = $dataJumlah["jumlah"] ?? 0;

$resultMenunggu = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS jumlah
     FROM pembayaran WHERE status_bayar = 'Menunggu'"
);

$dataMenunggu = mysqli_fetch_assoc($resultMenunggu);
$jumlahMenunggu = $dataMenunggu["jumlah"] ?? 0;

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Pembayaran - Teman.Nyuci</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f3f7fc;
    color: #34445a;
}

/* ==================================
   SIDEBAR SAMA DENGAN DASHBOARD
================================== */

.sidebar {
    width: 235px;
    height: 100vh;
    background: #fff;
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

/* PROFIL PENGGUNA */
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
    margin: 0;
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

/* ==================================
   KONTEN UTAMA
================================== */

.main {
    margin-left: 235px;
    padding: 30px 32px;
    min-height: 100vh;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 27px;
}

.header h1 {
    font-size: 25px;
    color: #34445a;
    margin-bottom: 6px;
}

.header p {
    color: #9aa8b7;
    font-size: 13px;
}

.header-date {
    color: #748399;
    font-size: 12px;
    background: white;
    border: 1px solid #e7eef6;
    border-radius: 8px;
    padding: 11px 15px;
}

/* ==================================
   RINGKASAN
================================== */

.summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 22px;
}

.summary-card {
    background: white;
    padding: 22px;
    border: 1px solid #e7eef6;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(70,110,150,.04);
}

.summary-card p {
    color: #8998aa;
    font-size: 12px;
    margin-bottom: 12px;
}

.summary-card h3 {
    color: #34445a;
    font-size: 23px;
}

/* ==================================
   CARD
================================== */

.card {
    background: white;
    padding: 25px;
    border: 1px solid #e7eef6;
    border-radius: 12px;
    margin-bottom: 22px;
    box-shadow: 0 4px 15px rgba(70,110,150,.04);
}

.card h2 {
    font-size: 19px;
    color: #34445a;
    margin-bottom: 22px;
}

/* ==================================
   FORM
================================== */

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 17px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    color: #526277;
    font-weight: 600;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #dfe8f1;
    border-radius: 8px;
    font-size: 13px;
    color: #34445a;
    background: white;
    outline: none;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #79b5e8;
    box-shadow: 0 0 0 3px rgba(100,169,229,.12);
}

.form-group input[readonly] {
    background: #f3f7fc;
}

/* ==================================
   TOMBOL
================================== */

.btn {
    border: none;
    padding: 10px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: .2s;
}

.btn-primary {
    background: #64a9e5;
    color: white;
}

.btn-primary:hover {
    background: #4d96d5;
}

.btn-delete {
    background: #fff0f0;
    color: #c03939;
}

.btn-delete:hover {
    background: #fce0e0;
}

.form-button {
    margin-top: 20px;
}

/* ==================================
   TABEL DAN PENCARIAN
================================== */

.table-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.table-top h2 {
    margin-bottom: 0;
}

.search {
    display: flex;
    gap: 7px;
}

.search input {
    padding: 10px 12px;
    border: 1px solid #dfe8f1;
    border-radius: 8px;
    width: 220px;
    outline: none;
    font-size: 12px;
}

.search input:focus {
    border-color: #79b5e8;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 850px;
}

th {
    background: #f3f7fc;
    color: #65768b;
    text-align: left;
    padding: 14px 10px;
    white-space: nowrap;
}

td {
    padding: 14px 10px;
    border-bottom: 1px solid #edf1f5;
    color: #66768a;
    vertical-align: middle;
}

tbody tr:hover td {
    background: #fafcff;
}

/* ==================================
   STATUS
================================== */

.badge {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.berhasil {
    background: #eaf5ee;
    color: #277344;
}

.menunggu {
    background: #fff7df;
    color: #956b12;
}

.dibatalkan {
    background: #fff0f0;
    color: #b83232;
}

/* ==================================
   NOTIFIKASI
================================== */

.alert {
    background: #eaf5ee;
    color: #277344;
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    border: 1px solid #d9efdf;
}

.alert.error {
    background: #fff0f0;
    color: #b83232;
    border-color: #f5dddd;
}

.empty {
    text-align: center;
    color: #9aa8b7;
    padding: 30px;
}

/* ==================================
   QRIS
================================== */

.qris-box {
    max-width: 440px;
    margin: 0 auto;
    padding: 25px;
    border: 1px solid #e7eef6;
    border-radius: 12px;
    text-align: center;
    background: #fff;
}

.qris-box img {
    width: 260px;
    max-width: 100%;
    height: auto;
    margin: 18px auto;
    display: block;
}

.qris-total {
    font-size: 28px;
    font-weight: bold;
    color: #559bd5;
    margin: 12px 0;
}

.qris-note {
    background: #fff7df;
    color: #805d12;
    padding: 12px;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.6;
    margin: 15px 0;
}

/* PREVIEW QRIS */
.preview-qris {
    display: none;
    margin-top: 20px;
    padding: 20px;
    background: #f3f7fc;
    border: 1px solid #e7eef6;
    border-radius: 8px;
    text-align: center;
}

.footer {
    text-align: center;
    color: #9aa8b7;
    font-size: 11px;
    padding: 20px;
}

/* ==================================
   RESPONSIVE
================================== */

@media (max-width: 1050px) {
    .form-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .summary {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 850px) {
    .sidebar {
        width: 190px;
    }

    .main {
        margin-left: 190px;
        padding: 24px 20px;
    }
}

@media (max-width: 700px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding: 18px;
    }

    .brand {
        margin-bottom: 20px;
    }

    .sidebar > a {
        display: inline-flex;
        margin-right: 3px;
        padding: 10px;
    }

    .account-menu {
        position: relative;
        margin-top: 12px;
    }

    .main {
        margin-left: 0;
        padding: 20px 15px;
    }

    .header,
    .table-top {
        align-items: flex-start;
        flex-direction: column;
    }

    .summary,
    .form-grid {
        grid-template-columns: 1fr;
    }

    .search {
        width: 100%;
    }

    .search input {
        width: 100%;
    }

    .card {
        padding: 18px;
    }
}
</style>
</head>

<body>

<!-- ==================================
     SIDEBAR
================================== -->

<div class="sidebar">

    <div class="brand">Teman<span>.Nyuci</span></div>

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

    <a href="pesanan.php">
        <i class="fa-solid fa-box"></i>
        Data Pesanan
    </a>

    <a href="konfirmasi_online.php">
        <i class="fa-solid fa-globe"></i>
        Pesanan Online
    </a>

    <a href="pembayaran.php" class="active">
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

    <!-- PROFIL PENGGUNA -->
    <div class="account-menu">

        <button class="sidebar-user" type="button"
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

<!-- ==================================
     KONTEN UTAMA
================================== -->

<div class="main">

    <!-- HEADER -->
    <div class="header">

        <div>
            <h1>Pembayaran</h1>
            <p>Kelola transaksi pembayaran pelanggan</p>
        </div>

        <div class="header-date">
            <i class="fa-regular fa-calendar"></i>
            <?= date("d F Y") ?>
        </div>

    </div>

    <!-- NOTIFIKASI -->
    <?php if ($pesan != ""): ?>
        <div class="alert">
            <i class="fa-solid fa-circle-check"></i>
            <?= aman($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error != ""): ?>
        <div class="alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= aman($error) ?>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN -->
    <div class="summary">

        <div class="summary-card">
            <p>
                <i class="fa-solid fa-wallet"></i>
                Total Pembayaran Berhasil
            </p>

            <h3><?= rupiah($totalPendapatan) ?></h3>
        </div>

        <div class="summary-card">
            <p>
                <i class="fa-solid fa-receipt"></i>
                Jumlah Transaksi Berhasil
            </p>

            <h3>
                <?= number_format($jumlahTransaksi, 0, ",", ".") ?>
                Transaksi
            </h3>
        </div>

        <div class="summary-card">
            <p>
                <i class="fa-solid fa-clock"></i>
                Pembayaran Menunggu
            </p>

            <h3>
                <?= number_format($jumlahMenunggu, 0, ",", ".") ?>
                Transaksi
            </h3>
        </div>

    </div>

    <!-- TAMPILAN QRIS MIDTRANS -->
    <?php if ($dataQris): ?>

        <div class="card">

            <h2>
                <i class="fa-solid fa-qrcode"></i>
                QRIS Pesanan <?= aman($dataQris["kode_pesanan"]) ?>
            </h2>

            <div class="qris-box">

                <h3>Teman.Nyuci</h3>

                <p>Pembayaran melalui Midtrans Sandbox</p>

                <div class="qris-total">
                    <?= rupiah($dataQris["jumlah_bayar"]) ?>
                </div>

                <?php if (!empty($dataQris["qr_url"])): ?>

                    <img
                        src="<?= aman($dataQris["qr_url"]) ?>"
                        alt="QRIS Midtrans">

                    <div class="qris-note">
                        <strong>Simulasi Pembayaran</strong>
                        <br>
                        Silakan gunakan simulator Midtrans Sandbox
                        untuk menguji pembayaran.
                    </div>

                <?php else: ?>

                    <div class="qris-note">
                        QR Code belum tersedia.
                        Silakan periksa transaksi Midtrans.
                    </div>

                <?php endif; ?>

                <p style="margin-bottom:15px;">
                    Status:

                    <?php if ($dataQris["status_bayar"] == "Berhasil"): ?>

                        <span class="badge berhasil">Berhasil</span>

                    <?php elseif ($dataQris["status_bayar"] == "Menunggu"): ?>

                        <span class="badge menunggu">Menunggu</span>

                    <?php else: ?>

                        <span class="badge dibatalkan">Gagal</span>

                    <?php endif; ?>
                </p>

                <?php if ($dataQris["status_bayar"] == "Menunggu"): ?>

                    <a
                        href="cek_status_midtrans.php?id=<?= (int)$dataQris["id_pembayaran"] ?>"
                        class="btn btn-primary"
                        onclick="return confirm('Periksa status pembayaran sekarang?')">

                        <i class="fa-solid fa-rotate"></i>
                        Cek Status Pembayaran

                    </a>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

    <!-- FORM TAMBAH PEMBAYARAN -->
    <div class="card">

        <h2>
            <i class="fa-solid fa-circle-plus"></i>
            Tambah Pembayaran
        </h2>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">
                    <label>Pilih Pesanan</label>

                    <select
                        name="id_pesanan"
                        id="pesanan"
                        onchange="tampilkanTagihan()"
                        required>

                        <option value="">Pilih pesanan</option>

                        <?php while ($p = mysqli_fetch_assoc($pesananBelumBayar)): ?>

                            <option
                                value="<?= (int)$p["id_pesanan"] ?>"
                                data-nama="<?= aman($p["nama"]) ?>"
                                data-total="<?= aman($p["total_harga"]) ?>">

                                <?= aman($p["kode_pesanan"]) ?>
                                - <?= aman($p["nama"]) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Nama Pelanggan</label>

                    <input
                        type="text"
                        id="nama_pelanggan"
                        readonly
                        placeholder="Otomatis">
                </div>

                <div class="form-group">
                    <label>Total Tagihan</label>

                    <input
                        type="text"
                        id="total_tagihan"
                        readonly
                        placeholder="Otomatis">
                </div>

                <div class="form-group">
                    <label>Metode Pembayaran</label>

                    <select
                        name="metode"
                        id="metode"
                        onchange="tampilkanQris()"
                        required>

                        <option value="">Pilih metode</option>
                        <option value="Tunai">Tunai</option>
                        <option value="QRIS">QRIS - Midtrans</option>

                    </select>
                </div>

            </div>

            <!-- PREVIEW QRIS -->
            <div class="preview-qris" id="previewQris">

                <strong>
                    <i class="fa-solid fa-qrcode"></i>
                    Pembayaran QRIS Midtrans
                </strong>

                <p style="margin-top:12px;">
                    QR Code akan dibuat otomatis oleh Midtrans
                    setelah pembayaran disimpan.
                </p>

                <p style="margin-top:12px;">Total tagihan:</p>

                <h3 class="qris-total" id="previewTotal">
                    Rp 0
                </h3>

                <p class="qris-note">
                    Pembayaran menggunakan Midtrans Sandbox.
                    Transaksi ini hanya untuk pengujian.
                </p>

            </div>

            <div class="form-button">

                <button
                    type="submit"
                    name="bayar"
                    class="btn btn-primary"
                    onclick="return confirm('Simpan pembayaran ini?')">

                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan Pembayaran

                </button>

            </div>

        </form>

    </div>

    <!-- RIWAYAT PEMBAYARAN -->
    <div class="card">

        <div class="table-top">

            <h2>
                <i class="fa-solid fa-clock-rotate-left"></i>
                Riwayat Pembayaran
            </h2>

            <form method="GET" class="search">

                <input
                    type="text"
                    name="cari"
                    placeholder="Cari pembayaran..."
                    value="<?= aman($cari) ?>">

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i>
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
                        <th>Tanggal Bayar</th>
                        <th>Jumlah Bayar</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($riwayat && mysqli_num_rows($riwayat) > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($row = mysqli_fetch_assoc($riwayat)): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <?= aman($row["kode_pesanan"]) ?>
                                </td>

                                <td>
                                    <?= aman($row["nama"]) ?>
                                </td>

                                <td>
                                    <?= date(
                                        "d-m-Y",
                                        strtotime($row["tanggal_bayar"])
                                    ) ?>
                                </td>

                                <td>
                                    <?= rupiah($row["jumlah_bayar"]) ?>
                                </td>

                                <td>
                                    <?= aman($row["metode"]) ?>
                                </td>

                                <td>

                                    <?php if ($row["status_bayar"] == "Berhasil"): ?>

                                        <span class="badge berhasil">
                                            <i class="fa-solid fa-check"></i>
                                            Berhasil
                                        </span>

                                    <?php elseif ($row["status_bayar"] == "Menunggu"): ?>

                                        <span class="badge menunggu">
                                            <i class="fa-solid fa-clock"></i>
                                            Menunggu
                                        </span>

                                    <?php else: ?>

                                        <span class="badge dibatalkan">
                                            <i class="fa-solid fa-xmark"></i>
                                            Gagal
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus riwayat pembayaran ini?')">

                                        <input
                                            type="hidden"
                                            name="id_pembayaran"
                                            value="<?= (int)$row["id_pembayaran"] ?>">

                                        <button
                                            type="submit"
                                            name="hapus_riwayat"
                                            class="btn btn-delete">

                                            <i class="fa-solid fa-trash"></i>
                                            Hapus

                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty">
                                <i class="fa-solid fa-inbox"
                                   style="font-size:25px;margin-bottom:10px;"></i>
                                <br>
                                Belum ada riwayat pembayaran.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <div class="footer">
        © <?= date("Y") ?> Teman.Nyuci. Semua Hak Dilindungi.
    </div>

</div>

<!-- ==================================
     JAVASCRIPT
================================== -->

<script>
function toggleAccount() {

    const dropdown = document.getElementById("accountDropdown");
    const arrow = document.getElementById("accountArrow");

    dropdown.classList.toggle("show");

    arrow.innerHTML = dropdown.classList.contains("show") ? "⌄" : "⌃";
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

function tampilkanTagihan() {

    const pilihan = document.getElementById("pesanan");
    const opsi = pilihan.options[pilihan.selectedIndex];

    const nama = document.getElementById("nama_pelanggan");
    const total = document.getElementById("total_tagihan");

    if (!opsi.value) {
        nama.value = "";
        total.value = "";
        tampilkanQris();
        return;
    }

    const jumlah = parseFloat(opsi.dataset.total);

    nama.value = opsi.dataset.nama;
    total.value = "Rp " + jumlah.toLocaleString("id-ID");

    tampilkanQris();
}

function tampilkanQris() {

    const metode = document.getElementById("metode").value;
    const pilihan = document.getElementById("pesanan");
    const opsi = pilihan.options[pilihan.selectedIndex];

    const preview = document.getElementById("previewQris");
    const previewTotal = document.getElementById("previewTotal");

    if (metode === "QRIS" && pilihan.value) {

        const jumlah = parseFloat(opsi.dataset.total);

        preview.style.display = "block";

        previewTotal.textContent =
            "Rp " + jumlah.toLocaleString("id-ID");

    } else {
        preview.style.display = "none";
    }
}
</script>

</body>
</html>
