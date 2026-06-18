<?php
// mail_config.php - Using SendGrid

function sendEmailWithSendGrid($to, $username, $resetLink) {
    $apiKey = getenv('SENDGRID_API_KEY');
    
    if (empty($apiKey)) {
        error_log("SENDGRID_API_KEY environment variable is not set!");
        return ['success' => false, 'message' => 'API key not configured'];
    }
    
    $subject = "Password Reset - Voting System";
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Password Reset</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: #f9fafb; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
            .warning { background: #fef3c7; padding: 10px; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🗳️ Voting System</h2>
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($username) . "</strong>,</p>
                <p>We received a request to reset your password for your Voting System account.</p>
                <div style='text-align: center;'>
                    <a href='" . $resetLink . "' class='button'>🔐 Reset Password</a>
                </div>
                <div class='warning'>
                    <strong>⚠️ Important:</strong> This link will expire in <strong>1 hour</strong>.
                </div>
                <p>If you didn't request this, please ignore this email.</p>
                <hr style='margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;'>
                <p style='font-size: 12px; color: #6b7280;'>This is an automated message, please do not reply.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Voting System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $emailData = [
        'personalizations' => [
            [
                'to' => [['email' => $to]],
                'subject' => $subject
            ]
        ],
        'from' => ['email' => 'noreply@yourdomain.com', 'name' => 'Voting System'],
        'content' => [
            [
                'type' => 'text/html',
                'value' => $html
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("SendGrid API Response: HTTP $httpCode");
    
    if ($httpCode === 202) {
        return ['success' => true, 'message' => 'Email sent successfully!'];
    } else {
        $errorMsg = "Failed to send email (HTTP $httpCode)";
        if ($response) {
            $decoded = json_decode($response, true);
            if (isset($decoded['errors'][0]['message'])) {
                $errorMsg .= " - " . $decoded['errors'][0]['message'];
            }
        }
        return ['success' => false, 'message' => $errorMsg];
    }
}

// Fallback function
function sendEmailFallback($to, $username, $resetLink) {
    $subject = "Password Reset - Voting System";
    $body = "<html><body><h2>Password Reset</h2><p>Hello $username,</p><p><a href='$resetLink'>$resetLink</a></p><p>Expires in 1 hour.</p></body></html>";
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    return mail($to, $subject, $body, $headers);
}

// Alias for backward compatibility
function sendEmailWithResend($to, $username, $resetLink) {
    return sendEmailWithSendGrid($to, $username, $resetLink);
}
?>
