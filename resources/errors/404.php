<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #111;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .box {
            text-align: center;
        }
        a {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>404</h1>
        <p>Page not found.</p>
        <p><a href="/login">Go to login</a></p>
    </div>
</body>
</html>
