
<?php
require_once "koneksi.php";
date_default_timezone_set("Asia/Jakarta");

// FUNGSI KEAMANAN
function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

$hasil = null;
$pesan = "";
$statusTampil = "";

// PROSES LACAK PESANAN
if (isset($_POST["lacak"])) {

    $kodeTracking = strtoupper(trim($_POST["kode_tracking"] ?? ""));

    if ($kodeTracking === "") {

        $pesan = "Masukkan kode tracking terlebih dahulu.";

    } else {

        $sql = "SELECT
                    po.id_online,
                    po.nama_pelanggan,
                    po.berat_estimasi,
                    po.catatan,
                    po.tanggal_pesan,
                    po.status AS status_online,
                    po.kode_tracking,
                    l.nama_layanan,
                    l.jenis_waktu,
                    p.kode_pesanan,
                    p.status AS status_pesanan,
                    p.tanggal_masuk,
                    p.tanggal_estimasi_selesai
                FROM pemesanan_online po
                JOIN layanan l
                    ON po.id_layanan = l.id_layanan
                LEFT JOIN pesanan p
                    ON po.id_pesanan = p.id_pesanan
                WHERE po.kode_tracking = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $pesan = "Terjadi kesalahan saat mencari pesanan.";

        } else {

            mysqli_stmt_bind_param($stmt, "s", $kodeTracking);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $hasil = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$hasil) {
                $pesan = "Kode tracking tidak ditemukan. Periksa kembali kode Anda.";
            }
        }
    }
}

// TENTUKAN STATUS PESANAN
if ($hasil) {

    if ($hasil["status_online"] === "Ditolak") {

        $statusTampil = "Ditolak";

    } elseif ($hasil["status_online"] === "Menunggu Konfirmasi") {

        $statusTampil = "Menunggu Konfirmasi";

    } elseif (!empty($hasil["status_pesanan"])) {

        $statusTampil = $hasil["status_pesanan"];

    } else {

        $statusTampil = "Dikonfirmasi";
    }
}

// WARNA STATUS
$statusClass = "status-menunggu";

if ($statusTampil === "Ditolak") {
    $statusClass = "status-ditolak";
} elseif (
    in_array($statusTampil, ["Selesai", "Lunas"], true)
) {
    $statusClass = "status-selesai";
} elseif ($statusTampil !== "" && $statusTampil !== "Menunggu Konfirmasi") {
    $statusClass = "status-proses";
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Lacak Pesanan - Teman.Nyuci</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

/* =========================
   DASAR
========================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f5faff;
    color: #34445a;
}

:root {
    --biru: #079fe0;
    --biru-tua: #07549a;
    --biru-muda: #eaf7ff;
    --kuning: #ffdf21;
    --abu: #8795a5;
}

/* =========================
   NAVBAR
========================= */

.navbar {
    width: 100%;
    padding: 12px 7%;
    background: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 15px rgba(0,0,0,.06);
}

.logo-area {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.logo-area img {
    width: 55px;
    height: 55px;
    object-fit: contain;
    display: block;
}

.logo-text {
    font-size: 21px;
    font-weight: 800;
    color: var(--biru-tua);
}

.logo-text span {
    color: var(--biru);
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 25px;
}

.nav-menu a {
    text-decoration: none;
    color: #536273;
    font-size: 13px;
    font-weight: 600;
    transition: .2s;
}

.nav-menu a:hover,
.nav-menu a.active {
    color: var(--biru);
}

.nav-menu .login-btn {
    background: var(--biru-tua);
    color: white;
    padding: 11px 18px;
    border-radius: 8px;
}

.nav-menu .login-btn:hover {
    background: var(--biru);
    color: white;
}

/* =========================
   HERO
========================= */

.hero {
    background: linear-gradient(
        110deg,
        #eaf8ff,
        #f6fcff,
        #dff3ff
    );
    padding: 55px 20px 70px;
    text-align: center;
}

.hero-label {
    display: inline-block;
    background: white;
    color: var(--biru-tua);
    padding: 8px 15px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 17px;
}

.hero-label i {
    color: #e9bd00;
    margin-right: 5px;
}

.hero h1 {
    color: var(--biru-tua);
    font-size: 32px;
    margin-bottom: 13px;
}

.hero h1 span {
    color: var(--biru);
}

.hero p {
    max-width: 600px;
    margin: auto;
    color: #68788a;
    font-size: 14px;
    line-height: 1.8;
}

/* =========================
   CONTAINER
========================= */

.container {
    max-width: 750px;
    margin: -35px auto 65px;
    padding: 0 20px;
    position: relative;
    z-index: 2;
}

/* =========================
   INFO BOX
========================= */

.info-box {
    background: white;
    border: 1px solid #e4eef7;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 22px;
    box-shadow: 0 5px 20px rgba(40,100,150,.05);
}

.info-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    background: var(--biru-muda);
    color: var(--biru);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
}

.info-box h3 {
    color: var(--biru-tua);
    font-size: 15px;
    margin-bottom: 5px;
}

.info-box p {
    color: var(--abu);
    font-size: 12px;
    line-height: 1.7;
}

/* =========================
   CARD
========================= */

.card {
    background: white;
    border: 1px solid #e4eef7;
    border-radius: 13px;
    padding: 32px;
    box-shadow: 0 7px 25px rgba(40,100,150,.06);
}

.card-heading {
    margin-bottom: 24px;
}

.card-heading h2 {
    color: var(--biru-tua);
    font-size: 21px;
    margin-bottom: 8px;
}

.card-heading h2 i {
    color: var(--biru);
    margin-right: 7px;
}

.card-heading p {
    color: var(--abu);
    font-size: 12px;
    line-height: 1.7;
}

/* =========================
   FORM
========================= */

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #526277;
    margin-bottom: 9px;
}

.form-group input {
    width: 100%;
    padding: 14px;
    border: 1px solid #dfe8f1;
    border-radius: 8px;
    background: white;
    color: #34445a;
    font-size: 13px;
    outline: none;
    transition: .2s;
}

.form-group input:focus {
    border-color: #79b5e8;
    box-shadow: 0 0 0 3px rgba(100,169,229,.12);
}

.form-group input::placeholder {
    color: #aab6c3;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 13px 20px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: .2s;
}

.btn-primary {
    width: 100%;
    background: var(--biru-tua);
    color: white;
}

.btn-primary:hover {
    background: var(--biru);
    transform: translateY(-1px);
}

/* =========================
   ALERT
========================= */

.alert {
    padding: 14px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    line-height: 1.7;
}

.error {
    background: #fff0f0;
    color: #b83232;
    border: 1px solid #f5dddd;
}

/* =========================
   HASIL TRACKING
========================= */

.result {
    margin-top: 28px;
    padding-top: 25px;
    border-top: 1px solid #e8edf2;
}

.result h3 {
    color: var(--biru-tua);
    font-size: 18px;
    margin-bottom: 18px;
}

.data {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 14px 0;
    border-bottom: 1px solid #edf0f3;
    font-size: 13px;
}

.data span:first-child {
    color: var(--abu);
}

.data strong {
    text-align: right;
    color: #34445a;
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
}

.status-menunggu {
    background: #fff7df;
    color: #a77a00;
}

.status-proses {
    background: #eaf4fd;
    color: #286da8;
}

.status-selesai {
    background: #e8f8ed;
    color: #27834a;
}

.status-ditolak {
    background: #fff0f0;
    color: #bd3939;
}

/* =========================
   INFO STATUS
========================= */

.status-info {
    margin-top: 20px;
    padding: 15px;
    background: #eef7ff;
    color: #47749a;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.8;
}

.status-info i {
    margin-right: 6px;
}

/* =========================
   LINK KEMBALI
========================= */

.back {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 23px;
    color: var(--biru-tua);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.back:hover {
    color: var(--biru);
}

/* =========================
   FOOTER
========================= */

.footer {
    background: #073e73;
    color: #d3e5f3;
    padding: 27px 20px;
    text-align: center;
    font-size: 12px;
}

/* =========================
   RESPONSIVE
========================= */

@media(max-width: 900px) {

    .navbar {
        padding: 12px 4%;
    }

    .nav-menu {
        gap: 15px;
    }
}

@media(max-width: 700px) {

    .navbar {
        flex-direction: column;
        gap: 13px;
        padding: 12px 15px;
    }

    .nav-menu {
        justify-content: center;
        flex-wrap: wrap;
        gap: 13px;
    }

    .nav-menu a {
        font-size: 11px;
    }

    .hero {
        padding: 42px 20px 65px;
    }

    .hero h1 {
        font-size: 26px;
    }

    .hero p {
        font-size: 12px;
    }

    .card {
        padding: 23px 18px;
    }

    .info-box {
        align-items: flex-start;
    }

    .data {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .data strong {
        text-align: left;
    }
}

@media(max-width: 420px) {

    .nav-menu {
        gap: 10px;
    }

    .nav-menu a {
        font-size: 10px;
    }

    .hero h1 {
        font-size: 23px;
    }
}

</style>

</head>

<body>

<!-- =========================
     NAVBAR
========================= -->

<nav class="navbar">

    <a href="beranda_pelanggan.php" class="logo-area">

        <img
            src="image/logo-teman-nyuci.jpeg"
            alt="Logo Teman.Nyuci"
        >

        <div class="logo-text">
            Teman<span>.Nyuci</span>
        </div>

    </a>

    <div class="nav-menu">

        <a href="beranda_pelanggan.php">
            Beranda
        </a>

        <a href="beranda_pelanggan.php#layanan">
            Layanan
        </a>

        <a href="beranda_pelanggan.php#cara-pesan">
            Cara Pesan
        </a>

        <a href="lacak_pesanan.php" class="active">
            Lacak Pesanan
        </a>

        <a href="faq.php">
            FAQ
        </a>

        <a href="login.php" class="login-btn">
            Login Petugas
        </a>

    </div>

</nav>

<!-- =========================
     HERO
========================= -->

<section class="hero">

    <span class="hero-label">
        <i class="fa-solid fa-star"></i>
        Teman.Nyuci Laundry Express
    </span>

    <h1>
        Lacak <span>Pesanan Laundry</span>
    </h1>

    <p>
        Periksa perkembangan pesanan laundry Anda dengan memasukkan
        kode tracking yang diberikan setelah melakukan pemesanan.
    </p>

</section>

<!-- =========================
     KONTEN UTAMA
========================= -->

<div class="container">

    <!-- INFORMASI -->
    <div class="info-box">

        <div class="info-icon">
            <i class="fa-solid fa-circle-info"></i>
        </div>

        <div>
            <h3>Informasi Pelacakan Pesanan</h3>

            <p>
                Masukkan kode tracking yang diperoleh setelah
                mengirim formulir pemesanan online untuk melihat
                perkembangan laundry Anda.
            </p>
        </div>

    </div>

    <!-- CARD TRACKING -->
    <div class="card">

        <div class="card-heading">

            <h2>
                <i class="fa-solid fa-magnifying-glass"></i>
                Cek Status Pesanan
            </h2>

            <p>
                Pastikan kode tracking yang dimasukkan sudah benar.
            </p>

        </div>

        <!-- PESAN ERROR -->
        <?php if ($pesan !== ""): ?>

            <div class="alert error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= aman($pesan) ?>
            </div>

        <?php endif; ?>

        <!-- FORM TRACKING -->
        <form method="POST" action="">

            <div class="form-group">

                <label for="kode_tracking">
                    Kode Tracking
                </label>

                <input
                    type="text"
                    id="kode_tracking"
                    name="kode_tracking"
                    placeholder="Masukkan kode tracking Anda"
                    maxlength="64"
                    value="<?= aman($_POST["kode_tracking"] ?? "") ?>"
                    required
                >

            </div>

            <button type="submit" name="lacak" class="btn btn-primary">

                <i class="fa-solid fa-magnifying-glass"></i>
                Lacak Pesanan

            </button>

        </form>

        <!-- HASIL PELACAKAN -->
        <?php if ($hasil): ?>

            <div class="result">

                <h3>
                    <i class="fa-solid fa-clipboard-list"></i>
                    Informasi Pesanan
                </h3>

                <div class="data">
                    <span>Nama Pelanggan</span>
                    <strong>
                        <?= aman($hasil["nama_pelanggan"]) ?>
                    </strong>
                </div>

                <div class="data">
                    <span>Kode Tracking</span>
                    <strong>
                        <?= aman($hasil["kode_tracking"]) ?>
                    </strong>
                </div>

                <div class="data">
                    <span>Layanan</span>
                    <strong>
                        <?= aman($hasil["nama_layanan"]) ?>
                    </strong>
                </div>

                <div class="data">
                    <span>Jenis Layanan</span>
                    <strong>
                        <?= aman($hasil["jenis_waktu"]) ?>
                    </strong>
                </div>

                <div class="data">
                    <span>Estimasi Berat</span>
                    <strong>
                        <?= number_format(
                            (float)$hasil["berat_estimasi"],
                            1,
                            ",",
                            "."
                        ) ?> Kg
                    </strong>
                </div>

                <div class="data">
                    <span>Tanggal Pemesanan</span>
                    <strong>
                        <?= date(
                            "d-m-Y H:i",
                            strtotime($hasil["tanggal_pesan"])
                        ) ?>
                    </strong>
                </div>

                <?php if (!empty($hasil["kode_pesanan"])): ?>

                    <div class="data">
                        <span>Kode Pesanan Laundry</span>
                        <strong>
                            <?= aman($hasil["kode_pesanan"]) ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <?php if (!empty($hasil["tanggal_estimasi_selesai"])): ?>

                    <div class="data">
                        <span>Estimasi Selesai</span>
                        <strong>
                            <?= date(
                                "d-m-Y",
                                strtotime($hasil["tanggal_estimasi_selesai"])
                            ) ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <div class="data">

                    <span>Status Pesanan</span>

                    <strong>
                        <span class="status <?= $statusClass ?>">
                            <?= aman($statusTampil) ?>
                        </span>
                    </strong>

                </div>

                <!-- KETERANGAN STATUS -->
                <div class="status-info">

                    <?php if ($statusTampil === "Menunggu Konfirmasi"): ?>

                        <i class="fa-solid fa-clock"></i>

                        Pesanan Anda sudah diterima dan sedang menunggu
                        pemeriksaan serta konfirmasi Admin atau Kasir.

                    <?php elseif ($statusTampil === "Ditolak"): ?>

                        <i class="fa-solid fa-circle-exclamation"></i>

                        Mohon maaf, pesanan Anda ditolak oleh petugas.
                        Silakan hubungi Teman.Nyuci untuk informasi lebih lanjut.

                    <?php else: ?>

                        <i class="fa-solid fa-circle-check"></i>

                        Pesanan Anda sudah dikonfirmasi. Status di atas
                        menunjukkan perkembangan pesanan berdasarkan
                        informasi yang tercatat di sistem.

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

        <!-- KEMBALI -->
        <a href="beranda_pelanggan.php" class="back">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Beranda
        </a>

    </div>

</div>

<!-- =========================
     FOOTER
========================= -->

<footer class="footer">

    © <?= date("Y") ?> Teman.Nyuci Laundry Express.
    Semua Hak Dilindungi.

</footer>

</body>
</html>
