<?php
session_start();

require_once '../vendor/autoload.php';

$client = new Google\Client();
$client->setClientId('999525143055-lr2qmkqvptjblcrfp32egarslm28b6b1.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/website/googleAuth/callback.php');

// Add required scopes
$client->addScope('https://www.googleapis.com/auth/userinfo.profile');
$client->addScope('https://www.googleapis.com/auth/userinfo.email');
$client->setAccessType('online');
$client->setPrompt('select_account'); // Force account selection

try {
    $auth_url = $client->createAuthUrl();
    header('Location: ' . $auth_url);
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to initialize Google login: ' . $e->getMessage();
    header('Location: ../login.php');
    exit();
}