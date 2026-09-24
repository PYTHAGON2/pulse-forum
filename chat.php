<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$currentUser = getCurrentUser();
$csrfToken = getCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chatroom - Pulse Community</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css">

</head>
<body>

    <header class="app-header">
        <a href="index.php" class="brand-logo">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
            Pulse Community
        </a>
        <a href="index.php" class="btn btn-secondary btn-sm">← Back to Forum</a>
    </header>

    <div class="app-container" style="grid-template-columns: 1fr 280px; height: calc(100vh - 95px);">
        <main class="main-content" style="height: 100%;">
            <div class="chat-widget" style="height: 100%;">
                <div class="chat-header">
                    <span style="font-size: 1.1rem;">⚡ Dedicated Live Chatroom</span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Shared-hosting optimized polling</span>
                </div>
                <div class="chat-messages" id="chat-messages" style="padding: 1.25rem;">
                    <!-- Messages dynamically rendered -->
                </div>
                <form class="chat-input-area" id="chat-form" style="padding: 1rem;" enctype="multipart/form-data">
                    <input type="text" id="chat-input" placeholder="Type a message to the community..." autocomplete="off">
                    <label for="chat-attachment" style="cursor: pointer; font-size: 1.2rem; display: flex; align-items: center;" title="Attach Image/File">📎</label>
                    <input type="file" id="chat-attachment" name="attachment" style="display: none;">
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>

            </div>
        </main>

        <aside class="right-sidebar">
            <div class="card" style="height: 100%;">
                <div class="section-title">Online Members</div>
                <div id="online-users-list">
                    <!-- Online members -->
                </div>
            </div>
        </aside>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <script src="assets/js/app.js"></script>
</body>
</html>
