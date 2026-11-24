
<?php
session_start();
require_once "koneksi.php";

date_default_timezone_set("Asia/Jakarta");

// CEK LOGIN
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

// DATA AKUN
$namaUser = $_SESSION["nama"] ?? "Pengguna";
$roleUser = $_SESSION["role"] ?? "Petugas";
$isAdmin = strtolower($roleUser) === "admin";

// FILTER TANGGAL
$tanggal_awal = $_GET["tanggal_awal"] ?? "";
$tanggal_akhir = $_GET["tanggal_akhir"] ?? "";

$where = "";
$params = [];
$types = "";

if ($tanggal_awal !== "" && $tanggal_akhir !== "") {
    $where = " WHERE DATE(p.tanggal_masuk) BETWEEN ? AND ?";
    $params = [$tanggal_awal, $tanggal_akhir];
    $types = "ss";
} elseif ($tanggal_awal !== "") {
    $where = " WHERE DATE(p.tanggal_masuk) >= ?";
    $params = [$tanggal_awal];
    $types = "s";
} elseif ($tanggal_akhir !== "") {
    $where = " WHERE DATE(p.tanggal_masuk) <= ?";
    $params = [$tanggal_akhir];
    $types = "s";
}

// RINGKASAN LAPORAN
$sqlRingkasan = "
    SELECT
        COUNT(*) AS total_pesanan,
        COALESCE(SUM(
            p.status IN ('Diproses', 'Diterima')
        ), 0) AS diproses,
        COALESCE(SUM(
            p.status IN ('Selesai', 'Diambil')
        ), 0) AS selesai,
        COALESCE(SUM(pb.total_bayar), 0) AS total_pendapatan
    FROM pesanan p
    LEFT JOIN (
        SELECT
            id_pesanan,
            SUM(jumlah_bayar) AS total_bayar
        FROM pembayaran
        WHERE status_bayar = 'Berhasil'
        GROUP BY id_pesanan
    ) pb ON p.id_pesanan = pb.id_pesanan
    $where
";

$stmt = $conn->prepare($sqlRingkasan);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$ringkasan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// DETAIL LAPORAN
$sqlDetail = "
    SELECT
        p.kode_pesanan,
        pl.nama AS nama_pelanggan,
        l.nama_layanan,
        p.tanggal_masuk,
        p.berat_kg,
        p.total_harga,

        CASE
            WHEN p.status IN ('Diproses', 'Diterima')
                THEN 'Diproses'
            WHEN p.status IN ('Selesai', 'Diambil')
                THEN 'Selesai'
            ELSE p.status
        END AS status,

        CASE
            WHEN COALESCE(pb.total_bayar, 0) > 0
                THEN 'Lunas'
            ELSE 'Belum Lunas'
        END AS status_pembayaran

    FROM pesanan p

    JOIN pelanggan pl
        ON p.id_pelanggan = pl.id_pelanggan

    JOIN layanan l
        ON p.id_layanan = l.id_layanan

    LEFT JOIN (
        SELECT
            id_pesanan,
            SUM(jumlah_bayar) AS total_bayar
        FROM pembayaran
        WHERE status_bayar = 'Berhasil'
        GROUP BY id_pesanan
    ) pb ON p.id_pesanan = pb.id_pesanan

    $where

    ORDER BY p.tanggal_masuk DESC
";

$stmt = $conn->prepare($sqlDetail);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$dataLaporan = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan | Teman.Nyuci</title>

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

        /* KONTEN UTAMA */
        .main {
            margin-left: 285px;
            padding: 30px 32px;
            min-height: 100vh;
        }

        .header {
            margin-bottom: 27px;
        }

        .header h1 {
            font-size: 27px;
            color: #34445a;
            margin-bottom: 6px;
        }

        .header p {
            color: #9aa8b7;
            font-size: 15px;
        }

        .header-date {
            display: inline-block;
            margin-top: 12px;
            color: #748399;
            font-size: 13px;
            background: white;
            border: 1px solid #e7eef6;
            border-radius: 8px;
            padding: 10px 14px;
        }

        /* KARTU RINGKASAN */
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .card {
            background: white;
            padding: 23px;
            border: 1px solid #e7eef6;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(70,110,150,.04);
        }

        .card p {
            color: #8998aa;
            font-size: 14px;
            margin-bottom: 13px;
        }

        .card p i {
            margin-right: 7px;
            color: #72b3e9;
        }

        .card h2 {
            font-size: 26px;
            color: #34445a;
        }

        /* STATUS PESANAN */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .status-card {
            background: white;
            padding: 22px;
            border: 1px solid #e7eef6;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(70,110,150,.04);
        }

        .status-card p {
            color: #8998aa;
            font-size: 14px;
            margin-bottom: 9px;
        }

        .status-card p i {
            color: #72b3e9;
            margin-right: 7px;
        }

        .status-card h3 {
            color: #34445a;
            font-size: 25px;
        }

        /* PANEL */
        .panel {
            background: white;
            padding: 25px;
            border: 1px solid #e7eef6;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(70,110,150,.04);
            margin-bottom: 22px;
        }

        .panel h3 {
            font-size: 18px;
            color: #34445a;
            margin-bottom: 20px;
        }

        .panel h3 i {
            color: #72b3e9;
            margin-right: 8px;
        }

        /* FILTER */
        .filter {
            display: flex;
            gap: 12px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter div {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .filter label {
            font-size: 13px;
            color: #748399;
        }

        .filter input {
            padding: 12px;
            border: 1px solid #dfe8f1;
            border-radius: 9px;
            font-family: inherit;
            color: #34445a;
            outline: none;
        }

        .filter input:focus {
            border-color: #79b5e8;
        }

        /* TOMBOL */
        button,
        .btn {
            border: none;
            padding: 12px 17px;
            border-radius: 9px;
            background: #64a9e5;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: .2s;
            font-family: inherit;
        }

        button:hover,
        .btn:hover {
            background: #4d96d5;
        }

        .btn-reset {
            background: #eaf3fb;
            color: #559bd5;
        }

        .btn-reset:hover {
            background: #dcecf9;
        }

        /* TABEL */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th,
        td {
            padding: 15px 12px;
            border-bottom: 1px solid #edf1f5;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f3f7fc;
            color: #65768b;
            font-weight: 650;
            white-space: nowrap;
        }

        td {
            color: #66768a;
        }

        tbody tr:hover td {
            background: #fafcff;
        }

        /* STATUS */
        .badge {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 7px;
            background: #eaf3fb;
            color: #559bd5;
            font-size: 12px;
            white-space: nowrap;
            font-weight: 600;
        }

        .status-selesai,
        .lunas {
            background: #eaf5ee;
            color: #277344;
        }

        .belum {
            background: #fff4e5;
            color: #a56816;
        }

        .footer {
            text-align: right;
            color: #9aa8b7;
            font-size: 12px;
            margin-top: 30px;
            padding-bottom: 10px;
        }

        /* RESPONSIVE */
        @media(max-width: 1050px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 850px) {
            .main {
                margin-left: 235px;
                padding: 24px 20px;
            }
        }

        @media(max-width: 650px) {
            .main {
                margin-left: 0;
                padding: 18px;
            }

            .cards,
            .status-grid {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 18px;
            }

            .filter {
                align-items: stretch;
                flex-direction: column;
            }

            .filter div,
            .filter input,
            .filter button,
            .filter .btn {
                width: 100%;
            }
        }

        /* CETAK LAPORAN */
        @media print {
            .sidebar,
            .filter,
            .no-print,
            .header-date {
                display: none !important;
            }

            .main {
                margin: 0;
                padding: 0;
            }

            body {
                background: white;
                color: black;
            }

            .panel,
            .card,
            .status-card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }

            table {
                min-width: 0;
                width: 100%;
            }

            th,
            td {
                font-size: 10px;
                padding: 8px 5px;
            }

            .footer {
                display: none;
            }
        }
    </style>
</head>

<body>

<?php
// PANGGIL SIDEBAR UTAMA
include "sidebar.php";
?>

<div class="main">

    <div class="header">
        <h1>Laporan</h1>
        <p>Ringkasan data pesanan dan pendapatan Teman.Nyuci.</p>

        <div class="header-date">
            <i class="fa-regular fa-calendar"></i>
            <?= date("d F Y") ?>
        </div>
    </div>

    <!-- RINGKASAN -->
    <div class="cards">

        <div class="card">
            <p>
                <i class="fa-solid fa-box"></i>
                Total Pesanan
            </p>
            <h2>
                <?= number_format($ringkasan["total_pesanan"] ?? 0) ?>
            </h2>
        </div>

        <div class="card">
            <p>
                <i class="fa-solid fa-wallet"></i>
                Total Pendapatan
            </p>
            <h2>
                Rp <?= number_format(
                    $ringkasan["total_pendapatan"] ?? 0,
                    0, ',', '.'
                ) ?>
            </h2>
        </div>

        <div class="card">
            <p>
                <i class="fa-solid fa-circle-check"></i>
                Pesanan Selesai
            </p>
            <h2>
                <?= number_format($ringkasan["selesai"] ?? 0) ?>
            </h2>
        </div>

    </div>

    <!-- STATUS PESANAN -->
    <div class="status-grid">

        <div class="status-card">
            <p>
                <i class="fa-solid fa-spinner"></i>
                Diproses
            </p>
            <h3>
                <?= number_format($ringkasan["diproses"] ?? 0) ?>
            </h3>
        </div>

        <div class="status-card">
            <p>
                <i class="fa-solid fa-check-circle"></i>
                Selesai
            </p>
            <h3>
                <?= number_format($ringkasan["selesai"] ?? 0) ?>
            </h3>
        </div>

    </div>

    <!-- FILTER LAPORAN -->
    <div class="panel">

        <h3>
            <i class="fa-solid fa-filter"></i>
            Filter Laporan
        </h3>

        <form method="GET" class="filter">

            <div>
                <label>Tanggal Awal</label>
                <input
                    type="date"
                    name="tanggal_awal"
                    value="<?= htmlspecialchars($tanggal_awal) ?>">
            </div>

            <div>
                <label>Tanggal Akhir</label>
                <input
                    type="date"
                    name="tanggal_akhir"
                    value="<?= htmlspecialchars($tanggal_akhir) ?>">
            </div>

            <button type="submit">
                <i class="fa-solid fa-filter"></i>
                Terapkan
            </button>

            <a href="laporan.php" class="btn btn-reset">
                <i class="fa-solid fa-rotate-left"></i>
                Reset
            </a>

            <button
                type="button"
                onclick="window.print()">
                <i class="fa-solid fa-print"></i>
                Cetak Laporan
            </button>

        </form>

    </div>

    <!-- DETAIL LAPORAN -->
    <div class="panel">

        <h3>
            <i class="fa-solid fa-table-list"></i>
            Detail Laporan Pesanan
        </h3>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Pesanan</th>
                        <th>Nama Pelanggan</th>
                        <th>Layanan</th>
                        <th>Tanggal Masuk</th>
                        <th>Berat</th>
                        <th>Total Harga</th>
                        <th>Status Pesanan</th>
                        <th>Pembayaran</th>
                    </tr>
                </thead>

                <tbody>

                <?php
                $no = 1;

                if ($dataLaporan->num_rows > 0):
                    while ($row = $dataLaporan->fetch_assoc()):
                ?>

                    <tr>
                        <td><?= $no++ ?></td>

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
                            <?= date(
                                "d-m-Y H:i",
                                strtotime($row["tanggal_masuk"])
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row["berat_kg"]) ?> kg
                        </td>

                        <td>
                            Rp <?= number_format(
                                $row["total_harga"],
                                0, ',', '.'
                            ) ?>
                        </td>

                        <td>
                            <span class="badge <?= $row["status"] === "Selesai" ? "status-selesai" : "" ?>">
                                <?= htmlspecialchars($row["status"]) ?>
                            </span>
                        </td>

                        <td>
                            <span class="badge <?= $row["status_pembayaran"] === "Lunas" ? "lunas" : "belum" ?>">
                                <?= htmlspecialchars($row["status_pembayaran"]) ?>
                            </span>
                        </td>
                    </tr>

                <?php
                    endwhile;
                else:
                ?>

                    <tr>
                        <td colspan="9" style="text-align:center;padding:30px;">
                            <i class="fa-solid fa-inbox"
                               style="font-size:25px;margin-bottom:10px;"></i>
                            <br>
                            Belum ada data laporan.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>
    </div>

    <div class="footer">
        Teman.Nyuci © <?= date("Y") ?> · Sistem Informasi Manajemen Laundry
    </div>

</div>

</body>
</html>

<?php
$stmt->close();
?>
