
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

$isAdmin = ($roleUser === "admin");

// FUNGSI KEAMANAN
function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

// ESTIMASI WAKTU
function estimasiWaktu($waktu) {
    return $waktu === "Reguler" ? 1 : 3;
}

// PERBAIKI ESTIMASI DATA LAYANAN YANG SUDAH ADA
mysqli_query($conn, "
    UPDATE layanan
    SET estimasi_hari = CASE
        WHEN jenis_waktu = 'Reguler' THEN 1
        WHEN jenis_waktu = 'Express' THEN 3
        ELSE estimasi_hari
    END
");

// VARIABEL FORM
$pesan = "";
$modeEdit = false;
$idEdit = "";
$namaEdit = "";
$waktuEdit = "Reguler";
$hargaEdit = "";

// ================================
// TAMBAH LAYANAN
// ================================
if (isset($_POST["tambah"]) && $isAdmin) {
    $nama = trim($_POST["nama_layanan"] ?? "");
    $waktu = trim($_POST["jenis_waktu"] ?? "");
    $harga = (float)($_POST["harga_per_kg"] ?? 0);
    $estimasi = estimasiWaktu($waktu);

    if (
        $nama === "" ||
        strlen($nama) > 100 ||
        !in_array($waktu, ["Reguler", "Express"], true) ||
        $harga <= 0
    ) {
        $pesan = "Mohon isi data layanan dengan benar.";
    } else {
        $sql = "INSERT INTO layanan
                (nama_layanan, jenis_waktu, estimasi_hari, harga_per_kg)
                VALUES (?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssid",
                $nama,
                $waktu,
                $estimasi,
                $harga
            );

            if (mysqli_stmt_execute($stmt)) {
                header("Location: layanan.php?status=tambah");
                exit;
            } else {
                $pesan = "Layanan gagal ditambahkan.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $pesan = "Terjadi kesalahan pada database.";
        }
    }
}

// ================================
// UPDATE LAYANAN
// ================================
if (isset($_POST["update"]) && $isAdmin) {
    $id = (int)($_POST["id_layanan"] ?? 0);
    $nama = trim($_POST["nama_layanan"] ?? "");
    $waktu = trim($_POST["jenis_waktu"] ?? "");
    $harga = (float)($_POST["harga_per_kg"] ?? 0);
    $estimasi = estimasiWaktu($waktu);

    if (
        $id <= 0 ||
        $nama === "" ||
        strlen($nama) > 100 ||
        !in_array($waktu, ["Reguler", "Express"], true) ||
        $harga <= 0
    ) {
        $pesan = "Mohon isi data layanan dengan benar.";

        $modeEdit = true;
        $idEdit = $id;
        $namaEdit = $nama;
        $waktuEdit = $waktu;
        $hargaEdit = $harga;
    } else {
        $sql = "UPDATE layanan
                SET nama_layanan = ?,
                    jenis_waktu = ?,
                    estimasi_hari = ?,
                    harga_per_kg = ?
                WHERE id_layanan = ?";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssidi",
                $nama,
                $waktu,
                $estimasi,
                $harga,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {
                header("Location: layanan.php?status=update");
                exit;
            } else {
                $pesan = "Layanan gagal diperbarui.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $pesan = "Terjadi kesalahan pada database.";
        }
    }
}

// ================================
// HAPUS LAYANAN
// ================================
if (isset($_POST["hapus"]) && $isAdmin) {
    $id = (int)($_POST["id_layanan"] ?? 0);

    $sql = "DELETE FROM layanan WHERE id_layanan = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                header("Location: layanan.php?status=hapus");
                exit;
            } else {
                $pesan = "Data layanan tidak ditemukan.";
            }
        } else {
            $pesan = "Layanan tidak dapat dihapus karena sudah digunakan dalam pesanan.";
        }

        mysqli_stmt_close($stmt);
    } else {
        $pesan = "Terjadi kesalahan pada database.";
    }
}

// ================================
// AMBIL DATA UNTUK DIEDIT
// ================================
if (isset($_GET["edit"]) && $isAdmin) {
    $id = (int)$_GET["edit"];

    $sql = "SELECT * FROM layanan WHERE id_layanan = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $hasil = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($hasil);

        if ($data) {
            $modeEdit = true;
            $idEdit = $data["id_layanan"];
            $namaEdit = $data["nama_layanan"];
            $waktuEdit = $data["jenis_waktu"];
            $hargaEdit = $data["harga_per_kg"];
        }

        mysqli_stmt_close($stmt);
    }
}

// ================================
// NOTIFIKASI
// ================================
if (isset($_GET["status"])) {
    if ($_GET["status"] === "tambah") {
        $pesan = "Layanan berhasil ditambahkan.";
    } elseif ($_GET["status"] === "update") {
        $pesan = "Layanan berhasil diperbarui.";
    } elseif ($_GET["status"] === "hapus") {
        $pesan = "Layanan berhasil dihapus.";
    }
}

// ================================
// PENCARIAN
// ================================
$cari = trim($_GET["cari"] ?? "");

if ($cari !== "") {
    $sql = "SELECT * FROM layanan
            WHERE nama_layanan LIKE ? OR jenis_waktu LIKE ?
            ORDER BY id_layanan DESC";

    $stmt = mysqli_prepare($conn, $sql);
    $keyword = "%" . $cari . "%";

    mysqli_stmt_bind_param($stmt, "ss", $keyword, $keyword);
    mysqli_stmt_execute($stmt);

    $layanan = mysqli_stmt_get_result($stmt);
} else {
    $layanan = mysqli_query(
        $conn,
        "SELECT * FROM layanan ORDER BY id_layanan DESC"
    );
}

// ================================
// TOTAL LAYANAN
// ================================
$queryTotal = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM layanan"
);

$dataTotal = mysqli_fetch_assoc($queryTotal);
$totalLayanan = $dataTotal["total"] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Layanan | Teman.Nyuci</title>

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

        .main {
            margin-left: 235px;
            padding: 30px 32px;
            min-height: 100vh;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 27px;
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

        /* FORM */
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
        .form-group select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #e2eaf2;
            border-radius: 8px;
            color: #60758c;
            background: white;
            font-family: inherit;
            font-size: 12px;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #83b9e9;
            box-shadow: 0 0 0 3px rgba(131,185,233,.12);
        }

        .form-group input[readonly] {
            background: #f5f8fc;
            color: #718197;
        }

        .form-hint {
            font-size: 10px;
            color: #9aa8b7;
            margin-top: 6px;
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

        /* TABEL */
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

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
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

        .badge-waktu {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            background: #e8f3fc;
            color: #559bd5;
        }

        .badge-express {
            background: #fff3e5;
            color: #d99542;
        }

        .info-petugas {
            background: #eaf4ff;
            border: 1px solid #d9eafa;
            color: #5d8fba;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 12px;
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

    <?php include "sidebar.php"; ?>

    <div class="main">

        <div class="header">
            <div>
                <h1>Data Layanan</h1>
                <p>Kelola jenis layanan dan harga laundry Teman.Nyuci</p>
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
                strpos($pesan, "tidak dapat") !== false ||
                strpos($pesan, "kesalahan") !== false ||
                strpos($pesan, "tidak ditemukan") !== false
            ) ? "error" : "" ?>">
                <i class="fa-solid fa-circle-info"></i>
                <?= aman($pesan) ?>
            </div>
        <?php endif; ?>

        <!-- TOTAL LAYANAN -->
        <div class="summary-card">
            <div class="summary-top">
                <div class="summary-icon">
                    <i class="fa-solid fa-shirt"></i>
                </div>

                <span class="summary-label">Total Jenis Layanan</span>
            </div>

            <h2>
                <?= number_format((int)$totalLayanan, 0, ",", ".") ?>
            </h2>
        </div>

        <?php if (!$isAdmin): ?>
            <div class="info-petugas">
                <i class="fa-solid fa-circle-info"></i>
                Kamu login sebagai Petugas. Halaman ini hanya dapat digunakan untuk melihat data layanan.
            </div>
        <?php endif; ?>

        <div class="content">

            <!-- FORM LAYANAN -->
            <?php if ($isAdmin): ?>
                <div class="box">

                    <h3 class="box-title">
                        <i class="fa-solid <?= $modeEdit ? "fa-pen-to-square" : "fa-plus-circle" ?>"></i>
                        <?= $modeEdit ? "Edit Layanan" : "Tambah Layanan" ?>
                    </h3>

                    <form method="POST" action="layanan.php">

                        <?php if ($modeEdit): ?>
                            <input
                                type="hidden"
                                name="id_layanan"
                                value="<?= aman($idEdit) ?>"
                            >
                        <?php endif; ?>

                        <div class="form-group">
                            <label>Nama Layanan</label>

                            <input
                                type="text"
                                name="nama_layanan"
                                placeholder="Contoh: Cuci Gosok"
                                value="<?= aman($namaEdit) ?>"
                                maxlength="100"
                                required
                            >

                            <div class="form-hint">
                                Masukkan nama layanan yang ingin ditambahkan.
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Jenis Waktu</label>

                            <select
                                name="jenis_waktu"
                                id="jenis_waktu"
                                onchange="aturEstimasi()"
                                required
                            >
                                <option
                                    value="Reguler"
                                    <?= $waktuEdit === "Reguler" ? "selected" : "" ?>
                                >
                                    Reguler
                                </option>

                                <option
                                    value="Express"
                                    <?= $waktuEdit === "Express" ? "selected" : "" ?>
                                >
                                    Express
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Estimasi Pengerjaan</label>

                            <input
                                type="text"
                                id="estimasi"
                                value="<?= estimasiWaktu($waktuEdit) ?> Hari"
                                readonly
                            >
                        </div>

                        <div class="form-group">
                            <label>Harga per Kg (Rp)</label>

                            <input
                                type="number"
                                name="harga_per_kg"
                                min="1"
                                step="1"
                                placeholder="Contoh: 15000"
                                value="<?= aman($hargaEdit) ?>"
                                required
                            >
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

                            <a href="layanan.php" class="btn btn-cancel">
                                Batal
                            </a>

                        <?php else: ?>

                            <button
                                type="submit"
                                name="tambah"
                                class="btn btn-primary"
                            >
                                <i class="fa-solid fa-plus"></i>
                                Tambah Layanan
                            </button>

                        <?php endif; ?>

                    </form>
                </div>
            <?php endif; ?>

            <!-- DAFTAR LAYANAN -->
            <div class="box">

                <div class="table-header">
                    <h3>
                        <i class="fa-solid fa-list"
                           style="color:#77b5e8;margin-right:6px;"></i>
                        Daftar Layanan
                    </h3>

                    <form
                        method="GET"
                        action="layanan.php"
                        class="search"
                    >
                        <input
                            type="text"
                            name="cari"
                            placeholder="Cari layanan..."
                            value="<?= aman($cari) ?>"
                        >

                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            Cari
                        </button>

                        <?php if ($cari !== ""): ?>
                            <a href="layanan.php" class="btn btn-cancel">
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
                                <th>Nama Layanan</th>
                                <th>Jenis Waktu</th>
                                <th>Estimasi</th>
                                <th>Harga/Kg</th>

                                <?php if ($isAdmin): ?>
                                    <th>Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($layanan && mysqli_num_rows($layanan) > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($row = mysqli_fetch_assoc($layanan)): ?>

                                    <tr>
                                        <td><?= $no++ ?></td>

                                        <td>
                                            <strong style="color:#43556a;">
                                                <?= aman($row["nama_layanan"]) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?php if ($row["jenis_waktu"] === "Express"): ?>
                                                <span class="badge-waktu badge-express">
                                                    Express
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-waktu">
                                                    Reguler
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= estimasiWaktu($row["jenis_waktu"]) ?> Hari
                                        </td>

                                        <td>
                                            Rp <?= number_format(
                                                (float)$row["harga_per_kg"],
                                                0,
                                                ",",
                                                "."
                                            ) ?>
                                        </td>

                                        <?php if ($isAdmin): ?>
                                            <td>
                                                <div class="aksi">

                                                    <a
                                                        class="btn btn-edit"
                                                        href="layanan.php?edit=<?= (int)$row["id_layanan"] ?>"
                                                    >
                                                        <i class="fa-solid fa-pen"></i>
                                                        Edit
                                                    </a>

                                                    <form
                                                        method="POST"
                                                        onsubmit="return confirm('Yakin ingin menghapus layanan ini?')"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id_layanan"
                                                            value="<?= (int)$row["id_layanan"] ?>"
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
                                        <?php endif; ?>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>
                                    <td
                                        colspan="<?= $isAdmin ? 6 : 5 ?>"
                                        class="empty"
                                    >
                                        <i
                                            class="fa-solid fa-shirt"
                                            style="font-size:23px;display:block;margin-bottom:9px;"
                                        ></i>

                                        <?= $cari !== ""
                                            ? "Layanan tidak ditemukan."
                                            : "Belum ada data layanan." ?>
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>

            </div>

        </div>

    </div>

    <script>
        // ESTIMASI PENGERJAAN OTOMATIS
        function aturEstimasi() {
            const waktu = document.getElementById("jenis_waktu").value;
            const estimasi = document.getElementById("estimasi");

            if (waktu === "Reguler") {
                estimasi.value = "1 Hari";
            } else {
                estimasi.value = "3 Hari";
            }
        }
    </script>

</body>
</html>
