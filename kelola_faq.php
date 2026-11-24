
<?php
session_start();
require_once "koneksi.php";

date_default_timezone_set("Asia/Jakarta");

// CEK LOGIN
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

// HALAMAN KELOLA FAQ KHUSUS ADMIN
$roleUser = strtolower($_SESSION["role"] ?? "");

if ($roleUser !== "admin") {
    header("Location: dashboard.php");
    exit;
}

// IDENTITAS PENGGUNA
$namaUser = $_SESSION["nama"] ?? "Pengguna";
$isAdmin = true;
$inisial = strtoupper(substr($namaUser, 0, 1));
$labelRole = "Administrator";

// FUNGSI
function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

$pesan = "";
$error = "";
$dataEdit = null;

// =====================================
// TAMBAH FAQ
// =====================================
if (isset($_POST["tambah"])) {
    $kategori = trim($_POST["kategori"] ?? "");
    $pertanyaan = trim($_POST["pertanyaan"] ?? "");
    $jawaban = trim($_POST["jawaban"] ?? "");

    if ($kategori === "" || $pertanyaan === "" || $jawaban === "") {
        $error = "Semua kolom wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO faq (kategori, pertanyaan, jawaban)
             VALUES (?, ?, ?)"
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $kategori,
                $pertanyaan,
                $jawaban
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: kelola_faq.php?status=tambah");
                exit;
            } else {
                $error = "FAQ gagal ditambahkan.";
                mysqli_stmt_close($stmt);
            }
        } else {
            $error = "Terjadi kesalahan saat menyiapkan data.";
        }
    }
}

// =====================================
// AMBIL DATA FAQ UNTUK DIEDIT
// =====================================
if (isset($_GET["edit"])) {
    $id_faq = (int)$_GET["edit"];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM faq WHERE id_faq = ?"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_faq);
        mysqli_stmt_execute($stmt);

        $dataEdit = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);

        if (!$dataEdit) {
            $error = "Data FAQ tidak ditemukan.";
        }
    }
}

// =====================================
// UPDATE FAQ
// =====================================
if (isset($_POST["update"])) {
    $id_faq = (int)($_POST["id_faq"] ?? 0);
    $kategori = trim($_POST["kategori"] ?? "");
    $pertanyaan = trim($_POST["pertanyaan"] ?? "");
    $jawaban = trim($_POST["jawaban"] ?? "");

    if (
        $id_faq <= 0 ||
        $kategori === "" ||
        $pertanyaan === "" ||
        $jawaban === ""
    ) {
        $error = "Semua kolom wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE faq
             SET kategori = ?, pertanyaan = ?, jawaban = ?
             WHERE id_faq = ?"
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "sssi",
                $kategori,
                $pertanyaan,
                $jawaban,
                $id_faq
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: kelola_faq.php?status=update");
                exit;
            } else {
                $error = "FAQ gagal diperbarui.";
                mysqli_stmt_close($stmt);
            }
        } else {
            $error = "Terjadi kesalahan saat menyiapkan data.";
        }
    }
}

// =====================================
// HAPUS FAQ
// =====================================
if (isset($_POST["hapus"])) {
    $id_faq = (int)($_POST["id_faq"] ?? 0);

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM faq WHERE id_faq = ?"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_faq);

        if (
            mysqli_stmt_execute($stmt) &&
            mysqli_stmt_affected_rows($stmt) > 0
        ) {
            mysqli_stmt_close($stmt);
            header("Location: kelola_faq.php?status=hapus");
            exit;
        } else {
            $error = "FAQ gagal dihapus atau tidak ditemukan.";
        }

        mysqli_stmt_close($stmt);
    } else {
        $error = "Terjadi kesalahan saat menyiapkan data.";
    }
}

// =====================================
// NOTIFIKASI
// =====================================
if (isset($_GET["status"])) {
    if ($_GET["status"] === "tambah") {
        $pesan = "FAQ berhasil ditambahkan.";
    } elseif ($_GET["status"] === "update") {
        $pesan = "FAQ berhasil diperbarui.";
    } elseif ($_GET["status"] === "hapus") {
        $pesan = "FAQ berhasil dihapus.";
    }
}

// =====================================
// AMBIL SEMUA DATA FAQ
// =====================================
$daftarFAQ = mysqli_query(
    $conn,
    "SELECT * FROM faq ORDER BY kategori ASC, id_faq DESC"
);

if (!$daftarFAQ) {
    die("Gagal mengambil data FAQ: " . mysqli_error($conn));
}

$totalFAQ = mysqli_num_rows($daftarFAQ);

// HITUNG KATEGORI
$kategoriFAQ = [];

while ($itemKategori = mysqli_fetch_assoc($daftarFAQ)) {
    $kategoriFAQ[$itemKategori["kategori"]] = true;
}

$totalKategori = count($kategoriFAQ);
mysqli_data_seek($daftarFAQ, 0);

// FORM TERBUKA JIKA EDIT ATAU ADA ERROR
$formTerbuka = ($dataEdit !== null || $error !== "");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Kelola FAQ - Teman.Nyuci</title>

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

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 27px;
    gap: 15px;
}

.header h1 {
    font-size: 25px;
    color: #34445a;
    margin-bottom: 6px;
}

.header p {
    color: #9aa8b7;
    font-size: 13px;
}

.header-date {
    color: #748399;
    font-size: 12px;
    background: white;
    border: 1px solid #e7eef6;
    border-radius: 8px;
    padding: 11px 15px;
    white-space: nowrap;
}

/* RINGKASAN */
.summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 22px;
}

.summary-card {
    background: white;
    padding: 20px;
    border: 1px solid #e7eef6;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(70,110,150,.04);
}

.summary-card p {
    color: #8998aa;
    font-size: 12px;
    margin-bottom: 10px;
}

.summary-card h3 {
    font-size: 23px;
    color: #34445a;
}

/* CARD */
.card {
    background: white;
    padding: 25px;
    border: 1px solid #e7eef6;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(70,110,150,.04);
    margin-bottom: 22px;
}

.card h2 {
    font-size: 18px;
    color: #34445a;
    margin-bottom: 20px;
}

/* FORM */
.form-card {
    display: none;
}

.form-card.show {
    display: block;
}

.form-group {
    margin-bottom: 17px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #526277;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #dfe8f1;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    color: #34445a;
    outline: none;
    background: white;
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
    line-height: 1.6;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #79b5e8;
    box-shadow: 0 0 0 3px rgba(100,169,229,.12);
}

/* TOMBOL */
.btn {
    border: none;
    padding: 10px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: .2s;
    font-family: inherit;
}

.btn-tambah {
    background: #64a9e5;
    color: white;
}

.btn-tambah:hover {
    background: #4d96d5;
}

.btn-edit {
    background: #eaf3fb;
    color: #559bd5;
}

.btn-edit:hover {
    background: #dcecf9;
}

.btn-hapus {
    background: #fff0f0;
    color: #c03939;
}

.btn-hapus:hover {
    background: #fce0e0;
}

.btn-batal {
    background: #eef1f5;
    color: #65768b;
}

.btn-batal:hover {
    background: #e0e6ed;
}

.aksi {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

/* HEADER DAFTAR */
.list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.list-header h2 {
    margin-bottom: 0;
}

/* TABEL */
.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 750px;
}

th {
    background: #f3f7fc;
    color: #65768b;
    text-align: left;
    padding: 14px 12px;
    font-weight: 650;
    white-space: nowrap;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid #edf1f5;
    color: #66768a;
    vertical-align: top;
    line-height: 1.6;
}

tbody tr:hover td {
    background: #fafcff;
}

/* ALERT */
.alert {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    background: #eaf5ee;
    color: #277344;
    border: 1px solid #d9efdf;
}

.alert.error {
    background: #fff0f0;
    color: #b83232;
    border-color: #f5dddd;
}

.empty {
    text-align: center;
    padding: 30px;
    color: #9aa8b7;
}

/* RESPONSIVE */
@media(max-width: 1050px) {
    .summary {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 850px) {
    .main {
        padding: 24px 20px;
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

    .card {
        padding: 18px;
    }

    .list-header {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
</head>

<body>

<?php include "sidebar.php"; ?>

<!-- KONTEN UTAMA -->
<div class="main">

    <div class="header">
        <div>
            <h1>Kelola FAQ</h1>
            <p>Kelola pertanyaan dan jawaban untuk membantu pelanggan.</p>
        </div>

        <div class="header-date">
            <i class="fa-regular fa-calendar"></i>
            <?= date("d F Y") ?>
        </div>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($pesan !== ""): ?>
        <div class="alert">
            <i class="fa-solid fa-circle-check"></i>
            <?= aman($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= aman($error) ?>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN -->
    <div class="summary">
        <div class="summary-card">
            <p>
                <i class="fa-solid fa-book-open"></i>
                Total FAQ
            </p>
            <h3><?= $totalFAQ ?></h3>
        </div>

        <div class="summary-card">
            <p>
                <i class="fa-solid fa-layer-group"></i>
                Kategori FAQ
            </p>
            <h3><?= $totalKategori ?></h3>
        </div>

        <div class="summary-card">
            <p>
                <i class="fa-solid fa-circle-info"></i>
                Fungsi FAQ
            </p>
            <h3 style="font-size:17px;">Informasi Pelanggan</h3>
        </div>
    </div>

    <!-- FORM TAMBAH / EDIT -->
    <div class="card form-card <?= $formTerbuka ? 'show' : '' ?>"
         id="formFAQ">

        <h2>
            <i class="fa-solid <?= $dataEdit ? 'fa-pen-to-square' : 'fa-circle-plus' ?>"></i>
            <?= $dataEdit ? 'Edit Pertanyaan FAQ' : 'Tambah Pertanyaan FAQ' ?>
        </h2>

        <form method="POST">

            <?php if ($dataEdit): ?>
                <input
                    type="hidden"
                    name="id_faq"
                    value="<?= (int)$dataEdit["id_faq"] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Kategori FAQ</label>
                <input
                    type="text"
                    name="kategori"
                    placeholder="Contoh: Pemesanan, Pembayaran, Layanan"
                    value="<?= aman($dataEdit["kategori"] ?? $_POST["kategori"] ?? "") ?>"
                    maxlength="100"
                    required>
            </div>

            <div class="form-group">
                <label>Pertanyaan</label>
                <input
                    type="text"
                    name="pertanyaan"
                    placeholder="Masukkan pertanyaan pelanggan"
                    value="<?= aman($dataEdit["pertanyaan"] ?? $_POST["pertanyaan"] ?? "") ?>"
                    maxlength="255"
                    required>
            </div>

            <div class="form-group">
                <label>Jawaban</label>
                <textarea
                    name="jawaban"
                    placeholder="Masukkan jawaban atau informasi yang dibutuhkan pelanggan"
                    required><?= aman($dataEdit["jawaban"] ?? $_POST["jawaban"] ?? "") ?></textarea>
            </div>

            <?php if ($dataEdit): ?>
                <button
                    type="submit"
                    name="update"
                    class="btn btn-tambah">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan Perubahan
                </button>

                <a href="kelola_faq.php" class="btn btn-batal">
                    <i class="fa-solid fa-xmark"></i>
                    Batal
                </a>
            <?php else: ?>
                <button
                    type="submit"
                    name="tambah"
                    class="btn btn-tambah">
                    <i class="fa-solid fa-plus"></i>
                    Tambah FAQ
                </button>

                <button
                    type="button"
                    class="btn btn-batal"
                    onclick="tutupFormFAQ()">
                    <i class="fa-solid fa-xmark"></i>
                    Batal
                </button>
            <?php endif; ?>

        </form>
    </div>

    <!-- DAFTAR FAQ -->
    <div class="card">
        <div class="list-header">
            <h2>
                <i class="fa-solid fa-list"></i>
                Daftar Pertanyaan dan Jawaban
            </h2>

            <button
                type="button"
                class="btn btn-tambah"
                onclick="tampilkanFormFAQ()">
                <i class="fa-solid fa-plus"></i>
                Tambah FAQ
            </button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kategori</th>
                        <th>Pertanyaan</th>
                        <th>Jawaban</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($totalFAQ > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($daftarFAQ)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= aman($row["kategori"]) ?></td>
                            <td><?= aman($row["pertanyaan"]) ?></td>
                            <td><?= nl2br(aman($row["jawaban"])) ?></td>

                            <td>
                                <div class="aksi">
                                    <a
                                        href="kelola_faq.php?edit=<?= (int)$row["id_faq"] ?>"
                                        class="btn btn-edit">
                                        <i class="fa-solid fa-pen"></i>
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus FAQ ini?')">

                                        <input
                                            type="hidden"
                                            name="id_faq"
                                            value="<?= (int)$row["id_faq"] ?>">

                                        <button
                                            type="submit"
                                            name="hapus"
                                            class="btn btn-hapus">
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
                            <i class="fa-solid fa-inbox"
                               style="font-size:25px;margin-bottom:10px;"></i>
                            <br>
                            Belum ada data FAQ.
                            Silakan tambahkan FAQ baru.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="text-align:center;color:#9aa8b7;font-size:11px;padding:20px;">
        © <?= date("Y") ?> Teman.Nyuci. Semua Hak Dilindungi.
    </div>

</div>

<script>
// TAMPILKAN FORM FAQ
function tampilkanFormFAQ() {
    const form = document.getElementById("formFAQ");

    form.classList.add("show");

    form.scrollIntoView({
        behavior: "smooth",
        block: "start"
    });
}

// TUTUP FORM FAQ
function tutupFormFAQ() {
    const form = document.getElementById("formFAQ");
    form.classList.remove("show");
}
</script>

</body>
</html>
