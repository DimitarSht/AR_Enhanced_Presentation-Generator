<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        die('Access denied. Admin privileges required.');
    }
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function getCurrentUser()
{
    if (!isLoggedIn()) return null;
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
    ];
}

function loginUser($username, $password)
{
    $db = getDB();
    $sql = "SELECT * FROM users WHERE username = :username";
    $stmt = $db->prepare($sql);
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        $sql = "UPDATE users SET last_login = NOW() WHERE user_id = :uid";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $user['user_id']]);

        return true;
    }
    return false;
}

function registerUser($username, $email, $password)
{
    $db = getDB();

    $sql = "SELECT user_id FROM users WHERE username = :username OR email = :email";
    $stmt = $db->prepare($sql);
    $stmt->execute([':username' => $username, ':email' => $email]);
    if ($stmt->fetch()) {
        return 'Username or email already exists.';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :hash, 'user')";
    $stmt = $db->prepare($sql);
    $stmt->execute([':username' => $username, ':email' => $email, ':hash' => $hash]);

    $userId = $db->lastInsertId();
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'user';

    return true;
}

function logout()
{
    session_destroy();
    header('Location: login.php');
    exit;
}
