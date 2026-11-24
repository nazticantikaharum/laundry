
<?php
require_once "koneksi.php";

function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

// DATA LAYANAN
$daftarLayanan = mysqli_query(
    $conn,
    "SELECT nama_layanan, jenis_waktu, estimasi_hari, harga_per_kg
     FROM layanan
     ORDER BY nama_layanan ASC"
);

if (!$daftarLayanan) {
    die("Gagal mengambil data layanan: " . mysqli_error($conn));
}

$totalLayanan = mysqli_num_rows($daftarLayanan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teman.Nyuci | Laundry Express</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --navy: #092D50;
    --navy2: #123F67;
    --blue: #087FC1;
    --ocean: #13A8DC;
    --ice: #EAF6FD;
    --light: #F5FAFE;
    --yellow: #FFDA45;
    --text: #30465D;
    --muted: #7D8FA2;
    --border: #E5EDF4;
    --white: #FFFFFF;
}

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
    color: var(--text);
    background: white;
}

a {
    text-decoration: none;
}

/* NAVBAR */
.navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    width: 100%;
    background: rgba(255,255,255,.97);
    backdrop-filter: blur(12px);
    padding: 13px 7%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border);
}

.logo-area {
    display: flex;
    align-items: center;
    gap: 10px;
}

.logo-area img {
    width: 48px;
    height: 48px;
    object-fit: contain;
}

.logo-text {
    color: var(--navy);
    font-size: 22px;
    font-weight: 800;
}

.logo-text span {
    color: var(--blue);
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 27px;
}

.nav-menu a {
    color: #52677D;
    font-size: 13px;
    font-weight: 600;
    transition: .2s;
}

.nav-menu a:hover {
    color: var(--blue);
}

.nav-menu .login-btn {
    color: white;
    background: var(--navy);
    padding: 12px 19px;
    border-radius: 8px;
}

.nav-menu .login-btn:hover {
    background: var(--blue);
    color: white;
}

/* HERO */
.hero {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 85% 20%, rgba(19,168,220,.15), transparent 27%),
        linear-gradient(115deg, #F0F9FE 0%, #F8FCFF 55%, #E2F3FC 100%);
    padding: 75px 8% 95px;
}

.hero::before {
    content: "";
    position: absolute;
    width: 400px;
    height: 400px;
    border: 65px solid rgba(8,127,193,.06);
    border-radius: 50%;
    right: -180px;
    bottom: -250px;
}

.hero-inner {
    max-width: 1250px;
    margin: auto;
    display: grid;
    grid-template-columns: 1.05fr .95fr;
    align-items: center;
    gap: 55px;
    position: relative;
    z-index: 2;
}

.hero-content {
    max-width: 620px;
}

.hero-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: white;
    color: var(--navy);
    border: 1px solid #DCEBF5;
    padding: 10px 16px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 24px;
    box-shadow: 0 5px 15px rgba(9,45,80,.05);
}

.hero-label i {
    color: #E9B923;
}

.hero h1 {
    color: var(--navy);
    font-size: clamp(38px, 4.2vw, 57px);
    line-height: 1.16;
    letter-spacing: -1.5px;
    margin-bottom: 20px;
}

.hero h1 span {
    color: var(--blue);
}

.hero-description {
    color: #718499;
    font-size: 15px;
    line-height: 1.9;
    max-width: 550px;
    margin-bottom: 28px;
}

.hero-buttons {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 14px 22px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
    transition: .25s;
}

.btn-primary {
    background: var(--navy);
    color: white;
    box-shadow: 0 7px 18px rgba(9,45,80,.17);
}

.btn-primary:hover {
    background: var(--blue);
    transform: translateY(-3px);
}

.btn-outline {
    background: white;
    color: var(--navy);
    border: 1px solid #C9DFEE;
}

.btn-outline:hover {
    background: var(--ice);
    transform: translateY(-3px);
}

.hero-note {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-top: 24px;
    color: #8495A7;
    font-size: 12px;
}

.hero-note i {
    color: #2CA77D;
}

/* HERO VISUAL */
.hero-visual {
    position: relative;
    min-height: 430px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.visual-circle {
    width: 350px;
    height: 350px;
    border-radius: 50%;
    background: linear-gradient(145deg, var(--navy), var(--blue), var(--ocean));
    position: absolute;
    box-shadow: 0 25px 55px rgba(8,90,145,.2);
}

.machine-card {
    position: relative;
    z-index: 2;
    width: 265px;
    background: white;
    border-radius: 23px;
    padding: 24px;
    text-align: center;
    box-shadow: 0 20px 45px rgba(9,45,80,.17);
    border: 1px solid rgba(255,255,255,.8);
}

.machine-icon {
    width: 145px;
    height: 145px;
    margin: 0 auto 18px;
    border-radius: 28px;
    background: linear-gradient(145deg, #E6F7FF, #CDEBFA);
    display: grid;
    place-items: center;
    color: var(--blue);
    font-size: 76px;
    position: relative;
}

.machine-icon::after {
    content: "";
    position: absolute;
    width: 72px;
    height: 72px;
    border: 7px solid white;
    border-radius: 50%;
    background: linear-gradient(135deg, #13A8DC, #07549A);
    opacity: .8;
}

.machine-icon i {
    position: relative;
    z-index: 2;
    font-size: 48px;
    color: white;
}

.machine-card h3 {
    color: var(--navy);
    font-size: 20px;
    margin-bottom: 7px;
}

.machine-card p {
    color: #8394A5;
    font-size: 11px;
    line-height: 1.7;
}

.machine-logo {
    width: 45px;
    height: 45px;
    object-fit: contain;
    margin-top: 13px;
}

.floating-card {
    position: absolute;
    z-index: 4;
    background: white;
    padding: 13px 16px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 11px;
    box-shadow: 0 10px 28px rgba(9,45,80,.13);
    border: 1px solid #EAF0F5;
}

.floating-card i {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: var(--ice);
    color: var(--blue);
}

.floating-card strong {
    display: block;
    color: var(--navy);
    font-size: 12px;
}

.floating-card span {
    display: block;
    color: #8999A9;
    font-size: 10px;
    margin-top: 3px;
}

.float-one {
    top: 55px;
    left: -5px;
}

.float-two {
    bottom: 50px;
    right: -5px;
}

/* INFORMATION STRIP */
.info-strip {
    position: relative;
    z-index: 5;
    max-width: 1100px;
    margin: -38px auto 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 24px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    box-shadow: 0 12px 32px rgba(9,45,80,.08);
}

.info-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 13px;
}

.info-icon {
    width: 45px;
    height: 45px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    background: var(--ice);
    color: var(--blue);
    border-radius: 12px;
    font-size: 18px;
}

.info-item strong {
    display: block;
    color: var(--navy);
    font-size: 13px;
    margin-bottom: 4px;
}

.info-item span {
    color: #8999A9;
    font-size: 11px;
}

/* SECTION */
.container {
    max-width: 1160px;
    margin: auto;
    padding: 85px 25px;
}

.section-heading {
    text-align: center;
    max-width: 650px;
    margin: 0 auto 40px;
}

.section-heading small {
    color: var(--blue);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.7px;
}

.section-heading h2 {
    color: var(--navy);
    font-size: 30px;
    margin: 11px 0;
}

.section-heading p {
    color: #8797A8;
    font-size: 13px;
    line-height: 1.8;
}

/* LAYANAN */
.layanan-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.layanan-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 25px;
    transition: .25s;
}

.layanan-card:hover {
    transform: translateY(-5px);
    border-color: #B7DFF3;
    box-shadow: 0 14px 30px rgba(9,45,80,.09);
}

.layanan-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.layanan-icon {
    width: 49px;
    height: 49px;
    display: grid;
    place-items: center;
    background: var(--ice);
    color: var(--blue);
    border-radius: 12px;
    font-size: 21px;
}

.layanan-label {
    font-size: 10px;
    color: var(--blue);
    background: #EFF8FD;
    padding: 7px 10px;
    border-radius: 20px;
    font-weight: 700;
}

.layanan-card h3 {
    color: var(--navy);
    font-size: 17px;
    margin-bottom: 15px;
}

.detail {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #8293A4;
    font-size: 12px;
    margin-bottom: 9px;
}

.detail i {
    color: var(--blue);
    width: 15px;
}

.harga-area {
    border-top: 1px solid #EDF2F6;
    margin-top: 20px;
    padding-top: 17px;
    display: flex;
    align-items: end;
    justify-content: space-between;
}

.harga-area small {
    color: #8999A9;
    font-size: 10px;
}

.harga-area strong {
    display: block;
    color: var(--navy);
    font-size: 20px;
    margin-top: 5px;
}

.harga-area span {
    color: #8999A9;
    font-size: 11px;
}

.layanan-card a {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    background: var(--navy);
    color: white;
    padding: 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    margin-top: 18px;
    transition: .2s;
}

.layanan-card a:hover {
    background: var(--blue);
}

.empty-service {
    grid-column: 1 / -1;
    text-align: center;
    padding: 35px;
    color: var(--muted);
    background: var(--light);
    border-radius: 12px;
}

/* KEUNGGULAN */
.keunggulan-section {
    background: #F3F9FD;
}

.keunggulan-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.keunggulan-card {
    background: white;
    border: 1px solid #E6EFF5;
    border-radius: 14px;
    padding: 27px;
    transition: .2s;
}

.keunggulan-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(9,45,80,.07);
}

.keunggulan-icon {
    width: 53px;
    height: 53px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    background: var(--ice);
    color: var(--blue);
    font-size: 21px;
    margin-bottom: 18px;
}

.keunggulan-card h3 {
    color: var(--navy);
    font-size: 16px;
    margin-bottom: 10px;
}

.keunggulan-card p {
    color: #8797A8;
    font-size: 12px;
    line-height: 1.8;
}

/* CARA PESAN */
.langkah-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}

.langkah-card {
    text-align: center;
    padding: 22px 14px;
}

.nomor {
    width: 48px;
    height: 48px;
    margin: 0 auto 17px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    background: var(--navy);
    color: white;
    font-size: 18px;
    font-weight: 800;
    box-shadow: 0 7px 16px rgba(9,45,80,.15);
}

.langkah-card h3 {
    color: var(--navy);
    font-size: 14px;
    margin-bottom: 9px;
}

.langkah-card p {
    color: #8797A8;
    font-size: 12px;
    line-height: 1.7;
}

/* TRACKING */
.tracking-section {
    background: #F3F9FD;
}

.tracking-box {
    max-width: 950px;
    margin: auto;
    padding: 36px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 17px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
}

.tracking-content h2 {
    color: var(--navy);
    font-size: 24px;
    margin-bottom: 10px;
}

.tracking-content p {
    color: #8797A8;
    font-size: 13px;
    line-height: 1.8;
    margin-bottom: 20px;
    max-width: 570px;
}

.tracking-icon {
    width: 95px;
    height: 95px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    background: var(--ice);
    color: var(--blue);
    border-radius: 23px;
    font-size: 38px;
}

/* CTA */
.cta {
    background: linear-gradient(110deg, #092D50, #075D99, #087FC1);
    padding: 70px 25px;
    text-align: center;
}

.cta h2 {
    color: white;
    font-size: 29px;
    margin-bottom: 13px;
}

.cta p {
    color: #D5EAF7;
    font-size: 13px;
    line-height: 1.8;
    margin-bottom: 24px;
}

.cta .btn {
    background: var(--yellow);
    color: var(--navy);
}

.cta .btn:hover {
    background: white;
    transform: translateY(-3px);
}

/* FOOTER */
footer {
    background: #071F38;
    color: white;
    padding: 45px 8% 20px;
}

.footer-content {
    max-width: 1120px;
    margin: auto;
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr;
    gap: 40px;
    padding-bottom: 30px;
}

.footer-brand {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 14px;
}

.footer-brand img {
    width: 48px;
    height: 48px;
    object-fit: contain;
    border-radius: 50%;
    background: white;
}

.footer-brand h3 {
    font-size: 19px;
}

.footer-description {
    max-width: 350px;
    color: #B8CCDE;
    font-size: 12px;
    line-height: 1.8;
}

.footer-links h4 {
    font-size: 14px;
    margin-bottom: 15px;
}

.footer-links a {
    display: block;
    color: #B8CCDE;
    font-size: 12px;
    margin-bottom: 11px;
    transition: .2s;
}

.footer-links a:hover {
    color: white;
    transform: translateX(3px);
}

.footer-bottom {
    max-width: 1120px;
    margin: auto;
    border-top: 1px solid rgba(255,255,255,.14);
    padding-top: 18px;
    text-align: center;
    color: #B8CCDE;
    font-size: 11px;
}

/* RESPONSIVE */
@media(max-width: 1000px) {
    .hero-inner {
        gap: 25px;
    }

    .visual-circle {
        width: 300px;
        height: 300px;
    }

    .float-one {
        left: 0;
    }

    .float-two {
        right: 0;
    }
}

@media(max-width: 800px) {
    .hero-inner {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .hero-content {
        margin: auto;
    }

    .hero-description {
        margin-left: auto;
        margin-right: auto;
    }

    .hero-buttons,
    .hero-note {
        justify-content: center;
    }

    .hero-visual {
        min-height: 390px;
    }

    .info-strip {
        margin-left: 20px;
        margin-right: 20px;
    }

    .layanan-grid,
    .keunggulan-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 650px) {
    .navbar {
        flex-direction: column;
        gap: 12px;
    }

    .nav-menu {
        justify-content: center;
        flex-wrap: wrap;
        gap: 12px 17px;
    }

    .nav-menu a {
        font-size: 11px;
    }

    .hero {
        padding: 50px 22px 80px;
    }

    .hero h1 {
        font-size: 35px;
    }

    .hero-visual {
        min-height: 350px;
    }

    .visual-circle {
        width: 275px;
        height: 275px;
    }

    .machine-card {
        width: 230px;
        padding: 19px;
    }

    .machine-icon {
        width: 120px;
        height: 120px;
        font-size: 60px;
    }

    .machine-icon::after {
        width: 62px;
        height: 62px;
    }

    .machine-icon i {
        font-size: 39px;
    }

    .floating-card {
        padding: 10px;
    }

    .float-one {
        top: 10px;
    }

    .float-two {
        bottom: 10px;
    }

    .info-strip {
        grid-template-columns: 1fr;
        padding: 22px;
    }

    .info-item {
        justify-content: flex-start;
    }

    .layanan-grid,
    .keunggulan-grid {
        grid-template-columns: 1fr;
    }

    .langkah-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .tracking-box {
        flex-direction: column-reverse;
        text-align: center;
        padding: 27px 20px;
    }

    .footer-content {
        grid-template-columns: 1fr;
        gap: 27px;
    }

    .section-heading h2 {
        font-size: 25px;
    }

    .cta h2 {
        font-size: 25px;
    }
}

@media(max-width: 420px) {
    .hero h1 {
        font-size: 30px;
    }

    .hero-buttons {
        flex-direction: column;
    }

    .hero-buttons .btn {
        width: 100%;
    }

    .langkah-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">

    <a href="beranda_pelanggan.php" class="logo-area">
        <img src="image/logo-teman-nyuci.jpeg" alt="Logo Teman.Nyuci">
        <div class="logo-text">Teman<span>.Nyuci</span></div>
    </a>

    <div class="nav-menu">
        <a href="beranda_pelanggan.php">Beranda</a>
        <a href="#layanan">Layanan</a>
        <a href="#cara-pesan">Cara Pesan</a>
        <a href="lacak_pesanan.php">Lacak Pesanan</a>
        <a href="faq.php">FAQ</a>
        <a href="login.php" class="login-btn">Login Petugas</a>
    </div>

</nav>

<!-- HERO -->
<section class="hero">

    <div class="hero-inner">

        <div class="hero-content">

            <div class="hero-label">
                <i class="fa-solid fa-star"></i>
                TEMAN.NYUCI LAUNDRY EXPRESS
            </div>

            <h1>
                Pakaian Bersih,
                <span>Hari Lebih Praktis!</span>
            </h1>

            <p class="hero-description">
                Kesibukan sehari-hari bukan lagi alasan untuk menunda mencuci.
                Teman.Nyuci hadir membantu kebutuhan laundry Anda melalui
                pemesanan online yang praktis dan informasi layanan yang jelas.
            </p>

            <div class="hero-buttons">

                <a href="pesan_online.php" class="btn btn-primary">
                    <i class="fa-solid fa-basket-shopping"></i>
                    Pesan Sekarang
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

                <a href="#layanan" class="btn btn-outline">
                    Lihat Layanan
                </a>

            </div>

            <div class="hero-note">
                <i class="fa-solid fa-circle-check"></i>
                Cek layanan, harga, dan estimasi sebelum memesan
            </div>

        </div>

        <div class="hero-visual">

            <div class="visual-circle"></div>

            <div class="floating-card float-one">
                <i class="fa-solid fa-shirt"></i>
                <div>
                    <strong>Pilihan Layanan</strong>
                    <span>Sesuaikan kebutuhan Anda</span>
                </div>
            </div>

            <div class="machine-card">

                <div class="machine-icon">
                    <i class="fa-solid fa-shirt"></i>
                </div>

                <h3>Laundry Lebih Praktis</h3>

                <p>
                    Pilih layanan, kirim pesanan,
                    dan pantau status laundry Anda.
                </p>

                <img
                    src="image/logo-teman-nyuci.jpeg"
                    class="machine-logo"
                    alt="Logo Teman.Nyuci"
                >

            </div>

            <div class="floating-card float-two">
                <i class="fa-solid fa-clock"></i>
                <div>
                    <strong>Info Estimasi</strong>
                    <span>Lihat waktu pengerjaan</span>
                </div>
            </div>

        </div>

    </div>

</section>

<!-- INFORMASI SINGKAT -->
<section class="info-strip">

    <div class="info-item">
        <div class="info-icon">
            <i class="fa-solid fa-shirt"></i>
        </div>
        <div>
            <strong>Pilihan Layanan</strong>
            <span><?= (int)$totalLayanan ?> layanan tersedia</span>
        </div>
    </div>

    <div class="info-item">
        <div class="info-icon">
            <i class="fa-solid fa-laptop"></i>
        </div>
        <div>
            <strong>Pemesanan Online</strong>
            <span>Pesan melalui website</span>
        </div>
    </div>

    <div class="info-item">
        <div class="info-icon">
            <i class="fa-solid fa-location-dot"></i>
        </div>
        <div>
            <strong>Lacak Pesanan</strong>
            <span>Pantau status laundry</span>
        </div>
    </div>

</section>

<!-- LAYANAN -->
<section class="container" id="layanan">

    <div class="section-heading">
        <small>LAYANAN KAMI</small>
        <h2>Pilih Layanan Laundry Anda</h2>
        <p>
            Temukan layanan yang sesuai dengan kebutuhan pakaian Anda.
            Lihat harga dan estimasi pengerjaan sebelum melakukan pemesanan.
        </p>
    </div>

    <div class="layanan-grid">

        <?php if ($totalLayanan > 0): ?>

            <?php
            $ikonLayanan = [
                "fa-shirt",
                "fa-soap",
                "fa-water",
                "fa-basket-shopping"
            ];

            $nomorIkon = 0;
            ?>

            <?php while ($l = mysqli_fetch_assoc($daftarLayanan)): ?>

                <div class="layanan-card">

                    <div class="layanan-top">
                        <div class="layanan-icon">
                            <i class="fa-solid <?= $ikonLayanan[$nomorIkon % count($ikonLayanan)] ?>"></i>
                        </div>

                        <span class="layanan-label">LAYANAN LAUNDRY</span>
                    </div>

                    <h3><?= aman($l["nama_layanan"]) ?></h3>

                    <div class="detail">
                        <i class="fa-regular fa-clock"></i>
                        Jenis waktu: <?= aman($l["jenis_waktu"]) ?>
                    </div>

                    <div class="detail">
                        <i class="fa-solid fa-calendar-check"></i>
                        Estimasi: <?= (int)$l["estimasi_hari"] ?> hari
                    </div>

                    <div class="harga-area">
                        <div>
                            <small>Harga layanan</small>
                            <strong>
                                Rp <?= number_format(
                                    (float)$l["harga_per_kg"],
                                    0,
                                    ",",
                                    "."
                                ) ?>
                            </strong>
                        </div>
                        <span>/ Kg</span>
                    </div>

                    <a href="pesan_online.php">
                        Pesan Layanan
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </div>

                <?php $nomorIkon++; ?>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty-service">
                Belum ada layanan yang tersedia.
            </div>

        <?php endif; ?>

    </div>

</section>

<!-- KEUNGGULAN -->
<section class="keunggulan-section">

    <div class="container">

        <div class="section-heading">
            <small>KEMUDAHAN UNTUK ANDA</small>
            <h2>Kenapa Memilih Teman.Nyuci?</h2>
            <p>
                Semua informasi yang dibutuhkan untuk memesan laundry
                tersedia dalam satu website.
            </p>
        </div>

        <div class="keunggulan-grid">

            <div class="keunggulan-card">
                <div class="keunggulan-icon">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>

                <h3>Pemesanan Praktis</h3>

                <p>
                    Kirim permintaan laundry melalui website
                    tanpa harus mengisi formulir secara langsung di tempat.
                </p>
            </div>

            <div class="keunggulan-card">
                <div class="keunggulan-icon">
                    <i class="fa-solid fa-tags"></i>
                </div>

                <h3>Informasi Harga</h3>

                <p>
                    Lihat pilihan layanan, harga per kilogram,
                    dan estimasi waktu pengerjaan.
                </p>
            </div>

            <div class="keunggulan-card">
                <div class="keunggulan-icon">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </div>

                <h3>Pelacakan Pesanan</h3>

                <p>
                    Periksa perkembangan pesanan menggunakan
                    kode tracking yang diperoleh setelah memesan.
                </p>
            </div>

        </div>

    </div>

</section>

<!-- CARA PEMESANAN -->
<section class="container" id="cara-pesan">

    <div class="section-heading">
        <small>LANGKAH MUDAH</small>
        <h2>Bagaimana Cara Memesan?</h2>
        <p>
            Ikuti empat langkah sederhana untuk mengirim
            permintaan laundry melalui Teman.Nyuci.
        </p>
    </div>

    <div class="langkah-grid">

        <div class="langkah-card">
            <div class="nomor">1</div>
            <h3>Pilih Layanan</h3>
            <p>
                Pilih jenis layanan laundry
                sesuai kebutuhan Anda.
            </p>
        </div>

        <div class="langkah-card">
            <div class="nomor">2</div>
            <h3>Isi Formulir</h3>
            <p>
                Masukkan nama, nomor WhatsApp,
                alamat, dan informasi pesanan.
            </p>
        </div>

        <div class="langkah-card">
            <div class="nomor">3</div>
            <h3>Tunggu Konfirmasi</h3>
            <p>
                Petugas akan memeriksa dan
                mengonfirmasi permintaan laundry Anda.
            </p>
        </div>

        <div class="langkah-card">
            <div class="nomor">4</div>
            <h3>Lacak Pesanan</h3>
            <p>
                Gunakan kode tracking untuk
                melihat perkembangan pesanan.
            </p>
        </div>

    </div>

</section>

<!-- LACAK PESANAN -->
<section class="tracking-section">

    <div class="container">

        <div class="tracking-box">

            <div class="tracking-content">

                <h2>Sudah Memesan Laundry?</h2>

                <p>
                    Ingin mengetahui perkembangan pesanan Anda?
                    Gunakan kode tracking untuk melihat status
                    pesanan melalui halaman pelacakan.
                </p>

                <a href="lacak_pesanan.php" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Lacak Pesanan
                </a>

            </div>

            <div class="tracking-icon">
                <i class="fa-solid fa-box-open"></i>
            </div>

        </div>

    </div>

</section>

<!-- CTA -->
<section class="cta">

    <h2>Yuk, Buat Urusan Laundry Lebih Praktis!</h2>

    <p>
        Pilih layanan yang sesuai dan mulai pesan laundry
        Anda melalui Teman.Nyuci.
    </p>

    <a href="pesan_online.php" class="btn">
        <i class="fa-solid fa-basket-shopping"></i>
        Mulai Pesan Sekarang
        <i class="fa-solid fa-arrow-right"></i>
    </a>

</section>

<!-- FOOTER -->
<footer>

    <div class="footer-content">

        <div>

            <div class="footer-brand">
                <img src="image/logo-teman-nyuci.jpeg" alt="Logo Teman.Nyuci">
                <h3>Teman.Nyuci</h3>
            </div>

            <p class="footer-description">
                Teman.Nyuci Laundry Express hadir untuk membantu
                kebutuhan laundry melalui pemesanan online,
                informasi layanan, dan pelacakan pesanan.
            </p>

        </div>

        <div class="footer-links">
            <h4>Navigasi</h4>
            <a href="beranda_pelanggan.php">Beranda</a>
            <a href="#layanan">Layanan</a>
            <a href="#cara-pesan">Cara Pemesanan</a>
            <a href="lacak_pesanan.php">Lacak Pesanan</a>
            <a href="faq.php">FAQ</a>
        </div>

        <div class="footer-links">
            <h4>Akses Sistem</h4>
            <a href="pesan_online.php">Pesan Laundry</a>
            <a href="login.php">Login Petugas</a>
        </div>

    </div>

    <div class="footer-bottom">
        © <?= date("Y") ?> Teman.Nyuci Laundry Express. Semua Hak Dilindungi.
    </div>

</footer>

</body>
</html>
