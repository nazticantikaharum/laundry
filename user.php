
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

$idLogin = (int) $_SESSION["id_user"];
$pesan = "";
$jenisPesan = "";

// =====================================
// PROSES HAPUS USER
// =====================================
if (isset($_GET["hapus"])) {
    $idHapus = filter_input(INPUT_GET, "hapus", FILTER_VALIDATE_INT);

    if (!$idHapus || $idHapus < 1) {
        header("Location: user.php?pesan=id_tidak_valid");
        exit;
    }

    if ($idHapus === $idLogin) {
        header("Location: user.php?pesan=hapus_sendiri");
        exit;
    }

    $hapus = mysqli_prepare(
        $conn,
        "DELETE FROM users
         WHERE id_user = ?
         AND LOWER(role) IN ('admin', 'petugas')"
    );

    mysqli_stmt_bind_param($hapus, "i", $idHapus);

    if (mysqli_stmt_execute($hapus)) {
        if (mysqli_stmt_affected_rows($hapus) > 0) {
            mysqli_stmt_close($hapus);
            header("Location: user.php?pesan=hapus_berhasil");
            exit;
        } else {
            mysqli_stmt_close($hapus);
            header("Location: user.php?pesan=tidak_ditemukan");
            exit;
        }
    } else {
        mysqli_stmt_close($hapus);
        header("Location: user.php?pesan=hapus_gagal");
        exit;
    }
}

// =====================================
// NOTIFIKASI
// =====================================
if (isset($_GET["pesan"])) {
    $notifikasi = [
        "berhasil" => ["Akun berhasil ditambahkan.", "success"],
        "edit_berhasil" => ["Akun berhasil diperbarui.", "success"],
        "hapus_berhasil" => ["Akun berhasil dihapus.", "success"],
        "hapus_sendiri" => ["Kamu tidak bisa menghapus akun sendiri.", "error"],
        "tidak_ditemukan" => ["Akun tidak ditemukan.", "error"],
        "hapus_gagal" => ["Akun gagal dihapus.", "error"],
        "id_tidak_valid" => ["ID akun tidak valid.", "error"]
    ];

    $kode = $_GET["pesan"];

    if (isset($notifikasi[$kode])) {
        $pesan = $notifikasi[$kode][0];
        $jenisPesan = $notifikasi[$kode][1];
    }
}

// =====================================
// AMBIL DATA USER
// =====================================
$query = mysqli_query(
    $conn,
    "SELECT id_user, nama, username, role
     FROM users
     WHERE LOWER(role) IN ('admin', 'petugas')
     ORDER BY id_user ASC"
);

if (!$query) {
    die("Gagal mengambil data user: " . htmlspecialchars(mysqli_error($conn)));
}

// HITUNG JUMLAH USER
$totalUser = mysqli_num_rows($query);
$totalAdmin = 0;
$totalPetugas = 0;

while ($dataHitung = mysqli_fetch_assoc($query)) {
    if (strtolower($dataHitung["role"]) === "admin") {
        $totalAdmin++;
    } elseif (strtolower($dataHitung["role"]) === "petugas") {
        $totalPetugas++;
    }
}

mysqli_data_seek($query, 0);

// DATA AKUN LOGIN
$namaUser = $_SESSION["nama"] ?? "Pengguna";
$roleUser = $_SESSION["role"] ?? "Petugas";
$isAdmin = strtolower($roleUser) === "admin";
$inisial = strtoupper(substr($namaUser, 0, 1));
$labelRole = $isAdmin ? "Administrator" : "Petugas";

function amanUser($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manajemen User | Teman.Nyuci</title>

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

/* KONTEN UTAMA */
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

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.page-title {
    font-size: 25px;
    font-weight: 700;
    color: #34445a;
}

.page-description {
    font-size: 13px;
    color: #91a0b2;
    margin-top: 6px;
}

/* TOMBOL */
.btn-tambah {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #64a9e5;
    color: white;
    text-decoration: none;
    padding: 12px 17px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    transition: .2s;
    white-space: nowrap;
}

.btn-tambah:hover {
    background: #4d96d5;
}

/* NOTIFIKASI */
.alert {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
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

/* RINGKASAN */
.summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.summary-card {
    background: white;
    border: 1px solid #e7eef6;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 4px 15px rgba(70,110,150,.04);
}

.summary-icon {
    width: 45px;
    height: 45px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.icon-total {
    background: #e6f3ff;
    color: #559bd5;
}

.icon-admin {
    background: #f5eafa;
    color: #9855a9;
}

.icon-petugas {
    background: #eaf7ee;
    color: #29975a;
}

.summary-info p {
    font-size: 12px;
    color: #91a0b2;
    margin-bottom: 5px;
}

.summary-info h3 {
    font-size: 23px;
    color: #34445a;
}

/* TABEL */
.table-box {
    background: white;
    border: 1px solid #e7eef6;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(70,110,150,.04);
}

.table-header {
    padding: 20px 22px;
    border-bottom: 1px solid #edf1f5;
}

.table-header h2 {
    font-size: 16px;
    color: #34445a;
    margin-bottom: 5px;
}

.table-header p {
    font-size: 12px;
    color: #98a6b7;
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th {
    background: #f8fafc;
    color: #8492a5;
    text-align: left;
    padding: 16px 14px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .4px;
}

td {
    padding: 15px 14px;
    border-top: 1px solid #edf1f5;
    font-size: 13px;
    color: #52647a;
}

tbody tr:hover {
    background: #fbfdff;
}

/* IDENTITAS USER */
.user-name {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-initial {
    width: 34px;
    height: 34px;
    background: #e6f3ff;
    color: #559bd5;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}

.user-name strong {
    color: #34445a;
}

.akun-sendiri {
    font-size: 11px;
    color: #64a9e5;
    margin-top: 3px;
}

/* ROLE */
.role {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.role-admin {
    background: #f5eafa;
    color: #9855a9;
}

.role-petugas {
    background: #eaf4fc;
    color: #559bd5;
}

/* STATUS */
.status-aktif {
    color: #29975a;
    font-size: 12px;
    font-weight: 600;
}

/* AKSI */
.aksi {
    display: flex;
    gap: 8px;
}

.btn-edit,
.btn-hapus {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    transition: .2s;
}

.btn-edit {
    color: #559bd5;
    border: 1px solid #cfe5f8;
    background: white;
}

.btn-hapus {
    color: #df7474;
    border: 1px solid #f3d2d2;
    background: white;
}

.btn-edit:hover {
    background: #e6f3ff;
}

.btn-hapus:hover {
    background: #fff0f0;
}

.akun-sendiri-action {
    color: #aab5c1;
    font-size: 11px;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #98a6b7;
}

.footer {
    text-align: right;
    color: #a0adbc;
    font-size: 12px;
    margin-top: 35px;
}

/* RESPONSIVE */
@media(max-width: 1000px) {
    .summary {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 650px) {
    .main {
        margin-left: 0;
        padding: 20px 15px;
    }

    .header {
        align-items: flex-start;
        flex-direction: column;
    }

    .summary {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<?php include "sidebar.php"; ?>

<!-- KONTEN UTAMA -->
<div class="main">

    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;›&nbsp;
        Manajemen User
    </div>

    <div class="header">
        <div>
            <h1 class="page-title">Manajemen User</h1>
            <p class="page-description">
                Kelola akun administrator dan petugas Teman.Nyuci.
            </p>
        </div>

        <a href="tambah_user.php" class="btn-tambah">
            <i class="fa-solid fa-user-plus"></i>
            Tambah User
        </a>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($pesan !== ""): ?>
        <div class="alert <?= amanUser($jenisPesan) ?>">
            <?php if ($jenisPesan === "success"): ?>
                <i class="fa-solid fa-circle-check"></i>
            <?php else: ?>
                <i class="fa-solid fa-circle-exclamation"></i>
            <?php endif; ?>

            <?= amanUser($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN -->
    <div class="summary">

        <div class="summary-card">
            <div class="summary-icon icon-total">
                <i class="fa-solid fa-users"></i>
            </div>

            <div class="summary-info">
                <p>Total Pengguna</p>
                <h3><?= $totalUser ?></h3>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon icon-admin">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div class="summary-info">
                <p>Administrator</p>
                <h3><?= $totalAdmin ?></h3>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon icon-petugas">
                <i class="fa-solid fa-user-tie"></i>
            </div>

            <div class="summary-info">
                <p>Petugas / Kasir</p>
                <h3><?= $totalPetugas ?></h3>
            </div>
        </div>

    </div>

    <!-- TABEL USER -->
    <div class="table-box">

        <div class="table-header">
            <h2>Daftar Pengguna</h2>
            <p>
                Daftar akun Admin dan Petugas yang terdaftar
                pada sistem Teman.Nyuci.
            </p>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="55">No.</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Hak Akses</th>
                        <th>Status Akun</th>
                        <th width="110">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($totalUser > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <div class="user-name">
                                    <div class="user-initial">
                                        <?= amanUser(strtoupper(substr($row["nama"], 0, 1))) ?>
                                    </div>

                                    <div>
                                        <strong>
                                            <?= amanUser($row["nama"]) ?>
                                        </strong>

                                        <?php if ((int)$row["id_user"] === $idLogin): ?>
                                            <div class="akun-sendiri">
                                                Anda
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?= amanUser($row["username"]) ?>
                            </td>

                            <td>
                                <?php if (strtolower($row["role"]) === "admin"): ?>
                                    <span class="role role-admin">
                                        Administrator
                                    </span>
                                <?php else: ?>
                                    <span class="role role-petugas">
                                        Petugas / Kasir
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="status-aktif">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Aktif
                                </span>
                            </td>

                            <td>
                                <div class="aksi">
                                    <a
                                        href="edit_user.php?id=<?= (int)$row["id_user"] ?>"
                                        class="btn-edit"
                                        title="Edit User">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    <?php if ((int)$row["id_user"] !== $idLogin): ?>
                                        <a
                                            href="user.php?hapus=<?= (int)$row["id_user"] ?>"
                                            class="btn-hapus"
                                            title="Hapus User"
                                            onclick="return confirm('Yakin ingin menghapus akun ini?')">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="akun-sendiri-action">
                                            Akun Anda
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty">
                            <i class="fa-solid fa-users"
                               style="font-size:30px;margin-bottom:10px;"></i>
                            <p>Belum ada akun Admin atau Petugas.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        Teman.Nyuci © <?= date("Y") ?> &nbsp; · &nbsp;
        Sistem Informasi Manajemen Laundry
    </div>

</div>

</body>
</html>
