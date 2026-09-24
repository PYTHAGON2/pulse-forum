/**
 * Pulse Forum & Chat - Frontend Application Controller
 * Handles Auth, Thread Loading, Realtime Polling Chat, Modal Dialogs, Toast Notifications
 */

const App = {
    currentUser: null,
    csrfToken: '',
    currentCategory: '',
    currentSort: 'latest',
    searchQuery: '',
    currentPage: 1,
    chatPollTimer: null,
    lastChatId: 0,

    init() {
        this.checkAuthStatus();
        this.setupEventListeners();
        this.loadThreads();
        this.initChat();
    },

    async checkAuthStatus() {
        try {
            const res = await fetch('api/auth.php?action=me');
            const data = await res.json();
            if (data.csrf_token) {
                this.csrfToken = data.csrf_token;
            }
            if (data.logged_in) {
                this.currentUser = data.user;
                this.updateAuthUI(true);
            } else {
                this.currentUser = null;
                this.updateAuthUI(false);
            }
        } catch (err) {
            console.error('Failed to check auth status', err);
        }
    },


    updateAuthUI(isLoggedIn) {
        const authActions = document.getElementById('auth-actions');
        const userMenu = document.getElementById('user-menu');

        if (isLoggedIn && this.currentUser) {
            authActions.style.display = 'none';
            userMenu.style.display = 'flex';
            document.getElementById('user-name-display').textContent = this.currentUser.username;
            document.getElementById('user-avatar-display').src = this.currentUser.avatar_url;
            document.querySelectorAll('.auth-required').forEach(el => el.classList.remove('hidden'));
        } else {
            authActions.style.display = 'flex';
            userMenu.style.display = 'none';
            document.querySelectorAll('.auth-required').forEach(el => el.classList.add('hidden'));
        }
    },

    setupEventListeners() {
        // Search Input Debounced
        const searchInput = document.getElementById('search-input');
        let searchTimeout = null;
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchQuery = e.target.value;
                    this.currentPage = 1;
                    this.loadThreads();
                }, 350);
            });
        }

        // Modal Triggers
        document.querySelectorAll('[data-modal]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const targetModal = e.currentTarget.getAttribute('data-modal');
                this.openModal(targetModal);
            });
        });

        // Modal Closes
        document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target === el) {
                    this.closeModals();
                }
            });
        });

        // Login Form Submit
        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Register Form Submit
        const registerForm = document.getElementById('register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegister(e));
        }

        // New Thread Form Submit
        const newThreadForm = document.getElementById('new-thread-form');
        if (newThreadForm) {
            newThreadForm.addEventListener('submit', (e) => this.handleNewThread(e));
        }

        // Chat Form Submit
        const chatForm = document.getElementById('chat-form');
        if (chatForm) {
            chatForm.addEventListener('submit', (e) => this.handleSendChatMessage(e));
        }

        // Logout
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleLogout();
            });
        }
    },

    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },

    openModal(modalId) {
        this.closeModals();
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    },

    closeModals() {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
    },

    async handleLogin(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('action', 'login');
        formData.append('csrf_token', this.csrfToken);

        try {
            const res = await fetch('api/auth.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (res.ok && data.success) {
                this.showToast(data.message, 'success');
                this.closeModals();
                this.checkAuthStatus();
                this.loadThreads();
            } else {
                this.showToast(data.error || 'Login failed', 'error');
            }
        } catch (err) {
            this.showToast('Network error occurred.', 'error');
        }
    },

    async handleRegister(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('action', 'register');
        formData.append('csrf_token', this.csrfToken);

        try {
            const res = await fetch('api/auth.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (res.ok && data.success) {
                this.showToast(data.message, 'success');
                this.closeModals();
                this.checkAuthStatus();
                this.loadThreads();
            } else {
                this.showToast(data.error || 'Registration failed', 'error');
            }
        } catch (err) {
            this.showToast('Network error occurred.', 'error');
        }
    },

    async handleLogout() {
        try {
            await fetch('api/auth.php?action=logout');
            this.currentUser = null;
            this.updateAuthUI(false);
            this.showToast('Logged out successfully.', 'info');
            this.loadThreads();
        } catch (err) {
            this.showToast('Failed to log out.', 'error');
        }
    },

    async loadThreads() {
        const container = document.getElementById('threads-container');
        if (!container) return;

        container.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                <div class="spinner"></div>
                <p style="margin-top: 1rem;">Loading discussions...</p>
            </div>
        `;

        const url = `api/threads.php?action=list&category=${encodeURIComponent(this.currentCategory)}&sort=${this.currentSort}&search=${encodeURIComponent(this.searchQuery)}&page=${this.currentPage}`;

        try {
            const res = await fetch(url);
            const data = await res.json();

            this.renderCategories(data.categories);
            this.renderThreads(data.threads);
            this.renderPagination(data.pagination);
        } catch (err) {
            container.innerHTML = `<div class="card" style="color: var(--status-danger);">Failed to load threads. Please try again.</div>`;
        }
    },

    renderCategories(categories) {
        const categoriesList = document.getElementById('categories-list');
        const selectCategory = document.getElementById('thread-category-select');

        if (categoriesList) {
            categoriesList.innerHTML = categories.map(cat => `
                <li class="nav-item ${this.currentCategory === cat.slug ? 'active' : ''}">
                    <a href="#" onclick="App.filterCategory('${cat.slug}'); return false;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background-color: ${cat.color};"></span>
                        ${cat.name}
                        <span style="margin-left: auto; font-size: 0.75rem; color: var(--text-dim);">${cat.thread_count}</span>
                    </a>
                </li>
            `).join('');
        }

        if (selectCategory) {
            selectCategory.innerHTML = categories.map(cat => `
                <option value="${cat.id}">${cat.name}</option>
            `).join('');
        }
    },

    filterCategory(slug) {
        this.currentCategory = slug;
        this.currentPage = 1;
        this.loadThreads();
    },

    renderThreads(threads) {
        const container = document.getElementById('threads-container');
        if (threads.length === 0) {
            container.innerHTML = `
                <div class="card" style="text-align: center; padding: 3rem;">
                    <h3 style="color: var(--text-muted); margin-bottom: 0.5rem;">No discussions found</h3>
                    <p style="color: var(--text-dim); font-size: 0.9rem;">Be the first to start a topic in this section!</p>
                </div>
            `;
            return;
        }

        container.innerHTML = threads.map(thread => `
            <div class="card thread-card" onclick="window.location.href='thread.php?id=${thread.id}'">
                <img src="${thread.avatar_url}" alt="${thread.username}" class="user-avatar">
                <div class="thread-main">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <span class="category-pill" style="background-color: ${thread.category_color}">
                            ${thread.category_name}
                        </span>
                        ${thread.is_pinned ? '<span style="font-size: 0.75rem; background: var(--status-warning); color: black; padding: 2px 6px; border-radius: 4px; font-weight: bold;">Pinned</span>' : ''}
                    </div>
                    <h2 class="thread-title">${this.escapeHtml(thread.title)}</h2>
                    <div class="thread-meta">
                        <span class="meta-item">By <strong>${this.escapeHtml(thread.username)}</strong></span>
                        <span>•</span>
                        <span class="meta-item">${thread.time_ago}</span>
                        <span>•</span>
                        <span class="meta-item">💬 ${thread.post_count} replies</span>
                        <span>•</span>
                        <span class="meta-item">👁️ ${thread.views} views</span>
                    </div>
                </div>
            </div>
        `).join('');
    },

    renderPagination(pagination) {
        const container = document.getElementById('pagination-container');
        if (!container || pagination.total_pages <= 1) {
            if (container) container.innerHTML = '';
            return;
        }

        let html = '<div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1rem;">';
        for (let i = 1; i <= pagination.total_pages; i++) {
            html += `
                <button class="btn btn-sm ${i === pagination.current_page ? 'btn-primary' : 'btn-secondary'}" 
                        onclick="App.goToPage(${i})">${i}</button>
            `;
        }
        html += '</div>';
        container.innerHTML = html;
    },

    goToPage(page) {
        this.currentPage = page;
        this.loadThreads();
    },

    async handleNewThread(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('csrf_token', this.csrfToken);

        try {
            const res = await fetch('api/threads.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (res.ok && data.success) {
                this.showToast(data.message, 'success');
                this.closeModals();
                e.target.reset();
                window.location.href = `thread.php?id=${data.thread_id}`;
            } else {
                this.showToast(data.error || 'Failed to create thread', 'error');
            }
        } catch (err) {
            this.showToast('Network error occurred.', 'error');
        }
    },


    /* Chat polling implementation */
    initChat() {
        const chatMessages = document.getElementById('chat-messages');
        if (!chatMessages) return;

        this.pollChat();
        // Intelligent polling every 4 seconds
        this.chatPollTimer = setInterval(() => this.pollChat(), 4000);
    },

    async pollChat() {
        const chatMessages = document.getElementById('chat-messages');
        if (!chatMessages) return;

        try {
            const res = await fetch(`api/chat.php?since=${this.lastChatId}`);
            const data = await res.json();

            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    if (msg.id > this.lastChatId) {
                        this.lastChatId = msg.id;
                    }
                    const bubble = document.createElement('div');
                    bubble.className = `chat-bubble ${msg.is_self ? 'self' : ''}`;
                    bubble.innerHTML = `
                        <img src="${msg.avatar_url}" class="user-avatar" style="width: 28px; height: 28px;" alt="${msg.username}">
                        <div class="chat-bubble-content">
                            <div class="chat-user">${this.escapeHtml(msg.username)} <span class="chat-time">${msg.time}</span></div>
                            <div>${msg.formatted_message}</div>
                        </div>
                    `;
                    chatMessages.appendChild(bubble);
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            if (data.online_users) {
                const onlineContainer = document.getElementById('online-users-list');
                if (onlineContainer) {
                    onlineContainer.innerHTML = data.online_users.map(u => `
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <img src="${u.avatar_url}" class="user-avatar" style="width: 24px; height: 24px;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">${this.escapeHtml(u.username)}</span>
                            <span style="width: 6px; height: 6px; background: var(--status-success); border-radius: 50%; margin-left: auto;"></span>
                        </div>
                    `).join('');
                }
            }
        } catch (err) {
            console.error('Chat polling error', err);
        }
    },

    async handleSendChatMessage(e) {
        e.preventDefault();
        if (!this.currentUser) {
            this.openModal('login-modal');
            this.showToast('Please login to participate in chat.', 'error');
            return;
        }

        const input = document.getElementById('chat-input');
        const fileInput = document.getElementById('chat-attachment');
        const message = input.value.trim();
        if (!message && (!fileInput || fileInput.files.length === 0)) return;

        const formData = new FormData();
        formData.append('message', message);
        formData.append('csrf_token', this.csrfToken);

        if (fileInput && fileInput.files.length > 0) {
            formData.append('attachment', fileInput.files[0]);
        }

        input.value = '';
        if (fileInput) fileInput.value = '';

        try {
            const res = await fetch('api/chat.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                this.pollChat();
            } else {
                this.showToast(data.error || 'Failed to send message', 'error');
            }
        } catch (err) {
            this.showToast('Failed to send chat message.', 'error');
        }
    },


    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
