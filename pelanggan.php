
<?php
session_start();
require_once "koneksi.php";

// CEK LOGIN DAN ROLE
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

$roleUser = strtolower($_SESSION["role"] ?? "");

if (!in_array($roleUser, ["admin", "petugas"], true)) {
    header("Location: login.php");
    exit;
}

// FUNGSI KEAMANAN
function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

// VARIABEL FORM
$pesan = "";
$modeEdit = false;
$idEdit = "";
$namaEdit = "";
$hpEdit = "";
$alamatEdit = "";

// ================================
// TAMBAH PELANGGAN
// ================================
if (isset($_POST["tambah"])) {
    $nama = trim($_POST["nama"] ?? "");
    $no_hp = trim($_POST["no_hp"] ?? "");
    $alamat = trim($_POST["alamat"] ?? "");

    if ($nama === "" || $no_hp === "") {
        $pesan = "Nama dan nomor HP wajib diisi.";
    } else {
        $sql = "INSERT INTO pelanggan (nama, no_hp, alamat)
                VALUES (?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $nama, $no_hp, $alamat);

            if (mysqli_stmt_execute($stmt)) {
                header("Location: pelanggan.php?status=tambah");
                exit;
            } else {
                $pesan = "Data pelanggan gagal ditambahkan.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $pesan = "Terjadi kesalahan pada database.";
        }
    }
}

// ================================
// UPDATE PELANGGAN
// ================================
if (isset($_POST["update"])) {
    $id = (int)($_POST["id_pelanggan"] ?? 0);
    $nama = trim($_POST["nama"] ?? "");
    $no_hp = trim($_POST["no_hp"] ?? "");
    $alamat = trim($_POST["alamat"] ?? "");

    if ($nama === "" || $no_hp === "") {
        $pesan = "Nama dan nomor HP wajib diisi.";

        $modeEdit = true;
        $idEdit = $id;
        $namaEdit = $nama;
        $hpEdit = $no_hp;
        $alamatEdit = $alamat;
    } else {
        $sql = "UPDATE pelanggan
                SET nama = ?, no_hp = ?, alamat = ?
                WHERE id_pelanggan = ?";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "sssi",
                $nama,
                $no_hp,
                $alamat,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {
                header("Location: pelanggan.php?status=update");
                exit;
            } else {
                $pesan = "Data pelanggan gagal diperbarui.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $pesan = "Terjadi kesalahan pada database.";
        }
    }
}

// ================================
// HAPUS PELANGGAN
// ================================
if (isset($_POST["hapus"])) {
    $id = (int)($_POST["id_pelanggan"] ?? 0);

    $sql = "DELETE FROM pelanggan WHERE id_pelanggan = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                header("Location: pelanggan.php?status=hapus");
                exit;
            } else {
                $pesan = "Data pelanggan tidak ditemukan.";
            }
        } else {
            $pesan = "Data tidak bisa dihapus. Pelanggan mungkin sudah memiliki pesanan.";
        }

        mysqli_stmt_close($stmt);
    } else {
        $pesan = "Terjadi kesalahan pada database.";
    }
}

// ================================
// AMBIL DATA UNTUK EDIT
// ================================
if (isset($_GET["edit"])) {
    $id = (int)$_GET["edit"];

    $sql = "SELECT * FROM pelanggan WHERE id_pelanggan = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $resultEdit = mysqli_stmt_get_result($stmt);
        $dataEdit = mysqli_fetch_assoc($resultEdit);

        if ($dataEdit) {
            $modeEdit = true;
            $idEdit = $dataEdit["id_pelanggan"];
            $namaEdit = $dataEdit["nama"];
            $hpEdit = $dataEdit["no_hp"];
            $alamatEdit = $dataEdit["alamat"];
        }

        mysqli_stmt_close($stmt);
    }
}

// ================================
// PESAN STATUS
// ================================
if (isset($_GET["status"])) {
    if ($_GET["status"] === "tambah") {
        $pesan = "Data pelanggan berhasil ditambahkan.";
    } elseif ($_GET["status"] === "update") {
        $pesan = "Data pelanggan berhasil diperbarui.";
    } elseif ($_GET["status"] === "hapus") {
        $pesan = "Data pelanggan berhasil dihapus.";
    }
}

// ================================
// PENCARIAN PELANGGAN
// ================================
$cari = trim($_GET["cari"] ?? "");

if ($cari !== "") {
    $sql = "SELECT * FROM pelanggan
            WHERE nama LIKE ? OR no_hp LIKE ?
            ORDER BY id_pelanggan DESC";

    $stmt = mysqli_prepare($conn, $sql);
    $keyword = "%" . $cari . "%";

    mysqli_stmt_bind_param($stmt, "ss", $keyword, $keyword);
    mysqli_stmt_execute($stmt);

    $pelanggan = mysqli_stmt_get_result($stmt);
} else {
    $pelanggan = mysqli_query(
        $conn,
        "SELECT * FROM pelanggan ORDER BY id_pelanggan DESC"
    );
}

// ================================
// TOTAL PELANGGAN
// ================================
$queryTotal = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM pelanggan"
);

$dataTotal = mysqli_fetch_assoc($queryTotal);
$totalPelanggan = $dataTotal["total"] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Pelanggan | Teman.Nyuci</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f3f7fc;
            color: #34445a;
        }

        /* KONTEN UTAMA */
        .main {
            margin-left: 235px;
            padding: 30px 32px;
            min-height: 100vh;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 27px;
            gap: 15px;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 650;
            color: #34445a;
        }

        .header p {
            color: #96a3b2;
            font-size: 12px;
            margin-top: 5px;
        }

        .date-now {
            background: white;
            border: 1px solid #e7eef6;
            padding: 11px 15px;
            border-radius: 9px;
            font-size: 12px;
            color: #718197;
            white-space: nowrap;
        }

        /* NOTIFIKASI */
        .alert {
            background: #e7f7f1;
            color: #3c9b7b;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 12px;
            border: 1px solid #d5eee5;
        }

        .alert.error {
            background: #fff0f0;
            color: #c45f5f;
            border-color: #f7dddd;
        }

        /* RINGKASAN */
        .summary-card {
            background: white;
            border: 1px solid #e8eef5;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            max-width: 300px;
            box-shadow: 0 3px 12px rgba(75,110,150,.035);
        }

        .summary-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .summary-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e6f3ff;
            color: #65a9e5;
        }

        .summary-label {
            color: #8997a7;
            font-size: 12px;
        }

        .summary-card h2 {
            color: #34445a;
            font-size: 23px;
        }

        /* FORM DAN TABEL */
        .content {
            display: grid;
            grid-template-columns: 310px minmax(0, 1fr);
            gap: 17px;
            align-items: start;
        }

        .box {
            background: white;
            border: 1px solid #e8eef5;
            border-radius: 12px;
            padding: 22px;
            min-width: 0;
            box-shadow: 0 3px 12px rgba(75,110,150,.035);
        }

        .box-title {
            font-size: 15px;
            font-weight: 650;
            color: #43556a;
            margin-bottom: 20px;
        }

        .box-title i {
            color: #77b5e8;
            margin-right: 7px;
        }

        /* INPUT FORM */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            color: #8a99aa;
            margin-bottom: 7px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #e2eaf2;
            border-radius: 8px;
            color: #60758c;
            font-family: inherit;
            font-size: 12px;
            outline: none;
            background: white;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #83b9e9;
            box-shadow: 0 0 0 3px rgba(131,185,233,.12);
        }

        .form-group textarea {
            height: 90px;
            resize: vertical;
        }

        /* TOMBOL */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            padding: 10px 13px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            font-family: inherit;
            transition: .2s;
            white-space: nowrap;
        }

        .btn-primary {
            background: #83b9e9;
            color: white;
        }

        .btn-primary:hover {
            background: #65a9e5;
        }

        .btn-edit {
            background: #e8f3fc;
            color: #559bd5;
        }

        .btn-edit:hover {
            background: #d8ebfb;
        }

        .btn-delete {
            background: #fff0f0;
            color: #df7474;
        }

        .btn-delete:hover {
            background: #ffe1e1;
        }

        .btn-cancel {
            background: #eef3f8;
            color: #718197;
        }

        .btn-cancel:hover {
            background: #e2eaf2;
        }

        /* HEADER TABEL */
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 19px;
        }

        .table-header h3 {
            font-size: 15px;
            color: #43556a;
            white-space: nowrap;
        }

        .search {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .search input {
            width: 180px;
            padding: 9px 10px;
            border: 1px solid #e2eaf2;
            border-radius: 8px;
            outline: none;
            font-size: 12px;
        }

        .search input:focus {
            border-color: #83b9e9;
        }

        /* TABEL */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 570px;
        }

        th {
            text-align: left;
            color: #8494a6;
            font-size: 11px;
            font-weight: 600;
            background: #f6f9fc;
            padding: 14px 12px;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf1f5;
            font-size: 12px;
            color: #617286;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f9fcff;
        }

        .aksi {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .aksi form {
            margin: 0;
        }

        .aksi .btn {
            padding: 8px 10px;
        }

        .empty {
            text-align: center;
            color: #9aa8b7;
            padding: 25px;
        }

        /* RESPONSIVE */
        @media(max-width: 1100px) {
            .content {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 700px) {
            .main {
                margin-left: 0;
                padding: 18px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .table-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .search {
                width: 100%;
                flex-wrap: wrap;
            }

            .search input {
                flex: 1;
                min-width: 0;
            }

            .summary-card {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <?php include "sidebar.php"; ?>

    <!-- KONTEN UTAMA -->
    <div class="main">

        <div class="header">
            <div>
                <h1>Data Pelanggan</h1>
                <p>Kelola informasi pelanggan Teman.Nyuci</p>
            </div>

            <div class="date-now">
                <i class="fa-regular fa-calendar"></i>
                <?= date("d F Y") ?>
            </div>
        </div>

        <!-- NOTIFIKASI -->
        <?php if ($pesan !== ""): ?>
            <div class="alert <?= (
                strpos($pesan, "gagal") !== false ||
                strpos($pesan, "tidak bisa") !== false ||
                strpos($pesan, "kesalahan") !== false ||
                strpos($pesan, "tidak ditemukan") !== false
            ) ? "error" : "" ?>">
                <i class="fa-solid fa-circle-info"></i>
                <?= aman($pesan) ?>
            </div>
        <?php endif; ?>

        <!-- TOTAL PELANGGAN -->
        <div class="summary-card">
            <div class="summary-top">
                <div class="summary-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <span class="summary-label">Total Pelanggan</span>
            </div>

            <h2>
                <?= number_format((int)$totalPelanggan, 0, ",", ".") ?>
            </h2>
        </div>

        <div class="content">

            <!-- FORM PELANGGAN -->
            <div class="box">

                <h3 class="box-title">
                    <i class="fa-solid <?= $modeEdit ? "fa-pen-to-square" : "fa-user-plus" ?>"></i>
                    <?= $modeEdit ? "Edit Pelanggan" : "Tambah Pelanggan" ?>
                </h3>

                <form method="POST" action="pelanggan.php">

                    <?php if ($modeEdit): ?>
                        <input type="hidden"
                               name="id_pelanggan"
                               value="<?= aman($idEdit) ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Nama Pelanggan</label>
                        <input
                            type="text"
                            name="nama"
                            placeholder="Masukkan nama pelanggan"
                            value="<?= aman($namaEdit) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Nomor HP</label>
                        <input
                            type="text"
                            name="no_hp"
                            placeholder="Contoh: 081234567890"
                            value="<?= aman($hpEdit) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea
                            name="alamat"
                            placeholder="Masukkan alamat pelanggan"
                        ><?= aman($alamatEdit) ?></textarea>
                    </div>

                    <?php if ($modeEdit): ?>

                        <button
                            type="submit"
                            name="update"
                            class="btn btn-primary"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            Simpan Perubahan
                        </button>

                        <a href="pelanggan.php" class="btn btn-cancel">
                            Batal
                        </a>

                    <?php else: ?>

                        <button
                            type="submit"
                            name="tambah"
                            class="btn btn-primary"
                        >
                            <i class="fa-solid fa-plus"></i>
                            Tambah Pelanggan
                        </button>

                    <?php endif; ?>

                </form>
            </div>

            <!-- DAFTAR PELANGGAN -->
            <div class="box">

                <div class="table-header">

                    <h3>
                        <i class="fa-solid fa-list"
                           style="color:#77b5e8;margin-right:6px;"></i>
                        Daftar Pelanggan
                    </h3>

                    <form method="GET" class="search">

                        <input
                            type="text"
                            name="cari"
                            placeholder="Cari pelanggan..."
                            value="<?= aman($cari) ?>"
                        >

                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            Cari
                        </button>

                        <?php if ($cari !== ""): ?>
                            <a href="pelanggan.php" class="btn btn-cancel">
                                Reset
                            </a>
                        <?php endif; ?>

                    </form>

                </div>

                <div class="table-wrap">

                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>No. HP</th>
                                <th>Alamat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($pelanggan && mysqli_num_rows($pelanggan) > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($row = mysqli_fetch_assoc($pelanggan)): ?>

                                    <tr>
                                        <td><?= $no++ ?></td>

                                        <td>
                                            <strong style="color:#43556a;">
                                                <?= aman($row["nama"]) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= aman($row["no_hp"]) ?>
                                        </td>

                                        <td>
                                            <?= aman($row["alamat"]) ?>
                                        </td>

                                        <td>
                                            <div class="aksi">

                                                <a
                                                    class="btn btn-edit"
                                                    href="pelanggan.php?edit=<?= (int)$row["id_pelanggan"] ?>"
                                                >
                                                    <i class="fa-solid fa-pen"></i>
                                                    Edit
                                                </a>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Yakin ingin menghapus pelanggan ini?')"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="id_pelanggan"
                                                        value="<?= (int)$row["id_pelanggan"] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="hapus"
                                                        class="btn btn-delete"
                                                    >
                                                        <i class="fa-solid fa-trash"></i>
                                                        Hapus
                                                    </button>
                                                </form>

                                            </div>
                                        </td>
                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="5" class="empty">
                                        <i
                                            class="fa-solid fa-users"
                                            style="font-size:23px;display:block;margin-bottom:9px;"
                                        ></i>

                                        <?= $cari !== ""
                                            ? "Pelanggan tidak ditemukan."
                                            : "Belum ada data pelanggan." ?>
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>
                    </table>

                </div>

            </div>

        </div>

    </div>

</body>
</html>
