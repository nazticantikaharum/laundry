
<?php
session_start();
require_once "koneksi.php";

$error = "";

// JIKA SUDAH LOGIN
if (isset($_SESSION["id_user"])) {
    header("Location: dashboard.php");
    exit;
}

// PROSES LOGIN
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Username dan password wajib diisi.";

    } else {

        $sql = "SELECT id_user, nama, username, password, role
                FROM users
                WHERE username = ?
                AND LOWER(role) IN ('admin', 'petugas')
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            if ($user && password_verify($password, $user["password"])) {

                session_regenerate_id(true);

                $_SESSION["id_user"] = $user["id_user"];
                $_SESSION["nama"] = $user["nama"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];

                header("Location: dashboard.php");
                exit;

            } else {
                $error = "Username atau password salah!";
            }

            mysqli_stmt_close($stmt);

        } else {
            $error = "Terjadi kesalahan pada sistem. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login | Teman.Nyuci</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #EAF6FC, #D5EDFA, #B9E3F7);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 25px;
}

/* DEKORASI BACKGROUND */
body::before,
body::after {
    content: "";
    position: fixed;
    border-radius: 50%;
    background: rgba(8, 127, 193, 0.07);
    z-index: -1;
}

body::before {
    width: 330px;
    height: 330px;
    top: -100px;
    left: -90px;
}

body::after {
    width: 280px;
    height: 280px;
    bottom: -100px;
    right: -60px;
}

/* LOGIN CONTAINER */
.login-container {
    width: 100%;
    max-width: 410px;
    background: #FFFFFF;
    padding: 38px 38px 30px;
    border-radius: 13px;
    box-shadow: 0 15px 45px rgba(9, 45, 80, 0.12);
}

/* LOGO */
.logo-area {
    text-align: center;
    margin-bottom: 25px;
}

.logo-area img {
    width: 76px;
    height: 76px;
    object-fit: contain;
    border-radius: 12px;
    margin-bottom: 10px;
}

.logo-area h1 {
    font-size: 22px;
    color: #092D50;
    margin-bottom: 7px;
}

/* WELCOME TEXT */
.login-heading {
    text-align: center;
    margin-bottom: 27px;
}

.login-heading p {
    color: #8496A8;
    font-size: 12px;
    line-height: 1.6;
}

/* ERROR */
.error {
    background: #fff0f0;
    color: #c83c3c;
    border: 1px solid #ffd6d6;
    padding: 11px 13px;
    border-radius: 7px;
    font-size: 13px;
    margin-bottom: 18px;
}

/* FORM */
.form-group {
    margin-bottom: 19px;
}

.form-group label {
    display: block;
    color: #29445E;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 9px;
}

.input-box {
    position: relative;
}

.input-box input {
    width: 100%;
    height: 46px;
    border: 1px solid #DCE7F0;
    border-radius: 7px;
    padding: 0 13px;
    font-size: 13px;
    color: #29445E;
    outline: none;
    background: #FBFDFF;
    transition: .2s;
}

.input-box input:focus {
    border-color: #087FC1;
    box-shadow: 0 0 0 3px rgba(8,127,193,0.10);
}

.input-box input::placeholder {
    color: #A8B6C4;
}

/* PASSWORD */
.password-box input {
    padding-right: 45px;
}

.toggle-password {
    position: absolute;
    top: 50%;
    right: 13px;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    cursor: pointer;
    color: #8499AD;
    display: flex;
    align-items: center;
}

.toggle-password:hover {
    color: #087FC1;
}

/* TOMBOL LOGIN */
.login-button {
    width: 100%;
    height: 47px;
    border: none;
    border-radius: 7px;
    background: #092D50;
    color: white;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 7px;
    transition: .2s;
}

.login-button:hover {
    background: #087FC1;
}

/* FOOTER */
.footer {
    text-align: center;
    border-top: 1px solid #EAF0F5;
    padding-top: 19px;
    margin-top: 25px;
    color: #9AAABD;
    font-size: 11px;
}

/* RESPONSIVE */
@media (max-width: 480px) {

    body {
        padding: 17px;
    }

    .login-container {
        padding: 32px 24px 25px;
    }

    .logo-area img {
        width: 70px;
        height: 70px;
    }
}
</style>
</head>

<body>

<div class="login-container">

    <!-- LOGO DAN NAMA -->
    <div class="logo-area">

        <img
            src="image/logo-teman-nyuci.jpeg"
            alt="Logo Teman.Nyuci"
        >

        <h1>Teman.Nyuci</h1>

    </div>

    <!-- WELCOME -->
    <div class="login-heading">
        <p>Welcome back! Please login to continue</p>
    </div>

    <!-- PESAN ERROR -->
    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>

    <?php endif; ?>

    <!-- FORM LOGIN -->
    <form method="POST">

        <div class="form-group">

            <label for="username">Username</label>

            <div class="input-box">

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Masukkan username"
                    value="<?= htmlspecialchars($_POST["username"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                    autocomplete="username"
                    required
                >

            </div>

        </div>

        <div class="form-group">

            <label for="password">Password</label>

            <div class="input-box password-box">

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    id="togglePassword"
                    onclick="lihatPassword()"
                    aria-label="Lihat password"
                >

                    <svg
                        id="eyeIcon"
                        width="21"
                        height="21"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>

                </button>

            </div>

        </div>

        <button type="submit" class="login-button">
            Login
        </button>

    </form>

    <!-- FOOTER -->
    <div class="footer">
        © 2026 Teman.Nyuci. All rights reserved.
    </div>

</div>

<script>
function lihatPassword() {

    const password = document.getElementById("password");
    const icon = document.getElementById("eyeIcon");
    const tombol = document.getElementById("togglePassword");

    if (password.type === "password") {

        password.type = "text";

        tombol.setAttribute("aria-label", "Sembunyikan password");

        icon.innerHTML = `
            <path d="M3 3l18 18"/>
            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
            <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a13 13 0 0 1-3 3.7"/>
            <path d="M6.6 6.6C3.7 8.3 2 12 2 12s3.6 7 10 7c1 0 2-.2 2.9-.5"/>
        `;

    } else {

        password.type = "password";

        tombol.setAttribute("aria-label", "Lihat password");

        icon.innerHTML = `
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/>
            <circle cx="12" cy="12" r="3"/>
        `;
    }
}
</script>

</body>
</html>
