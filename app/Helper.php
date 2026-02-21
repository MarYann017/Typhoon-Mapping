<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    function response($status, $message, $data) {
        return [
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];
    }

    function setAuthSession($user) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = (bool) $user['is_admin'];
    }

    function isLoggedIn() {
        return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
    }

    function isAdmin() {
        return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }