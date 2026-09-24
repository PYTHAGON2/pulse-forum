<?php
/**
 * Simple File-Based Database Driver (Zero-Dependency Fallback for Local Dev)
 * Used when neither MySQL nor SQLite PDO drivers are enabled on local CLI.
 */

class SimpleJSONDB {
    private string $dataDir;

    public function __construct() {
        $this->dataDir = __DIR__ . '/../database/json_db/';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
        $this->initTables();
    }

    private function getFilePath(string $table): string {
        return $this->dataDir . $table . '.json';
    }

    private function readTable(string $table): array {
        $file = $this->getFilePath($table);
        if (!file_exists($file)) return [];
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }

    private function writeTable(string $table, array $data): void {
        $file = $this->getFilePath($table);
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function initTables(): void {
        if (!file_exists($this->getFilePath('categories'))) {
            $categories = [
                ['id' => 1, 'name' => 'General', 'slug' => 'general', 'description' => 'General discussions and casual chats.', 'icon' => 'chat-bubble', 'color' => '#6366f1', 'display_order' => 1],
                ['id' => 2, 'name' => 'Technology', 'slug' => 'technology', 'description' => 'Tech news, hardware, and gadgets.', 'icon' => 'cpu', 'color' => '#06b6d4', 'display_order' => 2],
                ['id' => 3, 'name' => 'Programming', 'slug' => 'programming', 'description' => 'Code snippets, web dev, and algorithms.', 'icon' => 'code', 'color' => '#10b981', 'display_order' => 3],
                ['id' => 4, 'name' => 'Cybersecurity', 'slug' => 'cybersecurity', 'description' => 'Security analysis and info-sec news.', 'icon' => 'shield-check', 'color' => '#ef4444', 'display_order' => 4],
                ['id' => 5, 'name' => 'AI & Data', 'slug' => 'ai', 'description' => 'Artificial Intelligence and ML.', 'icon' => 'sparkles', 'color' => '#a855f7', 'display_order' => 5],
                ['id' => 6, 'name' => 'Gaming', 'slug' => 'gaming', 'description' => 'Video games and hardware.', 'icon' => 'gamepad', 'color' => '#f59e0b', 'display_order' => 6],
                ['id' => 7, 'name' => 'Music & Art', 'slug' => 'art', 'description' => 'Creative showcase and digital design.', 'icon' => 'palette', 'color' => '#ec4899', 'display_order' => 7]
            ];
            $this->writeTable('categories', $categories);
        }
        foreach (['users', 'threads', 'posts', 'votes', 'chat_messages'] as $t) {
            if (!file_exists($this->getFilePath($t))) {
                $this->writeTable($t, []);
            }
        }
    }

    public function findUserByUsernameOrEmail(string $login): ?array {
        $users = $this->readTable('users');
        foreach ($users as $u) {
            if (strtolower($u['username']) === strtolower($login) || strtolower($u['email']) === strtolower($login)) {
                return $u;
            }
        }
        return null;
    }

    public function findUserById(int $id): ?array {
        $users = $this->readTable('users');
        foreach ($users as $u) {
            if ($u['id'] == $id) return $u;
        }
        return null;
    }

    public function insertUser(string $username, string $email, string $passwordHash): array {
        $users = $this->readTable('users');
        $id = count($users) > 0 ? max(array_column($users, 'id')) + 1 : 1;
        $user = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'avatar' => 'default.png',
            'bio' => 'Hello! I am a member of Pulse Community.',
            'reputation' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $users[] = $user;
        $this->writeTable('users', $users);
        return $user;
    }

    public function getCategories(): array {
        $categories = $this->readTable('categories');
        $threads = $this->readTable('threads');
        foreach ($categories as &$cat) {
            $cat['thread_count'] = 0;
            foreach ($threads as $t) {
                if ($t['category_id'] == $cat['id']) $cat['thread_count']++;
            }
        }
        return $categories;
    }

    public function getThreads(?string $categorySlug = null, string $sort = 'latest', string $search = '', int $page = 1, int $limit = 15): array {
        $threads = $this->readTable('threads');
        $categories = $this->readTable('categories');
        $users = $this->readTable('users');
        $posts = $this->readTable('posts');

        $catMap = [];
        foreach ($categories as $c) $catMap[$c['id']] = $c;
        $userMap = [];
        foreach ($users as $u) $userMap[$u['id']] = $u;

        $filtered = [];
        foreach ($threads as $t) {
            $cat = $catMap[$t['category_id']] ?? null;
            if ($categorySlug && (!$cat || $cat['slug'] !== $categorySlug)) continue;

            if ($search) {
                $match = false;
                if (stripos($t['title'], $search) !== false) $match = true;
                if (!$match) {
                    foreach ($posts as $p) {
                        if ($p['thread_id'] == $t['id'] && stripos($p['content'], $search) !== false) {
                            $match = true;
                            break;
                        }
                    }
                }
                if (!$match) continue;
            }

            $u = $userMap[$t['user_id']] ?? ['username' => 'Unknown', 'avatar' => 'default.png'];
            $postCount = 0;
            $lastAct = $t['created_at'];
            foreach ($posts as $p) {
                if ($p['thread_id'] == $t['id']) {
                    $postCount++;
                    if ($p['created_at'] > $lastAct) $lastAct = $p['created_at'];
                }
            }

            $t['category_name'] = $cat['name'] ?? 'General';
            $t['category_slug'] = $cat['slug'] ?? 'general';
            $t['category_color'] = $cat['color'] ?? '#6366f1';
            $t['category_icon'] = $cat['icon'] ?? 'folder';
            $t['username'] = $u['username'];
            $t['avatar'] = $u['avatar'];
            $t['post_count'] = $postCount;
            $t['last_activity'] = $lastAct;

            $filtered[] = $t;
        }

        // Sorting
        usort($filtered, function($a, $b) use ($sort) {
            if ($a['is_pinned'] != $b['is_pinned']) return $b['is_pinned'] <=> $a['is_pinned'];
            if ($sort === 'popular') return ($b['views'] + $b['post_count']) <=> ($a['views'] + $a['post_count']);
            return strcmp($b['created_at'], $a['created_at']);
        });

        $total = count($filtered);
        $offset = ($page - 1) * $limit;
        $items = array_slice($filtered, $offset, $limit);

        return ['threads' => $items, 'total' => $total, 'categories' => $this->getCategories()];
    }

    public function getThreadDetail(int $id): ?array {
        $threads = $this->readTable('threads');
        foreach ($threads as &$t) {
            if ($t['id'] == $id) {
                $t['views']++;
                $this->writeTable('threads', $threads);
                
                $categories = $this->readTable('categories');
                $users = $this->readTable('users');
                $catMap = []; foreach ($categories as $c) $catMap[$c['id']] = $c;
                $userMap = []; foreach ($users as $u) $userMap[$u['id']] = $u;

                $cat = $catMap[$t['category_id']] ?? [];
                $u = $userMap[$t['user_id']] ?? [];

                $t['category_name'] = $cat['name'] ?? 'General';
                $t['category_slug'] = $cat['slug'] ?? 'general';
                $t['category_color'] = $cat['color'] ?? '#6366f1';
                $t['username'] = $u['username'] ?? 'Unknown';
                $t['avatar'] = $u['avatar'] ?? 'default.png';
                $t['reputation'] = $u['reputation'] ?? 0;

                return $t;
            }
        }
        return null;
    }

    public function createThread(int $userId, int $categoryId, string $title, string $content): int {
        $threads = $this->readTable('threads');
        $id = count($threads) > 0 ? max(array_column($threads, 'id')) + 1 : 1;
        $thread = [
            'id' => $id,
            'category_id' => $categoryId,
            'user_id' => $userId,
            'title' => $title,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'views' => 0,
            'is_pinned' => 0,
            'is_locked' => 0
        ];
        $threads[] = $thread;
        $this->writeTable('threads', $threads);

        $posts = $this->readTable('posts');
        $postId = count($posts) > 0 ? max(array_column($posts, 'id')) + 1 : 1;
        $posts[] = [
            'id' => $postId,
            'thread_id' => $id,
            'user_id' => $userId,
            'content' => $content,
            'upvotes' => 0,
            'is_original_post' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $this->writeTable('posts', $posts);

        return $id;
    }

    public function getPosts(int $threadId, int $currentUserId = 0): array {
        $posts = $this->readTable('posts');
        $users = $this->readTable('users');
        $votes = $this->readTable('votes');

        $userMap = []; foreach ($users as $u) $userMap[$u['id']] = $u;

        $filtered = [];
        foreach ($posts as $p) {
            if ($p['thread_id'] == $threadId) {
                $u = $userMap[$p['user_id']] ?? ['username' => 'Unknown', 'avatar' => 'default.png', 'reputation' => 0];
                $userPostCount = 0;
                foreach ($posts as $p2) if ($p2['user_id'] == $p['user_id']) $userPostCount++;

                $userHasVoted = false;
                if ($currentUserId > 0) {
                    foreach ($votes as $v) {
                        if ($v['post_id'] == $p['id'] && $v['user_id'] == $currentUserId) {
                            $userHasVoted = true;
                            break;
                        }
                    }
                }

                $p['username'] = $u['username'];
                $p['avatar'] = $u['avatar'];
                $p['reputation'] = $u['reputation'];
                $p['user_post_count'] = $userPostCount;
                $p['user_has_voted'] = $userHasVoted;

                $filtered[] = $p;
            }
        }

        usort($filtered, function($a, $b) {
            if ($a['is_original_post'] != $b['is_original_post']) return $b['is_original_post'] <=> $a['is_original_post'];
            return strcmp($a['created_at'], $b['created_at']);
        });

        return $filtered;
    }

    public function createReply(int $threadId, int $userId, string $content): int {
        $posts = $this->readTable('posts');
        $id = count($posts) > 0 ? max(array_column($posts, 'id')) + 1 : 1;
        $posts[] = [
            'id' => $id,
            'thread_id' => $threadId,
            'user_id' => $userId,
            'content' => $content,
            'upvotes' => 0,
            'is_original_post' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $this->writeTable('posts', $posts);
        return $id;
    }

    public function toggleVote(int $postId, int $userId): array {
        $votes = $this->readTable('votes');
        $posts = $this->readTable('posts');

        $voteIndex = -1;
        foreach ($votes as $i => $v) {
            if ($v['post_id'] == $postId && $v['user_id'] == $userId) {
                $voteIndex = $i;
                break;
            }
        }

        $voted = false;
        if ($voteIndex >= 0) {
            array_splice($votes, $voteIndex, 1);
            foreach ($posts as &$p) {
                if ($p['id'] == $postId) $p['upvotes'] = max(0, $p['upvotes'] - 1);
            }
        } else {
            $votes[] = ['id' => count($votes) + 1, 'user_id' => $userId, 'post_id' => $postId, 'created_at' => date('Y-m-d H:i:s')];
            foreach ($posts as &$p) {
                if ($p['id'] == $postId) $p['upvotes']++;
            }
            $voted = true;
        }

        $this->writeTable('votes', $votes);
        $this->writeTable('posts', $posts);

        $newUpvotes = 0;
        foreach ($posts as $p) if ($p['id'] == $postId) $newUpvotes = $p['upvotes'];

        return ['voted' => $voted, 'upvotes' => $newUpvotes];
    }

    public function getChatMessages(int $sinceId = 0): array {
        $chat = $this->readTable('chat_messages');
        $users = $this->readTable('users');
        $userMap = []; foreach ($users as $u) $userMap[$u['id']] = $u;

        $filtered = [];
        foreach ($chat as $m) {
            if ($m['id'] > $sinceId) {
                $u = $userMap[$m['user_id']] ?? ['username' => 'Unknown', 'avatar' => 'default.png'];
                $m['username'] = $u['username'];
                $m['avatar'] = $u['avatar'];
                $filtered[] = $m;
            }
        }

        if ($sinceId === 0) {
            $filtered = array_slice($filtered, -50);
        }

        return $filtered;
    }

    public function addChatMessage(int $userId, string $message): int {
        $chat = $this->readTable('chat_messages');
        $id = count($chat) > 0 ? max(array_column($chat, 'id')) + 1 : 1;
        $chat[] = [
            'id' => $id,
            'user_id' => $userId,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->writeTable('chat_messages', $chat);
        return $id;
    }
}
