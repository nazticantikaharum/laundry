<?php
/**
 * Sinkronkan semua pembayaran QRIS yang masih 'Menunggu' dengan status di Midtrans.
 * Mengembalikan jumlah yang berubah dan yang masih menunggu.
 */
function petakanStatusBayar(string $trxStatus, string $fraud = ''): string
{
    if ($trxStatus === 'settlement') return 'Berhasil';
    if ($trxStatus === 'capture' && $fraud !== 'challenge') return 'Berhasil';
    if (in_array($trxStatus, ['cancel', 'deny', 'expire', 'failure'], true)) return 'Dibatalkan';
    return 'Menunggu';
}

function sinkronkanPembayaran(mysqli $conn, int $limit = 20): array
{
    $berubah = 0;
    $menunggu = 0;

    $res = mysqli_query($conn,
        "SELECT id_pembayaran, midtrans_order_id
         FROM pembayaran
         WHERE metode = 'QRIS'
           AND status_bayar = 'Menunggu'
           AND midtrans_order_id IS NOT NULL
         ORDER BY id_pembayaran DESC
         LIMIT " . (int)$limit
    );

    if (!$res) {
        return ['berubah' => 0, 'menunggu' => 0];
    }

    while ($row = mysqli_fetch_assoc($res)) {
        try {
            $s = \Midtrans\Transaction::status($row['midtrans_order_id']);

            $trxStatus = $s->transaction_status ?? 'pending';
            $trxId     = $s->transaction_id ?? null;
            $fraud     = $s->fraud_status ?? '';
            $hasil     = petakanStatusBayar($trxStatus, $fraud);

            $stmt = mysqli_prepare($conn,
                "UPDATE pembayaran
                 SET tanggal_bayar = IF(? = 'Berhasil', NOW(), tanggal_bayar),
                     status_bayar = ?,
                     midtrans_status = ?,
                     midtrans_transaction_id = COALESCE(?, midtrans_transaction_id)
                 WHERE id_pembayaran = ?
                   AND status_bayar = 'Menunggu'"
            );
            $idBayar = (int)$row['id_pembayaran'];
            mysqli_stmt_bind_param($stmt, "ssssi", $hasil, $hasil, $trxStatus, $trxId, $idBayar);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($hasil === 'Menunggu') $menunggu++; else $berubah++;

        } catch (Throwable $e) {
            // 404 = pelanggan belum memilih/membayar di Snap -> anggap masih menunggu
            $menunggu++;
        }
    }

    return ['berubah' => $berubah, 'menunggu' => $menunggu];
}