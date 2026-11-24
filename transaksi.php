<?php
session_start();

require_once "koneksi.php";
require_once "midtrans_config.php";
require_once "midtrans_sync.php";   // <-- (a) BARU: fungsi sinkron status Midtrans

date_default_timezone_set("Asia/Jakarta");

// CEK LOGIN
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

$roleUser = strtolower($_SESSION["role"] ?? "");

if (!in_array($roleUser, ["admin", "petugas"], true)) {
    header("Location: login.php");
    exit;
}

$namaUser = $_SESSION["nama"] ?? "Pengguna";
$isAdmin = ($roleUser === "admin");

function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ",", ".");
}

function bukaWhatsApp($nomor, $pesan) {
    $nomor = preg_replace('/[^0-9]/', '', $nomor);

    if (substr($nomor, 0, 1) === "0") {
        $nomor = "62" . substr($nomor, 1);
    } elseif (substr($nomor, 0, 2) !== "62") {
        $nomor = "62" . $nomor;
    }

    if ($nomor !== "") {
        header("Location: https://wa.me/" . $nomor . "?text=" . urlencode($pesan));
        exit;
    }
}

$pesan = "";
$error = "";

/* =========================================
   TAMBAH PESANAN
========================================= */
if (isset($_POST["tambah_pesanan"])) {

    $id_pelanggan = (int)($_POST["id_pelanggan"] ?? 0);
    $id_layanan = (int)($_POST["id_layanan"] ?? 0);
    $berat = (float)($_POST["berat_kg"] ?? 0);

    if ($id_pelanggan <= 0 || $id_layanan <= 0 || $berat <= 0) {
        $error = "Lengkapi data pesanan dengan benar.";
    } else {

        $stmt = mysqli_prepare($conn,
            "SELECT harga_per_kg, estimasi_hari
             FROM layanan WHERE id_layanan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_layanan);
        mysqli_stmt_execute($stmt);
        $layanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn,
            "SELECT nama, no_hp FROM pelanggan WHERE id_pelanggan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_pelanggan);
        mysqli_stmt_execute($stmt);
        $pelanggan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$layanan || !$pelanggan) {
            $error = "Pelanggan atau layanan tidak ditemukan.";
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

                $stmt = mysqli_prepare($conn,
                    "INSERT INTO pesanan
                    (kode_pesanan, id_pelanggan, id_user, id_layanan,
                     tanggal_masuk, tanggal_estimasi_selesai,
                     berat_kg, total_harga, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

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

                mysqli_stmt_close($stmt);

                $id_pesanan = mysqli_insert_id($conn);

                $stmtRiwayat = mysqli_prepare($conn,
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

                mysqli_stmt_close($stmtRiwayat);
                mysqli_commit($conn);

                $pesanWA = "Halo " . $pelanggan["nama"] . ",\n\n"
                    . "Pesanan laundry Anda sudah kami terima.\n"
                    . "Kode Pesanan: " . $kode . "\n"
                    . "Status: DIPROSES\n\n"
                    . "Terima kasih telah menggunakan Teman.Nyuci.";

                bukaWhatsApp($pelanggan["no_hp"], $pesanWA);

                header("Location: transaksi.php?status=tambah_pesanan");
                exit;

            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $error = $e->getMessage();
            }
        }
    }
}

/* =========================================
   UBAH STATUS PESANAN
========================================= */
if (isset($_POST["ubah_status"])) {

    $id = (int)($_POST["id_pesanan"] ?? 0);
    $statusBaru = $_POST["status"] ?? "";

    if ($id > 0 && in_array($statusBaru, ["Diproses", "Selesai"], true)) {

        $stmt = mysqli_prepare($conn,
            "SELECT p.status, p.kode_pesanan, pl.nama, pl.no_hp
             FROM pesanan p
             JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
             WHERE p.id_pesanan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $dataLama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$dataLama) {
            $error = "Pesanan tidak ditemukan.";
        } else {

            $statusSebelumnya = $dataLama["status"];

            $stmt = mysqli_prepare($conn,
                "UPDATE pesanan SET status = ? WHERE id_pesanan = ?"
            );

            mysqli_stmt_bind_param($stmt, "si", $statusBaru, $id);

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                if ($statusSebelumnya !== $statusBaru) {

                    $id_user = (int)$_SESSION["id_user"];

                    $stmtRiwayat = mysqli_prepare($conn,
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
                    mysqli_stmt_close($stmtRiwayat);
                }

                if (
                    $statusBaru === "Selesai" &&
                    $statusSebelumnya !== "Selesai"
                ) {
                    $pesanWA = "Halo " . $dataLama["nama"] . ",\n\n"
                        . "Laundry Anda sudah SELESAI dan siap diambil.\n"
                        . "Kode Pesanan: " . $dataLama["kode_pesanan"] . "\n\n"
                        . "Silakan datang ke Teman.Nyuci. Terima kasih.";

                    bukaWhatsApp($dataLama["no_hp"], $pesanWA);
                }

                header("Location: transaksi.php?status=ubah_status");
                exit;

            } else {
                $error = "Status pesanan gagal diubah.";
                mysqli_stmt_close($stmt);
            }
        }

    } else {
        $error = "Status pesanan tidak valid.";
    }
}

/* =========================================
   HAPUS PESANAN - ADMIN
========================================= */
if (isset($_POST["hapus_pesanan"]) && $isAdmin) {

    $id = (int)($_POST["id_pesanan"] ?? 0);

    mysqli_begin_transaction($conn);

    try {

        $stmt = mysqli_prepare($conn,
            "SELECT id_pembayaran FROM pembayaran WHERE id_pesanan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $adaPembayaran = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($adaPembayaran) {
            throw new Exception("Pesanan sudah memiliki riwayat pembayaran.");
        }

        foreach (["riwayat_status", "notifikasi_wa"] as $tabel) {
            $stmt = mysqli_prepare($conn,
                "DELETE FROM $tabel WHERE id_pesanan = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($conn,
            "DELETE FROM pesanan WHERE id_pesanan = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) < 1) {
            throw new Exception("Pesanan tidak ditemukan.");
        }

        mysqli_stmt_close($stmt);
        mysqli_commit($conn);

        header("Location: transaksi.php?status=hapus_pesanan");
        exit;

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $error = "Pesanan tidak dapat dihapus. " . $e->getMessage();
    }
}

/* =========================================
   TAMBAH PEMBAYARAN - SNAP
========================================= */
if (isset($_POST["bayar"])) {

    $id_pesanan = (int)($_POST["id_pesanan_bayar"] ?? 0);
    $metode = $_POST["metode"] ?? "";

    if (
        $id_pesanan <= 0 ||
        !in_array($metode, ["Tunai", "QRIS"], true)
    ) {
        $error = "Data pembayaran tidak valid.";
    } else {

        $stmt = mysqli_prepare($conn,
            "SELECT p.total_harga, p.kode_pesanan, pl.nama, pl.no_hp
             FROM pesanan p
             JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
             WHERE p.id_pesanan = ?
             AND p.status != 'Dibatalkan'"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
        mysqli_stmt_execute($stmt);
        $dataPesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$dataPesanan) {
            $error = "Pesanan tidak ditemukan atau sudah dibatalkan.";
        } else {

            $stmt = mysqli_prepare($conn,
                "SELECT id_pembayaran FROM pembayaran
                 WHERE id_pesanan = ?
                 AND status_bayar IN ('Menunggu', 'Berhasil')"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
            mysqli_stmt_execute($stmt);
            $pembayaranAda = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($pembayaranAda) {
                $error = "Pesanan ini sudah memiliki transaksi pembayaran.";
            } else {

                $jumlah = (float)$dataPesanan["total_harga"];
                $tanggal = date("Y-m-d H:i:s");
                $statusBayar = ($metode === "QRIS") ? "Menunggu" : "Berhasil";

                mysqli_begin_transaction($conn);

                try {

                    $stmt = mysqli_prepare($conn,
                        "INSERT INTO pembayaran
                        (id_pesanan, tanggal_bayar, jumlah_bayar,
                         metode, status_bayar)
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "isdss",
                        $id_pesanan,
                        $tanggal,
                        $jumlah,
                        $metode,
                        $statusBayar
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Pembayaran gagal disimpan.");
                    }

                    $idBaru = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);

                    if ($metode === "QRIS") {

                        $orderId = "TN-" . $idBaru . "-" . time();

                        $params = [
                            "transaction_details" => [
                                "order_id" => $orderId,
                                "gross_amount" => (int)round($jumlah)
                            ],
                            "customer_details" => [
                                "first_name" => $dataPesanan["nama"],
                                "phone" => $dataPesanan["no_hp"]
                            ],
                            "item_details" => [
                                [
                                    "id" => $dataPesanan["kode_pesanan"],
                                    "price" => (int)round($jumlah),
                                    "quantity" => 1,
                                    "name" => "Laundry Teman.Nyuci"
                                ]
                            ]
                        ];

                        $snapToken = \Midtrans\Snap::getSnapToken($params);

                        $stmt = mysqli_prepare($conn,
                            "UPDATE pembayaran
                             SET midtrans_order_id = ?,
                                 snap_token = ?,
                                 midtrans_status = 'pending'
                             WHERE id_pembayaran = ?"
                        );

                        mysqli_stmt_bind_param(
                            $stmt,
                            "ssi",
                            $orderId,
                            $snapToken,
                            $idBaru
                        );

                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Snap Token gagal disimpan.");
                        }

                        mysqli_stmt_close($stmt);
                    }

                    mysqli_commit($conn);

                    if ($metode === "QRIS") {
                        header("Location: transaksi.php?status=snap&id=" . $idBaru);
                        exit;
                    }

                    header("Location: transaksi.php?status=tambah_bayar");
                    exit;

                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    $error = "Pembayaran gagal: " . $e->getMessage();
                }
            }
        }
    }
}

/* =========================================
   HAPUS RIWAYAT PEMBAYARAN - ADMIN
========================================= */
if (isset($_POST["hapus_riwayat"]) && $isAdmin) {

    $id = (int)($_POST["id_pembayaran"] ?? 0);

    if ($id > 0) {

        $stmt = mysqli_prepare($conn,
            "DELETE FROM pembayaran WHERE id_pembayaran = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (
            mysqli_stmt_execute($stmt) &&
            mysqli_stmt_affected_rows($stmt) > 0
        ) {
            mysqli_stmt_close($stmt);
            header("Location: transaksi.php?status=hapus_bayar");
            exit;
        } else {
            $error = "Riwayat pembayaran gagal dihapus.";
        }

        mysqli_stmt_close($stmt);
    }
}

/* =========================================
   NOTIFIKASI
========================================= */
if (isset($_GET["status"])) {

    $daftarPesan = [
        "tambah_pesanan" => "Pesanan berhasil ditambahkan.",
        "ubah_status" => "Status pesanan berhasil diperbarui.",
        "hapus_pesanan" => "Pesanan berhasil dihapus.",
        "tambah_bayar" => "Pembayaran tunai berhasil disimpan.",
        "hapus_bayar" => "Riwayat pembayaran berhasil dihapus."
    ];

    $pesan = $daftarPesan[$_GET["status"]] ?? "";
}

/* =========================================
   (b) BARU: SINKRON STATUS PEMBAYARAN DARI MIDTRANS
   Harus berada SEBELUM query data form, snap, ringkasan, dan daftar.
========================================= */
sinkronkanPembayaran($conn);

/* =========================================
   DATA FORM
========================================= */
$daftarPelanggan = mysqli_query($conn,
    "SELECT * FROM pelanggan ORDER BY nama ASC"
);

$daftarLayanan = mysqli_query($conn,
    "SELECT * FROM layanan ORDER BY nama_layanan ASC"
);

$pesananBelumBayar = mysqli_query($conn,
    "SELECT p.id_pesanan, p.kode_pesanan, p.total_harga, pl.nama
     FROM pesanan p
     JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
     WHERE p.status != 'Dibatalkan'
     AND NOT EXISTS (
         SELECT 1 FROM pembayaran pb
         WHERE pb.id_pesanan = p.id_pesanan
         AND pb.status_bayar IN ('Menunggu', 'Berhasil')
     )
     ORDER BY p.id_pesanan DESC"
);

/* =========================================
   DATA SNAP UNTUK POPUP
========================================= */
$dataSnap = null;

if (($_GET["status"] ?? "") === "snap") {

    $idSnap = (int)($_GET["id"] ?? 0);

    if ($idSnap > 0) {

        $stmtSnap = mysqli_prepare($conn,
            "SELECT pb.*, p.kode_pesanan
             FROM pembayaran pb
             JOIN pesanan p ON pb.id_pesanan = p.id_pesanan
             WHERE pb.id_pembayaran = ?
             AND pb.metode = 'QRIS'
             AND pb.status_bayar = 'Menunggu'"
        );

        mysqli_stmt_bind_param($stmtSnap, "i", $idSnap);
        mysqli_stmt_execute($stmtSnap);
        $dataSnap = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSnap));
        mysqli_stmt_close($stmtSnap);
    }

    if (!$dataSnap || empty($dataSnap["snap_token"])) {
        $error = "Token pembayaran tidak ditemukan atau pembayaran sudah selesai. Silakan periksa riwayat pembayaran.";
    }
}

/* =========================================
   PENCARIAN TRANSAKSI
========================================= */
$cari = trim($_GET["cari"] ?? "");

$sqlTransaksi = "
    SELECT
        p.*,
        pl.nama AS nama_pelanggan,
        l.nama_layanan,
        l.jenis_waktu,
        pb.id_pembayaran,
        pb.metode,
        pb.status_bayar,
        pb.jumlah_bayar
    FROM pesanan p
    JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    JOIN layanan l ON p.id_layanan = l.id_layanan
    LEFT JOIN pembayaran pb ON pb.id_pembayaran = (
        SELECT MAX(pb2.id_pembayaran)
        FROM pembayaran pb2
        WHERE pb2.id_pesanan = p.id_pesanan
    )
";

if ($cari !== "") {
    $sqlTransaksi .= "
        WHERE p.kode_pesanan LIKE ?
        OR pl.nama LIKE ?
        OR p.status LIKE ?
        OR pb.metode LIKE ?
        OR pb.status_bayar LIKE ?
    ";
}

$sqlTransaksi .= " ORDER BY p.id_pesanan DESC";

if ($cari !== "") {

    $stmt = mysqli_prepare($conn, $sqlTransaksi);
    $keyword = "%" . $cari . "%";

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    );

    mysqli_stmt_execute($stmt);
    $transaksi = mysqli_stmt_get_result($stmt);

} else {
    $transaksi = mysqli_query($conn, $sqlTransaksi);
}

/* =========================================
   RIWAYAT PEMBAYARAN
========================================= */
$sqlRiwayat = "
    SELECT pb.*, p.kode_pesanan, pl.nama
    FROM pembayaran pb
    JOIN pesanan p ON pb.id_pesanan = p.id_pesanan
    JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    ORDER BY pb.id_pembayaran DESC
";

$riwayat = mysqli_query($conn, $sqlRiwayat);

/* =========================================
   RINGKASAN
========================================= */
$dataJumlahPesanan = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS jumlah FROM pesanan"
));

$dataPendapatan = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(jumlah_bayar),0) AS total
     FROM pembayaran WHERE status_bayar = 'Berhasil'"
));

$dataMenunggu = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS jumlah
     FROM pembayaran WHERE status_bayar = 'Menunggu'"
));

$dataBelumLunas = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS jumlah
     FROM pesanan p
     WHERE p.status != 'Dibatalkan'
     AND NOT EXISTS (
         SELECT 1 FROM pembayaran pb
         WHERE pb.id_pesanan = p.id_pesanan
         AND pb.status_bayar = 'Berhasil'
     )"
));
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Transaksi | Teman.Nyuci</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<?php if ($dataSnap && !empty($dataSnap["snap_token"])): ?>
<script
    src="https://app.sandbox.midtrans.com/snap/snap.js"
    data-client-key="<?= aman(\Midtrans\Config::$clientKey) ?>">
</script>
<?php endif; ?>

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
    gap: 15px;
}

.header h1 {
    font-size: 25px;
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
    white-space: nowrap;
}

.summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.summary-card {
    background: white;
    padding: 21px;
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
    font-size: 21px;
}

.card {
    background: white;
    padding: 24px;
    border: 1px solid #e7eef6;
    border-radius: 12px;
    margin-bottom: 22px;
    box-shadow: 0 4px 15px rgba(70,110,150,.04);
}

.card h2 {
    font-size: 17px;
    color: #34445a;
    margin-bottom: 20px;
}

.card h2 i {
    color: #77b5e8;
    margin-right: 7px;
}

.card-desc {
    color: #9aa8b7;
    font-size: 12px;
    margin-top: -13px;
    margin-bottom: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 17px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 12px;
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
}

.form-group input[readonly] {
    background: #f3f7fc;
}

/* PILIHAN METODE PEMBAYARAN */
.metode-group {
    grid-column: 1 / -1;
}

.metode-pilihan {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.metode-card {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 16px;
    border: 1px solid #dfe8f1;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: .2s;
}

.metode-card:hover {
    border-color: #79b5e8;
    background: #f7fbff;
}

.metode-card:has(input:checked) {
    border-color: #64a9e5;
    background: #edf6ff;
}

.metode-card input {
    width: 17px;
    height: 17px;
    margin: 0;
    accent-color: #64a9e5;
    cursor: pointer;
}

.metode-card i {
    color: #64a9e5;
    font-size: 22px;
}

.metode-card span {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.metode-card strong {
    color: #34445a;
    font-size: 13px;
}

.metode-card small {
    color: #8998aa;
    font-size: 11px;
}

.form-button {
    margin-top: 20px;
}

.btn {
    border: none;
    padding: 9px 13px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    font-family: inherit;
    white-space: nowrap;
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

.btn-nota {
    background: #e8f3fc;
    color: #559bd5;
}

.btn-small {
    padding: 7px 9px;
    font-size: 11px;
}

.btn-continue {
    background: #e8f3fc;
    color: #559bd5;
    margin-bottom: 5px;
}

.table-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 18px;
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
    width: 230px;
    outline: none;
    font-size: 12px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 1100px;
}

th {
    background: #f3f7fc;
    color: #65768b;
    text-align: left;
    padding: 13px 10px;
    white-space: nowrap;
}

td {
    padding: 13px 10px;
    border-bottom: 1px solid #edf1f5;
    color: #66768a;
    vertical-align: middle;
}

tbody tr:hover td {
    background: #fafcff;
}

.status-form {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 7px;
}

.status-form select {
    padding: 7px;
    border: 1px solid #dfe8f1;
    border-radius: 6px;
    font-size: 11px;
}

.badge {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.berhasil, .selesai {
    background: #eaf5ee;
    color: #277344;
}

.menunggu {
    background: #fff7df;
    color: #956b12;
}

.belum {
    background: #fff0f0;
    color: #b83232;
}

.proses {
    background: #eaf3fc;
    color: #559bd5;
}

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

.footer {
    text-align: center;
    color: #9aa8b7;
    font-size: 11px;
    padding: 20px;
}

/* PANEL SNAP */
.snap-panel {
    background: #f8fbff;
    border: 1px solid #dceaf7;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 22px;
}

.snap-panel p {
    color: #718197;
    font-size: 13px;
    margin-bottom: 12px;
}

.snap-panel strong {
    color: #34445a;
}

.snap-panel .btn {
    margin-top: 8px;
}

@media(max-width: 1100px) {
    .summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .form-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 700px) {
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

    .search,
    .search input {
        width: 100%;
    }

    .metode-pilihan {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 18px;
    }
}
</style>
</head>

<body>

<?php include "sidebar.php"; ?>

<div class="main">

    <div class="header">
        <div>
            <h1>Transaksi</h1>
            <p>Kelola pesanan laundry dan pembayaran pelanggan.</p>
        </div>

        <div class="header-date">
            <i class="fa-regular fa-calendar"></i>
            <?= date("d F Y") ?>
        </div>
    </div>

    <?php if ($pesan): ?>
        <div class="alert">
            <i class="fa-solid fa-circle-check"></i>
            <?= aman($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= aman($error) ?>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN -->
    <div class="summary">

        <div class="summary-card">
            <p>Total Pesanan</p>
            <h3><?= (int)$dataJumlahPesanan["jumlah"] ?></h3>
        </div>

        <div class="summary-card">
            <p>Belum Lunas</p>
            <h3><?= (int)$dataBelumLunas["jumlah"] ?></h3>
        </div>

        <div class="summary-card">
            <p>Total Pendapatan</p>
            <h3><?= rupiah($dataPendapatan["total"]) ?></h3>
        </div>

        <div class="summary-card">
            <p>Pembayaran Menunggu</p>
            <h3><?= (int)$dataMenunggu["jumlah"] ?></h3>
        </div>

    </div>

    <!-- PANEL SNAP -->
    <?php if ($dataSnap && !empty($dataSnap["snap_token"])): ?>

        <div class="snap-panel">
            <h2>
                <i class="fa-solid fa-wallet"></i>
                Pembayaran Midtrans
            </h2>

            <p>
                Kode pesanan:
                <strong><?= aman($dataSnap["kode_pesanan"]) ?></strong>
            </p>

            <p>
                Total pembayaran:
                <strong><?= rupiah($dataSnap["jumlah_bayar"]) ?></strong>
            </p>

            <p>
                Status:
                <span class="badge menunggu">Menunggu</span>
            </p>

            <button type="button" id="pay-button" class="btn btn-primary">
                <i class="fa-solid fa-qrcode"></i>
                Buka Pembayaran Midtrans
            </button>

            <a href="transaksi.php" class="btn btn-nota">
                Kembali ke Transaksi
            </a>
        </div>

    <?php endif; ?>

    <!-- FORM TAMBAH PESANAN -->
    <div class="card">

        <h2>
            <i class="fa-solid fa-plus-circle"></i>
            Tambah Pesanan
        </h2>

        <p class="card-desc">
            Masukkan data pelanggan dan layanan laundry.
        </p>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">
                    <label>Pilih Pelanggan</label>

                    <select name="id_pelanggan" required>
                        <option value="">-- Pilih Pelanggan --</option>

                        <?php while ($pl = mysqli_fetch_assoc($daftarPelanggan)): ?>
                            <option value="<?= (int)$pl["id_pelanggan"] ?>">
                                <?= aman($pl["nama"]) ?>
                            </option>
                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Pilih Layanan</label>

                    <select
                        name="id_layanan"
                        id="layanan"
                        required
                        onchange="hitungTotal()"
                    >
                        <option value="">-- Pilih Layanan --</option>

                        <?php while ($l = mysqli_fetch_assoc($daftarLayanan)): ?>
                            <option
                                value="<?= (int)$l["id_layanan"] ?>"
                                data-harga="<?= (float)$l["harga_per_kg"] ?>"
                                data-estimasi="<?= (int)$l["estimasi_hari"] ?>"
                            >
                                <?= aman($l["nama_layanan"]) ?>
                                - <?= aman($l["jenis_waktu"]) ?>
                            </option>
                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Berat Laundry (Kg)</label>

                    <input
                        type="number"
                        name="berat_kg"
                        id="berat"
                        min="0.1"
                        step="0.1"
                        required
                        oninput="hitungTotal()"
                        placeholder="Contoh: 3"
                    >
                </div>

                <div class="form-group">
                    <label>Harga per Kg</label>
                    <input type="text" id="harga" readonly placeholder="Otomatis">
                </div>

                <div class="form-group">
                    <label>Estimasi Pengerjaan</label>
                    <input type="text" id="estimasi" readonly placeholder="Otomatis">
                </div>

                <div class="form-group">
                    <label>Total Harga</label>
                    <input type="text" id="total" readonly placeholder="Otomatis">
                </div>

            </div>

            <button
                type="submit"
                name="tambah_pesanan"
                class="btn btn-primary form-button"
            >
                <i class="fa-solid fa-save"></i>
                Simpan Pesanan
            </button>

        </form>
    </div>

    <!-- FORM PEMBAYARAN -->
    <div class="card">

        <h2>
            <i class="fa-solid fa-wallet"></i>
            Tambah Pembayaran
        </h2>

        <p class="card-desc">
            Pilih pesanan yang belum memiliki pembayaran aktif.
        </p>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">
                    <label>Pilih Kode Pesanan</label>

                    <select
                        name="id_pesanan_bayar"
                        id="pesananBayar"
                        required
                        onchange="tampilkanTagihan()"
                    >
                        <option value="">-- Pilih Pesanan --</option>

                        <?php while ($pb = mysqli_fetch_assoc($pesananBelumBayar)): ?>
                            <option
                                value="<?= (int)$pb["id_pesanan"] ?>"
                                data-nama="<?= aman($pb["nama"]) ?>"
                                data-total="<?= (float)$pb["total_harga"] ?>"
                            >
                                <?= aman($pb["kode_pesanan"]) ?>
                                - <?= aman($pb["nama"]) ?>
                            </option>
                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Nama Pelanggan</label>
                    <input type="text" id="namaBayar" readonly placeholder="Otomatis">
                </div>

                <div class="form-group">
                    <label>Total Tagihan</label>
                    <input type="text" id="tagihan" readonly placeholder="Otomatis">
                </div>

                <!-- METODE PEMBAYARAN RADIO BUTTON -->
                <div class="form-group metode-group">
                    <label>Metode Pembayaran</label>

                    <div class="metode-pilihan">

                        <label class="metode-card">
                            <input
                                type="radio"
                                name="metode"
                                value="Tunai"
                                required
                            >

                            <i class="fa-solid fa-money-bill-wave"></i>

                            <span>
                                <strong>Tunai</strong>
                                <small>Pembayaran langsung</small>
                            </span>
                        </label>

                        <label class="metode-card">
                            <input
                                type="radio"
                                name="metode"
                                value="QRIS"
                                required
                            >

                            <i class="fa-solid fa-qrcode"></i>

                            <span>
                                <strong>QRIS</strong>
                                <small>Pembayaran melalui Midtrans</small>
                            </span>
                        </label>

                    </div>
                </div>

            </div>

            <button
                type="submit"
                name="bayar"
                class="btn btn-primary form-button"
            >
                <i class="fa-solid fa-money-bill-wave"></i>
                Simpan Pembayaran
            </button>

        </form>
    </div>

    <!-- DAFTAR TRANSAKSI -->
    <div class="card">

        <div class="table-top">
            <h2>
                <i class="fa-solid fa-receipt"></i>
                Daftar Transaksi
            </h2>

            <form method="GET" class="search">
                <input
                    type="text"
                    name="cari"
                    placeholder="Cari kode, pelanggan, status..."
                    value="<?= aman($cari) ?>"
                >

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-search"></i>
                    Cari
                </button>

                <?php if ($cari !== ""): ?>
                    <a href="transaksi.php" class="btn btn-nota">Reset</a>
                <?php endif; ?>
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
                        <th>Status Pesanan</th>
                        <th>Status Pembayaran</th>
                        <th>Metode</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($transaksi && mysqli_num_rows($transaksi) > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($transaksi)): ?>

                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <strong><?= aman($row["kode_pesanan"]) ?></strong>
                            </td>

                            <td><?= aman($row["nama_pelanggan"]) ?></td>

                            <td><?= aman($row["nama_layanan"]) ?></td>

                            <td><?= aman($row["berat_kg"]) ?> Kg</td>

                            <td><?= rupiah($row["total_harga"]) ?></td>

                            <td>
                                <?= date("d/m/Y", strtotime($row["tanggal_masuk"])) ?>
                            </td>

                            <td>
                                <?php if ($row["status"] === "Selesai"): ?>
                                    <span class="badge selesai">Selesai</span>
                                <?php else: ?>
                                    <span class="badge proses">
                                        <?= aman($row["status"]) ?>
                                    </span>
                                <?php endif; ?>

                                <form method="POST" class="status-form">
                                    <input
                                        type="hidden"
                                        name="id_pesanan"
                                        value="<?= (int)$row["id_pesanan"] ?>"
                                    >

                                    <select name="status">
                                        <option
                                            value="Diproses"
                                            <?= $row["status"] === "Diproses" ? "selected" : "" ?>
                                        >
                                            Diproses
                                        </option>

                                        <option
                                            value="Selesai"
                                            <?= $row["status"] === "Selesai" ? "selected" : "" ?>
                                        >
                                            Selesai
                                        </option>
                                    </select>

                                    <button
                                        type="submit"
                                        name="ubah_status"
                                        class="btn btn-primary btn-small"
                                    >
                                        Ubah
                                    </button>
                                </form>
                            </td>

                            <td>
                                <?php if ($row["status_bayar"] === "Berhasil"): ?>
                                    <span class="badge berhasil">Lunas</span>

                                <?php elseif ($row["status_bayar"] === "Menunggu"): ?>
                                    <span class="badge menunggu">Menunggu</span>

                                <?php else: ?>
                                    <span class="badge belum">Belum Lunas</span>
                                <?php endif; ?>
                            </td>

                            <td><?= aman($row["metode"] ?? "-") ?></td>

                            <td>
                                <a
                                    href="nota.php?id=<?= (int)$row["id_pesanan"] ?>"
                                    class="btn btn-nota btn-small"
                                >
                                    <i class="fa-solid fa-print"></i>
                                    Nota
                                </a>

                                <?php if (!$row["id_pembayaran"] && $isAdmin): ?>
                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirm('Yakin ingin menghapus pesanan ini?')"
                                    >
                                        <input
                                            type="hidden"
                                            name="id_pesanan"
                                            value="<?= (int)$row["id_pesanan"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="hapus_pesanan"
                                            class="btn btn-delete btn-small"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="11" class="empty">
                            Belum ada data transaksi.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <!-- RIWAYAT PEMBAYARAN -->
    <div class="card">

        <div class="table-top">
            <h2>
                <i class="fa-solid fa-clock-rotate-left"></i>
                Riwayat Pembayaran
            </h2>
        </div>

        <div class="table-wrap">
            <table style="min-width:850px;">
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

                    <?php while ($bayar = mysqli_fetch_assoc($riwayat)): ?>

                        <tr>
                            <td><?= $no++ ?></td>

                            <td><?= aman($bayar["kode_pesanan"]) ?></td>

                            <td><?= aman($bayar["nama"]) ?></td>

                            <td>
                                <?= date("d/m/Y", strtotime($bayar["tanggal_bayar"])) ?>
                            </td>

                            <td><?= rupiah($bayar["jumlah_bayar"]) ?></td>

                            <td><?= aman($bayar["metode"]) ?></td>

                            <td>
                                <?php if ($bayar["status_bayar"] === "Berhasil"): ?>
                                    <span class="badge berhasil">Berhasil</span>

                                <?php elseif ($bayar["status_bayar"] === "Menunggu"): ?>
                                    <span class="badge menunggu">Menunggu</span>

                                <?php else: ?>
                                    <span class="badge belum">
                                        <?= aman($bayar["status_bayar"]) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (
                                    $bayar["metode"] === "QRIS" &&
                                    $bayar["status_bayar"] === "Menunggu"
                                ): ?>
                                    <a
                                        href="transaksi.php?status=snap&id=<?= (int)$bayar["id_pembayaran"] ?>"
                                        class="btn btn-continue btn-small"
                                    >
                                        <i class="fa-solid fa-qrcode"></i>
                                        Lanjutkan Pembayaran
                                    </a>
                                <?php endif; ?>

                                <?php if ($isAdmin): ?>
                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus riwayat pembayaran ini?')"
                                    >
                                        <input
                                            type="hidden"
                                            name="id_pembayaran"
                                            value="<?= (int)$bayar["id_pembayaran"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="hapus_riwayat"
                                            class="btn btn-delete btn-small"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                            Hapus
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty">
                            Belum ada riwayat pembayaran.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        © <?= date("Y") ?> Teman.Nyuci - Sistem Informasi Laundry
    </div>

</div>

<script>
function hitungTotal() {
    const layanan = document.getElementById("layanan");
    const berat = parseFloat(document.getElementById("berat").value) || 0;

    const pilihan = layanan.options[layanan.selectedIndex];

    const harga = parseFloat(pilihan.dataset.harga) || 0;
    const estimasi = parseInt(pilihan.dataset.estimasi) || 0;

    document.getElementById("harga").value =
        harga > 0 ? "Rp " + harga.toLocaleString("id-ID") : "";

    document.getElementById("estimasi").value =
        estimasi > 0 ? estimasi + " Hari" : "";

    const total = harga * berat;

    document.getElementById("total").value =
        total > 0 ? "Rp " + total.toLocaleString("id-ID") : "";
}

function tampilkanTagihan() {
    const select = document.getElementById("pesananBayar");
    const pilihan = select.options[select.selectedIndex];

    const nama = pilihan.dataset.nama || "";
    const total = parseFloat(pilihan.dataset.total) || 0;

    document.getElementById("namaBayar").value = nama;

    document.getElementById("tagihan").value =
        total > 0 ? "Rp " + total.toLocaleString("id-ID") : "";
}

// (c) BARU: SINKRON STATUS KE SERVER
async function sinkronStatus() {
    try {
        const res = await fetch("sync_pending.php", { cache: "no-store" });
        return await res.json();
    } catch (e) {
        return null;
    }
}

// MIDTRANS SNAP
<?php if ($dataSnap && !empty($dataSnap["snap_token"])): ?>

document.addEventListener("DOMContentLoaded", function () {

    const tombolBayar = document.getElementById("pay-button");

    if (tombolBayar) {
        tombolBayar.addEventListener("click", function () {

            snap.pay(
                <?= json_encode($dataSnap["snap_token"]) ?>,
                {
                    onSuccess: async function (result) {
                        await sinkronStatus();
                        window.location.href = "transaksi.php";
                    },

                    onPending: async function (result) {
                        await sinkronStatus();
                        window.location.href = "transaksi.php";
                    },

                    onError: function (result) {
                        alert("Pembayaran gagal diproses. Silakan coba kembali.");
                    },

                    onClose: async function () {
                        await sinkronStatus();
                        window.location.href = "transaksi.php";
                    }
                }
            );

        });
    }

});

<?php endif; ?>

// (c) BARU: AUTO-REFRESH KALAU MASIH ADA YANG MENUNGGU
<?php if ((int)$dataMenunggu["jumlah"] > 0 && !$dataSnap): ?>

(function () {
    let sedangCek = false;
    let percobaan = 0;

    const timer = setInterval(async function () {
        if (sedangCek) return;
        sedangCek = true;
        percobaan++;

        const data = await sinkronStatus();
        sedangCek = false;

        if (data && data.berubah > 0) {
            clearInterval(timer);
            window.location.reload();
        } else if (data && data.menunggu === 0) {
            clearInterval(timer);
        }

        if (percobaan >= 60) clearInterval(timer); // berhenti setelah ±5 menit
    }, 5000);
})();

<?php endif; ?>

</script>

</body>
</html>