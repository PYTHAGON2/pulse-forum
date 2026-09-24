<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$username = trim($_GET['username'] ?? '');
$currentUser = getCurrentUser();
if (empty($username) && $currentUser) {
    $username = $currentUser['username'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Pulse Community</title>
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
        <a href="index.php" class="btn btn-secondary btn-sm">← Home</a>
    </header>

    <div class="app-container" style="grid-template-columns: 1fr; max-width: 900px;">
        <main class="main-content">
            <div class="card" id="profile-card" style="text-align: center; padding: 2.5rem;">
                <p style="color: var(--text-muted);">Loading profile...</p>
            </div>

            <div class="section-title" style="margin-top: 1.5rem;">Recent Discussions Started</div>
            <div id="user-threads-container" style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- User threads -->
            </div>
        </main>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <script src="assets/js/app.js"></script>
    <script>
        const targetUsername = "<?php echo htmlspecialchars($username, ENT_QUOTES); ?>";

        async function loadProfile() {
            if (!targetUsername) {
                document.getElementById('profile-card').innerHTML = `<div style="color: var(--status-danger);">No user specified. Please sign in or select a user.</div>`;
                return;
            }
            try {
                const res = await fetch(`api/users.php?username=${encodeURIComponent(targetUsername)}`);
                const data = await res.json();
                if (!res.ok) {
                    document.getElementById('profile-card').innerHTML = `<div style="color: var(--status-danger);">${data.error || 'User not found.'}</div>`;
                    return;
                }
                const u = data.user;
                document.title = `${u.username}'s Profile - Pulse`;

                document.getElementById('profile-card').innerHTML = `
                    <img src="${u.avatar_url}" class="user-avatar" style="width: 96px; height: 96px; margin: 0 auto 1rem auto; box-shadow: var(--shadow-glow);">
                    <h1 style="font-size: 1.75rem; font-weight: 700;">${u.username}</h1>
                    <p style="color: var(--text-muted); max-width: 500px; margin: 0.5rem auto 1.5rem auto; font-size: 0.95rem;">${u.bio || 'No bio provided.'}</p>
                    
                    <div style="display: flex; justify-content: center; gap: 2rem; border-top: 1px solid var(--border-color); pt: 1.5rem; padding-top: 1.5rem;">
                        <div>
                            <div style="font-size: 1.25rem; font-weight: 700; color: var(--accent-primary);">⭐ ${u.reputation}</div>
                            <div style="font-size: 0.75rem; color: var(--text-dim); uppercase;">Reputation</div>
                        </div>
                        <div>
                            <div style="font-size: 1.25rem; font-weight: 700;">💬 ${u.post_count}</div>
                            <div style="font-size: 0.75rem; color: var(--text-dim); uppercase;">Posts</div>
                        </div>
                        <div>
                            <div style="font-size: 1.25rem; font-weight: 700;">📝 ${u.thread_count}</div>
                            <div style="font-size: 0.75rem; color: var(--text-dim); uppercase;">Topics</div>
                        </div>
                        <div>
                            <div style="font-size: 1.25rem; font-weight: 700;">📅 ${u.joined_date}</div>
                            <div style="font-size: 0.75rem; color: var(--text-dim); uppercase;">Joined</div>
                        </div>
                    </div>
                `;

                const threadsContainer = document.getElementById('user-threads-container');
                if (data.recent_threads.length === 0) {
                    threadsContainer.innerHTML = `<div class="card" style="color: var(--text-dim); text-align: center;">No topics created yet.</div>`;
                } else {
                    threadsContainer.innerHTML = data.recent_threads.map(t => `
                        <div class="card thread-card" onclick="window.location.href='thread.php?id=${t.id}'">
                            <div class="thread-main">
                                <span class="category-pill" style="background-color: ${t.category_color}; margin-bottom: 0.3rem;">${t.category_name}</span>
                                <h3 class="thread-title">${t.title}</h3>
                                <div class="thread-meta">
                                    <span>Created ${t.time_ago}</span>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (err) {
                console.error(err);
            }
        }

        document.addEventListener('DOMContentLoaded', loadProfile);
    </script>
</body>
</html>
