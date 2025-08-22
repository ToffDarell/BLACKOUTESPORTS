<?php
session_start();
require_once '../vendor/autoload.php';

try {
    $client = new Google\Client();
    $client->setClientId('999525143055-lr2qmkqvptjblcrfp32egarslm28b6b1.apps.googleusercontent.com');
    $client->setClientSecret('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');
    $client->setRedirectUri('http://localhost/website/googleAuth/callback.php');
    
    if (isset($_GET['code'])) {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        
        if ($client->getAccessToken()) {
            $oauth2 = new Google\Service\Oauth2($client);
            $userInfo = $oauth2->userinfo->get();
            
            $_SESSION['user_type'] = 'google';
            $_SESSION['user_name'] = $userInfo->name;
            $_SESSION['user_email'] = $userInfo->email;
            $_SESSION['user_image'] = $userInfo->picture;
            $_SESSION['success'] = 'Successfully logged in with Google';
            
            header('Location: ../login.php?google_login=true');
            exit();
        }
    }
    
    throw new Exception('Authorization code not received');
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Google login failed: ' . $e->getMessage();
    header('Location: ../login.php?google_login=true');
    exit();
}
