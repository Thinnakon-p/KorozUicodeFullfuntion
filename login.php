<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "⛔ CSRF token ไม่ถูกต้อง";
        header("Location: login.php");
        exit;
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        $_SESSION['error'] = "⛔ เซสชันหมดอายุ กรุณาล็อกอินใหม่";
        header("Location: login.php");
        exit;
    }
    $_SESSION['last_activity'] = time();

    $conn = new mysqli('localhost', 'root', '', 'user_db');
    if ($conn->connect_error) {
        $_SESSION['error'] = "❌ การเชื่อมต่อฐานข้อมูลล้มเหลว";
        error_log("Database connection failed: " . $conn->connect_error);
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($password)) {
            $_SESSION['error'] = "❌ กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
        } else {
            $stmt = $conn->prepare("SELECT id, password, role, display_name, email, phone, avatar, name_color FROM users WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows === 1) {
                        $stmt->bind_result($id, $hashedPassword, $role, $displayName, $email, $phone, $avatar, $nameColor);
                        $stmt->fetch();

                        if (password_verify($password, $hashedPassword)) {
                            session_regenerate_id(true);
                            $_SESSION['loggedin'] = true;
                            $_SESSION['user_id'] = $id;
                            $_SESSION['username'] = $username;
                            $_SESSION['role'] = $role;
                            $_SESSION['display_name'] = $displayName;
                            $_SESSION['email'] = $email;
                            $_SESSION['phone'] = $phone;
                            $_SESSION['avatar'] = $avatar ?? 'korox.webp';
                            $_SESSION['name_color'] = $nameColor ?? 'text-white';
                            $_SESSION['last_activity'] = time();

                            if ($role === 'admin') {
                                $_SESSION['admin_access'] = true;
                            }

                            $log = date('Y-m-d H:i:s') . " | Login by ID $id | IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
                            file_put_contents('logs/login_log.txt', $log, FILE_APPEND);

                            if ($role === 'admin') {
                                header("Location: dashboard.php");
                            } else {
                                header("Location: index.php");
                            }
                            exit;
                        } else {
                            $_SESSION['error'] = "❌ รหัสผ่านไม่ถูกต้อง";
                        }
                    } else {
                        $_SESSION['error'] = "❌ ไม่พบผู้ใช้";
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "❌ การรันคำสั่ง SQL ล้มเหลว";
                    error_log("Query execution failed: " . $conn->error);
                }
            } else {
                $_SESSION['error'] = "❌ การเตรียมคำสั่ง SQL ล้มเหลว";
                error_log("Query preparation failed: " . $conn->error);
            }
            $conn->close();
        }
    }
    header("Location: login.php");
    exit;
}

$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Red Glow</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
   <style> /* Space theme — replace existing styles with this */ :root{ --card-w: 400px; --accent1: #ff4da6; --accent2: #6b8cff; --accent3: #00ffe1; --glass-bg: rgba(255,255,255,0.03); } /* Page & animated background */ *{box-sizing:border-box} html,body{height:100%} body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto, "Helvetica Neue", Arial; color:#fff; background:#030217; overflow:hidden; } /* Nebula & subtle gradients behind everything */ body::before{ content:""; position:fixed; inset:-10% -10%; z-index:-4; background: radial-gradient(ellipse at 10% 20%, rgba(255,80,200,0.08) 0%, transparent 20%), radial-gradient(ellipse at 80% 70%, rgba(80,150,255,0.06) 0%, transparent 18%), radial-gradient(circle at 50% 30%, rgba(140,60,255,0.05) 0%, transparent 22%), linear-gradient(180deg, rgba(5,8,25,1) 0%, rgba(2,6,23,1) 100%); filter: blur(40px); } /* Stars layer (simple twinkling) */ body::after{ content:""; position:fixed; inset:0; z-index:-3; background-image: radial-gradient(1.6px 1.6px at 10% 20%, rgba(255,255,255,0.95) 50%, transparent 51%), radial-gradient(1.2px 1.2px at 22% 40%, rgba(255,255,255,0.9) 50%, transparent 51%), radial-gradient(1.4px 1.4px at 35% 10%, rgba(255,255,255,0.85) 50%, transparent 51%), radial-gradient(1px 1px at 55% 70%, rgba(255,255,255,0.9) 50%, transparent 51%), radial-gradient(1.6px 1.6px at 76% 28%, rgba(255,255,255,0.95) 50%, transparent 51%), radial-gradient(1.2px 1.2px at 85% 80%, rgba(255,255,255,0.8) 50%, transparent 51%); background-repeat:repeat; background-size: 400px 400px; opacity:0.95; animation: twinkle 6s linear infinite; pointer-events:none; } /* Center card (glass + neon rim + planet) */ .login-box{ width:var(--card-w); max-width:calc(100% - 40px); margin:5vh auto; position:relative; padding:40px; border-radius:16px; background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.02)); border: 1px solid rgba(255,255,255,0.06); box-shadow: 0 10px 30px rgba(2,6,23,0.7), 0 0 80px rgba(107,140,255,0.04), inset 0 1px 0 rgba(255,255,255,0.02); backdrop-filter: blur(8px) saturate(120%); text-align:center; overflow:hidden; } /* Moving neon rim */ .login-box::before{ content:""; position:absolute; inset:-2px; z-index:-1; border-radius:18px; background: linear-gradient(90deg, var(--accent1), var(--accent2), var(--accent3), var(--accent1)); filter: blur(14px); opacity:0.85; transform: translateZ(0); animation: rim 6s linear infinite; } /* Decorative planet */ .login-box::after{ content:""; position:absolute; width:130px; height:130px; right:-50px; top:-50px; border-radius:50%; background: radial-gradient(circle at 30% 25%, #ffd9a3 0%, #ff8aa3 30%, #6b5cff 65%); box-shadow: 0 25px 60px rgba(107,92,255,0.12), inset -10px -6px 18px rgba(0,0,0,0.25); transform: rotate(-15deg); opacity:0.95; } /* Heading */ .login-box h2{ margin:0 0 18px; color:var(--accent1); font-weight:600; font-size:22px; text-shadow: 0 2px 20px rgba(255,77,166,0.12); letter-spacing:0.6px; } /* Form elements */ .login-box form{margin-top:6px} .login-box input{ width:100%; padding:12px 14px; margin:10px 0; border-radius:8px; border:1px solid rgba(255,255,255,0.06); background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); color:#fff; outline:none; font-size:15px; transition: box-shadow .18s, transform .12s; caret-color:var(--accent3); -webkit-appearance:none; } /* glowing underline on focus */ .login-box input:focus{ box-shadow: 0 6px 24px rgba(107,140,255,0.06), 0 0 10px rgba(0,255,225,0.03); transform: translateY(-2px); border-color: rgba(107,140,255,0.22); } /* Placeholder color */ .login-box input::placeholder{ color:rgba(255,255,255,0.55) } /* Button — neon gradient */ .login-box button{ width:100%; padding:12px 14px; margin-top:8px; border-radius:10px; border:none; background: linear-gradient(90deg, var(--accent1) 0%, var(--accent2) 50%, var(--accent3) 100%); color:#071025; font-weight:600; font-size:16px; cursor:pointer; box-shadow: 0 8px 30px rgba(107,140,255,0.18), 0 0 30px rgba(255,77,166,0.08); transition: transform .15s ease, box-shadow .15s; } /* Hover & active states */ .login-box button:hover{ transform: translateY(-3px); box-shadow: 0 14px 40px rgba(107,140,255,0.22) } .login-box button:active{ transform: translateY(-1px) } /* Error message */ .error-message{ background: linear-gradient(90deg,#ff6b6b,#ff4d4d); color:#0b0610; padding:10px; border-radius:8px; margin-bottom:16px; font-weight:600; box-shadow: 0 6px 18px rgba(255,77,77,0.12); } /* Register link */ .register-link{ margin-top:16px; color:rgba(255,255,255,0.66); font-size:14px; } .register-link a{ color:var(--accent2); text-decoration:none; font-weight:600; } .register-link a:hover{ text-decoration:underline } /* small screens */ @media (max-width:480px){ :root{ --card-w: 100% } .login-box{ margin:8vh 16px; padding:28px; border-radius:12px; } .login-box::after{ display:none } /* hide planet on tiny screens */ .login-box h2{ font-size:18px } } /* Animations */ @keyframes rim{ 0%{filter: blur(12px); opacity:0.9; transform: rotate(0deg) translateZ(0)} 50%{filter: blur(18px); opacity:0.7; transform: rotate(8deg) translateZ(0)} 100%{filter: blur(12px); opacity:0.9; transform: rotate(0deg) translateZ(0)} } @keyframes twinkle{ 0%{background-position:0 0; opacity:0.95} 50%{background-position:200px 120px; opacity:0.6} 100%{background-position:0 0; opacity:0.95} } </style>
</head>
<body>
    <div class="login-box">
        <h2>Login</h2>
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="text" name="username" placeholder="Username" required />
            <input type="password" name="password" placeholder="Password" required />
            <button type="submit">Login</button>
        </form>
        <div class="register-link">
            Don't have an account? <a href="register.php">Register</a>
        </div>
    </div>
</body>
</html>