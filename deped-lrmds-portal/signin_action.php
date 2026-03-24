<?php
session_start();
header('Content-Type: application/json');

$email    = $_POST['email']    ?? '';
$password = $_POST['password'] ?? '';

if ($email && $password) {
    $_SESSION['user'] = $email;
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false]);
}