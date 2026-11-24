<?php
require_once __DIR__ . '/vendor/autoload.php';

\Midtrans\Config::$serverKey    = 'SB-Mid-server-18lex0YcsPi-RLQdBph.Ixnl';
\Midtrans\Config::$isProduction = false;
\Midtrans\Config::$isSanitized  = true;
\Midtrans\Config::$is3ds        = true;

$conn = new mysqli('localhost', 'root', '', 'teman_nyuci');
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Koneksi DB gagal']));
}
$conn->set_charset('utf8mb4');