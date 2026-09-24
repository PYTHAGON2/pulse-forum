<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$threadId = (int)($_GET['id'] ?? 0);
if (!$threadId) {
    header('Location: index.php');
    exit;
}

$currentUser = getCurrentUser();
$csrfToken = getCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion - Pulse Community</title>
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
        <a href="index.php" class="btn btn-secondary btn-sm">← Back to Discussions</a>
    </header>

    <div class="app-container" style="grid-template-columns: 1fr 300px;">
        <main class="main-content">
            <!-- Thread Details Header -->
            <div class="card" id="thread-header-card">
                <div style="text-align: center; padding: 2rem;">Loading topic...</div>
            </div>

            <!-- Thread Replies Container -->
            <div style="font-weight: 600; font-size: 1rem; margin-top: 1rem;" id="replies-count-title">Replies</div>
            <div id="posts-container" style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Posts rendered dynamically -->
            </div>

            <!-- Reply Box -->
            <div class="card" style="margin-top: 1.5rem;">
                <h3 style="margin-bottom: 0.75rem; font-size: 1.05rem;">Leave a Reply</h3>
                <form id="reply-form" enctype="multipart/form-data">
                    <textarea id="reply-content" name="content" rows="4" placeholder="Write your reply... (**bold**, *italic*, `code` allowed)" required style="width: 100%; padding: 0.75rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: white; resize: vertical; margin-bottom: 0.5rem;"></textarea>
                    <div style="margin-bottom: 1rem;">
                        <input type="file" id="reply-attachment" name="attachment" style="font-size: 0.8rem; color: var(--text-muted);">
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">Post Reply</button>
                    </div>
                </form>

            </div>
        </main>

        <aside class="right-sidebar">
            <div class="card" id="thread-author-sidebar">
                <div class="section-title">Topic Author</div>
                <div id="author-info-box" style="text-align: center; padding: 1rem 0;">
                    <!-- Author info rendered dynamically -->
                </div>
            </div>
        </aside>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <script src="assets/js/app.js"></script>
    <script>
        const threadId = <?php echo $threadId; ?>;
        
        async function loadThreadDetail() {
            try {
                const res = await fetch(`api/threads.php?action=detail&id=${threadId}`);
                const data = await res.json();
                if (!res.ok) {
                    document.getElementById('thread-header-card').innerHTML = `<div style="color: var(--status-danger);">Thread not found.</div>`;
                    return;
                }
                const t = data.thread;
                document.title = `${t.title} - Pulse Community`;

                document.getElementById('thread-header-card').innerHTML = `
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <span class="category-pill" style="background-color: ${t.category_color}">${t.category_name}</span>
                        <span style="font-size: 0.8rem; color: var(--text-dim);">${t.time_ago}</span>
                    </div>
                    <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem;">${t.title}</h1>
                    <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">
                        <img src="${t.avatar_url}" class="user-avatar" style="width: 32px; height: 32px;">
                        <span>Started by <a href="profile.php?username=${encodeURIComponent(t.username)}"><strong>${t.username}</strong></a></span>
                        <span>•</span>
                        <span>👁️ ${t.views} views</span>
                    </div>
                `;

                document.getElementById('author-info-box').innerHTML = `
                    <img src="${t.avatar_url}" class="user-avatar" style="width: 64px; height: 64px; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 1.1rem; margin-bottom: 0.2rem;"><a href="profile.php?username=${encodeURIComponent(t.username)}">${t.username}</a></h3>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">Reputation: ⭐ ${t.reputation}</div>
                `;

                loadPosts();
            } catch (err) {
                console.error(err);
            }
        }

        async function loadPosts() {
            const container = document.getElementById('posts-container');
            try {
                const res = await fetch(`api/posts.php?thread_id=${threadId}`);
                const data = await res.json();
                
                document.getElementById('replies-count-title').textContent = `${data.pagination.total_items} Comments`;

                container.innerHTML = data.posts.map(post => `
                    <div class="card" id="post-${post.id}" style="display: flex; gap: 1rem; ${post.is_original_post ? 'border-left: 4px solid var(--accent-primary);' : ''}">
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                            <img src="${post.avatar_url}" class="user-avatar" style="width: 42px; height: 42px;">
                            <button class="upvote-btn ${post.user_has_voted ? 'voted' : ''}" onclick="toggleVote(${post.id})">
                                ▲
                                <span style="font-size: 0.8rem; font-weight: bold;">${post.upvotes}</span>
                            </button>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem;">
                                <div>
                                    <a href="profile.php?username=${encodeURIComponent(post.username)}" style="font-weight: 600; color: var(--text-main);">${post.username}</a>
                                    ${post.is_original_post ? '<span style="font-size: 0.7rem; background: rgba(99,102,241,0.2); color: var(--accent-primary); padding: 2px 6px; border-radius: 4px; margin-left: 0.4rem;">Author</span>' : ''}
                                </div>
                                <div style="color: var(--text-dim);">${post.time_ago}</div>
                            </div>
                            <div style="line-height: 1.6; color: #e2e8f0; font-size: 0.95rem;" id="post-body-${post.id}">${post.formatted_content}</div>
                            
                            ${post.is_owner ? `
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.5rem;">
                                    <button class="btn btn-secondary btn-sm" onclick="deletePost(${post.id})">Delete</button>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('');
            } catch (err) {
                container.innerHTML = `<div class="card" style="color: var(--status-danger);">Failed to load comments.</div>`;
            }
        }

        async function toggleVote(postId) {
            if (!App.currentUser) {
                App.openModal('login-modal');
                App.showToast('Please login to vote.', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'vote');
            formData.append('post_id', postId);
            formData.append('csrf_token', App.csrfToken);

            try {
                const res = await fetch('api/posts.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    loadPosts();
                } else {
                    App.showToast(data.error || 'Vote failed', 'error');
                }
            } catch (err) {
                App.showToast('Failed to connect.', 'error');
            }
        }

        async function deletePost(postId) {
            if (!confirm('Are you sure you want to delete this post?')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('post_id', postId);
            formData.append('csrf_token', App.csrfToken);

            try {
                const res = await fetch('api/posts.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    App.showToast(data.message, 'success');
                    if (data.thread_deleted) {
                        window.location.href = 'index.php';
                    } else {
                        loadPosts();
                    }
                }
            } catch (err) {
                App.showToast('Delete failed.', 'error');
            }
        }

        document.getElementById('reply-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!App.currentUser) {
                App.openModal('login-modal');
                App.showToast('Please login to reply.', 'error');
                return;
            }
            const content = document.getElementById('reply-content').value.trim();
            if (!content) return;

            const formData = new FormData();
            formData.append('action', 'reply');
            formData.append('thread_id', threadId);
            formData.append('content', content);
            formData.append('csrf_token', App.csrfToken);

            const fileInput = document.getElementById('reply-attachment');
            if (fileInput && fileInput.files.length > 0) {
                formData.append('attachment', fileInput.files[0]);
            }

            try {
                const res = await fetch('api/posts.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('reply-content').value = '';
                    if (fileInput) fileInput.value = '';
                    App.showToast(data.message, 'success');
                    loadPosts();
                } else {
                    App.showToast(data.error || 'Failed to post reply', 'error');
                }
            } catch (err) {
                App.showToast('Error posting reply.', 'error');
            }
        });


        document.addEventListener('DOMContentLoaded', loadThreadDetail);
    </script>
</body>
</html>
