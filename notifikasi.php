<?php
require_once 'config_midtrans.php';
require_once 'midtrans_helper.php';

try {
    $n = new \Midtrans\Notification(); // otomatis membaca body POST dari Midtrans
    simpanStatusPembayaran(
        $conn,
        $n->order_id,
        $n->transaction_status,
        $n->transaction_id ?? null,
        $n->fraud_status ?? ''
    );
    http_response_code(200);
    echo 'OK';
} catch (Exception $e) {
    error_log('Notifikasi Midtrans gagal: ' . $e->getMessage());
    http_response_code(500);
}