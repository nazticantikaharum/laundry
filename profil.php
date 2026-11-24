
<?php
session_start();
require_once "koneksi.php";

// CEK LOGIN
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

// CEK ROLE
$roleSession = strtolower($_SESSION["role"] ?? "");

if (!in_array($roleSession, ["admin", "petugas"], true)) {
    header("Location: login.php");
    exit;
}

$id_user = (int) $_SESSION["id_user"];
$pesan = "";
$jenisPesan = "";

// AMBIL DATA AKUN
$stmt = mysqli_prepare(
    $conn,
    "SELECT id_user, nama, username, password, role
     FROM users
     WHERE id_user = ?
     AND LOWER(role) IN ('admin', 'petugas')"
);

mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);

$akun = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$akun) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// SIMPAN PERUBAHAN PROFIL
if (isset($_POST["simpan"])) {

    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $passwordLama = $_POST["password_lama"] ?? "";
    $passwordBaru = $_POST["password_baru"] ?? "";
    $konfirmasi = $_POST["konfirmasi_password"] ?? "";

    if ($nama === "" || $username === "") {
        $pesan = "Nama dan username wajib diisi.";
        $jenisPesan = "error";

    } elseif (
        $passwordBaru !== "" &&
        $passwordLama === ""
    ) {
        $pesan = "Masukkan password lama untuk mengganti password.";
        $jenisPesan = "error";

    } elseif (
        $passwordBaru !== "" &&
        !password_verify($passwordLama, $akun["password"])
    ) {
        $pesan = "Password lama tidak sesuai.";
        $jenisPesan = "error";

    } elseif (
        $passwordBaru !== "" &&
        $passwordBaru !== $konfirmasi
    ) {
        $pesan = "Konfirmasi password tidak sama.";
        $jenisPesan = "error";

    } elseif (
        $passwordBaru !== "" &&
        strlen($passwordBaru) < 8
    ) {
        $pesan = "Password baru minimal 8 karakter.";
        $jenisPesan = "error";

    } else {

        // CEK USERNAME
        $cek = mysqli_prepare(
            $conn,
            "SELECT id_user
             FROM users
             WHERE username = ?
             AND id_user != ?"
        );

        mysqli_stmt_bind_param($cek, "si", $username, $id_user);
        mysqli_stmt_execute($cek);

        $usernameAda = mysqli_fetch_assoc(
            mysqli_stmt_get_result($cek)
        );

        mysqli_stmt_close($cek);

        if ($usernameAda) {

            $pesan = "Username sudah digunakan akun lain.";
            $jenisPesan = "error";

        } else {

            // UPDATE PROFIL DAN PASSWORD JIKA DIISI
            if ($passwordBaru !== "") {

                $passwordHash = password_hash(
                    $passwordBaru,
                    PASSWORD_DEFAULT
                );

                $update = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET nama = ?, username = ?, password = ?
                     WHERE id_user = ?
                     AND LOWER(role) IN ('admin', 'petugas')"
                );

                mysqli_stmt_bind_param(
                    $update,
                    "sssi",
                    $nama,
                    $username,
                    $passwordHash,
                    $id_user
                );

            } else {

                // PASSWORD LAMA TETAP DIGUNAKAN
                $update = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET nama = ?, username = ?
                     WHERE id_user = ?
                     AND LOWER(role) IN ('admin', 'petugas')"
                );

                mysqli_stmt_bind_param(
                    $update,
                    "ssi",
                    $nama,
                    $username,
                    $id_user
                );
            }

            if (mysqli_stmt_execute($update)) {

                $_SESSION["nama"] = $nama;
                $_SESSION["username"] = $username;

                $akun["nama"] = $nama;
                $akun["username"] = $username;

                if ($passwordBaru !== "") {
                    $akun["password"] = $passwordHash;
                }

                $pesan = "Profil akun berhasil diperbarui.";
                $jenisPesan = "success";

            } else {

                $pesan = "Profil gagal diperbarui.";
                $jenisPesan = "error";
            }

            mysqli_stmt_close($update);
        }
    }

    // TAMPILKAN KEMBALI DATA JIKA ADA KESALAHAN
    $akun["nama"] = $nama;
    $akun["username"] = $username;
}

// DATA AKUN LOGIN
$namaUser = $akun["nama"];
$roleUser = $akun["role"];

$isAdmin = strtolower($roleUser) === "admin";
$labelRole = $isAdmin ? "Administrator" : "Petugas";
$inisial = strtoupper(substr($namaUser, 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Profil Akun | Teman.Nyuci</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f3f7fc;
    color: #34445a;
}

/* SIDEBAR */
.sidebar {
    width: 235px;
    height: 100vh;
    background: #fff;
    position: fixed;
    left: 0;
    top: 0;
    padding: 25px 17px;
    border-right: 1px solid #e7eef6;
    z-index: 10;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.brand {
    font-size: 23px;
    font-weight: 750;
    color: #64a9e5;
    padding: 5px 12px;
    margin-bottom: 38px;
    flex-shrink: 0;
}

.brand span {
    color: #34445a;
}

.menu-label {
    font-size: 11px;
    color: #a0adbc;
    letter-spacing: 1px;
    padding: 0 12px;
    margin-bottom: 13px;
}

.sidebar > a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    margin-bottom: 7px;
    text-decoration: none;
    color: #748399;
    font-size: 13px;
    border-radius: 9px;
    transition: .2s;
    flex-shrink: 0;
}

.sidebar > a i {
    width: 18px;
    font-size: 15px;
    text-align: center;
}

.sidebar > a:hover,
.sidebar > a.active {
    background: #e6f3ff;
    color: #559bd5;
    font-weight: 600;
}

/* MENU AKUN */
.account-menu {
    position: sticky;
    bottom: 0;
    margin-top: auto;
    padding-top: 12px;
    background: white;
    flex-shrink: 0;
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 13px 5px;
    background: white;
    border: none;
    border-top: 1px solid #edf1f5;
    cursor: pointer;
    text-align: left;
}

.user-avatar {
    width: 39px;
    height: 39px;
    min-width: 39px;
    border-radius: 10px;
    background: #8fc5f3;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
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
    color: #34445a;
}

.user-info span {
    font-size: 11px;
    color: #9aa8b7;
}

.arrow {
    color: #9aa8b7;
}

.account-dropdown {
    display: none;
    position: absolute;
    bottom: 72px;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e5edf5;
    border-radius: 10px;
    padding: 7px;
    box-shadow: 0 5px 20px rgba(70,110,150,.12);
}

.account-dropdown.show {
    display: block;
}

.account-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px;
    text-decoration: none;
    color: #748399;
    font-size: 13px;
    border-radius: 7px;
}

.account-dropdown a:hover {
    background: #e6f3ff;
    color: #559bd5;
}

.account-dropdown .logout-menu {
    color: #df7474;
}

/* KONTEN */
.main {
    margin-left: 235px;
    padding: 30px 32px;
    min-height: 100vh;
}

.breadcrumb {
    font-size: 13px;
    color: #98a6b7;
    margin-bottom: 10px;
}

.breadcrumb a {
    color: #64a9e5;
    text-decoration: none;
}

.page-title {
    font-size: 25px;
    font-weight: 700;
    color: #34445a;
    margin-bottom: 7px;
}

.page-description {
    font-size: 13px;
    color: #91a0b2;
    margin-bottom: 25px;
}

/* FORM PROFIL */
.form-container {
    max-width: 680px;
    margin: 20px auto;
    background: white;
    border: 1px solid #e7eef6;
    border-radius: 13px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(60,100,140,.04);
}

.form-header {
    padding: 22px 25px;
    border-bottom: 1px solid #edf1f5;
}

.form-header h2 {
    font-size: 17px;
    color: #34445a;
    margin-bottom: 5px;
}

.form-header p {
    font-size: 12px;
    color: #98a6b7;
}

.form-body {
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 9px;
    color: #52647a;
}

.form-group input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #dce6f0;
    border-radius: 8px;
    font-size: 13px;
    outline: none;
    background: white;
    color: #34445a;
}

.form-group input:focus {
    border-color: #80b9e8;
    box-shadow: 0 0 0 3px rgba(100,169,229,.12);
}

.form-group input::placeholder {
    color: #b3bfcc;
}

.form-group small {
    display: block;
    color: #98a6b7;
    font-size: 11px;
    margin-top: 7px;
}

.role-info {
    background: #f3f7fc;
    border: 1px solid #e5edf5;
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 13px;
    color: #559bd5;
    font-weight: 600;
}

.alert {
    padding: 13px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
}

.alert.success {
    background: #eaf7ee;
    color: #267444;
    border: 1px solid #d1edda;
}

.alert.error {
    background: #fff0f0;
    color: #bd3636;
    border: 1px solid #f8d6d6;
}

.form-footer {
    padding: 18px 25px;
    border-top: 1px solid #edf1f5;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn {
    padding: 11px 18px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: .2s;
}

.btn-primary {
    background: #64a9e5;
    color: white;
}

.btn-primary:hover {
    background: #4d96d5;
}

.btn-secondary {
    background: #f0f4f8;
    color: #718197;
}

.btn-secondary:hover {
    background: #e5edf5;
}

.footer {
    text-align: right;
    color: #a0adbc;
    font-size: 12px;
    margin-top: 35px;
}

/* RESPONSIVE */
@media(max-width: 900px) {
    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;
        padding: 25px 20px;
    }
}

@media(max-width: 650px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .account-menu {
        position: relative;
        bottom: auto;
        margin-top: 15px;
    }

    .main {
        margin-left: 0;
        padding: 20px 15px;
    }

    .form-body {
        padding: 20px;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="brand">Teman<span>.Nyuci</span></div>

    <div class="menu-label">MENU UTAMA</div>

    <a href="dashboard.php">
        <i class="fa-solid fa-house"></i>
        Dashboard
    </a>

    <a href="pelanggan.php">
        <i class="fa-solid fa-users"></i>
        Data Pelanggan
    </a>

    <a href="layanan.php">
        <i class="fa-solid fa-shirt"></i>
        Data Layanan
    </a>

    <a href="pesanan.php">
        <i class="fa-solid fa-box"></i>
        Data Pesanan
    </a>

    <a href="konfirmasi_online.php">
        <i class="fa-solid fa-globe"></i>
        Pesanan Online
    </a>

    <a href="pembayaran.php">
        <i class="fa-solid fa-wallet"></i>
        Pembayaran
    </a>

    <a href="laporan.php">
        <i class="fa-solid fa-file-lines"></i>
        Laporan
    </a>

    <a href="faq.php">
        <i class="fa-solid fa-circle-question"></i>
        FAQ Pelanggan
    </a>

    <a href="kelola_faq.php">
        <i class="fa-solid fa-book-open"></i>
        Kelola FAQ
    </a>

    <?php if ($isAdmin): ?>
    <a href="user.php">
        <i class="fa-solid fa-user-gear"></i>
        Manajemen User
    </a>
    <?php endif; ?>

    <!-- AKUN -->
    <div class="account-menu">

        <button class="sidebar-user" type="button"
                onclick="toggleAccount()">

            <div class="user-avatar">
                <?= htmlspecialchars($inisial) ?>
            </div>

            <div class="user-info">
                <strong><?= htmlspecialchars($namaUser) ?></strong>
                <span><?= htmlspecialchars($labelRole) ?></span>
            </div>

            <span class="arrow" id="accountArrow">⌃</span>

        </button>

        <div class="account-dropdown" id="accountDropdown">

            <a href="profil.php" class="active">
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

<!-- KONTEN -->
<div class="main">

    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;›&nbsp;
        Profil Akun
    </div>

    <h1 class="page-title">Profil Akun</h1>

    <p class="page-description">
        Kelola informasi akun dan keamanan password kamu.
    </p>

    <div class="form-container">

        <div class="form-header">
            <h2>
                <i class="fa-solid fa-user-pen"></i>
                Pengaturan Profil
            </h2>

            <p>
                Perbarui informasi akun Teman.Nyuci.
            </p>
        </div>

        <form method="POST">

            <div class="form-body">

                <?php if ($pesan !== ""): ?>
                    <div class="alert <?= htmlspecialchars($jenisPesan) ?>">

                        <?php if ($jenisPesan === "success"): ?>
                            <i class="fa-solid fa-circle-check"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-exclamation"></i>
                        <?php endif; ?>

                        <?= htmlspecialchars($pesan) ?>

                    </div>
                <?php endif; ?>

                <div class="form-group">

                    <label>Nama Lengkap *</label>

                    <input
                        type="text"
                        name="nama"
                        value="<?= htmlspecialchars($akun["nama"]) ?>"
                        placeholder="Masukkan nama lengkap"
                        required>

                </div>

                <div class="form-group">

                    <label>Username *</label>

                    <input
                        type="text"
                        name="username"
                        value="<?= htmlspecialchars($akun["username"]) ?>"
                        placeholder="Masukkan username"
                        required>

                </div>

                <div class="form-group">

                    <label>Hak Akses / Role</label>

                    <div class="role-info">
                        <i class="fa-solid fa-shield-halved"></i>
                        <?= htmlspecialchars($labelRole) ?>
                    </div>

                    <small>
                        Role hanya dapat diubah oleh Admin melalui Manajemen User.
                    </small>

                </div>

                <div class="form-group">

                    <label>Password Lama</label>

                    <input
                        type="password"
                        name="password_lama"
                        placeholder="Masukkan password lama jika ingin mengganti">

                </div>

                <div class="form-group">

                    <label>Password Baru</label>

                    <input
                        type="password"
                        name="password_baru"
                        placeholder="Masukkan password baru"
                        minlength="8">

                    <small>
                        Kosongkan jika tidak ingin mengganti password.
                        Minimal 8 karakter.
                    </small>

                </div>

                <div class="form-group">

                    <label>Konfirmasi Password Baru</label>

                    <input
                        type="password"
                        name="konfirmasi_password"
                        placeholder="Ulangi password baru"
                        minlength="8">

                </div>

            </div>

            <div class="form-footer">

                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                    Batal
                </a>

                <button
                    type="submit"
                    name="simpan"
                    class="btn btn-primary">

                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan Perubahan

                </button>

            </div>

        </form>

    </div>

    <div class="footer">
        Teman.Nyuci © 2026 · Sistem Informasi Manajemen Laundry
    </div>

</div>

<script>
function toggleAccount() {
    const dropdown = document.getElementById("accountDropdown");
    const arrow = document.getElementById("accountArrow");

    dropdown.classList.toggle("show");

    arrow.innerHTML = dropdown.classList.contains("show")
        ? "⌄"
        : "⌃";
}

document.addEventListener("click", function(event) {
    const menu = document.querySelector(".account-menu");
    const dropdown = document.getElementById("accountDropdown");
    const arrow = document.getElementById("accountArrow");

    if (!menu.contains(event.target)) {
        dropdown.classList.remove("show");
        arrow.innerHTML = "⌃";
    }
});
</script>

</body>
</html>
