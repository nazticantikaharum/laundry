<?php
// Halaman PUBLIK: tidak ada session login, tidak ada sidebar, tidak ada dashboard.
require_once "koneksi.php";
date_default_timezone_set("Asia/Jakarta");

function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

$error = "";
$kodeTracking = "";
$input = [
    "nama"    => "",
    "no_hp"   => "",
    "alamat"  => "",
    "layanan" => (int)($_GET["layanan"] ?? 0),
    "berat"   => "",
    "catatan" => ""
];

// DATA LAYANAN
$daftarLayanan = [];
$resLayanan = mysqli_query($conn,
    "SELECT id_layanan, nama_layanan, jenis_waktu, estimasi_hari, harga_per_kg
     FROM layanan ORDER BY nama_layanan ASC, jenis_waktu ASC"
);
while ($resLayanan && $l = mysqli_fetch_assoc($resLayanan)) {
    $daftarLayanan[(int)$l["id_layanan"]] = $l;
}

// PROSES FORM
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $input["nama"]    = trim($_POST["nama"] ?? "");
    $input["no_hp"]   = trim($_POST["no_hp"] ?? "");
    $input["alamat"]  = trim($_POST["alamat"] ?? "");
    $input["layanan"] = (int)($_POST["id_layanan"] ?? 0);
    $input["berat"]   = trim($_POST["berat"] ?? "");
    $input["catatan"] = trim($_POST["catatan"] ?? "");

    $berat = (float)str_replace(",", ".", $input["berat"]);
    $noHpBersih = preg_replace('/[^0-9+]/', '', $input["no_hp"]);

    if ($input["nama"] === "" || $input["alamat"] === "") {
        $error = "Nama dan alamat wajib diisi.";
    } elseif (!preg_match('/^(\+62|62|0)[0-9]{8,13}$/', $noHpBersih)) {
        $error = "Nomor WhatsApp tidak valid. Contoh: 081234567890.";
    } elseif (!isset($daftarLayanan[$input["layanan"]])) {
        $error = "Silakan pilih layanan terlebih dahulu.";
    } elseif ($berat <= 0 || $berat > 100) {
        $error = "Berat estimasi harus antara 0,1 sampai 100 Kg.";
    } else {

        $idLayanan = $input["layanan"];
        $catatan = $input["catatan"] !== "" ? $input["catatan"] : "-";
        $status = "Menunggu Konfirmasi";

        // kode tracking unik (16 karakter hex), coba ulang jika bentrok
        $berhasil = false;
        for ($i = 0; $i < 5 && !$berhasil; $i++) {

            $kode = strtoupper(bin2hex(random_bytes(8)));

            $stmt = mysqli_prepare($conn,
                "INSERT INTO pemesanan_online
                 (nama_pelanggan, no_hp, alamat, id_layanan, berat_estimasi,
                  catatan, tanggal_pesan, status, kode_tracking)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt, "sssidsss",
                $input["nama"], $noHpBersih, $input["alamat"],
                $idLayanan, $berat, $catatan, $status, $kode
            );

            try {
                $berhasil = mysqli_stmt_execute($stmt);
            } catch (Throwable $e) {
                $berhasil = false; // kemungkinan kode_tracking bentrok, ulangi
            }

            mysqli_stmt_close($stmt);

            if ($berhasil) {
                $kodeTracking = $kode;
            }
        }

        if (!$berhasil) {
            $error = "Pesanan gagal disimpan. Silakan coba lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesan Laundry | Teman.Nyuci</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --navy: #092D50;
    --blue: #087FC1;
    --ice: #EAF6FD;
    --yellow: #FFDA45;
    --text: #30465D;
    --muted: #7D8FA2;
    --border: #E5EDF4;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: "Segoe UI", Arial, sans-serif; color: var(--text); background: #F3F9FD; }
a { text-decoration: none; }

/* NAVBAR (sama dengan beranda) */
.navbar {
    position: sticky; top: 0; z-index: 1000; width: 100%;
    background: rgba(255,255,255,.97); backdrop-filter: blur(12px);
    padding: 13px 7%; display: flex; align-items: center;
    justify-content: space-between; border-bottom: 1px solid var(--border);
}
.logo-area { display: flex; align-items: center; gap: 10px; }
.logo-area img { width: 48px; height: 48px; object-fit: contain; }
.logo-text { color: var(--navy); font-size: 22px; font-weight: 800; }
.logo-text span { color: var(--blue); }
.nav-menu { display: flex; align-items: center; gap: 27px; }
.nav-menu a { color: #52677D; font-size: 13px; font-weight: 600; transition: .2s; }
.nav-menu a:hover { color: var(--blue); }
.nav-menu .login-btn { color: white; background: var(--navy); padding: 12px 19px; border-radius: 8px; }
.nav-menu .login-btn:hover { background: var(--blue); color: white; }

.wrap { max-width: 760px; margin: 45px auto; padding: 0 20px; }
.heading { text-align: center; margin-bottom: 28px; }
.heading small { color: var(--blue); font-size: 11px; font-weight: 800; letter-spacing: 1.7px; }
.heading h1 { color: var(--navy); font-size: 29px; margin: 10px 0; }
.heading p { color: #8797A8; font-size: 13px; line-height: 1.8; }

.card {
    background: white; border: 1px solid var(--border); border-radius: 16px;
    padding: 30px; box-shadow: 0 10px 28px rgba(9,45,80,.06);
}
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 17px; }
.full { grid-column: 1 / -1; }
label { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; color: #526277; }
input, select, textarea {
    width: 100%; padding: 12px 13px; border: 1px solid #dfe8f1; border-radius: 8px;
    font-size: 13px; color: #34445a; background: white; outline: none; font-family: inherit;
}
input:focus, select:focus, textarea:focus { border-color: var(--blue); }
input[readonly] { background: #f3f7fc; }
textarea { resize: vertical; min-height: 85px; }

.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 9px;
    padding: 14px 22px; border-radius: 9px; font-size: 13px; font-weight: 700;
    border: none; cursor: pointer; font-family: inherit; transition: .25s;
}
.btn-primary { background: var(--navy); color: white; }
.btn-primary:hover { background: var(--blue); }
.btn-outline { background: white; color: var(--navy); border: 1px solid #C9DFEE; }
.btn-outline:hover { background: var(--ice); }
.submit { width: 100%; margin-top: 22px; }

.alert-error {
    background: #fff0f0; color: #b83232; border: 1px solid #f5dddd;
    padding: 13px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;
}

/* SUKSES */
.success { text-align: center; }
.success .icon {
    width: 70px; height: 70px; margin: 0 auto 18px; border-radius: 50%;
    display: grid; place-items: center; background: #eaf5ee; color: #277344; font-size: 30px;
}
.success h2 { color: var(--navy); font-size: 22px; margin-bottom: 10px; }
.success p { color: #8797A8; font-size: 13px; line-height: 1.8; }
.kode-box {
    margin: 22px auto; padding: 16px; max-width: 360px; background: var(--ice);
    border: 1px dashed #9ccbe8; border-radius: 12px;
}
.kode-box small { display: block; color: var(--muted); font-size: 11px; margin-bottom: 6px; }
.kode-box strong { color: var(--navy); font-size: 21px; letter-spacing: 2px; }
.success .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 8px; }

@media (max-width: 700px) {
    .navbar { flex-direction: column; gap: 12px; }
    .nav-menu { flex-wrap: wrap; justify-content: center; gap: 12px 17px; }
    .nav-menu a { font-size: 11px; }
    .grid { grid-template-columns: 1fr; }
    .card { padding: 22px 18px; }
}
</style>
</head>

<body>

<!-- NAVBAR PUBLIK: tidak ada sidebar / menu dashboard -->
<nav class="navbar">
    <a href="beranda_pelanggan.php" class="logo-area">
        <img src="image/logo-teman-nyuci.jpeg" alt="Logo Teman.Nyuci">
        <div class="logo-text">Teman<span>.Nyuci</span></div>
    </a>

    <div class="nav-menu">
        <a href="beranda_pelanggan.php">Beranda</a>
        <a href="beranda_pelanggan.php#layanan">Layanan</a>
        <a href="beranda_pelanggan.php#cara-pesan">Cara Pesan</a>
        <a href="lacak_pesanan.php">Lacak Pesanan</a>
        <a href="faq.php">FAQ</a>
        <a href="login.php" class="login-btn">Login Petugas</a>
    </div>
</nav>

<div class="wrap">

    <div class="heading">
        <small>PEMESANAN ONLINE</small>
        <h1>Pesan Laundry Sekarang</h1>
        <p>Isi formulir berikut. Petugas akan memeriksa dan mengonfirmasi pesanan Anda.</p>
    </div>

    <div class="card">

    <?php if ($kodeTracking !== ""): ?>

        <div class="success">
            <div class="icon"><i class="fa-solid fa-check"></i></div>
            <h2>Pesanan Berhasil Dikirim!</h2>
            <p>
                Terima kasih, <?= aman($input["nama"]) ?>.<br>
                Simpan kode tracking di bawah ini untuk memantau status pesanan Anda.
            </p>

            <div class="kode-box">
                <small>KODE TRACKING</small>
                <strong><?= aman($kodeTracking) ?></strong>
            </div>

            <div class="actions">
                <a href="lacak_pesanan.php" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Lacak Pesanan
                </a>
                <a href="beranda_pelanggan.php" class="btn btn-outline">
                    <i class="fa-solid fa-house"></i> Kembali ke Beranda
                </a>
            </div>
        </div>

    <?php else: ?>

        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= aman($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

            <div class="grid">

                <div>
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" required maxlength="100"
                           value="<?= aman($input["nama"]) ?>" placeholder="Nama Anda">
                </div>

                <div>
                    <label>Nomor WhatsApp</label>
                    <input type="tel" name="no_hp" required maxlength="20"
                           value="<?= aman($input["no_hp"]) ?>" placeholder="081234567890">
                </div>

                <div class="full">
                    <label>Alamat Penjemputan / Pengantaran</label>
                    <textarea name="alamat" required placeholder="Alamat lengkap"><?= aman($input["alamat"]) ?></textarea>
                </div>

                <div class="full">
                    <label>Pilih Layanan</label>
                    <select name="id_layanan" id="layanan" required onchange="hitung()">
                        <option value="">-- Pilih Layanan --</option>
                        <?php foreach ($daftarLayanan as $id => $l): ?>
                            <option
                                value="<?= $id ?>"
                                data-harga="<?= (float)$l["harga_per_kg"] ?>"
                                data-estimasi="<?= (int)$l["estimasi_hari"] ?>"
                                <?= $input["layanan"] === $id ? "selected" : "" ?>
                            >
                                <?= aman($l["nama_layanan"]) ?> - <?= aman($l["jenis_waktu"]) ?>
                                (Rp <?= number_format((float)$l["harga_per_kg"], 0, ",", ".") ?>/Kg)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Berat Estimasi (Kg)</label>
                    <input type="number" name="berat" id="berat" min="0.1" max="100" step="0.1"
                           required value="<?= aman($input["berat"]) ?>" oninput="hitung()"
                           placeholder="Contoh: 3">
                </div>

                <div>
                    <label>Estimasi Pengerjaan</label>
                    <input type="text" id="estimasi" readonly placeholder="Otomatis">
                </div>

                <div class="full">
                    <label>Perkiraan Total (final menyesuaikan timbangan)</label>
                    <input type="text" id="total" readonly placeholder="Otomatis">
                </div>

                <div class="full">
                    <label>Catatan (opsional)</label>
                    <textarea name="catatan" placeholder="Contoh: pisahkan pakaian putih"><?= aman($input["catatan"]) ?></textarea>
                </div>

            </div>

            <button type="submit" class="btn btn-primary submit">
                <i class="fa-solid fa-paper-plane"></i> Kirim Pesanan
            </button>

        </form>

    <?php endif; ?>

    </div>
</div>

<script>
function hitung() {
    const sel = document.getElementById("layanan");
    const opt = sel.options[sel.selectedIndex];
    const harga = parseFloat(opt.dataset.harga) || 0;
    const est = parseInt(opt.dataset.estimasi) || 0;
    const berat = parseFloat(document.getElementById("berat").value) || 0;

    document.getElementById("estimasi").value = est > 0 ? est + " Hari" : "";
    const total = harga * berat;
    document.getElementById("total").value =
        total > 0 ? "Rp " + total.toLocaleString("id-ID") : "";
}
hitung(); // isi otomatis kalau layanan sudah terpilih dari beranda
</script>

</body>
</html>