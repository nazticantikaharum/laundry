<?php
function petakanStatus(string $trxStatus, string $fraud = ''): string
{
    if ($trxStatus === 'settlement') return 'Berhasil';
    if ($trxStatus === 'capture' && $fraud !== 'challenge') return 'Berhasil';
    if (in_array($trxStatus, ['cancel', 'deny', 'expire', 'failure'], true)) return 'Dibatalkan';
    return 'Menunggu'; // pending, authorize, challenge
}

/**
 * Update tabel pembayaran dari data Midtrans.
 * Aman dipanggil berulang, status 'Berhasil' tidak akan diturunkan lagi.
 */
function simpanStatusPembayaran(mysqli $conn, string $orderId, string $trxStatus, ?string $trxId, string $fraud = ''): string
{
    $statusBayar = petakanStatus($trxStatus, $fraud);

    $stmt = $conn->prepare(
        "UPDATE pembayaran
            SET status_bayar = ?,
                midtrans_status = ?,
                midtrans_transaction_id = COALESCE(?, midtrans_transaction_id),
                tanggal_bayar = IF(? = 'Berhasil' AND status_bayar <> 'Berhasil', NOW(), tanggal_bayar)
          WHERE midtrans_order_id = ?
            AND status_bayar <> 'Berhasil'"
    );
    $stmt->bind_param('sssss', $statusBayar, $trxStatus, $trxId, $statusBayar, $orderId);
    $stmt->execute();

    return $statusBayar;
}