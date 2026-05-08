<?php
session_start();
include('connect.php');

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Fetch user by ID number
    $sql = "SELECT * FROM users WHERE id_number = '$username'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        
        // Use password_verify if you hashed the passwords, 
        // otherwise keep your original string comparison logic:
        if ($password === $row['password']) { 
            
            // Set Session Variables
            $_SESSION['account_id'] = $row['id']; 
            $_SESSION['account_number'] = $row['id_number'];
            $_SESSION['name'] = $row['name']; // Store name for the Prof Dashboard
            $_SESSION['role'] = $row['role']; 
            
            session_regenerate_id(true);

            // --- ROLE-BASED REDIRECTION ---
            if ($row['role'] === 'admin') {
                header("Location: admin/index.php");
            } elseif ($row['role'] === 'professor') {
                header("Location: professor/index.php");
            } else {
                header("Location: user/index.php"); // Default for students
            }
            exit();
        } else {
            $error = "Invalid Password";
        }
    } else {
        $error = "Invalid ID Number";
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | PUP-STC </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: url('img/bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            color: white;
            padding: 40px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            border-radius: 10px;
        }

        .form-control::placeholder { color: rgba(255, 255, 255, 0.7); }

        .btn-gold {
            background-color: #FFD700;
            color: #800000;
            font-weight: bold;
            border-radius: 10px;
            transition: 0.3s;
        }

        .btn-gold:hover {
            background-color: #e6c200;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">

                <div class="glass-card">
                    <div class="text-center mb-4">
                        <img src="img/PUPLogo.png" alt="PUP Logo"
                            style="width: 70px; filter: drop-shadow(0 0 10px rgba(255,255,255,0.3));">
                        <h3 class="mt-3 fw-bold">PUP-STC</h3>
                        <p class="small opacity-75">Classroom Management System</p>
                    </div>

                    <?php if($error): ?>
                        <div class="alert alert-danger py-2 small text-center" 
                             style="background: rgba(255,0,0,0.2); border: none; color: white;">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="username" placeholder="Account Number" required>
                        </div>

                        <div class="mb-4">
                            <input type="password" class="form-control" name="password" placeholder="Password" required>
                        </div>

                        <button type="submit" class="btn btn-gold w-100 py-2 mb-3">SIGN IN</button>

                        <div class="text-center">
                            <a href="admin_login.php" class="text-white-50 small text-decoration-none">
                                <i class="fas fa-user-shield me-1"></i> Admin Login
                            </a>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>