<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "user_db");
if ($conn->connect_error) {
    http_response_code(500);
    $error_message = "❌ Connection failed. Please try again later.";
}

// Create CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        $error_message = "❌ Invalid request. Please refresh and try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $errors = [];
        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = "❌ Username must be 3-50 characters.";
        }
        if (empty($password) || !preg_match("/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/", $password)) {
            $errors[] = "❌ Password must be at least 8 characters with uppercase, lowercase, and number.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "❌ Passwords do not match.";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "❌ Invalid email format.";
        }
        if (!empty($phone) && !preg_match("/^[0-9]{10,15}$/", $phone)) {
            $errors[] = "❌ Phone number must be 10-15 digits.";
        }

        if (empty($errors)) {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error_message = "❌ Username or Email already exists.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $ip = $_SERVER['REMOTE_ADDR'];
                $role = 'user';

                $stmt = $conn->prepare("INSERT INTO users (username, password, email, phone, ip_address, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $hashedPassword, $email, $phone, $ip, $role);

                if ($stmt->execute()) {
                    unset($_SESSION['csrf_token']);
                    $success_message = "✅ Registration successful! <a href='login.php'>Click here to login</a>";
                } else {
                    http_response_code(500);
                    $error_message = "❌ An error occurred. Please try again later.";
                }
                $stmt->close();
            }
            $check->close();
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก</title>
    <style>
        /* Black and White Space Theme with Security-Compliant Standards */
        body {
            margin: 0;
            padding: 20px;
            background: #000000;
            color: #ffffff;
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Moving Static Stars (Drifting and Twinkling) */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(2px 2px at 20px 30px, #ffffff, transparent),
                radial-gradient(2px 2px at 40px 70px, #ffffff, transparent),
                radial-gradient(1px 1px at 90px 40px, #ffffff, transparent),
                radial-gradient(1px 1px at 130px 80px, #ffffff, transparent),
                radial-gradient(2px 2px at 160px 30px, #ffffff, transparent);
            background-repeat: repeat;
            background-size: 200px 100px;
            animation: driftAndTwinkle 6s ease-in-out infinite alternate;
            z-index: -1;
        }

        @keyframes driftAndTwinkle {
            0% { 
                opacity: 0.5; 
                transform: translateX(0px) translateY(0px);
            }
            50% { 
                opacity: 1; 
                transform: translateX(5px) translateY(2px);
            }
            100% { 
                opacity: 0.7; 
                transform: translateX(-3px) translateY(1px);
            }
        }

        /* Straight Falling Stars (No Rotation, Pure Vertical Fall) */
        .falling-star {
            position: fixed;
            width: 2px;
            height: 2px;
            background: #ffffff;
            border-radius: 50%;
            box-shadow: 0 0 5px #ffffff;
            animation: straightFall linear infinite;
            z-index: -1;
            pointer-events: none;
        }

        .falling-star::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent, #ffffff);
            animation: verticalStreak 1s linear infinite;
        }

        @keyframes straightFall {
            0% {
                transform: translateY(-100vh);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh);
                opacity: 0;
            }
        }

        @keyframes verticalStreak {
            0% { height: 0; }
            100% { height: 30px; }
        }

        /* Pause animations for reduced motion preference (Accessibility) */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0s !important;
            }
        }

        /* Form Styles */
        h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 2em;
        }

        form {
            max-width: 400px;
            margin: 0 auto;
            background: rgba(0, 0, 0, 0.8);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
            border: 1px solid #ffffff;
        }

        label {
            display: block;
            margin-top: 10px;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ffffff;
            border-radius: 4px;
            background: #000000;
            color: #ffffff;
            box-sizing: border-box;
            font-size: 16px;
        }

        input:focus {
            outline: 2px solid #ffffff;
        }

        input::placeholder {
            color: #cccccc;
        }

        input:invalid {
            border-color: #ff0000;
            box-shadow: 0 0 5px #ff0000;
        }

        .error {
            color: #ff0000;
            font-size: 0.8em;
            margin-top: 5px;
            display: none;
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: #ffffff;
            color: #000000;
            border: none;
            border-radius: 4px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 20px;
        }

        button[type="submit"]:hover {
            background: #cccccc;
        }

        p {
            text-align: center;
            margin-top: 20px;
        }

        a {
            color: #ffffff;
            text-decoration: underline;
        }

        a:hover {
            color: #cccccc;
        }

        /* Responsive */
        @media (max-width: 600px) {
            form {
                margin: 10px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <h2>สมัครสมาชิก</h2>
    <?php if (isset($error_message)): ?>
        <div style="color: #ff0000; text-align: center; margin-bottom: 10px;"><?php echo $error_message; ?></div>
    <?php endif; ?>
    <?php if (isset($success_message)): ?>
        <div style="color: #2e7d32; text-align: center; margin-bottom: 10px;"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <label for="username">ชื่อผู้ใช้:</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required minlength="3" maxlength="50">
        <div class="error" id="username-error">Username must be 3-50 characters.</div>

        <label for="email">อีเมล:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
        <div class="error" id="email-error">Invalid email format.</div>

        <label for="phone">เบอร์โทร (ไม่บังคับ):</label>
        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" pattern="[0-9]{10,15}">
        <div class="error" id="phone-error">Invalid phone number.</div>

        <label for="password">รหัสผ่าน:</label>
        <input type="password" id="password" name="password" required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}">
        <small>Password must be at least 8 characters with uppercase, lowercase, and number.</small>
        <div class="error" id="password-error">Password does not meet requirements.</div>

        <label for="confirm_password">ยืนยันรหัสผ่าน:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <div class="error" id="confirm-password-error">Passwords do not match.</div>

        <button type="submit">สมัครสมาชิก</button>
    </form>

    <p><a href="login.php">มีบัญชีแล้ว? เข้าสู่ระบบ</a></p>

    <script>
        // Generate multiple random falling stars
        function createFallingStar() {
            const star = document.createElement('div');
            star.classList.add('falling-star');
            
            const left = Math.random() * 100 + '%';
            const size = Math.random() * 2 + 1 + 'px';
            const duration = Math.random() * 5 + 4 + 's';
            const delay = Math.random() * 5 + 's';
            
            star.style.left = left;
            star.style.width = size;
            star.style.height = size;
            star.style.animationDuration = duration;
            star.style.animationDelay = delay;
            
            document.body.appendChild(star);
            
            setTimeout(() => {
                star.remove();
            }, parseFloat(duration) * 1000 + 1000);
        }

        for (let i = 0; i < 20; i++) {
            createFallingStar();
        }
        setInterval(createFallingStar, 500);

        // Enhanced client-side validation with error displays
        const form = document.querySelector('form');
        form.addEventListener('submit', function(event) {
            let isValid = true;
            const errors = document.querySelectorAll('.error');
            errors.forEach(err => err.style.display = 'none');

            const username = document.getElementById('username').value;
            if (username.length < 3 || username.length > 50) {
                document.getElementById('username-error').style.display = 'block';
                isValid = false;
            }

            const email = document.getElementById('email').value;
            if (!/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i.test(email)) {
                document.getElementById('email-error').style.display = 'block';
                isValid = false;
            }

            const phone = document.getElementById('phone').value;
            if (phone && !/^[0-9]{10,15}$/.test(phone)) {
                document.getElementById('phone-error').style.display = 'block';
                isValid = false;
            }

            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            if (!/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/.test(password)) {
                document.getElementById('password-error').style.display = 'block';
                isValid = false;
            }
            if (password !== confirmPassword) {
                document.getElementById('confirm-password-error').style.display = 'block';
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>