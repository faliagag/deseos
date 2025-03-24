<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkAuth() {
    return isset($_SESSION["user"]);
}

function requireAuth() {
    if (!checkAuth()) {
        header("Location: login.php");
        exit;
    }
}

function isAdmin() {
    return checkAuth() && $_SESSION["user"]["role"] === "admin";
}
