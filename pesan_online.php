
<?php
session_start();
require_once "koneksi.php";

if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set("Asia/Jakarta");

// =====================================
// DATA AKUN
// =====================================
$namaUser = $_SESSION["nama"] ?? "Pengguna";
$roleUser = $_SESSION["role"] ?? "Petugas";
$isAdmin = strtolower($roleUser) === "admin";
$labelRole = $isAdmin ? "Administrator" : "Petugas";

// =====================================
// FORMAT RUPIAH
// =====================================
function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ",", ".");
}

function hitungData($conn, $sql) {
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return 0;
    }

    $data = mysqli_fetch_assoc($result);
    return $data["total"] ?? 0;
}

// =====================================
// FILTER TANGGAL
// =====================================
$tanggal_awal = $_GET["tanggal_awal"] ?? date("Y-m-01");
$tanggal_akhir = $_GET["tanggal_akhir"] ?? date("Y-m-d");

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_awal) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir) ||
    $tanggal_awal > $tanggal_akhir
) {
    $tanggal_awal = date("Y-m-01");
    $tanggal_akhir = date("Y-m-d");
}

// =====================================
// TOTAL PELANGGAN
// =====================================
$total_pelanggan = hitungData(
    $conn,
    "SELECT COUNT(*) AS total FROM pelanggan"
);

// =====================================
// TOTAL LAYANAN
// =====================================
$total_layanan = hitungData(
    $conn,
    "SELECT COUNT(*) AS total FROM layanan"
);

// =====================================
// TOTAL PESANAN
// =====================================
$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pesanan
     WHERE DATE(tanggal_masuk) BETWEEN ? AND ?"
);

mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);

$total_pesanan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
)["total"] ?? 0;

// =====================================
// TOTAL PENDAPATAN
// =====================================
$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(jumlah_bayar), 0) AS total
     FROM pembayaran
     WHERE status_bayar = 'Berhasil'
     AND DATE(tanggal_bayar) BETWEEN ? AND ?"
);

mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);

$total_pendapatan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
)["total"] ?? 0;

// =====================================
// PESANAN DIPROSES
// =====================================
$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pesanan
     WHERE status = 'Diproses'
     AND DATE(tanggal_masuk) BETWEEN ? AND ?"
);

mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);

$total_proses = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
)["total"] ?? 0;

// =====================================
// SISA TAGIHAN
// =====================================
$sqlTagihan = "
    SELECT COALESCE(
        SUM(
            GREATEST(
                p.total_harga - COALESCE(b.dibayar, 0),
                0
            )
        ), 0
    ) AS total
    FROM pesanan p
    LEFT JOIN (
        SELECT id_pesanan, SUM(jumlah_bayar) AS dibayar
        FROM pembayaran
        WHERE status_bayar = 'Berhasil'
        GROUP BY id_pesanan
    ) b ON p.id_pesanan = b.id_pesanan
    WHERE p.status != 'Dibatalkan'
    AND DATE(p.tanggal_masuk) BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $sqlTagihan);
mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);

$sisa_tagihan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
)["total"] ?? 0;

// =====================================
// JUMLAH PEMBAYARAN BERHASIL
// =====================================
$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pembayaran
     WHERE status_bayar = 'Berhasil'
     AND DATE(tanggal_bayar) BETWEEN ? AND ?"
);

mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);

$pembayaran_berhasil = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
)["total"] ?? 0;

// =====================================
// PEMBAYARAN MENUNGGU
// =====================================
$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pembayaran
     WHERE status_bayar = 'Menunggu'
     AND DATE(tanggal_bayar) BETWEEN ? AND ?"
);

mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);

$pembayaran_menunggu = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
)["total"] ?? 0;

// =====================================
// GRAFIK PENDAPATAN
// =====================================
$stmt = mysqli_prepare(
    $conn,
    "SELECT DATE(tanggal_bayar) AS tanggal,
            SUM(jumlah_bayar) AS total
     FROM pembayaran
     WHERE status_bayar = 'Berhasil'
     AND DATE(tanggal_bayar) BETWEEN ? AND ?
     GROUP BY DATE(tanggal_bayar)
     ORDER BY tanggal ASC"
);

mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);

$resultGrafik = mysqli_stmt_get_result($stmt);

$labelPendapatan = [];
$dataPendapatan = [];

while ($row = mysqli_fetch_assoc($resultGrafik)) {
    $labelPendapatan[] = date("d M", strtotime($row["tanggal"]));
    $dataPendapatan[] = (float)$row["total"];
}

// =====================================
// GRAFIK STATUS PESANAN
// =====================================
$stmt = mysqli_prepare(
    $conn,
    "SELECT status, COUNT(*) AS jumlah
     FROM pesanan
     WHERE DATE(tanggal_masuk) BETWEEN ? AND ?
     GROUP BY status"
);

mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);

$resultStatus = mysqli_stmt_get_result($stmt);

$labelStatus = [];
$dataStatus = [];

while ($row = mysqli_fetch_assoc($resultStatus)) {
    $labelStatus[] = $row["status"];
    $dataStatus[] = (int)$row["jumlah"];
}

// =====================================
// ANALISIS PESANAN DAN PEMBAYARAN
// =====================================
$sqlAnalisis = "
    SELECT
        p.kode_pesanan,
        pl.nama AS nama_pelanggan,
        p.tanggal_masuk,
        p.status,
        p.total_harga,
        COALESCE(b.dibayar, 0) AS sudah_dibayar,
        GREATEST(
            p.total_harga - COALESCE(b.dibayar, 0),
            0
        ) AS sisa_tagihan
    FROM pesanan p
    JOIN pelanggan pl
        ON p.id_pelanggan = pl.id_pelanggan
    LEFT JOIN (
        SELECT id_pesanan, SUM(jumlah_bayar) AS dibayar
        FROM pembayaran
        WHERE status_bayar = 'Berhasil'
        GROUP BY id_pesanan
    ) b ON p.id_pesanan = b.id_pesanan
    WHERE DATE(p.tanggal_masuk) BETWEEN ? AND ?
    ORDER BY p.id_pesanan DESC
    LIMIT 10
";

$stmt = mysqli_prepare($conn, $sqlAnalisis);
mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);

$queryAnalisis = mysqli_stmt_get_result($stmt);

// =====================================
// PESANAN TERBARU
// =====================================
$queryTerbaru = mysqli_query(
    $conn,
    "SELECT
        p.kode_pesanan,
        pl.nama AS nama_pelanggan,
        l.nama_layanan,
        p.tanggal_masuk,
        p.total_harga,
        p.status
    FROM pesanan p
    JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    JOIN layanan l ON p.id_layanan = l.id_layanan
    ORDER BY p.id_pesanan DESC
    LIMIT 5"
);

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard | Teman.Nyuci</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    background: #F3F7FC;
    color: #34445A;
}

/* =====================================
   KONTEN UTAMA
===================================== */

.main {
    margin-left: 235px;
    padding: 25px 30px;
    min-height: 100vh;
}

/* HEADER */
.top-header {
    background: #FFFFFF;
    border: 1px solid #E7EEF6;
    border-radius: 11px;
    padding: 16px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header-title h1 {
    font-size: 20px;
    color: #092D50;
    font-weight: 700;
}

.header-title p {
    font-size: 12px;
    color: #8999AA;
    margin-top: 5px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 18px;
}

.date-now {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #647A90;
}

.date-now i {
    color: #087FC1;
}

.header-user {
    display: flex;
    align-items: center;
    gap: 9px;
    padding-left: 17px;
    border-left: 1px solid #E8EEF4;
}

.header-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #E3F2FC;
    color: #087FC1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.header-user strong {
    display: block;
    color: #092D50;
    font-size: 12px;
}

.header-user span {
    display: block;
    color: #91A0AE;
    font-size: 10px;
    margin-top: 3px;
}

/* =====================================
   JUDUL HALAMAN
===================================== */

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.page-heading h2 {
    color: #092D50;
    font-size: 19px;
    margin-bottom: 5px;
}

.page-heading p {
    font-size: 12px;
    color: #8A9BAC;
}

/* =====================================
   FILTER TANGGAL
===================================== */

.filter-box {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
}

.filter-box form {
    display: flex;
    align-items: end;
    gap: 9px;
    flex-wrap: wrap;
}

.filter-group label {
    display: block;
    font-size: 11px;
    color: #71869A;
    margin-bottom: 6px;
}

.filter-group input {
    height: 37px;
    border: 1px solid #DCE7F0;
    border-radius: 7px;
    padding: 0 10px;
    color: #526A80;
    background: #FFFFFF;
    outline: none;
}

.filter-group input:focus {
    border-color: #087FC1;
}

.btn-filter {
    height: 37px;
    padding: 0 15px;
    background: #087FC1;
    color: #FFFFFF;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
}

.btn-filter:hover {
    background: #092D50;
}

.btn-reset {
    height: 37px;
    display: flex;
    align-items: center;
    padding: 0 13px;
    color: #087FC1;
    background: #EAF6FC;
    text-decoration: none;
    border-radius: 7px;
    font-size: 12px;
}

/* =====================================
   KARTU STATISTIK
===================================== */

.cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 22px;
}

.stat-card {
    background: #FFFFFF;
    border: 1px solid #E7EEF6;
    border-radius: 11px;
    padding: 17px;
    box-shadow: 0 3px 12px rgba(9,45,80,0.035);
    min-width: 0;
    transition: .2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 18px rgba(9,45,80,0.08);
}

.stat-top {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.blue {
    background: #E5F3FC;
    color: #087FC1;
}

.green {
    background: #E5F6EF;
    color: #40A981;
}

.orange {
    background: #FFF3DF;
    color: #D99A37;
}

.purple {
    background: #F0EBFF;
    color: #9279D0;
}

.cyan {
    background: #E2F7FA;
    color: #26A2B8;
}

.red {
    background: #FFF0F0;
    color: #D96E6E;
}

.stat-label {
    color: #8293A4;
    font-size: 11px;
    line-height: 1.4;
}

.stat-card h2 {
    color: #092D50;
    font-size: 21px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.stat-card small {
    display: block;
    margin-top: 6px;
    color: #A0ACB9;
    font-size: 10px;
}

/* =====================================
   GRAFIK
===================================== */

.chart-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 17px;
    margin-bottom: 20px;
}

.chart-box {
    background: #FFFFFF;
    border: 1px solid #E7EEF6;
    border-radius: 11px;
    padding: 20px;
    min-width: 0;
    box-shadow: 0 3px 12px rgba(9,45,80,0.035);
}

.chart-title {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 13px;
    font-weight: 650;
    margin-bottom: 18px;
    color: #092D50;
}

.chart-title i {
    color: #087FC1;
}

.chart-container {
    height: 250px;
    position: relative;
}

/* =====================================
   TABEL
===================================== */

.table-box {
    background: #FFFFFF;
    border: 1px solid #E7EEF6;
    border-radius: 11px;
    padding: 20px;
    margin-bottom: 20px;
    overflow-x: auto;
    box-shadow: 0 3px 12px rgba(9,45,80,0.035);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 17px;
}

.table-header h3 {
    font-size: 14px;
    color: #092D50;
}

.table-header span {
    color: #93A2B0;
    font-size: 11px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th {
    text-align: left;
    color: #71869A;
    font-size: 11px;
    font-weight: 600;
    background: #F3F8FC;
    padding: 13px 11px;
    white-space: nowrap;
}

td {
    padding: 13px 11px;
    border-bottom: 1px solid #EDF2F6;
    font-size: 11px;
    color: #5F7388;
}

tbody tr:hover {
    background: #F8FBFE;
}

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 5px;
    font-size: 10px;
    background: #EAF4FC;
    color: #087FC1;
}

.tagihan {
    color: #D96E6E;
    font-weight: 650;
}

.lunas {
    color: #40A981;
    font-weight: 650;
}

.empty {
    text-align: center;
    color: #9AA8B7;
    padding: 25px;
}

/* FOOTER */
.dashboard-footer {
    text-align: center;
    color: #9AAABD;
    font-size: 11px;
    padding: 12px 0 5px;
}

/* =====================================
   RESPONSIVE
===================================== */

@media (max-width: 1200px) {
    .cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .chart-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .main {
        margin-left: 0;
        padding: 18px;
    }

    .top-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 15px;
    }

    .header-right {
        width: 100%;
        justify-content: space-between;
    }

    .page-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 10px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .filter-box {
        justify-content: flex-start;
    }

    .filter-box form {
        width: 100%;
    }

    .filter-group {
        flex: 1;
        min-width: 125px;
    }

    .filter-group input {
        width: 100%;
    }

    .table-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 7px;
    }
}

</style>
</head>

<body>

<!-- SIDEBAR DARI FILE TERPISAH -->
<?php include "sidebar.php"; ?>

<!-- =====================================
     KONTEN DASHBOARD
===================================== -->

<div class="main">

    <!-- HEADER ATAS -->
    <div class="top-header">

        <div class="header-title">
            <h1>Dashboard</h1>
            <p>Ringkasan operasional Teman.Nyuci</p>
        </div>

        <div class="header-right">

            <div class="date-now">
                <i class="fa-regular fa-calendar"></i>
                <?= date("d F Y") ?>
            </div>

            <div class="header-user">

                <div class="header-avatar">
                    <?= htmlspecialchars(strtoupper(substr($namaUser, 0, 1))) ?>
                </div>

                <div>
                    <strong>
                        <?= htmlspecialchars($namaUser) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($labelRole) ?>
                    </span>
                </div>

            </div>

        </div>

    </div>

    <!-- JUDUL HALAMAN -->
    <div class="page-heading">

        <div>
            <h2>Ringkasan Dashboard</h2>

            <p>
                Pantau aktivitas laundry dan informasi operasional
                berdasarkan data terbaru.
            </p>
        </div>

    </div>

    <!-- FILTER TANGGAL -->
    <div class="filter-box">

        <form method="GET">

            <div class="filter-group">
                <label>Tanggal Mulai</label>

                <input
                    type="date"
                    name="tanggal_awal"
                    value="<?= htmlspecialchars($tanggal_awal) ?>"
                    required
                >
            </div>

            <div class="filter-group">
                <label>Tanggal Akhir</label>

                <input
                    type="date"
                    name="tanggal_akhir"
                    value="<?= htmlspecialchars($tanggal_akhir) ?>"
                    required
                >
            </div>

            <button type="submit" class="btn-filter">
                <i class="fa-solid fa-filter"></i>
                Terapkan
            </button>

            <a href="dashboard.php" class="btn-reset">
                Reset
            </a>

        </form>

    </div>

    <!-- =====================================
         8 KARTU STATISTIK
    ===================================== -->

    <div class="cards">

        <!-- PENDAPATAN -->
        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon blue">
                    <i class="fa-solid fa-wallet"></i>
                </div>

                <span class="stat-label">
                    Total Pendapatan
                </span>
            </div>

            <h2><?= rupiah($total_pendapatan) ?></h2>

            <small>Pembayaran berhasil pada periode ini</small>

        </div>

        <!-- TOTAL PESANAN -->
        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon green">
                    <i class="fa-solid fa-box"></i>
                </div>

                <span class="stat-label">
                    Total Pesanan
                </span>
            </div>

            <h2><?= number_format($total_pesanan, 0, ",", ".") ?></h2>

            <small>Pesanan masuk pada periode ini</small>

        </div>

        <!-- DIPROSES -->
        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon orange">
                    <i class="fa-solid fa-shirt"></i>
                </div>

                <span class="stat-label">
                    Sedang Diproses
                </span>
            </div>

            <h2><?= number_format($total_proses, 0, ",", ".") ?></h2>

            <small>Pesanan yang sedang dikerjakan</small>

        </div>

        <!-- SISA TAGIHAN -->
        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon purple">
                    <i class="fa-solid fa-receipt"></i>
                </div>

                <span class="stat-label">
                    Sisa Tagihan
                </span>
            </div>

            <h2><?= rupiah($sisa_tagihan) ?></h2>

            <small>Tagihan pesanan belum lunas</small>

        </div>

        <!-- TOTAL PELANGGAN -->
        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon cyan">
                    <i class="fa-solid fa-users"></i>
                </div>

                <span class="stat-label">
                    Total Pelanggan
                </span>
            </div>

            <h2><?= number_format($total_pelanggan, 0, ",", ".") ?></h2>

            <small>Seluruh data pelanggan</small>

        </div>

        <!-- TOTAL LAYANAN -->
        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon blue">
                    <i class="fa-solid fa-soap"></i>
                </div>

                <span class="stat-label">
                    Total Layanan
                </span>
            </div>

            <h2><?= number_format($total_layanan, 0, ",", ".") ?></h2>

            <small>Layanan laundry tersedia</small>

        </div>

        <!-- PEMBAYARAN BERHASIL -->
        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <span class="stat-label">
                    Pembayaran Berhasil
                </span>
            </div>

            <h2><?= number_format($pembayaran_berhasil, 0, ",", ".") ?></h2>

            <small>Pembayaran pada periode ini</small>

        </div>

        <!-- PEMBAYARAN MENUNGGU -->
        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon red">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <span class="stat-label">
                    Pembayaran Menunggu
                </span>
            </div>

            <h2><?= number_format($pembayaran_menunggu, 0, ",", ".") ?></h2>

            <small>Menunggu konfirmasi pembayaran</small>

        </div>

    </div>

    <!-- =====================================
         GRAFIK
    ===================================== -->

    <div class="chart-grid">

        <!-- GRAFIK PENDAPATAN -->
        <div class="chart-box">

            <div class="chart-title">
                <i class="fa-solid fa-chart-line"></i>
                Pendapatan pada Periode Terpilih
            </div>

            <div class="chart-container">
                <canvas id="grafikPendapatan"></canvas>
            </div>

        </div>

        <!-- GRAFIK STATUS -->
        <div class="chart-box">

            <div class="chart-title">
                <i class="fa-solid fa-chart-pie"></i>
                Pesanan Berdasarkan Status
            </div>

            <div class="chart-container">
                <canvas id="grafikStatus"></canvas>
            </div>

        </div>

    </div>

    <!-- =====================================
         ANALISIS PESANAN DAN PEMBAYARAN
    ===================================== -->

    <div class="table-box">

        <div class="table-header">

            <h3>Analisis Pesanan dan Pembayaran</h3>

            <span>
                10 pesanan terbaru pada periode terpilih
            </span>

        </div>

        <table>

            <thead>
                <tr>
                    <th>Kode Pesanan</th>
                    <th>Pelanggan</th>
                    <th>Tanggal Masuk</th>
                    <th>Status</th>
                    <th>Total Harga</th>
                    <th>Sudah Dibayar</th>
                    <th>Sisa Tagihan</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($queryAnalisis && mysqli_num_rows($queryAnalisis) > 0): ?>

                <?php while ($row = mysqli_fetch_assoc($queryAnalisis)): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($row["kode_pesanan"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row["nama_pelanggan"]) ?>
                        </td>

                        <td>
                            <?= date("d-m-Y", strtotime($row["tanggal_masuk"])) ?>
                        </td>

                        <td>
                            <span class="status">
                                <?= htmlspecialchars($row["status"]) ?>
                            </span>
                        </td>

                        <td>
                            <?= rupiah($row["total_harga"]) ?>
                        </td>

                        <td>
                            <?= rupiah($row["sudah_dibayar"]) ?>
                        </td>

                        <td>

                            <?php if ($row["sisa_tagihan"] > 0): ?>

                                <span class="tagihan">
                                    <?= rupiah($row["sisa_tagihan"]) ?>
                                </span>

                            <?php else: ?>

                                <span class="lunas">
                                    Lunas
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7" class="empty">
                        Belum ada data pesanan pada periode ini.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- =====================================
         PESANAN TERBARU
    ===================================== -->

    <div class="table-box">

        <div class="table-header">

            <h3>Pesanan Terbaru</h3>

            <span>5 pesanan terakhir</span>

        </div>

        <table>

            <thead>
                <tr>
                    <th>Kode Pesanan</th>
                    <th>Pelanggan</th>
                    <th>Layanan</th>
                    <th>Tanggal Masuk</th>
                    <th>Total Harga</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($queryTerbaru && mysqli_num_rows($queryTerbaru) > 0): ?>

                <?php while ($row = mysqli_fetch_assoc($queryTerbaru)): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($row["kode_pesanan"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row["nama_pelanggan"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row["nama_layanan"]) ?>
                        </td>

                        <td>
                            <?= date("d-m-Y", strtotime($row["tanggal_masuk"])) ?>
                        </td>

                        <td>
                            <?= rupiah($row["total_harga"]) ?>
                        </td>

                        <td>
                            <span class="status">
                                <?= htmlspecialchars($row["status"]) ?>
                            </span>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="empty">
                        Belum ada pesanan.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <div class="dashboard-footer">
        © <?= date("Y") ?> Teman.Nyuci. All rights reserved.
    </div>

</div>

<!-- =====================================
     JAVASCRIPT GRAFIK
===================================== -->

<script>

// DATA DARI DATABASE
const labelPendapatan = <?= json_encode($labelPendapatan) ?>;
const dataPendapatan = <?= json_encode($dataPendapatan) ?>;

const labelStatus = <?= json_encode($labelStatus) ?>;
const dataStatus = <?= json_encode($dataStatus) ?>;

// FORMAT RUPIAH
function formatRupiah(value) {
    return "Rp " + Number(value).toLocaleString("id-ID");
}

// GRAFIK PENDAPATAN
new Chart(document.getElementById("grafikPendapatan"), {

    type: "line",

    data: {
        labels: labelPendapatan,

        datasets: [{
            label: "Pendapatan",
            data: dataPendapatan,

            borderColor: "#087FC1",
            backgroundColor: "rgba(8,127,193,0.12)",

            fill: true,
            tension: 0.35,
            borderWidth: 3,

            pointBackgroundColor: "#087FC1",
            pointBorderColor: "#FFFFFF",
            pointBorderWidth: 2,
            pointRadius: 4
        }]
    },

    options: {

        responsive: true,
        maintainAspectRatio: false,

        plugins: {

            legend: {
                display: false
            },

            tooltip: {
                callbacks: {
                    label: function(context) {
                        return formatRupiah(context.raw);
                    }
                }
            }

        },

        scales: {

            y: {
                beginAtZero: true,

                grid: {
                    color: "#EDF2F7"
                },

                ticks: {
                    callback: function(value) {
                        return formatRupiah(value);
                    }
                }
            },

            x: {
                grid: {
                    display: false
                }
            }

        }

    }

});

// GRAFIK STATUS PESANAN
new Chart(document.getElementById("grafikStatus"), {

    type: "doughnut",

    data: {

        labels: labelStatus,

        datasets: [{

            data: dataStatus,

            backgroundColor: [
                "#087FC1",
                "#55B5E3",
                "#8EC5F5",
                "#A8D9D0",
                "#F5C98B",
                "#B8A8E8"
            ],

            borderWidth: 3,
            borderColor: "#FFFFFF"

        }]

    },

    options: {

        responsive: true,
        maintainAspectRatio: false,
        cutout: "65%",

        plugins: {

            legend: {

                position: "bottom",

                labels: {
                    usePointStyle: true,
                    padding: 17,

                    font: {
                        size: 10
                    }
                }

            }

        }

    }

});

</script>

</body>
</html>
