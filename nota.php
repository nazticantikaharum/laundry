
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

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    die("ID pesanan tidak valid.");
}

// DATA PESANAN
$sql = "
    SELECT p.*,
        pl.nama AS nama_pelanggan,
        pl.no_hp,
        pl.alamat,
        l.nama_layanan,
        l.harga_per_kg,
        u.nama AS nama_petugas
    FROM pesanan p
    JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    JOIN layanan l ON p.id_layanan = l.id_layanan
    JOIN users u ON p.id_user = u.id_user
    WHERE p.id_pesanan = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$pesanan) {
    die("Data pesanan tidak ditemukan.");
}

// DATA PEMBAYARAN TERBARU
$sqlBayar = "
    SELECT metode, status_bayar, jumlah_bayar, tanggal_bayar
    FROM pembayaran
    WHERE id_pesanan = ?
    ORDER BY id_pembayaran DESC
    LIMIT 1
";

$stmtBayar = mysqli_prepare($conn, $sqlBayar);
mysqli_stmt_bind_param($stmtBayar, "i", $id);
mysqli_stmt_execute($stmtBayar);

$pembayaran = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtBayar));

$metode = $pembayaran["metode"] ?? "-";
$statusBayar = $pembayaran["status_bayar"] ?? "Belum Lunas";

if ($statusBayar == "Berhasil") {
    $labelBayar = "LUNAS";
} elseif ($statusBayar == "Menunggu") {
    $labelBayar = "MENUNGGU PEMBAYARAN";
} elseif ($statusBayar == "Dibatalkan") {
    $labelBayar = "DIBATALKAN";
} else {
    $labelBayar = "BELUM LUNAS";
}

// NOMOR WHATSAPP
$nomor = preg_replace('/[^0-9]/', '', $pesanan["no_hp"]);

if (substr($nomor, 0, 1) == "0") {
    $nomor = "62" . substr($nomor, 1);
}

// PESAN WHATSAPP
$isiPesan =
    "STRUK PESANAN TEMAN.NYUCI\n\n" .
    "Kode Pesanan: " . $pesanan["kode_pesanan"] . "\n" .
    "Nama: " . $pesanan["nama_pelanggan"] . "\n" .
    "Layanan: " . $pesanan["nama_layanan"] . "\n" .
    "Berat: " . $pesanan["berat_kg"] . " kg\n" .
    "Total: " . rupiah($pesanan["total_harga"]) . "\n" .
    "Metode Pembayaran: " . $metode . "\n" .
    "Status Pembayaran: " . $labelBayar . "\n" .
    "Status Pesanan: " . $pesanan["status"] . "\n\n" .
    "Terima kasih telah menggunakan Teman.Nyuci.";

$linkWA = "https://wa.me/" . $nomor . "?text=" . urlencode($isiPesan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Nota Pesanan - Teman.Nyuci</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f2f5f8;
    color: #333;
    padding: 25px 15px;
}

.container {
    max-width: 600px;
    margin: auto;
}

.nota {
    background: white;
    padding: 30px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.header {
    text-align: center;
    padding-bottom: 15px;
    border-bottom: 2px dashed #d5dce4;
}

.logo {
    color: #286da8;
    font-size: 25px;
    font-weight: bold;
    margin-bottom: 5px;
}

.header p {
    font-size: 12px;
    color: #777;
    margin-top: 4px;
}

.judul {
    text-align: center;
    margin: 18px 0;
    font-size: 16px;
    letter-spacing: 1px;
}

.info {
    margin-bottom: 13px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 6px 0;
    font-size: 13px;
}

.info-row span:first-child {
    color: #777;
}

.info-row span:last-child {
    text-align: right;
    font-weight: 600;
}

.garis {
    border: none;
    border-top: 1px dashed #cbd5e1;
    margin: 13px 0;
}

.total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 15px;
    font-weight: bold;
    margin: 15px 0;
}

.total strong {
    color: #286da8;
    font-size: 20px;
}

.status {
    text-align: center;
    background: #f4f7fa;
    padding: 10px;
    border-radius: 6px;
    margin-top: 12px;
    line-height: 1.7;
    font-size: 12px;
}

.status strong {
    color: #286da8;
}

.qris {
    text-align: center;
    margin-top: 15px;
    padding: 12px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    page-break-inside: avoid;
    break-inside: avoid;
}

.qris h3 {
    font-size: 14px;
    margin-bottom: 5px;
}

.qris p {
    color: #777;
    font-size: 11px;
}

.qris img {
    width: 150px;
    max-width: 100%;
    max-height: 155px;
    object-fit: contain;
    display: block;
    margin: 8px auto;
}

.qris .nominal {
    font-size: 19px;
    color: #286da8;
    font-weight: bold;
    margin: 6px 0;
}

.qris .peringatan {
    color: #946b13;
    background: #fff7df;
    border-radius: 5px;
    padding: 7px;
    line-height: 1.4;
    margin-top: 7px;
    font-size: 10px;
}

.qris .lunas {
    color: #238348;
    margin-top: 7px;
    font-weight: bold;
}

.footer {
    text-align: center;
    margin-top: 15px;
    padding-top: 12px;
    border-top: 2px dashed #d5dce4;
    font-size: 11px;
    color: #777;
    line-height: 1.6;
}

.tombol {
    display: flex;
    gap: 10px;
    margin-top: 18px;
}

.tombol a,
.tombol button {
    flex: 1;
    text-align: center;
    border: none;
    border-radius: 6px;
    padding: 12px 8px;
    font-size: 13px;
    text-decoration: none;
    cursor: pointer;
}

.wa {
    background: #e8f6ed;
    color: #238348;
}

.cetak {
    background: #286da8;
    color: white;
}

.kembali {
    background: #e5eaf0;
    color: #444;
}

/* PENGATURAN CETAK SATU HALAMAN */
@page {
    size: A4 portrait;
    margin: 8mm;
}

@media print {
    html, body {
        width: 100%;
        height: auto;
        margin: 0;
        padding: 0;
        background: white;
        font-size: 11px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .container {
        width: 100%;
        max-width: 100%;
        margin: 0;
    }

    .nota {
        width: 100%;
        padding: 10px 18px;
        margin: 0;
        border: none;
        border-radius: 0;
    }

    .header {
        padding-bottom: 10px;
    }

    .logo {
        font-size: 22px;
        margin-bottom: 3px;
    }

    .header p {
        font-size: 10px;
        margin-top: 3px;
    }

    .judul {
        font-size: 14px;
        margin: 12px 0;
    }

    .info {
        margin-bottom: 8px;
    }

    .info-row {
        padding: 3px 0;
        font-size: 10px;
    }

    .garis {
        margin: 8px 0;
    }

    .total {
        margin: 10px 0;
        font-size: 12px;
    }

    .total strong {
        font-size: 16px;
    }

    .status {
        margin-top: 9px;
        padding: 7px;
        font-size: 10px;
    }

    .qris {
        margin-top: 9px;
        padding: 8px;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .qris h3 {
        font-size: 12px;
        margin-bottom: 3px;
    }

    .qris p {
        font-size: 9px;
    }

    .qris img {
        width: 125px;
        height: 125px;
        max-height: 125px;
        margin: 5px auto;
    }

    .qris .nominal {
        font-size: 15px;
        margin: 4px 0;
    }

    .qris .peringatan {
        padding: 5px;
        margin-top: 5px;
        font-size: 9px;
    }

    .footer {
        margin-top: 9px;
        padding-top: 8px;
        font-size: 9px;
    }

    .tombol {
        display: none !important;
    }
}

@media (max-width: 500px) {
    .nota {
        padding: 20px 16px;
    }

    .tombol {
        flex-direction: column;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="nota">

        <div class="header">
            <div class="logo">Teman.Nyuci</div>
            <p>Layanan Laundry Bersih, Rapi, dan Wangi</p>
            <p>Terima kasih telah mempercayakan laundry Anda kepada kami.</p>
        </div>

        <h3 class="judul">STRUK PESANAN LAUNDRY</h3>

        <div class="info">

            <div class="info-row">
                <span>Kode Pesanan</span>
                <span><?= aman($pesanan["kode_pesanan"]) ?></span>
            </div>

            <div class="info-row">
                <span>Nama Pelanggan</span>
                <span><?= aman($pesanan["nama_pelanggan"]) ?></span>
            </div>

            <div class="info-row">
                <span>No. HP</span>
                <span><?= aman($pesanan["no_hp"]) ?></span>
            </div>

            <div class="info-row">
                <span>Alamat</span>
                <span><?= aman($pesanan["alamat"]) ?></span>
            </div>

            <div class="info-row">
                <span>Tanggal Masuk</span>
                <span><?= date("d-m-Y H:i", strtotime($pesanan["tanggal_masuk"])) ?></span>
            </div>

            <div class="info-row">
                <span>Estimasi Selesai</span>
                <span><?= date("d-m-Y", strtotime($pesanan["tanggal_estimasi_selesai"])) ?></span>
            </div>

        </div>

        <hr class="garis">

        <div class="info">

            <div class="info-row">
                <span>Layanan</span>
                <span><?= aman($pesanan["nama_layanan"]) ?></span>
            </div>

            <div class="info-row">
                <span>Berat Laundry</span>
                <span><?= aman($pesanan["berat_kg"]) ?> kg</span>
            </div>

            <div class="info-row">
                <span>Harga per Kg</span>
                <span><?= rupiah($pesanan["harga_per_kg"]) ?></span>
            </div>

            <div class="info-row">
                <span>Petugas</span>
                <span><?= aman($pesanan["nama_petugas"]) ?></span>
            </div>

        </div>

        <hr class="garis">

        <div class="total">
            <span>Total Pembayaran</span>
            <strong><?= rupiah($pesanan["total_harga"]) ?></strong>
        </div>

        <div class="info">

            <div class="info-row">
                <span>Metode Pembayaran</span>
                <span><?= aman($metode) ?></span>
            </div>

            <div class="info-row">
                <span>Status Pembayaran</span>
                <span><?= aman($labelBayar) ?></span>
            </div>

            <div class="info-row">
                <span>Status Pesanan</span>
                <span><?= aman($pesanan["status"]) ?></span>
            </div>

        </div>

        <?php if ($metode == "QRIS"): ?>

            <div class="qris">
                <h3>Pembayaran QRIS</h3>
                <p>Scan QRIS di bawah ini untuk melakukan pembayaran.</p>

                <div class="nominal">
                    <?= rupiah($pesanan["total_harga"]) ?>
                </div>

                <img src="image/qris.jpeg" alt="QRIS Teman.Nyuci">

                <?php if ($statusBayar == "Menunggu"): ?>
                    <p class="peringatan">
                        Pembayaran masih menunggu konfirmasi.
                        Silakan lakukan pembayaran dan konfirmasi melalui sistem.
                    </p>
                <?php elseif ($statusBayar == "Berhasil"): ?>
                    <p class="lunas">
                        Pembayaran telah berhasil dicatat.
                    </p>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <div class="status">
            Status Pembayaran:
            <br>
            <strong><?= aman($labelBayar) ?></strong>
            <br>
            Status Laundry:
            <br>
            <strong><?= aman($pesanan["status"]) ?></strong>
        </div>

        <div class="footer">
            <strong>Terima Kasih!</strong>
            <br>
            Simpan struk ini sebagai bukti transaksi.
            <br>
            Teman.Nyuci - Laundry Anda, Prioritas Kami.
        </div>

    </div>

    <div class="tombol">

        <a class="wa"
           href="<?= aman($linkWA) ?>"
           target="_blank">
            Kirim Nota via WhatsApp
        </a>

        <button class="cetak" onclick="window.print()">
            Cetak Struk
        </button>

        <a class="kembali" href="pesanan.php">
            Kembali
        </a>

    </div>

</div>

</body>
</html>
