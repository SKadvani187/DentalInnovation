<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in → redirect
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password.';
    } elseif (!loginAdmin($email, $password)) {
        $error = 'Invalid credentials. Please try again.';
    } else {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — DentInno CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>
        .login-grid {
            display: grid;
            grid-template-columns: 1fr 460px;
            min-height: 100vh;
        }
        .login-left {
            background: var(--bg-surface);
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 60px;
            border-right: 1px solid var(--border-color);
            position: relative; overflow: hidden;
        }
        .login-left::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at center, rgba(184,134,11,0.08) 0%, transparent 70%);
        }
        .login-brand { position: relative; z-index: 1; text-align: center; }
        .login-brand img { width: 160px; filter: drop-shadow(0 0 40px rgba(184,134,11,0.4)); margin-bottom: 24px; }
        .login-brand h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem; font-weight: 700;
            background: linear-gradient(135deg, #B8860B 0%, #D4A017 50%, #8B6508 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .login-brand p { color: var(--text-secondary); margin-top: 8px; font-size: 1rem; }
        .features { position: relative; z-index: 1; margin-top: 48px; display: flex; flex-direction: column; gap: 16px; }
        .feature-item { display: flex; align-items: center; gap: 14px; }
        .feature-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(184,134,11,0.1);
            border: 1px solid rgba(184,134,11,0.2);
            display: grid; place-items: center;
            color: var(--gold-primary); font-size: 0.9rem; flex-shrink: 0;
        }
        .feature-text { font-size: 0.88rem; color: var(--text-secondary); }
        .feature-text strong { color: var(--text-primary); display: block; font-size: 0.92rem; }

        .login-right {
            display: flex; align-items: center; justify-content: center;
            padding: 48px 56px;
            background: var(--bg-base);
        }
        .login-form-wrap { width: 100%; }
        .login-form-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem; font-weight: 700; margin-bottom: 6px;
            background: linear-gradient(135deg, #B8860B 0%, #D4A017 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .login-form-sub { color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 32px; }
        .input-icon-wrapper { position: relative; }
        .input-icon-wrapper i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 0.9rem; pointer-events: none;
        }
        .input-icon-wrapper .form-control { padding-left: 40px; }
        .show-pass {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); cursor: pointer; font-size: 0.9rem;
        }
        .remember-row {
            display: flex; justify-content: space-between; align-items: center;
            margin: 16px 0 24px;
        }
        .checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; color: var(--text-secondary); }
        .checkbox-label input[type=checkbox] { accent-color: var(--gold-primary); width: 16px; height: 16px; }
        .forgot-link { color: var(--gold-primary); font-size: 0.83rem; font-weight: 500; }
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #B8860B 0%, #D4A017 50%, #8B6508 100%);
            color: var(--bg-base);
            border: none; border-radius: 10px;
            padding: 13px; font-size: 0.95rem; font-weight: 700;
            cursor: pointer; transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(184,134,11,0.3);
            letter-spacing: 0.5px;
        }
        .btn-login:hover { box-shadow: 0 8px 30px rgba(184,134,11,0.5); transform: translateY(-1px); }
        .login-hint {
            margin-top: 24px; padding: 14px 16px;
            background: rgba(184,134,11,0.06);
            border: 1px solid rgba(184,134,11,0.15);
            border-radius: 10px;
            font-size: 0.8rem; color: var(--text-secondary);
        }
        .login-hint strong { color: var(--gold-primary); }
        @media (max-width: 900px) {
            .login-grid { grid-template-columns: 1fr; }
            .login-left  { display: none; }
            .login-right { padding: 32px 28px; }
        }
    </style>
</head>
<body>
<div class="login-grid">
    <!-- Left Branding -->
    <div class="login-left">
        <div class="login-brand">
            <img src="<?= APP_URL ?>/assets/images/logo.png" alt="DentInno">
            <h1>DentInno</h1>
            <p>Where Innovation Meets Dentistry</p>
        </div>
        <div class="features">
            <div class="feature-item">
                <div class="feature-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div class="feature-text"><strong>Product Management</strong>Full catalog with stock tracking</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                <div class="feature-text"><strong>Order Management</strong>Track every order in real-time</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fa-solid fa-user-group"></i></div>
                <div class="feature-text"><strong>Customer CRM</strong>Complete customer history & insights</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div class="feature-text"><strong>Analytics & Reports</strong>Revenue charts and business insights</div>
            </div>
        </div>
    </div>

    <!-- Right Form -->
    <div class="login-right">
        <div class="login-form-wrap">
            <h2 class="login-form-title">Welcome Back</h2>
            <p class="login-form-sub">Sign in to your DentInno CRM dashboard</p>

            <?php if ($error): ?>
            <div class="login-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" class="form-control"
                               placeholder="admin@dentinno.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" id="passwordInput"
                               class="form-control" placeholder="Enter your password" required>
                        <i class="fa-solid fa-eye show-pass" id="togglePass"></i>
                    </div>
                </div>

                <div class="remember-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In to Dashboard
                </button>
            </form>

            <div class="login-hint">
                <strong>Demo Credentials:</strong><br>
                Email: <strong>admin@dentinno.com</strong><br>
                Password: <strong>password</strong>
                <br><small style="color:var(--text-muted);margin-top:6px;display:block;">⚠️ Default password is "password" — change it after setup</small>
            </div>
        </div>
    </div>
</div>

<script>
const togglePass = document.getElementById('togglePass');
const passwordInput = document.getElementById('passwordInput');
if (togglePass) {
    togglePass.addEventListener('click', () => {
        const isText = passwordInput.type === 'text';
        passwordInput.type = isText ? 'password' : 'text';
        togglePass.className = `fa-solid fa-${isText ? 'eye' : 'eye-slash'} show-pass`;
    });
}
</script>
</body>
</html>
