<?php
session_start();
require_once "koneksi.php";
require_once "midtrans_config.php";
require_once "midtrans_sync.php";

header('Content-Type: application/json');

if (!isset($_SESSION["id_user"])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

session_write_close(); // supaya tidak memblokir halaman lain

echo json_encode(sinkronkanPembayaran($conn));