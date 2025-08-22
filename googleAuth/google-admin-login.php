<?php
session_start();

require_once '../vendor/autoload.php';

// Make sure to update these in the Google Cloud Console to match your environment
$client_id = '999525143055-lr2qmkqvptjblcrfp32egarslm28b6b1.apps.googleusercontent.com';
$client_secret = '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';
$redirect_uri = 'http://localhost/website/googleAuth/admin-callback.php';

try {
    $client = new Google\Client();
    $client->setApplicationName('Blackout Esports Cafe');
    $client->setClientId($client_id);
    $client->setClientSecret($client_secret);
    $client->setRedirectUri($redirect_uri);
    
    // Add required scopes
    $client->addScope('https://www.googleapis.com/auth/userinfo.profile');
    $client->addScope('https://www.googleapis.com/auth/userinfo.email');
    $client->setAccessType('online');
    $client->setPrompt('select_account'); // Force account selection
    $client->setIncludeGrantedScopes(true);
    
    // Generate the auth URL and redirect
    $auth_url = $client->createAuthUrl();
    
    // Log the auth URL for debugging
    error_log('Google admin auth URL: ' . $auth_url);
    
    header('Location: ' . $auth_url);
    exit();
} catch (Exception $e) {
    error_log('Google admin login initialization error: ' . $e->getMessage());
    $_SESSION['admin_error'] = 'Failed to initialize Google login: ' . $e->getMessage();
    header('Location: ../admin_login.php');
    exit();
} 