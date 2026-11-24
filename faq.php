
<?php
require_once "koneksi.php";

function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

// AMBIL DATA FAQ
$queryFAQ = mysqli_query($conn, "
    SELECT kategori, pertanyaan, jawaban
    FROM faq
    ORDER BY kategori ASC, id_faq ASC
");

if (!$queryFAQ) {
    die("Gagal mengambil FAQ: " . mysqli_error($conn));
}

$kelompokFAQ = [];

while ($faq = mysqli_fetch_assoc($queryFAQ)) {
    $kelompokFAQ[$faq["kategori"]][] = $faq;
}

// FAQ BAWAAN JIKA DATABASE KOSONG
if (empty($kelompokFAQ)) {
    $kelompokFAQ = [
        "Cara Pemesanan" => [
            [
                "pertanyaan" => "Bagaimana cara memesan laundry di Teman.Nyuci?",
                "jawaban" => "Buka halaman Beranda, klik Pesan Laundry Sekarang, isi nama, nomor HP, alamat, pilih layanan, masukkan estimasi berat pakaian, lalu klik Kirim Pesanan."
            ],
            [
                "pertanyaan" => "Apakah pelanggan harus login untuk memesan?",
                "jawaban" => "Tidak. Pelanggan dapat melakukan pemesanan secara langsung tanpa membuat akun atau login."
            ],
            [
                "pertanyaan" => "Bagaimana mengetahui pesanan berhasil dikirim?",
                "jawaban" => "Setelah pemesanan berhasil, sistem akan menampilkan kode tracking. Simpan kode tersebut untuk melacak pesanan."
            ],
            [
                "pertanyaan" => "Apakah saya bisa menambahkan catatan pesanan?",
                "jawaban" => "Bisa. Tuliskan informasi tambahan mengenai pakaian pada kolom catatan di formulir pemesanan."
            ]
        ],

        "Pelacakan Pesanan" => [
            [
                "pertanyaan" => "Bagaimana cara melacak pesanan laundry?",
                "jawaban" => "Buka menu Lacak Pesanan, masukkan kode tracking yang diperoleh setelah pemesanan, kemudian klik tombol Lacak."
            ],
            [
                "pertanyaan" => "Apa arti status Menunggu Konfirmasi?",
                "jawaban" => "Pesanan sudah dikirim dan sedang menunggu pemeriksaan serta konfirmasi dari Admin atau Petugas."
            ],
            [
                "pertanyaan" => "Bagaimana mengetahui laundry sudah selesai?",
                "jawaban" => "Masukkan kode tracking pada halaman Lacak Pesanan untuk melihat status terbaru pesanan."
            ]
        ],

        "Layanan dan Harga" => [
            [
                "pertanyaan" => "Apa saja layanan yang tersedia?",
                "jawaban" => "Teman.Nyuci menyediakan pilihan layanan seperti Cuci Lipat Rapi Wangi, Cuci + Setrika, dan Setrika Aja sesuai layanan yang tersedia."
            ],
            [
                "pertanyaan" => "Bagaimana mengetahui harga laundry?",
                "jawaban" => "Pelanggan dapat melihat daftar layanan dan harga pada halaman Beranda Teman.Nyuci."
            ],
            [
                "pertanyaan" => "Berapa lama proses pengerjaan laundry?",
                "jawaban" => "Estimasi pengerjaan mengikuti jenis layanan yang dipilih. Informasi estimasi dapat dilihat pada daftar layanan."
            ]
        ],

        "Pembayaran" => [
            [
                "pertanyaan" => "Apa saja metode pembayaran yang tersedia?",
                "jawaban" => "Pembayaran dapat dilakukan menggunakan metode Tunai atau QRIS sesuai pilihan yang tersedia."
            ],
            [
                "pertanyaan" => "Bagaimana cara membayar menggunakan QRIS?",
                "jawaban" => "Pindai kode QRIS yang disediakan oleh petugas, lakukan pembayaran, kemudian tunggu petugas melakukan verifikasi."
            ],
            [
                "pertanyaan" => "Bagaimana mengetahui pembayaran sudah dikonfirmasi?",
                "jawaban" => "Pelanggan dapat meminta informasi kepada petugas atau melihat status pembayaran pada nota."
            ]
        ]
    ];
}

$totalFAQ = 0;

foreach ($kelompokFAQ as $items) {
    $totalFAQ += count($items);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>FAQ | Teman.Nyuci</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --biru: #079fe0;
    --biru-tua: #07549a;
    --biru-muda: #eaf7ff;
    --kuning: #ffdf21;
    --abu: #8795a5;
    --border: #e4eef7;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f5faff;
    color: #34445a;
}

/* =========================
   NAVBAR - SAMA DENGAN LACAK
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
    flex-shrink: 0;
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
    white-space: nowrap;
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
   HERO FAQ
========================= */

.hero {
    background: linear-gradient(
        110deg,
        #eaf8ff,
        #f6fcff,
        #dff3ff
    );
    padding: 55px 7%;
}

.hero-inner {
    max-width: 1100px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 35px;
}

.hero-content {
    max-width: 580px;
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
    font-size: 36px;
    line-height: 1.3;
    margin-bottom: 13px;
}

.hero h1 span {
    color: var(--biru);
}

.hero p {
    max-width: 550px;
    color: #68788a;
    font-size: 14px;
    line-height: 1.8;
}

.hero-buttons {
    display: flex;
    gap: 12px;
    margin-top: 23px;
    flex-wrap: wrap;
}

.btn-primary,
.btn-outline {
    display: inline-block;
    text-decoration: none;
    padding: 13px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    transition: .2s;
}

.btn-primary {
    background: var(--biru-tua);
    color: white;
}

.btn-primary:hover {
    background: var(--biru);
}

.btn-outline {
    background: white;
    color: var(--biru-tua);
    border: 1px solid #cfe4f4;
}

.btn-outline:hover {
    background: var(--biru-muda);
}

/* ILUSTRASI */

.hero-illustration {
    width: 280px;
    min-width: 230px;
    height: 250px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 12px 30px rgba(49,136,197,.10);
}

.hero-illustration svg {
    width: 230px;
    height: 210px;
}

/* =========================
   STATISTIK
========================= */

.stats {
    max-width: 900px;
    margin: -25px auto 0;
    padding: 22px;
    position: relative;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(30,80,120,.06);
}

.stat-item {
    text-align: center;
    border-right: 1px solid var(--border);
}

.stat-item:last-child {
    border-right: none;
}

.stat-item strong {
    display: block;
    font-size: 23px;
    color: var(--biru);
    margin-bottom: 5px;
}

.stat-item span {
    color: var(--abu);
    font-size: 12px;
}

/* =========================
   KONTEN FAQ
========================= */

.container {
    max-width: 1100px;
    margin: 65px auto;
    padding: 0 22px;
}

.section-heading {
    text-align: center;
    margin-bottom: 30px;
}

.section-heading small {
    color: var(--biru);
    font-weight: bold;
    font-size: 11px;
    letter-spacing: 1.5px;
}

.section-heading h2 {
    color: var(--biru-tua);
    font-size: 27px;
    margin: 11px 0;
}

.section-heading p {
    color: var(--abu);
    font-size: 13px;
    line-height: 1.8;
}

/* LAYOUT */

.faq-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 25px;
    align-items: start;
}

/* PANEL KIRI */

.faq-sidebar {
    background: white;
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 25px;
    position: sticky;
    top: 100px;
}

.sidebar-icon {
    width: 52px;
    height: 52px;
    background: var(--biru-muda);
    color: var(--biru);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
    margin-bottom: 17px;
}

.faq-sidebar h3 {
    color: var(--biru-tua);
    font-size: 18px;
    margin-bottom: 10px;
}

.faq-sidebar p {
    color: var(--abu);
    font-size: 12px;
    line-height: 1.8;
}

.sidebar-list {
    margin-top: 22px;
    border-top: 1px solid var(--border);
    padding-top: 17px;
}

.sidebar-list div {
    color: #617589;
    font-size: 12px;
    margin-bottom: 13px;
}

.sidebar-list span {
    color: var(--biru);
    margin-right: 8px;
}

.sidebar-contact {
    display: block;
    text-align: center;
    background: var(--biru-tua);
    color: white;
    text-decoration: none;
    padding: 12px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: bold;
    margin-top: 20px;
}

.sidebar-contact:hover {
    background: var(--biru);
}

/* PANEL PERTANYAAN */

.faq-content {
    background: white;
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 24px;
}

.faq-content-header {
    margin-bottom: 22px;
}

.faq-content-header h3 {
    color: var(--biru-tua);
    font-size: 19px;
    margin-bottom: 7px;
}

.faq-content-header p {
    color: var(--abu);
    font-size: 12px;
    line-height: 1.7;
}

/* PENCARIAN */

.search-box {
    margin-top: 16px;
}

.search-box input {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid #dfe8f1;
    border-radius: 8px;
    outline: none;
    font-size: 12px;
    background: #fbfdff;
}

.search-box input:focus {
    border-color: var(--biru);
    background: white;
}

/* KATEGORI */

.faq-category {
    margin-bottom: 22px;
}

.category-title {
    color: var(--biru-tua);
    font-size: 12px;
    font-weight: bold;
    letter-spacing: .8px;
    text-transform: uppercase;
    margin-bottom: 12px;
}

/* ITEM FAQ */

.faq-item {
    border: 1px solid var(--border);
    border-radius: 9px;
    margin-bottom: 10px;
    overflow: hidden;
    transition: .2s;
}

.faq-item:hover {
    border-color: #b9d9f0;
}

.faq-question {
    width: 100%;
    background: white;
    border: none;
    padding: 17px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    text-align: left;
    cursor: pointer;
    color: #34495e;
    font-size: 13px;
    font-weight: bold;
    line-height: 1.6;
}

.faq-question:hover {
    color: var(--biru);
}

.faq-icon {
    width: 25px;
    height: 25px;
    min-width: 25px;
    background: var(--biru-muda);
    color: var(--biru);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: .2s;
}

.faq-item.active {
    border-color: #b8d9ef;
    background: #fbfdff;
}

.faq-item.active .faq-icon {
    background: var(--biru);
    color: white;
    transform: rotate(45deg);
}

.faq-answer {
    display: none;
    padding: 0 17px 17px;
    color: #738497;
    font-size: 12px;
    line-height: 1.9;
}

.faq-item.active .faq-answer {
    display: block;
}

/* HASIL PENCARIAN KOSONG */

.no-result {
    display: none;
    text-align: center;
    color: var(--abu);
    font-size: 13px;
    padding: 25px;
}

/* CTA */

.cta {
    background: linear-gradient(110deg,#eaf8ff,#f6fcff,#dff3ff);
    border: 1px solid #e0edf7;
    border-radius: 13px;
    padding: 35px 20px;
    text-align: center;
    margin-top: 55px;
}

.cta h3 {
    color: var(--biru-tua);
    font-size: 22px;
    margin-bottom: 10px;
}

.cta p {
    color: var(--abu);
    font-size: 13px;
    line-height: 1.8;
    margin-bottom: 20px;
}

/* FOOTER */

footer {
    background: #073e73;
    color: #d3e5f3;
    padding: 27px 20px;
    text-align: center;
    font-size: 12px;
    margin-top: 55px;
}

/* =========================
   RESPONSIVE
========================= */

@media(max-width: 950px) {
    .navbar {
        padding: 12px 4%;
    }

    .nav-menu {
        gap: 15px;
    }

    .faq-layout {
        grid-template-columns: 230px 1fr;
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
        padding: 42px 20px 55px;
    }

    .hero-inner {
        flex-direction: column;
        text-align: center;
    }

    .hero h1 {
        font-size: 27px;
    }

    .hero p {
        font-size: 12px;
        margin: auto;
    }

    .hero-buttons {
        justify-content: center;
    }

    .hero-illustration {
        width: 230px;
        height: 220px;
    }

    .stats {
        margin: 20px 15px 0;
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .stat-item {
        border-right: none;
        border-bottom: 1px solid var(--border);
        padding-bottom: 13px;
    }

    .stat-item:last-child {
        border-bottom: none;
    }

    .faq-layout {
        grid-template-columns: 1fr;
    }

    .faq-sidebar {
        position: static;
    }

    .container {
        margin: 45px auto;
    }
}
</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">

    <a href="beranda_pelanggan.php" class="logo-area">

        <img src="image/logo-teman-nyuci.jpeg"
             alt="Logo Teman.Nyuci">

        <div class="logo-text">
            Teman<span>.Nyuci</span>
        </div>

    </a>

    <div class="nav-menu">

        <a href="beranda_pelanggan.php">Beranda</a>

        <a href="beranda_pelanggan.php#layanan">Layanan</a>

        <a href="beranda_pelanggan.php#cara-pesan">Cara Pesan</a>

        <a href="lacak_pesanan.php">Lacak Pesanan</a>

        <a href="faq.php" class="active">FAQ</a>

        <a href="login.php" class="login-btn">Login Petugas</a>

    </div>

</nav>

<!-- HERO -->
<section class="hero">

    <div class="hero-inner">

        <div class="hero-content">

            <span class="hero-label">
                <i class="fa-solid fa-circle-question"></i>
                PUSAT BANTUAN TEMAN.NYUCI
            </span>

            <h1>
                Ada yang ingin kamu
                <span>tanyakan?</span>
            </h1>

            <p>
                Temukan informasi seputar cara memesan laundry,
                pilihan layanan, pelacakan pesanan, dan pembayaran
                di Teman.Nyuci.
            </p>

            <div class="hero-buttons">

                <a href="#daftar-faq" class="btn-primary">
                    Lihat Pertanyaan
                </a>

                <a href="pesan_online.php" class="btn-outline">
                    Pesan Laundry
                </a>

            </div>

        </div>

        <div class="hero-illustration">

            <svg viewBox="0 0 250 220"
                 xmlns="http://www.w3.org/2000/svg">

                <circle cx="125" cy="105" r="88" fill="#eaf7ff"/>

                <g fill="#079fe0" font-family="Arial" font-weight="bold">
                    <text x="48" y="57" font-size="27">?</text>
                    <text x="119" y="36" font-size="34">?</text>
                    <text x="188" y="64" font-size="27">?</text>
                </g>

                <rect x="39" y="151" width="172" height="8"
                      rx="4" fill="#4389b9"/>

                <path d="M57 159 L57 193 M193 159 L193 193"
                      stroke="#4389b9" stroke-width="6"
                      stroke-linecap="round"/>

                <circle cx="125" cy="85" r="25"
                        fill="#f3d4c0"/>

                <path d="M100 83 Q98 52 126 57 Q153 54 151 84
                         L142 72 L132 76 L119 66 L105 83 Z"
                      fill="#36536c"/>

                <circle cx="116" cy="88" r="2" fill="#304356"/>
                <circle cx="134" cy="88" r="2" fill="#304356"/>

                <path d="M117 98 Q125 94 133 98"
                      stroke="#304356" stroke-width="2"
                      fill="none"/>

                <path d="M103 116 Q125 105 147 116
                         L158 151 L92 151 Z"
                      fill="#ffffff" stroke="#7fb4d8" stroke-width="3"/>

                <path d="M106 122 L88 143 M144 122 L162 143"
                      stroke="#f3d4c0" stroke-width="9"
                      stroke-linecap="round"/>

                <rect x="103" y="137" width="44" height="14"
                      rx="3" fill="#079fe0"/>

                <rect x="108" y="140" width="34" height="8"
                      rx="2" fill="#eaf7ff"/>

            </svg>

        </div>

    </div>

</section>

<!-- STATISTIK -->
<div class="stats">

    <div class="stat-item">
        <strong><?= $totalFAQ ?></strong>
        <span>Informasi Pertanyaan</span>
    </div>

    <div class="stat-item">
        <strong><?= count($kelompokFAQ) ?></strong>
        <span>Kategori Bantuan</span>
    </div>

    <div class="stat-item">
        <strong>24/7</strong>
        <span>Akses Informasi Online</span>
    </div>

</div>

<!-- KONTEN FAQ -->
<main class="container" id="daftar-faq">

    <div class="section-heading">

        <small>HELP CENTER</small>

        <h2>Pertanyaan yang Sering Diajukan</h2>

        <p>
            Kami menyediakan informasi untuk membantu pelanggan
            menggunakan layanan Teman.Nyuci dengan mudah.
        </p>

    </div>

    <div class="faq-layout">

        <!-- PANEL KIRI -->
        <aside class="faq-sidebar">

            <div class="sidebar-icon">
                <i class="fa-solid fa-headset"></i>
            </div>

            <h3>Butuh Bantuan?</h3>

            <p>
                Belum menemukan jawaban yang kamu cari?
                Pelajari panduan berikut untuk menggunakan
                layanan Teman.Nyuci.
            </p>

            <div class="sidebar-list">

                <div><span>✓</span> Cara melakukan pemesanan</div>
                <div><span>✓</span> Cara melacak pesanan</div>
                <div><span>✓</span> Informasi layanan laundry</div>
                <div><span>✓</span> Informasi pembayaran</div>

            </div>

            <a href="pesan_online.php" class="sidebar-contact">
                Mulai Pemesanan →
            </a>

        </aside>

        <!-- PANEL PERTANYAAN -->
        <section class="faq-content">

            <div class="faq-content-header">

                <h3>Pusat Pertanyaan</h3>

                <p>
                    Klik pertanyaan untuk melihat informasi
                    dan panduan lengkapnya.
                </p>

                <div class="search-box">

                    <input
                        type="text"
                        id="searchFAQ"
                        placeholder="Cari pertanyaan, misalnya cara memesan..."
                    >

                </div>

            </div>

            <div id="faqList">

                <?php foreach ($kelompokFAQ as $kategori => $items): ?>

                    <div class="faq-category">

                        <div class="category-title">
                            <?= aman($kategori) ?>
                        </div>

                        <?php foreach ($items as $faq): ?>

                            <div class="faq-item">

                                <button type="button"
                                        class="faq-question">

                                    <span>
                                        <?= aman($faq["pertanyaan"]) ?>
                                    </span>

                                    <span class="faq-icon">+</span>

                                </button>

                                <div class="faq-answer">
                                    <?= nl2br(aman($faq["jawaban"])) ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="no-result" id="noResult">
                Pertanyaan tidak ditemukan.
                Coba kata kunci lainnya.
            </div>

        </section>

    </div>

    <!-- CTA -->
    <section class="cta">

        <h3>Siap Menggunakan Layanan Teman.Nyuci?</h3>

        <p>
            Isi formulir pemesanan online dan simpan kode tracking
            untuk memantau pesanan laundry-mu.
        </p>

        <a href="pesan_online.php" class="btn-primary">
            Pesan Laundry Sekarang →
        </a>

    </section>

</main>

<!-- FOOTER -->
<footer>
    © <?= date("Y") ?> Teman.Nyuci Laundry Express.
    Semua Hak Dilindungi.
</footer>

<script>

// BUKA DAN TUTUP JAWABAN FAQ
document.querySelectorAll(".faq-question").forEach(function(button) {

    button.addEventListener("click", function() {

        const item = this.parentElement;
        item.classList.toggle("active");

    });

});

// PENCARIAN FAQ
const searchInput = document.getElementById("searchFAQ");
const faqItems = document.querySelectorAll(".faq-item");
const categories = document.querySelectorAll(".faq-category");
const noResult = document.getElementById("noResult");

searchInput.addEventListener("input", function() {

    const keyword = this.value.toLowerCase().trim();
    let jumlahTampil = 0;

    faqItems.forEach(function(item) {

        const text = item.innerText.toLowerCase();
        const cocok = text.includes(keyword);

        item.style.display = cocok ? "block" : "none";

        if (cocok) {
            jumlahTampil++;
        }

    });

    categories.forEach(function(category) {

        const items = category.querySelectorAll(".faq-item");

        const adaHasil = Array.from(items).some(function(item) {
            return item.style.display !== "none";
        });

        category.style.display = adaHasil ? "block" : "none";

    });

    noResult.style.display =
        jumlahTampil === 0 ? "block" : "none";

});

</script>

</body>
</html>
