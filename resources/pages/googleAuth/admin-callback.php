<?php
session_start();
require_once APP_ROOT . '/vendor/autoload.php';

try {
    $base_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

    $client = new Google\Client();
    $client->setClientId('999525143055-lr2qmkqvptjblcrfp32egarslm28b6b1.apps.googleusercontent.com');
    $client->setClientSecret('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');
    $client->setRedirectUri($base_url . '/googleAuth/admin-callback.php');
    
    if (isset($_GET['code'])) {
        try {
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            
            if (isset($token['error'])) {
                throw new Exception('Token error: ' . $token['error'] . ' - ' . ($token['error_description'] ?? 'No description'));
            }
            
            $client->setAccessToken($token);
            
            if ($client->getAccessToken()) {
                $oauth2 = new Google\Service\Oauth2($client);
                $userInfo = $oauth2->userinfo->get();
                
                $_SESSION['user_type'] = 'google';
                $_SESSION['user_name'] = $userInfo->name;
                $_SESSION['user_email'] = $userInfo->email;
                $_SESSION['user_image'] = $userInfo->picture;
                
                // Redirect directly to /admin/login which will handle the admin authentication
                header('Location: /admin/login');
                exit();
            }
        } catch (Exception $tokenException) {
            throw new Exception('Authentication error: ' . $tokenException->getMessage());
        }
    } else if (isset($_GET['error'])) {
        throw new Exception('Google returned an error: ' . $_GET['error'] . ' - ' . ($_GET['error_description'] ?? 'No description'));
    } else {
        throw new Exception('Authorization code not received');
    }
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Google admin login error: ' . $e->getMessage());
    $_SESSION['admin_error'] = 'Google login failed: ' . $e->getMessage();
    header('Location: /admin/login');
    exit();
} 
