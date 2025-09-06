<?php
// config.php - Database configuration
class Database {
    private $db;
    
    public function __construct() {
        try {
            $this->db = new PDO('sqlite:forum.db');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    private function createTables() {
        // Users table
        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            bio TEXT,
            avatar TEXT,
            is_admin INTEGER DEFAULT 0,
            reputation INTEGER DEFAULT 0,
            post_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Communities table
        $this->db->exec("CREATE TABLE IF NOT EXISTS communities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            description TEXT,
            created_by INTEGER,
            member_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )");
        
        // Posts table
        $this->db->exec("CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            author_id INTEGER,
            community_id INTEGER,
            likes INTEGER DEFAULT 0,
            comment_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id),
            FOREIGN KEY (community_id) REFERENCES communities(id)
        )");
        
        // Comments table
        $this->db->exec("CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER,
            author_id INTEGER,
            content TEXT NOT NULL,
            likes INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )");
        
        // Likes table
        $this->db->exec("CREATE TABLE IF NOT EXISTS post_likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER,
            user_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(post_id, user_id),
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        
        // Comment likes table
        $this->db->exec("CREATE TABLE IF NOT EXISTS comment_likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            comment_id INTEGER,
            user_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(comment_id, user_id),
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        
        // Community members table
        $this->db->exec("CREATE TABLE IF NOT EXISTS community_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            community_id INTEGER,
            user_id INTEGER,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(community_id, user_id),
            FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        
        // Create default admin user if not exists
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 1");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password, is_admin, bio) VALUES (?, ?, ?, 1, ?)");
            $stmt->execute(['admin', 'admin@forum.com', password_hash('admin123', PASSWORD_DEFAULT), 'Forum Administrator']);
        }
        
        // Create default communities
        $defaultCommunities = [
            ['General Discussion', 'Talk about anything and everything'],
            ['Tech Talk', 'Technology discussions and news'],
            ['Gaming', 'Gaming related topics and reviews'],
            ['Programming', 'Code, development, and programming help']
        ];
        
        foreach ($defaultCommunities as $community) {
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO communities (name, description, created_by) VALUES (?, ?, 1)");
            $stmt->execute($community);
        }
    }
    
    public function getConnection() {
        return $this->db;
    }
}

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Start session
session_start();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'register':
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $bio = trim($_POST['bio'] ?? '');
            
            if (empty($username) || empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, bio) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashedPassword, $bio]);
                echo json_encode(['success' => true, 'message' => 'Account created successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
            }
            exit;
            
        case 'login':
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = $user;
                echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'bio' => $user['bio'],
                    'is_admin' => $user['is_admin'],
                    'reputation' => $user['reputation'],
                    'post_count' => $user['post_count']
                ]]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
            exit;
            
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            exit;
            
        case 'update_profile':
            if (!isset($_SESSION['user'])) {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
                exit;
            }
            
            $newUsername = trim($_POST['username']);
            $email = trim($_POST['email']);
            $bio = trim($_POST['bio'] ?? '');
            $userId = $_SESSION['user']['id'];
            
            try {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, bio = ? WHERE id = ?");
                $stmt->execute([$newUsername, $email, $bio, $userId]);
                
                // Update session
                $_SESSION['user']['username'] = $newUsername;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['bio'] = $bio;
                
                echo json_encode(['success' => true, 'message' => 'Profile updated']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
            }
            exit;
            
        case 'create_post':
            if (!isset($_SESSION['user'])) {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
                exit;
            }
            
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $communityId = (int)$_POST['community_id'];
            $userId = $_SESSION['user']['id'];
            
            $stmt = $db->prepare("INSERT INTO posts (title, content, author_id, community_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $userId, $communityId]);
            
            // Update user post count
            $stmt = $db->prepare("UPDATE users SET post_count = post_count + 1 WHERE id = ?");
            $stmt->execute([$userId]);
            
            echo json_encode(['success' => true, 'message' => 'Post created']);
            exit;
            
        case 'delete_post':
            if (!isset($_SESSION['user'])) {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
                exit;
            }
            
            $postId = (int)$_POST['post_id'];
            $userId = $_SESSION['user']['id'];
            $isAdmin = $_SESSION['user']['is_admin'];
            
            // Check if user owns the post or is admin
            $stmt = $db->prepare("SELECT author_id FROM posts WHERE id = ?");
            $stmt->execute([$postId]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post && ($post['author_id'] == $userId || $isAdmin)) {
                $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
                $stmt->execute([$postId]);
                
                // Update user post count
                $stmt = $db->prepare("UPDATE users SET post_count = post_count - 1 WHERE id = ?");
                $stmt->execute([$post['author_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Post deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
            }
            exit;
            
        case 'like_post':
            if (!isset($_SESSION['user'])) {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
                exit;
            }
            
            $postId = (int)$_POST['post_id'];
            $userId = $_SESSION['user']['id'];
            
            try {
                // Check if already liked
                $stmt = $db->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
                $stmt->execute([$postId, $userId]);
                
                if ($stmt->fetch()) {
                    // Unlike
                    $stmt = $db->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
                    $stmt->execute([$postId, $userId]);
                    
                    $stmt = $db->prepare("UPDATE posts SET likes = likes - 1 WHERE id = ?");
                    $stmt->execute([$postId]);
                    
                    $action = 'unliked';
                } else {
                    // Like
                    $stmt = $db->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$postId, $userId]);
                    
                    $stmt = $db->prepare("UPDATE posts SET likes = likes + 1 WHERE id = ?");
                    $stmt->execute([$postId]);
                    
                    $action = 'liked';
                }
                
                // Get updated like count
                $stmt = $db->prepare("SELECT likes FROM posts WHERE id = ?");
                $stmt->execute([$postId]);
                $likes = $stmt->fetchColumn();
                
                echo json_encode(['success' => true, 'action' => $action, 'likes' => $likes]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error processing like']);
            }
            exit;
            
        case 'add_comment':
            if (!isset($_SESSION['user'])) {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
                exit;
            }
            
            $postId = (int)$_POST['post_id'];
            $content = trim($_POST['content']);
            $userId = $_SESSION['user']['id'];
            
            $stmt = $db->prepare("INSERT INTO comments (post_id, author_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$postId, $userId, $content]);
            
            // Update comment count
            $stmt = $db->prepare("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?");
            $stmt->execute([$postId]);
            
            echo json_encode(['success' => true, 'message' => 'Comment added']);
            exit;
            
        case 'create_community':
            if (!isset($_SESSION['user'])) {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
                exit;
            }
            
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $userId = $_SESSION['user']['id'];
            
            try {
                $stmt = $db->prepare("INSERT INTO communities (name, description, created_by) VALUES (?, ?, ?)");
                $stmt->execute([$name, $description, $userId]);
                
                $communityId = $db->lastInsertId();
                
                // Join creator to community
                $stmt = $db->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)");
                $stmt->execute([$communityId, $userId]);
                
                // Update member count
                $stmt = $db->prepare("UPDATE communities SET member_count = 1 WHERE id = ?");
                $stmt->execute([$communityId]);
                
                echo json_encode(['success' => true, 'message' => 'Community created']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Community name already exists']);
            }
            exit;
            
        case 'join_community':
            if (!isset($_SESSION['user'])) {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
                exit;
            }
            
            $communityId = (int)$_POST['community_id'];
            $userId = $_SESSION['user']['id'];
            
            try {
                $stmt = $db->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)");
                $stmt->execute([$communityId, $userId]);
                
                // Update member count
                $stmt = $db->prepare("UPDATE communities SET member_count = member_count + 1 WHERE id = ?");
                $stmt->execute([$communityId]);
                
                echo json_encode(['success' => true, 'message' => 'Joined community']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Already a member']);
            }
            exit;
    }
}

// Get data for page load
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_posts':
            $communityId = isset($_GET['community_id']) ? (int)$_GET['community_id'] : null;
            
            $sql = "SELECT p.*, u.username, u.is_admin, c.name as community_name 
                    FROM posts p 
                    JOIN users u ON p.author_id = u.id 
                    JOIN communities c ON p.community_id = c.id";
            
            if ($communityId) {
                $sql .= " WHERE p.community_id = ?";
                $stmt = $db->prepare($sql . " ORDER BY p.created_at DESC");
                $stmt->execute([$communityId]);
            } else {
                $stmt = $db->prepare($sql . " ORDER BY p.created_at DESC");
                $stmt->execute();
            }
            
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($posts);
            exit;
            
        case 'get_comments':
            $postId = (int)$_GET['post_id'];
            
            $stmt = $db->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.author_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC");
            $stmt->execute([$postId]);
            
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($comments);
            exit;
            
        case 'get_communities':
            $stmt = $db->query("SELECT * FROM communities ORDER BY member_count DESC");
            $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($communities);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DarkForum - Community Discussion</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #16213e 100%);
            color: #e0e6ed;
            min-height: 100vh;
        }

        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            background: transparent;
            color: inherit;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #e0e6ed;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .main-content {
            display: grid;
            grid-template-columns: 250px 1fr 300px;
            gap: 2rem;
            margin-top: 2rem;
        }

        .forum-section {
            background: rgba(0, 0, 0, 0.6);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .section-title {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: #667eea;
            border-bottom: 2px solid rgba(102, 126, 234, 0.3);
            padding-bottom: 0.5rem;
        }

        .post-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .post-item:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .admin-badge {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .username {
            font-weight: 600;
            color: #667eea;
        }

        .timestamp {
            color: #a0a9b8;
            font-size: 0.9rem;
        }

        .community-tag {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .post-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            align-items: center;
        }

        .action-btn {
            background: none;
            border: none;
            color: #a0a9b8;
            cursor: pointer;
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #667eea;
        }

        .action-btn.liked {
            color: #ff6b6b;
        }

        .delete-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 71, 87, 0.2);
            color: #ff4757;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .post-item:hover .delete-btn {
            display: flex;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .community-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .community-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .community-item.active {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .modal-content {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 15px;
            padding: 2rem;
            margin: 5% auto;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }

        .close {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 2rem;
            cursor: pointer;
            color: #a0a9b8;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #e0e6ed;
            font-weight: 500;
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #e0e6ed;
            font-size: 1rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .user-card {
            text-align: center;
            padding: 1.5rem;
        }

        .user-card .avatar {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
        }

        .create-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }

        .create-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
        }

        .comment-item {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            padding: 0.8rem;
            margin-bottom: 0.5rem;
            border-left: 3px solid rgba(102, 126, 234, 0.3);
        }

        .comment-author {
            font-weight: 600;
            color: #667eea;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .comment-text {
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(46, 213, 115, 0.1);
            color: #2ed573;
            border-color: rgba(46, 213, 115, 0.3);
        }

        .alert-error {
            background: rgba(255, 71, 87, 0.1);
            color: #ff4757;
            border-color: rgba(255, 71, 87, 0.3);
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .navbar {
                padding: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">DarkForum</div>
        <div class="nav-buttons">
            <span id="user-display" style="display: none; margin-right: 1rem;"></span>
            <button class="btn btn-secondary" id="register-btn">Register</button>
            <button class="btn btn-secondary" id="profile-btn" style="display: none;">Profile</button>
            <button class="btn btn-secondary" id="logout-btn" style="display: none;">Logout</button>
        </div>
    </nav>

    <div class="container">
        <div class="main-content">
            <!-- Communities Sidebar -->
            <div class="sidebar">
                <div class="forum-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 class="section-title" style="margin-bottom: 0;">Communities</h3>
                        <button class="btn btn-primary" id="create-community-btn" style="display: none; padding: 0.3rem 0.6rem; font-size: 0.8rem;">+</button>
                    </div>
                    <div class="community-item active" data-community-id="" onclick="selectCommunity(null, 'All Posts')">
                        <h4>All Posts</h4>
                        <p style="font-size: 0.8rem; color: #a0a9b8;">View all communities</p>
                    </div>
                    <div id="communities-container"></div>
                </div>
            </div>

            <!-- Main Content -->
            <div>
                <div class="forum-section">
                    <h2 class="section-title" id="current-view">All Recent Posts</h2>
                    <div id="posts-container">
                        <div style="text-align: center; padding: 2rem; color: #a0a9b8;">
                            <p>Loading posts...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Profile Sidebar -->
            <div class="sidebar">
                <div class="forum-section user-card" id="user-profile" style="display: none;">
                    <div class="avatar" id="profile-avatar">U</div>
                    <h3 id="profile-username">Username</h3>
                    <div id="admin-badge" style="display: none;" class="admin-badge">ADMIN</div>
                    <p id="profile-bio">User bio goes here</p>
                    <div class="stats">
                        <div class="stat-item">
                            <div class="stat-number" id="post-count">0</div>
                            <div>Posts</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="rep-count">0</div>
                            <div>Rep</div>
                        </div>
                    </div>
                </div>

                <div class="forum-section">
                    <h3 class="section-title">Quick Stats</h3>
                    <div id="forum-stats">
                        <div class="stat-item">
                            <div class="stat-number" id="total-posts">0</div>
                            <div>Total Posts</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="total-users">1</div>
                            <div>Total Users</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="total-communities">4</div>
                            <div>Communities</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button class="create-btn" id="create-post-btn" style="display: none;">+</button>

    <!-- Login Modal -->
    <div id="login-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('login-modal')">&times;</span>
            <h2>Login</h2>
            <div id="login-alert"></div>
            <form id="login-form">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="login-username" required placeholder="Enter your username">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="login-password" required placeholder="Enter your password">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
            <p style="text-align: center; margin-top: 1rem; color: #a0a9b8;">
                Default admin: username "admin", password "admin123"
            </p>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="register-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('register-modal')">&times;</span>
            <h2>Create Account</h2>
            <div id="register-alert"></div>
            <form id="register-form">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="register-username" required placeholder="Choose a username">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="register-email" required placeholder="Enter your email">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="register-password" required placeholder="Create a password">
                </div>
                <div class="form-group">
                    <label>Bio (optional)</label>
                    <textarea id="register-bio" placeholder="Tell us about yourself..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
            </form>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profile-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('profile-modal')">&times;</span>
            <h2>Edit Profile</h2>
            <div id="profile-alert"></div>
            <form id="profile-form">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="edit-username" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="edit-email" required>
                </div>
                <div class="form-group">
                    <label>Bio</label>
                    <textarea id="edit-bio" placeholder="Tell us about yourself..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Create Post Modal -->
    <div id="create-post-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('create-post-modal')">&times;</span>
            <h2>Create New Post</h2>
            <div id="create-post-alert"></div>
            <form id="create-post-form">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" id="post-title" required placeholder="What's your post about?">
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea id="post-content" required placeholder="Share your thoughts..." style="min-height: 150px;"></textarea>
                </div>
                <div class="form-group">
                    <label>Community</label>
                    <select id="post-community" required>
                        <option value="">Select a community</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Post</button>
            </form>
        </div>
    </div>

    <!-- Create Community Modal -->
    <div id="create-community-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('create-community-modal')">&times;</span>
            <h2>Create Community</h2>
            <div id="create-community-alert"></div>
            <form id="create-community-form">
                <div class="form-group">
                    <label>Community Name</label>
                    <input type="text" id="community-name" required placeholder="Enter community name">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="community-description" required placeholder="Describe your community..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Community</button>
            </form>
        </div>
    </div>

    <!-- Comments Modal -->
    <div id="comments-modal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close" onclick="closeModal('comments-modal')">&times;</span>
            <h2 id="comments-title">Comments</h2>
            <div id="comments-container"></div>
            <div id="add-comment-section" style="display: none;">
                <div class="form-group">
                    <textarea id="comment-content" placeholder="Write a comment..." style="min-height: 80px;"></textarea>
                </div>
                <button id="submit-comment" class="btn btn-primary">Add Comment</button>
            </div>
        </div>
    </div>

    <script>
        let currentUser = null;
        let currentCommunity = null;
        let currentPostForComments = null;
        let likedPosts = new Set();

        // Utility functions
        function showAlert(containerId, message, type = 'error') {
            const container = document.getElementById(containerId);
            container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
            if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
            if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
            
            return date.toLocaleDateString();
        }

        // API functions
        async function apiCall(url, data = null, method = 'GET') {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            };
            
            if (data) {
                options.body = new URLSearchParams(data);
            }
            
            const response = await fetch(url, options);
            return response.json();
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Load data functions
        async function loadPosts(communityId = null) {
            try {
                const url = communityId ? 
                    `?action=get_posts&community_id=${communityId}` : 
                    '?action=get_posts';
                
                const posts = await apiCall(url);
                renderPosts(posts);
            } catch (error) {
                console.error('Error loading posts:', error);
                document.getElementById('posts-container').innerHTML = 
                    '<div style="text-align: center; padding: 2rem; color: #ff4757;">Error loading posts</div>';
            }
        }

        async function loadCommunities() {
            try {
                const communities = await apiCall('?action=get_communities');
                renderCommunities(communities);
                
                // Populate community dropdown
                const select = document.getElementById('post-community');
                select.innerHTML = '<option value="">Select a community</option>';
                communities.forEach(community => {
                    select.innerHTML += `<option value="${community.id}">${community.name}</option>`;
                });
            } catch (error) {
                console.error('Error loading communities:', error);
            }
        }

        async function loadComments(postId) {
            try {
                const comments = await apiCall(`?action=get_comments&post_id=${postId}`);
                renderComments(comments);
                currentPostForComments = postId;
            } catch (error) {
                console.error('Error loading comments:', error);
            }
        }

        // Render functions
        function renderPosts(posts) {
            const container = document.getElementById('posts-container');
            
            if (posts.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 2rem; color: #a0a9b8;">No posts yet. Be the first to post!</div>';
                return;
            }
            
            container.innerHTML = posts.map(post => {
                const canDelete = currentUser && (currentUser.id == post.author_id || currentUser.is_admin == 1);
                const isLiked = likedPosts.has(post.id);
                
                return `
                    <div class="post-item" data-post-id="${post.id}">
                        ${canDelete ? `<button class="delete-btn" onclick="deletePost(${post.id})" title="Delete post">×</button>` : ''}
                        
                        <div class="post-header">
                            <div class="user-info">
                                <div class="avatar">${post.username[0].toUpperCase()}</div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div class="username">${post.username}</div>
                                        ${post.is_admin == 1 ? '<div class="admin-badge">ADMIN</div>' : ''}
                                    </div>
                                    <div class="timestamp">${formatDate(post.created_at)}</div>
                                </div>
                            </div>
                            <div class="community-tag">${post.community_name}</div>
                        </div>
                        
                        <h3 style="margin-bottom: 0.5rem;">${post.title}</h3>
                        <p style="line-height: 1.6; margin-bottom: 1rem;">${post.content.replace(/\n/g, '<br>')}</p>
                        
                        <div class="post-actions">
                            <button class="action-btn ${isLiked ? 'liked' : ''}" 
                                    onclick="likePost(${post.id})" 
                                    ${!currentUser ? 'disabled' : ''}>
                                👍 <span id="likes-${post.id}">${post.likes}</span>
                            </button>
                            <button class="action-btn" onclick="viewComments(${post.id}, '${post.title.replace(/'/g, "\\'")}')">
                                💬 <span>${post.comment_count}</span>
                            </button>
                            <button class="action-btn" onclick="sharePost(${post.id})">
                                🔗 Share
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderCommunities(communities) {
            const container = document.getElementById('communities-container');
            container.innerHTML = communities.map(community => `
                <div class="community-item" data-community-id="${community.id}" onclick="selectCommunity(${community.id}, '${community.name.replace(/'/g, "\\'")}')">
                    <h4>${community.name}</h4>
                    <p style="font-size: 0.8rem; color: #a0a9b8;">${community.member_count} members</p>
                    <p style="font-size: 0.8rem; margin-top: 0.3rem;">${community.description}</p>
                </div>
            `).join('');
        }

        function renderComments(comments) {
            const container = document.getElementById('comments-container');
            
            if (comments.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 2rem; color: #a0a9b8;">No comments yet. Be the first to comment!</div>';
            } else {
                container.innerHTML = comments.map(comment => `
                    <div class="comment-item">
                        <div class="comment-author">${comment.username} • ${formatDate(comment.created_at)}</div>
                        <div class="comment-text">${comment.content.replace(/\n/g, '<br>')}</div>
                    </div>
                `).join('');
            }
        }

        // Action functions
        function selectCommunity(communityId, communityName) {
            document.querySelectorAll('.community-item').forEach(item => {
                item.classList.remove('active');
            });
            
            if (communityId) {
                document.querySelector(`[data-community-id="${communityId}"]`).classList.add('active');
                document.getElementById('current-view').textContent = communityName;
                currentCommunity = communityId;
            } else {
                document.querySelector('[data-community-id=""]').classList.add('active');
                document.getElementById('current-view').textContent = 'All Recent Posts';
                currentCommunity = null;
            }
            
            loadPosts(currentCommunity);
        }

        async function likePost(postId) {
            if (!currentUser) {
                alert('Please login to like posts');
                return;
            }
            
            try {
                const result = await apiCall('', {
                    action: 'like_post',
                    post_id: postId
                }, 'POST');
                
                if (result.success) {
                    document.getElementById(`likes-${postId}`).textContent = result.likes;
                    const btn = document.querySelector(`[onclick="likePost(${postId})"]`);
                    if (result.action === 'liked') {
                        btn.classList.add('liked');
                        likedPosts.add(postId);
                    } else {
                        btn.classList.remove('liked');
                        likedPosts.delete(postId);
                    }
                }
            } catch (error) {
                console.error('Error liking post:', error);
            }
        }

        async function deletePost(postId) {
            if (!confirm('Are you sure you want to delete this post?')) return;
            
            try {
                const result = await apiCall('', {
                    action: 'delete_post',
                    post_id: postId
                }, 'POST');
                
                if (result.success) {
                    loadPosts(currentCommunity);
                    if (currentUser) {
                        currentUser.post_count--;
                        updateUI();
                    }
                } else {
                    alert(result.message);
                }
            } catch (error) {
                console.error('Error deleting post:', error);
            }
        }

        function viewComments(postId, postTitle) {
            document.getElementById('comments-title').textContent = `Comments - ${postTitle}`;
            loadComments(postId);
            
            if (currentUser) {
                document.getElementById('add-comment-section').style.display = 'block';
            } else {
                document.getElementById('add-comment-section').style.display = 'none';
            }
            
            openModal('comments-modal');
        }

        function sharePost(postId) {
            if (navigator.share) {
                navigator.share({
                    title: 'Check out this post on DarkForum',
                    url: window.location.href + `#post-${postId}`
                });
            } else {
                navigator.clipboard.writeText(window.location.href + `#post-${postId}`);
                alert('Post link copied to clipboard!');
            }
        }

        // Update UI based on login status
        function updateUI() {
            const loginBtn = document.getElementById('login-btn');
            const registerBtn = document.getElementById('register-btn');
            const profileBtn = document.getElementById('profile-btn');
            const logoutBtn = document.getElementById('logout-btn');
            const userDisplay = document.getElementById('user-display');
            const userProfile = document.getElementById('user-profile');
            const createPostBtn = document.getElementById('create-post-btn');
            const createCommunityBtn = document.getElementById('create-community-btn');

            if (currentUser) {
                loginBtn.style.display = 'none';
                registerBtn.style.display = 'none';
                profileBtn.style.display = 'inline-block';
                logoutBtn.style.display = 'inline-block';
                userDisplay.style.display = 'inline-block';
                userDisplay.textContent = `Welcome, ${currentUser.username}!`;
                userProfile.style.display = 'block';
                createPostBtn.style.display = 'block';
                createCommunityBtn.style.display = 'inline-block';

                // Update profile card
                document.getElementById('profile-avatar').textContent = currentUser.username[0].toUpperCase();
                document.getElementById('profile-username').textContent = currentUser.username;
                document.getElementById('profile-bio').textContent = currentUser.bio || 'No bio set';
                document.getElementById('post-count').textContent = currentUser.post_count || 0;
                document.getElementById('rep-count').textContent = currentUser.reputation || 0;
                
                if (currentUser.is_admin == 1) {
                    document.getElementById('admin-badge').style.display = 'block';
                } else {
                    document.getElementById('admin-badge').style.display = 'none';
                }
            } else {
                loginBtn.style.display = 'inline-block';
                registerBtn.style.display = 'inline-block';
                profileBtn.style.display = 'none';
                logoutBtn.style.display = 'none';
                userDisplay.style.display = 'none';
                userProfile.style.display = 'none';
                createPostBtn.style.display = 'none';
                createCommunityBtn.style.display = 'none';
            }
        }

        // Event listeners
        document.getElementById('login-btn').addEventListener('click', () => openModal('login-modal'));
        document.getElementById('register-btn').addEventListener('click', () => openModal('register-modal'));
        document.getElementById('profile-btn').addEventListener('click', () => {
            if (currentUser) {
                document.getElementById('edit-username').value = currentUser.username;
                document.getElementById('edit-email').value = currentUser.email;
                document.getElementById('edit-bio').value = currentUser.bio || '';
                openModal('profile-modal');
            }
        });
        document.getElementById('create-post-btn').addEventListener('click', () => openModal('create-post-modal'));
        document.getElementById('create-community-btn').addEventListener('click', () => openModal('create-community-modal'));

        // Form submissions
        document.getElementById('register-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                action: 'register',
                username: document.getElementById('register-username').value,
                email: document.getElementById('register-email').value,
                password: document.getElementById('register-password').value,
                bio: document.getElementById('register-bio').value
            };
            
            try {
                const result = await apiCall('', formData, 'POST');
                
                if (result.success) {
                    showAlert('register-alert', result.message, 'success');
                    document.getElementById('register-form').reset();
                    setTimeout(() => {
                        closeModal('register-modal');
                    }, 2000);
                } else {
                    showAlert('register-alert', result.message);
                }
            } catch (error) {
                showAlert('register-alert', 'Registration failed');
            }
        });

        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                action: 'login',
                username: document.getElementById('login-username').value,
                password: document.getElementById('login-password').value
            };
            
            try {
                const result = await apiCall('', formData, 'POST');
                
                if (result.success) {
                    currentUser = result.user;
                    updateUI();
                    closeModal('login-modal');
                    document.getElementById('login-form').reset();
                    loadPosts(currentCommunity);
                } else {
                    showAlert('login-alert', result.message);
                }
            } catch (error) {
                showAlert('login-alert', 'Login failed');
            }
        });

        document.getElementById('profile-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                action: 'update_profile',
                username: document.getElementById('edit-username').value,
                email: document.getElementById('edit-email').value,
                bio: document.getElementById('edit-bio').value
            };
            
            try {
                const result = await apiCall('', formData, 'POST');
                
                if (result.success) {
                    currentUser.username = formData.username;
                    currentUser.email = formData.email;
                    currentUser.bio = formData.bio;
                    updateUI();
                    closeModal('profile-modal');
                    loadPosts(currentCommunity);
                } else {
                    showAlert('profile-alert', result.message);
                }
            } catch (error) {
                showAlert('profile-alert', 'Update failed');
            }
        });

        document.getElementById('create-post-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                action: 'create_post',
                title: document.getElementById('post-title').value,
                content: document.getElementById('post-content').value,
                community_id: document.getElementById('post-community').value
            };
            
            try {
                const result = await apiCall('', formData, 'POST');
                
                if (result.success) {
                    closeModal('create-post-modal');
                    document.getElementById('create-post-form').reset();
                    loadPosts(currentCommunity);
                    currentUser.post_count++;
                    updateUI();
                } else {
                    showAlert('create-post-alert', result.message);
                }
            } catch (error) {
                showAlert('create-post-alert', 'Post creation failed');
            }
        });

        document.getElementById('create-community-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                action: 'create_community',
                name: document.getElementById('community-name').value,
                description: document.getElementById('community-description').value
            };
            
            try {
                const result = await apiCall('', formData, 'POST');
                
                if (result.success) {
                    closeModal('create-community-modal');
                    document.getElementById('create-community-form').reset();
                    loadCommunities();
                } else {
                    showAlert('create-community-alert', result.message);
                }
            } catch (error) {
                showAlert('create-community-alert', 'Community creation failed');
            }
        });

        document.getElementById('submit-comment').addEventListener('click', async () => {
            const content = document.getElementById('comment-content').value.trim();
            if (!content || !currentPostForComments) return;
            
            try {
                const result = await apiCall('', {
                    action: 'add_comment',
                    post_id: currentPostForComments,
                    content: content
                }, 'POST');
                
                if (result.success) {
                    document.getElementById('comment-content').value = '';
                    loadComments(currentPostForComments);
                    loadPosts(currentCommunity);
                }
            } catch (error) {
                console.error('Error adding comment:', error);
            }
        });

        document.getElementById('logout-btn').addEventListener('click', async () => {
            try {
                await apiCall('', { action: 'logout' }, 'POST');
                currentUser = null;
                likedPosts.clear();
                updateUI();
                loadPosts(currentCommunity);
            } catch (error) {
                console.error('Logout error:', error);
            }
        });

        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // Initialize the application
        async function init() {
            await loadCommunities();
            await loadPosts();
            updateUI();
            
            // Check if user is already logged in (session)
            <?php if (isset($_SESSION['user'])): ?>
            currentUser = <?php echo json_encode($_SESSION['user']); ?>;
            updateUI();
            <?php endif; ?>
        }

        // Start the application
        init();
    </script>
</body>
</html> class="btn btn-primary" id="login-btn">Login</button>
            <button
