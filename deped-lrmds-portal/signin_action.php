<?php
/**
 * signin_action.php — Prototype endpoint
 * Accepts any credentials, sets a session flag, returns JSON.
 */
session_start();
header('Content-Type: application/json');
if (isset($_SESSION['user']) && $_SESSION['user']) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Prototype: accept any non-empty email + password
$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing credentials']);
    exit;
}

// "Authenticate" — prototype always succeeds
$_SESSION['user'] = [
    'email' => $email,
    'name'  => 'Demo User',
];

echo json_encode(['ok' => true]);