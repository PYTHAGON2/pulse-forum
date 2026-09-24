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
    <title>Pulse Forum & Chat - Modern Community Platform</title>
    <meta name="description" content="A lightweight, fast, modern forum and real-time chat community platform.">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css">

</head>
<body>

    <!-- Header Navigation Shell -->
    <header class="app-header">
        <a href="index.php" class="brand-logo">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
            Pulse Community
        </a>

        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <input type="text" id="search-input" placeholder="Search discussions, posts, topics...">
        </div>

        <div class="nav-actions">
            <div id="auth-actions" style="display: flex; gap: 0.5rem;">
                <button class="btn btn-secondary btn-sm" data-modal="login-modal">Sign In</button>
                <button class="btn btn-primary btn-sm" data-modal="register-modal">Register</button>
            </div>

            <div id="user-menu" style="display: none; align-items: center; gap: 0.75rem;">
                <button class="btn btn-primary btn-sm auth-required" data-modal="new-thread-modal">+ New Topic</button>
                <a href="profile.php" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                    <img id="user-avatar-display" src="" class="user-avatar" style="width: 34px; height: 34px;" alt="Avatar">
                    <span id="user-name-display" style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);"></span>
                </a>
                <a href="#" id="logout-btn" style="font-size: 0.8rem; color: var(--text-dim);">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="app-container">
        
        <!-- Left Sidebar -->
        <aside class="sidebar">
            <div class="section-title">Navigation</div>
            <ul class="nav-menu">
                <li class="nav-item active">
                    <a href="index.php" onclick="App.filterCategory(''); return false;">
                        💬 All Discussions
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" onclick="App.currentSort='popular'; App.loadThreads(); return false;">
                        🔥 Popular Topics
                    </a>
                </li>
                <li class="nav-item">
                    <a href="chat.php">
                        ⚡ Live Chatroom
                    </a>
                </li>
            </ul>

            <div class="section-title" style="margin-top: 1rem;">Categories</div>
            <ul class="nav-menu" id="categories-list">
                <!-- Loaded dynamically -->
            </ul>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Filter Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); padding: 0.75rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="font-weight: 600; font-size: 0.95rem;" id="current-view-title">Latest Discussions</div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-secondary btn-sm" onclick="App.currentSort='latest'; App.loadThreads();">Latest</button>
                    <button class="btn btn-secondary btn-sm" onclick="App.currentSort='popular'; App.loadThreads();">Top</button>
                    <button class="btn btn-secondary btn-sm" onclick="App.currentSort='unanswered'; App.loadThreads();">Unanswered</button>
                </div>
            </div>

            <!-- Threads List -->
            <div id="threads-container">
                <!-- Dynamically rendered threads -->
            </div>

            <!-- Pagination Container -->
            <div id="pagination-container"></div>
        </main>

        <!-- Right Sidebar -->
        <aside class="right-sidebar">
            <!-- Live Chat Box -->
            <div class="chat-widget">
                <div class="chat-header">
                    <span>⚡ Live Community Chat</span>
                    <a href="chat.php" style="font-size: 0.75rem; font-weight: normal; color: var(--accent-secondary);">Expand ↗</a>
                </div>
                <div class="chat-messages" id="chat-messages">
                    <!-- Dynamic chat messages -->
                </div>
                <form class="chat-input-area" id="chat-form" enctype="multipart/form-data">
                    <input type="text" id="chat-input" placeholder="Type a message..." autocomplete="off">
                    <label for="chat-attachment" style="cursor: pointer; font-size: 1.1rem; display: flex; align-items: center;" title="Attach Image/File">📎</label>
                    <input type="file" id="chat-attachment" name="attachment" style="display: none;">
                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.4rem 0.8rem;">Send</button>
                </form>

            </div>

            <!-- Active Online Users -->
            <div class="card" style="padding: 1rem;">
                <div class="section-title">Active Members</div>
                <div id="online-users-list">
                    <!-- Online users list -->
                </div>
            </div>
        </aside>
    </div>

    <!-- Modals -->

    <!-- Login Modal -->
    <div class="modal-overlay" id="login-modal">
        <div class="modal-card">
            <h2 style="margin-bottom: 0.5rem;">Welcome Back</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Sign in to start conversations and reply to topics.</p>
            <form id="login-form">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Username or Email</label>
                    <input type="text" name="login" required style="width: 100%; padding: 0.6rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Password</label>
                    <input type="password" name="password" required style="width: 100%; padding: 0.6rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Sign In</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Register Modal -->
    <div class="modal-overlay" id="register-modal">
        <div class="modal-card">
            <h2 style="margin-bottom: 0.5rem;">Create Account</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Join Pulse community today.</p>
            <form id="register-form">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Username</label>
                    <input type="text" name="username" required style="width: 100%; padding: 0.6rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Email Address</label>
                    <input type="email" name="email" required style="width: 100%; padding: 0.6rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Password</label>
                    <input type="password" name="password" required style="width: 100%; padding: 0.6rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- New Thread Modal -->
    <div class="modal-overlay" id="new-thread-modal">
        <div class="modal-card" style="max-width: 650px;">
            <h2 style="margin-bottom: 0.5rem;">Start a New Topic</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Share ideas or ask questions to the community.</p>
            <form id="new-thread-form">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Category</label>
                    <select id="thread-category-select" name="category_id" style="width: 100%; padding: 0.6rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Topic Title</label>
                    <input type="text" name="title" placeholder="What is on your mind?" required style="width: 100%; padding: 0.6rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Attach Image or File (Optional, max 5MB)</label>
                    <input type="file" name="attachment" style="width: 100%; padding: 0.4rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white;">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 0.3rem;">Content (**bold**, *italic*, `code` formatting supported)</label>
                    <textarea name="content" rows="6" placeholder="Provide details, questions, or context..." required style="width: 100%; padding: 0.6rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white; resize: vertical;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Publish Topic</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toast-container"></div>

    <script src="assets/js/app.js"></script>
</body>
</html>
