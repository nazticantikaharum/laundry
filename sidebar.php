
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$namaUser = $_SESSION["nama"] ?? "Pengguna";
$roleUser = $_SESSION["role"] ?? "Petugas";
$isAdmin = strtolower($roleUser) === "admin";
$inisial = strtoupper(substr($namaUser, 0, 1));

$halamanAktif = basename($_SERVER["PHP_SELF"]);

function menuAktif($halaman) {
    global $halamanAktif;
    return $halamanAktif === $halaman ? "active" : "";
}
?>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* SIDEBAR */
.sidebar {
    width: 235px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: #092D50;
    padding: 22px 15px 15px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 100;
    color: #fff;
}

/* BRAND */
.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 9px 24px;
    margin-bottom: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.10);
}

.sidebar-brand img {
    width: 40px;
    height: 40px;
    object-fit: contain;
    border-radius: 50%;
    background: #fff;
}

.sidebar-brand .brand-name {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -0.4px;
}

.sidebar-brand .brand-name span {
    color: #35A6E0;
}

/* LABEL MENU */
.sidebar-label {
    font-size: 10px;
    font-weight: 700;
    color: #91ABC1;
    letter-spacing: 1px;
    padding: 0 12px;
    margin: 19px 0 9px;
}

/* LINK MENU */
.sidebar a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 13px;
    margin-bottom: 4px;
    text-decoration: none;
    color: #C3D3E1;
    font-size: 12px;
    border-radius: 8px;
    transition: .2s;
}

.sidebar a i {
    width: 18px;
    text-align: center;
    font-size: 14px;
}

/* HOVER DAN ACTIVE */
.sidebar a:hover {
    background: rgba(8,127,193,0.18);
    color: #fff;
}

.sidebar a.active {
    background: #087FC1;
    color: #fff;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0,0,0,0.10);
}

/* AKUN */
.account-menu {
    position: relative;
    margin-top: auto;
    padding-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.12);
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 5px;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
}

.user-avatar {
    width: 39px;
    height: 39px;
    flex: 0 0 39px;
    border-radius: 50%;
    background: #087FC1;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: bold;
}

.user-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.user-info strong {
    font-size: 12px;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-info span {
    font-size: 10px;
    color: #9BB4C9;
}

.sidebar-user > i {
    color: #9BB4C9;
    font-size: 11px;
}

/* DROPDOWN */
.account-dropdown {
    display: none;
    position: absolute;
    bottom: 65px;
    left: 0;
    right: 0;
    background: #103B60;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 9px;
    padding: 7px;
    box-shadow: 0 8px 20px rgba(0,0,0,.2);
}

.account-dropdown.show {
    display: block;
}

.account-dropdown a {
    margin: 0;
    color: #D4E2EF;
}

.account-dropdown a:hover {
    background: #087FC1;
    color: #fff;
}

.account-dropdown .logout-menu {
    color: #FFAAAA;
}

/* SCROLLBAR */
.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.20);
    border-radius: 10px;
}

/* RESPONSIVE */
@media (max-width: 700px) {
    .sidebar {
        width: 235px;
    }
}
</style>

<div class="sidebar">

    <!-- BRAND -->
    <div class="sidebar-brand">

        <img
            src="image/logo-teman-nyuci.jpeg"
            alt="Logo Teman.Nyuci"
        >

        <div class="brand-name">
            Teman<span>.Nyuci</span>
        </div>

    </div>

    <!-- MENU UTAMA -->
    <div class="sidebar-label">MENU UTAMA</div>

    <a href="dashboard.php"
       class="<?= menuAktif('dashboard.php') ?>">
        <i class="fa-solid fa-house"></i>
        Dashboard
    </a>

    <!-- MASTER DATA -->
    <div class="sidebar-label">MASTER DATA</div>

    <a href="pelanggan.php"
       class="<?= menuAktif('pelanggan.php') ?>">
        <i class="fa-solid fa-users"></i>
        Data Pelanggan
    </a>

    <a href="layanan.php"
       class="<?= menuAktif('layanan.php') ?>">
        <i class="fa-solid fa-shirt"></i>
        Data Layanan
    </a>

    <!-- TRANSAKSI -->
    <div class="sidebar-label">TRANSAKSI</div>

    <a href="transaksi.php"
       class="<?= menuAktif('transaksi.php') ?>">
        <i class="fa-solid fa-receipt"></i>
        Transaksi
    </a>

    <a href="konfirmasi_online.php"
       class="<?= menuAktif('konfirmasi_online.php') ?>">
        <i class="fa-solid fa-globe"></i>
        Konfirmasi Pesanan Online
    </a>

    <!-- LAPORAN -->
    <div class="sidebar-label">LAPORAN</div>

    <a href="laporan.php"
       class="<?= menuAktif('laporan.php') ?>">
        <i class="fa-solid fa-file-lines"></i>
        Laporan
    </a>

    <!-- LAYANAN PELANGGAN -->
    <div class="sidebar-label">LAYANAN PELANGGAN</div>

    <a href="faq.php"
       class="<?= menuAktif('faq.php') ?>">
        <i class="fa-solid fa-circle-question"></i>
        FAQ Pelanggan
    </a>

    <?php if ($isAdmin): ?>

        <a href="kelola_faq.php"
           class="<?= menuAktif('kelola_faq.php') ?>">
            <i class="fa-solid fa-book-open"></i>
            Kelola FAQ
        </a>

    <?php endif; ?>

    <!-- PENGATURAN -->
    <?php if ($isAdmin): ?>

        <div class="sidebar-label">PENGATURAN</div>

        <a href="user.php"
           class="<?= menuAktif('user.php') ?>">
            <i class="fa-solid fa-user-gear"></i>
            Manajemen User
        </a>

    <?php endif; ?>

    <!-- PROFIL AKUN -->
    <div class="account-menu">

        <button
            class="sidebar-user"
            type="button"
            onclick="toggleAccount()"
        >

            <div class="user-avatar">
                <?= htmlspecialchars($inisial, ENT_QUOTES, "UTF-8") ?>
            </div>

            <div class="user-info">
                <strong>
                    <?= htmlspecialchars($namaUser, ENT_QUOTES, "UTF-8") ?>
                </strong>

                <span>
                    <?= htmlspecialchars($roleUser, ENT_QUOTES, "UTF-8") ?>
                </span>
            </div>

            <i class="fa-solid fa-chevron-up"></i>

        </button>

        <div class="account-dropdown" id="accountDropdown">

            <a href="profil.php">
                <i class="fa-solid fa-user"></i>
                Profil Akun
            </a>

            <a href="logout.php" class="logout-menu">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </div>

    </div>

</div>

<script>
function toggleAccount() {
    document.getElementById("accountDropdown")
        .classList.toggle("show");
}

document.addEventListener("click", function(event) {

    const dropdown = document.getElementById("accountDropdown");
    const tombol = document.querySelector(".sidebar-user");

    if (
        dropdown &&
        tombol &&
        !dropdown.contains(event.target) &&
        !tombol.contains(event.target)
    ) {
        dropdown.classList.remove("show");
    }

});
</script>
