<?php
// login.php - User login
// Purpose: Authenticate and redirect to dashboard
// Version: 3.26.0

require_once __DIR__ . '/includes/functions.php';

session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard_" . $_SESSION['role'] . ".php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    if (loginUser($username, $password)) {
        $userStmt = $db->prepare("SELECT id, role, name FROM users WHERE username = :username");
        $userStmt->execute([':username' => $username]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user_id'] = $user['id'];
        // For backward compatibility the UI expects 'parent' or 'child' dashboards.
        // We'll set a generic session role for UI and a detailed role_type for permissions.
        $_SESSION['role'] = ($user['role'] === 'child') ? 'child' : 'parent';
        $_SESSION['role_type'] = $user['role']; // 'main_parent', 'family_member', 'caregiver', or 'child'
        $_SESSION['username'] = $username;
        // Normalize display name consistently
        $_SESSION['name'] = getDisplayName($user['id']);
        error_log("Login successful for user_id=" . $user['id'] . ", role_type=" . $user['role']);
        header("Location: dashboard_" . $_SESSION['role'] . ".php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Family Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css?v=3.28.0">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-bg);
            font-family: var(--font-base);
            padding: var(--mobile-pad);
            box-sizing: border-box;
        }

        .login-card {
            background: var(--color-white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-hero);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }

        .login-card__header {
            background: var(--gradient-primary);
            padding: 28px 24px 24px;
            text-align: center;
        }

        .login-card__logo {
            font-size: var(--text-hero);
            font-weight: 700;
            color: var(--color-white);
            letter-spacing: -0.5px;
            margin: 0 0 4px;
        }

        .login-card__tagline {
            font-size: var(--text-base);
            color: rgba(255, 255, 255, 0.80);
            margin: 0;
        }

        .login-card__body {
            padding: 28px 24px 24px;
        }

        .login-error {
            background: var(--color-danger-light);
            color: var(--color-danger-dark);
            font-size: var(--text-base);
            font-weight: 500;
            border-radius: var(--radius-md);
            padding: 10px 14px;
            margin-bottom: 20px;
        }

        .login-field {
            margin-bottom: 16px;
        }

        .login-field label {
            display: block;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--color-text-dark);
            margin-bottom: 6px;
        }

        .login-field input {
            width: 100%;
            box-sizing: border-box;
            background: var(--color-slate);
            border: none;
            border-radius: var(--radius-md);
            padding: 12px 14px;
            font-size: var(--text-md);
            font-family: var(--font-base);
            color: var(--color-text-dark);
            outline: none;
            transition: box-shadow 0.15s;
        }

        .login-field input:focus {
            box-shadow: 0 0 0 2px var(--color-primary-mid);
        }

        .login-field input::placeholder {
            color: var(--color-text-sec);
        }

        .login-submit {
            width: 100%;
            height: 48px;
            background: var(--gradient-primary);
            color: var(--color-white);
            font-size: var(--text-xl);
            font-weight: 600;
            font-family: var(--font-base);
            border: none;
            border-radius: var(--radius-lg);
            cursor: pointer;
            margin-top: 8px;
            box-shadow: var(--shadow-fab);
            transition: opacity 0.15s;
        }

        .login-submit:hover { opacity: 0.92; }
        .login-submit:active { opacity: 0.85; }

        .login-card__footer {
            text-align: center;
            padding: 0 24px 24px;
            font-size: var(--text-base);
            color: var(--color-text-sec);
        }

        .login-card__footer a {
            color: var(--color-primary);
            font-weight: 600;
            text-decoration: none;
        }

        .login-card__footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-card__header">
            <h1 class="login-card__logo">Family Dashboard</h1>
            <p class="login-card__tagline">Tasks, goals &amp; rewards for the whole family</p>
        </div>

        <div class="login-card__body">
            <?php if (isset($error)): ?>
                <div class="login-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="login-field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" autocomplete="username" required>
                </div>
                <div class="login-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="login-submit">Sign In</button>
            </form>
        </div>

        <div class="login-card__footer">
            New to Family Dashboard? <a href="register.php">Create an account</a>
        </div>
    </div>
</body>
</html>





