
<?php
session_start();
require_once "koneksi.php";

date_default_timezone_set("Asia/Jakarta");

if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

function aman($data) {
    return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
}

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ",", ".");
}

// IDENTITAS PENGGUNA
$namaUser = $_SESSION["nama"] ?? "Pengguna";
$roleUser = $_SESSION["role"] ?? "Petugas";
$inisial = strtoupper(substr($namaUser, 0, 1));
$isAdmin = strtolower($roleUser) === "admin";
$labelRole = ucfirst($roleUser);

$pesan = "";

// =====================================
// KONFIRMASI PESANAN ONLINE
// =====================================
if (isset($_POST["konfirmasi"])) {

    $id_online = (int)($_POST["id_online"] ?? 0);

    mysqli_begin_transaction($conn);

    try {

        // AMBIL DATA PESANAN ONLINE
        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM pemesanan_online
             WHERE id_online = ? AND status = 'Menunggu Konfirmasi'"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_online);
        mysqli_stmt_execute($stmt);

        $online = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);

        if (!$online) {
            throw new Exception("Pesanan tidak ditemukan atau sudah diproses.");
        }

        // AMBIL HARGA DAN ESTIMASI LAYANAN
        $stmt = mysqli_prepare(
            $conn,
            "SELECT harga_per_kg, estimasi_hari
             FROM layanan
             WHERE id_layanan = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $online["id_layanan"]
        );

        mysqli_stmt_execute($stmt);

        $layanan = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);

        if (!$layanan) {
            throw new Exception("Layanan tidak ditemukan.");
        }

        // CARI PELANGGAN BERDASARKAN NOMOR HP
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_pelanggan
             FROM pelanggan
             WHERE no_hp = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $online["no_hp"]
        );

        mysqli_stmt_execute($stmt);

        $pelanggan = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);

        if ($pelanggan) {

            // PELANGGAN SUDAH TERDAFTAR
            $id_pelanggan = (int)$pelanggan["id_pelanggan"];

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE pelanggan
                 SET nama = ?, alamat = ?
                 WHERE id_pelanggan = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $online["nama_pelanggan"],
                $online["alamat"],
                $id_pelanggan
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Data pelanggan gagal diperbarui.");
            }

            mysqli_stmt_close($stmt);

        } else {

            // TAMBAH PELANGGAN BARU
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO pelanggan (nama, no_hp, alamat)
                 VALUES (?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $online["nama_pelanggan"],
                $online["no_hp"],
                $online["alamat"]
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Data pelanggan gagal ditambahkan.");
            }

            $id_pelanggan = mysqli_insert_id($conn);

            mysqli_stmt_close($stmt);
        }

        // HITUNG TOTAL HARGA
        $harga = (float)$layanan["harga_per_kg"];
        $berat = (float)$online["berat_estimasi"];
        $total = $harga * $berat;

        $estimasi = (int)$layanan["estimasi_hari"];

        $tanggalMasuk = date("Y-m-d H:i:s");

        $tanggalSelesai = date(
            "Y-m-d",
            strtotime("+$estimasi days")
        );

        $kode = "TN-" . date("YmdHis") . "-" . $id_online;

        $id_user = (int)$_SESSION["id_user"];
        $id_layanan = (int)$online["id_layanan"];
        $status = "Diproses";

        // MASUKKAN KE TABEL PESANAN
        $sql = "INSERT INTO pesanan
                (
                    kode_pesanan,
                    id_pelanggan,
                    id_user,
                    id_layanan,
                    tanggal_masuk,
                    tanggal_estimasi_selesai,
                    berat_kg,
                    total_harga,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "siiissdds",
            $kode,
            $id_pelanggan,
            $id_user,
            $id_layanan,
            $tanggalMasuk,
            $tanggalSelesai,
            $berat,
            $total,
            $status
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Pesanan gagal dimasukkan ke sistem.");
        }

        $id_pesanan = mysqli_insert_id($conn);

        mysqli_stmt_close($stmt);

        // SIMPAN RIWAYAT STATUS
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO riwayat_status
             (id_pesanan, id_user, status, waktu)
             VALUES (?, ?, ?, NOW())"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iis",
            $id_pesanan,
            $id_user,
            $status
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Riwayat status gagal disimpan.");
        }

        mysqli_stmt_close($stmt);

        // UPDATE STATUS DAN HUBUNGKAN PESANAN UTAMA
        $statusOnline = "Dikonfirmasi";

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE pemesanan_online
             SET status = ?, id_pesanan = ?
             WHERE id_online = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sii",
            $statusOnline,
            $id_pesanan,
            $id_online
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Status pemesanan online gagal diperbarui.");
        }

        mysqli_stmt_close($stmt);

        mysqli_commit($conn);

        header("Location: konfirmasi_online.php?status=konfirmasi");
        exit;

    } catch (Throwable $e) {

        mysqli_rollback($conn);
        $pesan = $e->getMessage();
    }
}

// =====================================
// TOLAK PESANAN ONLINE
// =====================================
if (isset($_POST["tolak"])) {

    $id_online = (int)($_POST["id_online"] ?? 0);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE pemesanan_online
         SET status = 'Ditolak'
         WHERE id_online = ?
         AND status = 'Menunggu Konfirmasi'"
    );

    mysqli_stmt_bind_param($stmt, "i", $id_online);

    if (
        mysqli_stmt_execute($stmt) &&
        mysqli_stmt_affected_rows($stmt) > 0
    ) {
        mysqli_stmt_close($stmt);

        header("Location: konfirmasi_online.php?status=tolak");
        exit;
    } else {
        $pesan = "Pesanan gagal ditolak atau sudah diproses.";
    }

    mysqli_stmt_close($stmt);
}

// =====================================
// NOTIFIKASI
// =====================================
if (isset($_GET["status"])) {

    if ($_GET["status"] == "konfirmasi") {
        $pesan = "Pesanan berhasil dikonfirmasi dan dimasukkan ke sistem.";
    } elseif ($_GET["status"] == "tolak") {
        $pesan = "Pesanan berhasil ditolak.";
    }
}

// =====================================
// AMBIL DATA PESANAN ONLINE
// =====================================
$sql = "SELECT
            po.*,
            l.nama_layanan,
            l.jenis_waktu,
            l.harga_per_kg,
            l.estimasi_hari
        FROM pemesanan_online po
        JOIN layanan l
            ON po.id_layanan = l.id_layanan
        ORDER BY po.id_online DESC";

$daftarOnline = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Pesanan Online - Teman.Nyuci</title>

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

/* SIDEBAR DIAMBIL DARI sidebar.php */

/* MAIN */
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
    border: 1px solid #e7eef6;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(70,110,150,.04);
}

.card-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    gap: 12px;
    flex-wrap: wrap;
}

.card-heading h2 {
    font-size: 18px;
    color: #34445a;
}

.card-heading p {
    color: #9aa8b7;
    font-size: 12px;
    margin-top: 6px;
}

/* TABLE */
.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 1150px;
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
    padding: 15px 12px;
    border-bottom: 1px solid #edf1f5;
    color: #66768a;
    vertical-align: middle;
}

tbody tr:hover td {
    background: #fafcff;
}

/* BUTTON */
.btn {
    border: none;
    padding: 9px 12px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: .2s;
    white-space: nowrap;
}

.btn-konfirmasi {
    background: #eaf7ee;
    color: #277344;
}

.btn-konfirmasi:hover {
    background: #d8efdf;
}

.btn-tolak {
    background: #fff0f0;
    color: #c03939;
}

.btn-tolak:hover {
    background: #fce0e0;
}

.aksi {
    display: flex;
    gap: 6px;
    align-items: center;
}

/* STATUS */
.badge {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
}

.menunggu {
    background: #fff4e5;
    color: #a56816;
}

.dikonfirmasi {
    background: #eaf5ee;
    color: #277344;
}

.ditolak {
    background: #fff0f0;
    color: #c03939;
}

/* ALERT */
.alert {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    background: #eaf7ee;
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
    color: #9aa8b7;
    padding: 35px;
}

/* FOOTER */
.footer {
    text-align: center;
    color: #9aa8b7;
    font-size: 11px;
    padding: 22px;
}

/* RESPONSIVE */
@media(max-width: 1050px) {
    .summary {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 850px) {
    .main {
        margin-left: 235px;
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
        gap: 12px;
    }

    .summary {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 18px;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR UTAMA -->
<?php include "sidebar.php"; ?>

<!-- KONTEN UTAMA -->
<div class="main">

    <!-- HEADER -->
    <div class="header">

        <div>
            <h1>Pesanan Online</h1>
            <p>Kelola dan konfirmasi pesanan dari pelanggan</p>
        </div>

        <div class="header-date">
            <i class="fa-regular fa-calendar"></i>
            <?= date("d F Y") ?>
        </div>

    </div>

    <!-- RINGKASAN -->
    <?php
    $jumlahMenunggu = 0;
    $jumlahDikonfirmasi = 0;
    $jumlahDitolak = 0;

    if ($daftarOnline) {
        mysqli_data_seek($daftarOnline, 0);

        while ($data = mysqli_fetch_assoc($daftarOnline)) {
            if ($data["status"] == "Menunggu Konfirmasi") {
                $jumlahMenunggu++;
            } elseif ($data["status"] == "Dikonfirmasi") {
                $jumlahDikonfirmasi++;
            } elseif ($data["status"] == "Ditolak") {
                $jumlahDitolak++;
            }
        }

        mysqli_data_seek($daftarOnline, 0);
    }
    ?>

    <div class="summary">

        <div class="summary-card">
            <p><i class="fa-solid fa-clock"></i> Menunggu Konfirmasi</p>
            <h3><?= $jumlahMenunggu ?></h3>
        </div>

        <div class="summary-card">
            <p><i class="fa-solid fa-circle-check"></i> Dikonfirmasi</p>
            <h3><?= $jumlahDikonfirmasi ?></h3>
        </div>

        <div class="summary-card">
            <p><i class="fa-solid fa-circle-xmark"></i> Ditolak</p>
            <h3><?= $jumlahDitolak ?></h3>
        </div>

    </div>

    <!-- NOTIFIKASI -->
    <?php if ($pesan != ""): ?>

        <div class="alert <?= (
            strpos($pesan, "gagal") !== false ||
            strpos($pesan, "tidak ditemukan") !== false ||
            strpos($pesan, "sudah") !== false
        ) ? "error" : "" ?>">

            <?= aman($pesan) ?>

        </div>

    <?php endif; ?>

    <!-- DAFTAR PESANAN -->
    <div class="card">

        <div class="card-heading">

            <div>
                <h2>
                    <i class="fa-solid fa-list-check"></i>
                    Daftar Pemesanan Pelanggan
                </h2>

                <p>
                    Periksa dan kelola permintaan laundry yang masuk secara online.
                </p>
            </div>

        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Pelanggan</th>
                        <th>No. WhatsApp</th>
                        <th>Alamat</th>
                        <th>Layanan</th>
                        <th>Berat</th>
                        <th>Estimasi</th>
                        <th>Total Perkiraan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($daftarOnline && mysqli_num_rows($daftarOnline) > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($daftarOnline)): ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td>
                                <strong>
                                    <?= aman($row["nama_pelanggan"]) ?>
                                </strong>
                            </td>

                            <td>
                                <?= aman($row["no_hp"]) ?>
                            </td>

                            <td>
                                <?= aman($row["alamat"]) ?>
                            </td>

                            <td>
                                <?= aman($row["nama_layanan"]) ?>
                                (<?= aman($row["jenis_waktu"]) ?>)
                            </td>

                            <td>
                                <?= number_format(
                                    (float)$row["berat_estimasi"],
                                    1,
                                    ",",
                                    "."
                                ) ?> Kg
                            </td>

                            <td>
                                <?= (int)$row["estimasi_hari"] ?> Hari
                            </td>

                            <td>
                                <?= rupiah(
                                    $row["harga_per_kg"] *
                                    $row["berat_estimasi"]
                                ) ?>
                            </td>

                            <td>

                                <?php if ($row["status"] == "Menunggu Konfirmasi"): ?>

                                    <span class="badge menunggu">
                                        <i class="fa-solid fa-clock"></i>
                                        Menunggu Konfirmasi
                                    </span>

                                <?php elseif ($row["status"] == "Dikonfirmasi"): ?>

                                    <span class="badge dikonfirmasi">
                                        <i class="fa-solid fa-check"></i>
                                        Dikonfirmasi
                                    </span>

                                <?php else: ?>

                                    <span class="badge ditolak">
                                        <i class="fa-solid fa-xmark"></i>
                                        Ditolak
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ($row["status"] == "Menunggu Konfirmasi"): ?>

                                    <div class="aksi">

                                        <form method="POST"
                                            onsubmit="return confirm('Yakin ingin mengonfirmasi pesanan ini?')">

                                            <input
                                                type="hidden"
                                                name="id_online"
                                                value="<?= (int)$row["id_online"] ?>">

                                            <button
                                                type="submit"
                                                name="konfirmasi"
                                                class="btn btn-konfirmasi">

                                                <i class="fa-solid fa-check"></i>
                                                Konfirmasi

                                            </button>

                                        </form>

                                        <form method="POST"
                                            onsubmit="return confirm('Yakin ingin menolak pesanan ini?')">

                                            <input
                                                type="hidden"
                                                name="id_online"
                                                value="<?= (int)$row["id_online"] ?>">

                                            <button
                                                type="submit"
                                                name="tolak"
                                                class="btn btn-tolak">

                                                <i class="fa-solid fa-xmark"></i>
                                                Tolak

                                            </button>

                                        </form>

                                    </div>

                                <?php else: ?>

                                    <span style="color:#a0adbc;">-</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10" class="empty">
                            <i class="fa-solid fa-inbox"
                               style="font-size:25px;margin-bottom:10px;"></i>
                            <br>
                            Belum ada pesanan online.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <div class="footer">
        © <?= date("Y") ?> Teman.Nyuci. Semua Hak Dilindungi.
    </div>

</div>

<script>
function toggleAccount() {
    const dropdown = document.getElementById("accountDropdown");
    const arrow = document.getElementById("accountArrow");

    dropdown.classList.toggle("show");

    arrow.innerHTML = dropdown.classList.contains("show") ? "⌄" : "⌃";
}

document.addEventListener("click", function(event) {
    const menu = document.querySelector(".account-menu");
    const dropdown = document.getElementById("accountDropdown");
    const arrow = document.getElementById("accountArrow");

    if (menu && !menu.contains(event.target)) {
        dropdown.classList.remove("show");
        arrow.innerHTML = "⌃";
    }
});
</script>

</body>
</html>
