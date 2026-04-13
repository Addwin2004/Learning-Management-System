<?php
session_start();
include("../config/db.php");

if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    if ($name && $email && $password && $role) {

        $stmt = $conn->prepare("INSERT INTO Users(name,email,password,role) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $name, $email, $password, $role);

        if ($stmt->execute()) {
            $msg = "User added successfully!";
        } else {
            $msg = "Error: Email might already exist";
        }

    } else {
        $msg = "All fields required";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - LMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_add_user.css">
</head>

<body>

<!-- Animated Background -->
<div class="background-animation">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
</div>

<div class="container">
    <!-- Header Section -->
    <header class="header-section">
        <div class="header-left">
            <div class="back-button">
                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
        <div class="header-right">
            <a href="../login.php?logout=1" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </header>

    <!-- Form Container -->
    <div class="form-container">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1>Add New User</h1>
            <p class="form-subtitle">Create a new user account for the LMS system</p>
        </div>

        <!-- Status Message -->
        <?php if ($msg): ?>
            <div class="status-message <?php echo strpos($msg, 'successfully') !== false ? 'success' : 'error'; ?>">
                <i class="fas <?php echo strpos($msg, 'successfully') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" class="add-user-form" onsubmit="return validateForm()">
            
            <!-- Name Field -->
            <div class="form-group">
                <label for="name">
                    <i class="fas fa-user"></i> Full Name
                </label>
                <div class="input-wrapper">
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        placeholder="Enter full name" 
                        required
                        class="form-input"
                    >
                    <div class="input-underline"></div>
                </div>
            </div>

            <!-- Email Field -->
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <div class="input-wrapper">
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter email address" 
                        required
                        class="form-input"
                    >
                    <div class="input-underline"></div>
                </div>
            </div>

            <!-- Password Field -->
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="input-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter password" 
                        required
                        class="form-input"
                    >
                    <div class="input-underline"></div>
                </div>
            </div>

            <!-- Role Field -->
            <div class="form-group">
                <label for="role">
                    <i class="fas fa-shield-alt"></i> User Role
                </label>
                <div class="select-wrapper">
                    <select id="role" name="role" required class="form-select">
                        <option value="">-- Select Role --</option>
                        <option value="student">Student</option>
                        <option value="instructor">Instructor</option>
                    </select>
                    <i class="fas fa-chevron-down select-icon"></i>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-submit">
                <i class="fas fa-plus-circle"></i>
                <span>Create User</span>
            </button>
        </form>

        <!-- Form Footer -->
        <div class="form-footer">
            <p>Need help? <a href="dashboard.php">Go back to dashboard</a></p>
        </div>
    </div>
</div>

<script>
function validateForm() {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value.trim();
    const role = document.getElementById('role').value;

    if (name.length < 2) {
        alert('Name must be at least 2 characters');
        return false;
    }

    const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;
    if (!email.match(emailPattern)) {
        alert('Please enter a valid email address');
        return false;
    }

    if (password.length < 3) {
        alert('Password must be at least 3 characters');
        return false;
    }

    if (!role) {
        alert('Please select a user role');
        return false;
    }

    return true;
}
</script>

</body>
</html>