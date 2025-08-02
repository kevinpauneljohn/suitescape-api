<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Password Reset Request</h2>
    <p>You are receiving this email because we received a password reset request for your account.</p>
    <p><strong>Your reset code:</strong></p>
    <h1 style="letter-spacing: 2px;">{{ $token }}</h1>
    <p>If you did not request a password reset, no further action is required.</p>
    <br>
    <p>Regards,<br>{{ config('app.name') }}</p>
</body>
</html>