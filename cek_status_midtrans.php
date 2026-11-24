
<?php
session_start();

require_once "koneksi.php";
require_once "midtrans_config.php";

if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

$id_pembayaran = (int)($_GET["id"] ?? 0);

if ($id_pembayaran <= 0) {
    die("ID pembayaran tidak valid.");
}

// Ambil pembayaran dari database
$sql = "SELECT *
        FROM pembayaran
        WHERE id_pembayaran = ?
        AND metode = 'QRIS'
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_pembayaran);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data || empty($data["midtrans_order_id"])) {
    die("Data transaksi Midtrans tidak ditemukan.");
}

try {
    // Minta status transaksi langsung ke Midtrans
    $status = \Midtrans\Transaction::status(
        $data["midtrans_order_id"]
    );

    // Pastikan transaksi ditemukan
    if (
        !isset($status->status_code) ||
        $status->status_code != "200"
    ) {
        die("Gagal mengambil status dari Midtrans.");
    }

    // Cocokkan Order ID dan nominal
    if (
        $status->order_id != $data["midtrans_order_id"] ||
        (float)$status->gross_amount != (float)$data["jumlah_bayar"]
    ) {
        die("Data transaksi tidak sesuai.");
    }

    $transaction_status = $status->transaction_status;
    $transaction_id = $status->transaction_id ?? "";

    // Tentukan status pembayaran
    if (
        $transaction_status == "settlement" ||
        (
            $transaction_status == "capture" &&
            ($status->fraud_status ?? "") == "accept"
        )
    ) {
        $status_bayar = "Berhasil";
    } elseif ($transaction_status == "pending") {
        $status_bayar = "Menunggu";
    } elseif (
        in_array(
            $transaction_status,
            ["deny", "cancel", "expire"]
        )
    ) {
        $status_bayar = "Gagal";
    } else {
        $status_bayar = "Menunggu";
    }

    // Simpan status terbaru ke database
    $sql = "UPDATE pembayaran
            SET status_bayar = ?,
                midtrans_status = ?,
                midtrans_transaction_id = ?
            WHERE id_pembayaran = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sssi",
        $status_bayar,
        $transaction_status,
        $transaction_id,
        $id_pembayaran
    );

    if (!mysqli_stmt_execute($stmt)) {
        die("Gagal memperbarui database.");
    }

    // Kembali ke halaman pembayaran
    header(
        "Location: pembayaran.php?status=cek_" .
        strtolower($status_bayar) .
        "&id=" . $id_pembayaran
    );
    exit;

} catch (Exception $e) {
    die(
        "Gagal mengecek pembayaran: " .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8")
    );
}
?>
