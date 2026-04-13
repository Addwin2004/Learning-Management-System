<?php
session_start();

/* ---- LOGOUT HANDLER ---- */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

/* ---- DATABASE CONNECTION ---- */
$conn = new mysqli("localhost", "root", "", "lms");
if ($conn->connect_error) {
    die("Database connection failed");
}

$error = "";

/* ---- LOGIN HANDLER ---- */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "All fields are required.";
    } else {

        $stmt = $conn->prepare("SELECT user_id, name, password, role FROM Users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {

            $user = $result->fetch_assoc();

            if ($password === $user['password']) {

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] == "admin") {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] == "instructor") {
                    header("Location: instructor/dashboard.php");
                } else {
                    header("Location: student/dashboard.php");
                }
                exit();

            } else {
                $error = "Incorrect password.";
            }

        } else {
            $error = "User not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/login.css">
</head>

<body>

<div class="background-animation">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
</div>

<div class="container">
    <div class="form-wrapper">
        <div class="header-section">
            <div class="logo-icon">
                <i class="fas fa-book"></i>
            </div>
            <h1>LMS Portal</h1>
            <p class="subtitle">Welcome Back</p>
        </div>

        <?php if ($error): ?>
            <div class="error-banner">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validateForm()" class="login-form">
            <div class="input-group">
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" id="email" placeholder="Email Address" required>
                    <div class="input-underline"></div>
                </div>
            </div>

            <div class="input-group">
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <div class="input-underline"></div>
                </div>
            </div>

            <button type="submit" class="login-btn">
                <span>Sign In</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <!-- <div class="footer-text">
            <p>Don't have an account? <a href="#signup">Create one</a></p>
        </div> -->
    </div>

    <div class="side-decoration">
        <div class="decoration-circle"></div>
    </div>
</div>

<script>
function validateForm(){
    let email = document.getElementById("email").value.trim();
    let password = document.getElementById("password").value.trim();
    let pattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;

    if(!email.match(pattern)){
        alert("Enter valid email");
        return false;
    }

    if(password.length < 3){
        alert("Password must be at least 3 characters");
        return false;
    }

    return true;
}
</script>

</body>
</html>