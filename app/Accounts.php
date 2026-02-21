<?php
require_once 'Db.php';
require_once 'Helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Accounts
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            if (empty($username) || empty($password)) {
                $_SESSION['login_error'] = 'Please enter both username and password.';
                $_SESSION['login_username'] = htmlspecialchars($username);
                return;
            }

            try {
                $sql = 'SELECT user_id, username, password, is_admin FROM users WHERE username = ?';
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $_SESSION['login_error'] = 'Invalid username or password.';
                    $_SESSION['login_username'] = htmlspecialchars($username);
                    return;
                }

                // Simple comparison since database has plain text passwords
                if ($password !== $user['password']) {
                    $_SESSION['login_error'] = 'Invalid username or password.';
                    $_SESSION['login_username'] = htmlspecialchars($username);
                    return;
                }

                setAuthSession($user);
                header('Location: index.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['login_error'] = 'Database error occurred.';
                $_SESSION['login_username'] = htmlspecialchars($username);
                return;
            }
        }
    }

    public function login()
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            return response('error', 'Please enter both username and password.', null);
        }

        try {
            $sql = 'SELECT user_id, username, password, is_admin FROM users WHERE username = ?';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return response('error', 'Invalid username or password.', null);
            }

            // Simple comparison since database has plain text passwords
            if ($password !== $user['password']) {
                return response('error', 'Invalid username or password.', null);
            }

            setAuthSession($user);

            return response('success', 'Login successful.', [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'is_admin' => (bool) $user['is_admin'],
            ]);
        } catch (PDOException $e) {
            return response('error', 'Database error: ' . $e->getMessage(), null);
        }
    }

    public function getUsers()
    {
        $sql = "SELECT user_id, username, is_admin, created_at FROM users ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return response('success', 'Users fetched successfully.', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function addUser($data)
    {
        $sql = "INSERT INTO users (username, password, is_admin) VALUES (:username, :password, :is_admin)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':password', $data['password']);
        $stmt->bindParam(':is_admin', $data['is_admin']);
        $stmt->execute();
        return response('success', 'User added successfully.', ['id' => $this->db->lastInsertId()]);
    }

    public function updateUser($id, $data)
    {
        $sql = "UPDATE users SET username = :username, password = :password, is_admin = :is_admin WHERE user_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':password', $data['password']);
        $stmt->bindParam(':is_admin', $data['is_admin']);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return response('success', 'User updated successfully.', null);
    }

    public function deleteUser($id)
    {
        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return response('success', 'User deleted successfully.', null);
    }
}