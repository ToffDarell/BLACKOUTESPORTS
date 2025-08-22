<?php
session_start();

require_once '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
   
    if(!isset($token['error'])) { 
        $client->setAccessToken($token['access_token']);
        
        // Get user info
        $oauth2 = new Google\Service\Oauth2($client);
        $userInfo = $oauth2->userinfo->get();

        $_SESSION['user_type'] = 'google';
        $_SESSION['user_name'] = $userInfo->name;
        $_SESSION['user_email'] = $userInfo->email;
        $_SESSION['user_image'] = $userInfo->picture;

        $_SESSION['success'] = 'Successfully logged in with Google';
        header('Location: ../login.php');
        exit();
    } else {
        $_SESSION['error'] = 'Login Failed: ';
        header('Location: ../login.php');
        exit();
    }
} else {
    $_SESSION['error'] = 'Invalid Login';
    header('Location: ../login.php');
    exit();
}









