
<?php
session_start();
require_once "koneksi.php";

// CEK LOGIN
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

// KHUSUS ADMIN
if (strtolower($_SESSION["role"] ?? "") !== "admin") {
    header("Location: dashboard.php");
    exit;
}

$pesan = "";
$jenisPesan = "";

$nama = "";
$username = "";
$role = "";

// PROSES SIMPAN USER
if (isset($_POST["simpan"])) {

    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $role = $_POST["role"] ?? "";
    $password = $_POST["password"] ?? "";
    $konfirmasi = $_POST["konfirmasi"] ?? "";

    if (
        $nama === "" ||
        $username === "" ||
        $role === "" ||
        $password === "" ||
        $konfirmasi === ""
    ) {
        $pesan = "Semua kolom wajib diisi.";
        $jenisPesan = "error";

    } elseif (!in_array($role, ["Admin", "Petugas"], true)) {
        $pesan = "Role yang dipilih tidak valid.";
        $jenisPesan = "error";

    } elseif (strlen($password) < 8) {
        $pesan = "Password minimal 8 karakter.";
        $jenisPesan = "error";

    } elseif ($password !== $konfirmasi) {
        $pesan = "Konfirmasi password tidak sama.";
        $jenisPesan = "error";

    } else {

        // CEK USERNAME
        $cek = mysqli_prepare(
            $conn,
            "SELECT id_user FROM users WHERE username = ?"
        );

        mysqli_stmt_bind_param($cek, "s", $username);
        mysqli_stmt_execute($cek);
        $hasilCek = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($hasilCek) > 0) {

            $pesan = "Username sudah digunakan. Silakan pilih username lain.";
            $jenisPesan = "error";

        } else {

            // HASH PASSWORD
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // SIMPAN USER
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (nama, username, password, role)
                 VALUES (?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $nama,
                $username,
                $passwordHash,
                $role
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                mysqli_stmt_close($cek);

                header("Location: user.php?pesan=berhasil");
                exit;

            } else {
                $pesan = "Gagal menyimpan akun. Silakan coba lagi.";
                $jenisPesan = "error";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($cek);
    }
}

// DATA AKUN LOGIN
$namaUser = $_SESSION["nama"] ?? "Pengguna";
$roleUser = $_SESSION["role"] ?? "Petugas";
$isAdmin = strtolower($roleUser) === "admin";

$inisial = strtoupper(substr($namaUser, 0, 1));
$labelRole = $isAdmin ? "Administrator" : "Petugas";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah User | Teman.Nyuci</title>

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
    margin: 0;
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
    margin-bottom: 8px;
    color: #34445a;
}

.page-description {
    color: #91a0b2;
    font-size: 13px;
    margin-bottom: 25px;
}

/* FORM */
.form-container {
    max-width: 680px;
    margin: 15px auto;
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

.required {
    color: #e16e6e;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #dce6f0;
    border-radius: 8px;
    font-size: 13px;
    outline: none;
    background: white;
    color: #34445a;
    transition: .2s;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #80b9e8;
    box-shadow: 0 0 0 3px rgba(100,169,229,.12);
}

.form-group input::placeholder {
    color: #b3bfcc;
}

.form-hint {
    font-size: 11px;
    color: #9aa8b7;
    margin-top: 7px;
}

.alert {
    padding: 13px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
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
        max-height: none;
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

    .form-footer {
        padding: 15px 20px;
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
        <i class="fa-solid fa-house"></i> Dashboard
    </a>

    <a href="pelanggan.php">
        <i class="fa-solid fa-users"></i> Data Pelanggan
    </a>

    <a href="layanan.php">
        <i class="fa-solid fa-shirt"></i> Data Layanan
    </a>

    <a href="pesanan.php">
        <i class="fa-solid fa-box"></i> Data Pesanan
    </a>

    <a href="konfirmasi_online.php">
        <i class="fa-solid fa-globe"></i> Pesanan Online
    </a>

    <a href="pembayaran.php">
        <i class="fa-solid fa-wallet"></i> Pembayaran
    </a>

    <a href="laporan.php">
        <i class="fa-solid fa-file-lines"></i> Laporan
    </a>

    <a href="faq.php">
        <i class="fa-solid fa-circle-question"></i> FAQ Pelanggan
    </a>

    <a href="kelola_faq.php">
        <i class="fa-solid fa-book-open"></i> Kelola FAQ
    </a>

    <?php if ($isAdmin): ?>
    <a href="user.php" class="active">
        <i class="fa-solid fa-user-gear"></i> Manajemen User
    </a>
    <?php endif; ?>

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

            <a href="profil.php">
                <i class="fa-solid fa-user"></i> Profil Akun
            </a>

            <a href="logout.php" class="logout-menu">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>

        </div>

    </div>

</div>

<!-- KONTEN -->
<div class="main">

    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;›&nbsp;
        <a href="user.php">Manajemen User</a>
        &nbsp;›&nbsp;
        Tambah User
    </div>

    <h1 class="page-title">Tambah User</h1>

    <p class="page-description">
        Tambahkan akun baru untuk mengakses sistem Teman.Nyuci.
    </p>

    <div class="form-container">

        <div class="form-header">
            <h2><i class="fa-solid fa-user-plus"></i> Form User Baru</h2>
            <p>Lengkapi informasi akun di bawah ini.</p>
        </div>

        <form method="POST">

            <div class="form-body">

                <?php if ($pesan !== ""): ?>
                    <div class="alert <?= htmlspecialchars($jenisPesan) ?>">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?= htmlspecialchars($pesan) ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>
                        Nama Lengkap <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="nama"
                        value="<?= htmlspecialchars($nama) ?>"
                        placeholder="Contoh: Aji Saputra"
                        required>
                </div>

                <div class="form-group">
                    <label>
                        Username <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="username"
                        value="<?= htmlspecialchars($username) ?>"
                        placeholder="Masukkan username"
                        required>
                </div>

                <div class="form-group">
                    <label>
                        Hak Akses (Role) <span class="required">*</span>
                    </label>

                    <select name="role" required>
                        <option value="">-- Pilih Role --</option>

                        <option value="Admin"
                            <?= $role === "Admin" ? "selected" : "" ?>>
                            Administrator
                        </option>

                        <option value="Petugas"
                            <?= $role === "Petugas" ? "selected" : "" ?>>
                            Petugas / Kasir
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        Password <span class="required">*</span>
                    </label>

                    <input
                        type="password"
                        name="password"
                        placeholder="Masukkan password"
                        minlength="8"
                        required>

                    <p class="form-hint">
                        Password harus memiliki minimal 8 karakter.
                    </p>
                </div>

                <div class="form-group">
                    <label>
                        Konfirmasi Password <span class="required">*</span>
                    </label>

                    <input
                        type="password"
                        name="konfirmasi"
                        placeholder="Ulangi password"
                        minlength="8"
                        required>
                </div>

            </div>

            <div class="form-footer">

                <a href="user.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Batal
                </a>

                <button
                    type="submit"
                    name="simpan"
                    class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan User
                </button>

            </div>

        </form>

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
