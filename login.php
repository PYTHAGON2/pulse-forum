<?php
require_once __DIR__ . '/includes/auth.php';
$currentUser = getCurrentUser();
if ($currentUser) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Pulse Community</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css">

</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">

    <div class="modal-card" style="transform: none; max-width: 440px;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <a href="index.php" class="brand-logo" style="justify-content: center;">
                ⚡ Pulse Community
            </a>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">Sign in to your account</p>
        </div>
        
        <form id="standalone-login-form">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Username or Email</label>
                <input type="text" name="login" required style="width: 100%; padding: 0.65rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Password</label>
                <input type="password" name="password" required style="width: 100%; padding: 0.65rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
        </form>

        <p style="text-align: center; font-size: 0.85rem; color: var(--text-muted); margin-top: 1.5rem;">
            Don't have an account? <a href="register.php">Register now</a>
        </p>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <script src="assets/js/app.js"></script>
    <script>
        document.getElementById('standalone-login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'login');
            formData.append('csrf_token', App.csrfToken);

            try {
                const res = await fetch('api/auth.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (res.ok && data.success) {
                    App.showToast(data.message, 'success');
                    setTimeout(() => window.location.href = 'index.php', 1000);
                } else {
                    App.showToast(data.error || 'Login failed', 'error');
                }
            } catch (err) {
                App.showToast('Network error occurred.', 'error');
            }
        });
    </script>
</body>
</html>
