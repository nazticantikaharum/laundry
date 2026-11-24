<?php

require_once __DIR__ . '/vendor/autoload.php';

// Konfigurasi Midtrans Sandbox
\Midtrans\Config::$serverKey = 'SB-Mid-server-18lex0YcsPi-RLQdBph.Ixnl';
\Midtrans\Config::$clientKey = 'SB-Mid-client-h0EJEGJJ3sjqoUDL';

\Midtrans\Config::$isProduction = false;
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

?>