
<?php
session_start();

require_once "koneksi.php";
require_once "midtrans_config.php";

date_default_timezone_set("Asia/Jakarta");

if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

$id_pembayaran = (int)($_GET["id"] ?? 0);

if ($id_pembayaran <= 0) {
    die("ID pembayaran tidak valid.");
}

// Ambil transaksi QRIS yang masih menunggu
$sql = "SELECT
            pb.*,
            p.kode_pesanan
        FROM pembayaran pb
        JOIN pesanan p ON pb.id_pesanan = p.id_pesanan
        WHERE pb.id_pembayaran = ?
          AND pb.metode = 'QRIS'
          AND pb.status_bayar = 'Menunggu'
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Gagal menyiapkan data pembayaran.");
}

mysqli_stmt_bind_param($stmt, "i", $id_pembayaran);
mysqli_stmt_execute($stmt);

$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$data) {
    die("Pembayaran QRIS tidak ditemukan, sudah dibayar, atau sudah tidak berstatus menunggu.");
}

// Jika QRIS sudah pernah dibuat, jangan membuat transaksi baru
if (!empty($data["qr_url"]) && !empty($data["midtrans_order_id"])) {
    header("Location: transaksi.php?status=qris&id=" . $id_pembayaran);
    exit;
}

// Buat QRIS hanya jika belum tersedia
$order_id = "TN-" . $id_pembayaran . "-" . time();

try {

    $params = [
        "payment_type" => "qris",

        "transaction_details" => [
            "order_id" => $order_id,
            "gross_amount" => (int)$data["jumlah_bayar"]
        ],

        "item_details" => [
            [
                "id" => $data["kode_pesanan"],
                "price" => (int)$data["jumlah_bayar"],
                "quantity" => 1,
                "name" => "Laundry Teman.Nyuci"
            ]
        ],

        "qris" => [
            "acquirer" => "gopay"
        ]
    ];

    $response = \Midtrans\CoreApi::charge($params);

    // Cari URL QRIS
    $qr_url = "";

    if (!empty($response->actions)) {
        foreach ($response->actions as $action) {

            if (
                isset($action->name, $action->url) &&
                in_array(
                    $action->name,
                    ["generate-qr-code-v2", "generate-qr-code"],
                    true
                )
            ) {
                $qr_url = $action->url;

                if ($action->name === "generate-qr-code-v2") {
                    break;
                }
            }
        }
    }

    if ($qr_url === "") {
        throw new Exception("URL QRIS tidak ditemukan dari respons Midtrans.");
    }

    $transaction_id = $response->transaction_id ?? "";
    $midtrans_status = $response->transaction_status ?? "pending";

    // Simpan data QRIS
    $sqlUpdate = "UPDATE pembayaran
                  SET midtrans_order_id = ?,
                      midtrans_transaction_id = ?,
                      midtrans_status = ?,
                      qr_url = ?
                  WHERE id_pembayaran = ?
                    AND status_bayar = 'Menunggu'";

    $stmtUpdate = mysqli_prepare($conn, $sqlUpdate);

    if (!$stmtUpdate) {
        throw new Exception("Gagal menyiapkan penyimpanan data QRIS.");
    }

    mysqli_stmt_bind_param(
        $stmtUpdate,
        "ssssi",
        $order_id,
        $transaction_id,
        $midtrans_status,
        $qr_url,
        $id_pembayaran
    );

    if (!mysqli_stmt_execute($stmtUpdate)) {
        mysqli_stmt_close($stmtUpdate);
        throw new Exception("Data QRIS gagal disimpan ke database.");
    }

    mysqli_stmt_close($stmtUpdate);

    header("Location: transaksi.php?status=qris&id=" . $id_pembayaran);
    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo "Gagal membuat QRIS Midtrans: " .
         htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8");
}
?>
