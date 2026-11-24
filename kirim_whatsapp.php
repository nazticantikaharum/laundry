
<?php
require_once 'whatsapp_config.php';

$hasil = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nomor = preg_replace('/\D/', '', $_POST['nomor'] ?? '');
    $pesan = trim($_POST['pesan'] ?? '');

    if (empty($nomor) || empty($pesan)) {
        $error = 'Nomor WhatsApp dan pesan wajib diisi.';
    } else {

        $url = 'https://graph.facebook.com/' .
               WA_API_VERSION . '/' .
               WA_PHONE_ID . '/messages';

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $nomor,
            'type' => 'text',
            'text' => [
                'body' => $pesan
            ]
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . WA_TOKEN,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            $error = 'Koneksi gagal: ' . $curlError;
        } else {
            $result = json_decode($response, true);

            if (
                $httpCode >= 200 &&
                $httpCode < 300 &&
                isset($result['messages'][0]['id'])
            ) {
                $hasil = 'Pesan berhasil dikirim!';
            } else {
                $error = $result['error']['message']
                    ?? 'Pesan gagal dikirim.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Uji WhatsApp Teman.Nyuci</title>
</head>
<body>

<h2>Uji Pengiriman WhatsApp</h2>

<?php if ($hasil): ?>
    <p style="color:green;">
        <?= htmlspecialchars($hasil) ?>
    </p>
<?php endif; ?>

<?php if ($error): ?>
    <p style="color:red;">
        <?= htmlspecialchars($error) ?>
    </p>
<?php endif; ?>

<form method="POST">

    <label>Nomor WhatsApp penerima</label><br>

    <input
        type="text"
        name="nomor"
        value="6282195666346"
        required
    >

    <br><br>

    <label>Isi pesan</label><br>

    <textarea name="pesan" required>Halo, ini pesan percobaan dari Teman.Nyuci.</textarea>

    <br><br>

    <button type="submit">Kirim Pesan</button>

</form>

</body>
</html>
