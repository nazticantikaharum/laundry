
<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/midtrans_error.log');
error_reporting(E_ALL);
require_once "koneksi.php";
require_once "midtrans_config.php";

date_default_timezone_set("Asia/Jakarta");

// Ambil notifikasi JSON dari Midtrans
error_log("Midtrans notification accessed.");

$input = file_get_contents("php://input");
error_log("Notification input: " . $input);

$data = json_decode($input, true);

// Validasi data notifikasi
if (
    !$data ||
    empty($data["order_id"]) ||
    empty($data["status_code"]) ||
    !isset($data["gross_amount"]) ||
    empty($data["signature_key"]) ||
    empty($data["transaction_status"])
) {
    http_response_code(400);
    exit("Notifikasi tidak lengkap.");
}

// Verifikasi signature Midtrans
$signature = hash(
    "sha512",
    $data["order_id"] .
    $data["status_code"] .
    $data["gross_amount"] .
    \Midtrans\Config::$serverKey
);

if (!hash_equals($signature, $data["signature_key"])) {
    http_response_code(403);
    exit("Signature tidak valid.");
}

// Cari pembayaran berdasarkan Order ID
$orderId = $data["order_id"];

$sql = "SELECT id_pembayaran, jumlah_bayar, status_bayar
        FROM pembayaran
        WHERE midtrans_order_id = ?
        AND metode = 'QRIS'
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $orderId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$pembayaran = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$pembayaran) {
    http_response_code(404);
    exit("Pembayaran tidak ditemukan.");
}

// Cocokkan nominal dengan pembulatan Snap
$nominalMidtrans = (int) round((float)$data["gross_amount"]);
$nominalDatabase = (int) round((float)$pembayaran["jumlah_bayar"]);

if ($nominalMidtrans !== $nominalDatabase) {
    http_response_code(400);
    exit("Nominal pembayaran tidak sesuai.");
}

// Ambil status transaksi
$transactionStatus = $data["transaction_status"];
$statusCode = $data["status_code"];
$fraudStatus = $data["fraud_status"] ?? "";

// Tentukan status pembayaran
if (
    $transactionStatus === "settlement" &&
    $statusCode === "200"
) {
    $statusBayar = "Berhasil";

} elseif (
    $transactionStatus === "capture" &&
    $statusCode === "200" &&
    $fraudStatus === "accept"
) {
    $statusBayar = "Berhasil";

} elseif ($transactionStatus === "pending") {
    $statusBayar = "Menunggu";

} elseif (
    in_array(
        $transactionStatus,
        ["deny", "cancel", "expire"],
        true
    )
) {
    $statusBayar = "Dibatalkan";

} else {
    $statusBayar = $pembayaran["status_bayar"];
}

// Jangan menurunkan status pembayaran yang sudah berhasil
if ($pembayaran["status_bayar"] === "Berhasil") {
    $statusBayar = "Berhasil";
}

// Simpan ID transaksi Midtrans
$transactionId = $data["transaction_id"] ?? "";

// Perbarui pembayaran
$sql = "UPDATE pembayaran
        SET status_bayar = ?,
            midtrans_status = ?,
            midtrans_transaction_id = ?
        WHERE id_pembayaran = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "sssi",
    $statusBayar,
    $transactionStatus,
    $transactionId,
    $pembayaran["id_pembayaran"]
);

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    exit("Gagal memperbarui pembayaran.");
}

mysqli_stmt_close($stmt);

// Respons sukses untuk Midtrans
http_response_code(200);
echo "OK";
?>
