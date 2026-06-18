<?php
// test_resend.php - Test your Resend API configuration
include("mail_config.php");

echo "<h2>Resend API Test</h2>";

// Check if API key is set
$apiKey = getenv('RESEND_API_KEY');
echo "<h3>1. API Key Status:</h3>";
if (empty($apiKey)) {
    echo "<p style='color:red;'>❌ RESEND_API_KEY is NOT set!</p>";
    echo "<p>Please add it in Render dashboard:</p>";
    echo "<ol>";
    echo "<li>Go to your service on Render</li>";
    echo "<li>Click 'Environment' tab</li>";
    echo "<li>Add Environment Variable: RESEND_API_KEY</li>";
    echo "<li>Value: your_resend_api_key</li>";
    echo "<li>Click 'Save' and redeploy</li>";
    echo "</ol>";
} else {
    echo "<p style='color:green;'>✅ RESEND_API_KEY is set!</p>";
    echo "<p>Key: " . substr($apiKey, 0, 10) . "...</p>";
}

// Test cURL
echo "<h3>2. cURL Status:</h3>";
if (function_exists('curl_version')) {
    echo "<p style='color:green;'>✅ cURL is enabled</p>";
    $version = curl_version();
    echo "<p>Version: " . $version['version'] . "</p>";
} else {
    echo "<p style='color:red;'>❌ cURL is NOT enabled!</p>";
}

// Test sending email
echo "<h3>3. Send Test Email:</h3>";
$testEmail = "your-email@example.com"; // CHANGE THIS TO YOUR EMAIL
$testUsername = "Test User";
$testLink = "https://example.com/reset?token=test123";

echo "<p>Sending test email to: <strong>$testEmail</strong></p>";

$result = sendEmailWithResend($testEmail, $testUsername, $testLink);

if ($result['success']) {
    echo "<p style='color:green;'>✅ Test email sent successfully!</p>";
    echo "<p>Check your inbox (and spam folder).</p>";
} else {
    echo "<p style='color:red;'>❌ Failed to send test email:</p>";
    echo "<pre>" . $result['message'] . "</pre>";
}

// Show server info
echo "<h3>4. Server Info:</h3>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li>Host: " . $_SERVER['HTTP_HOST'] . "</li>";
echo "</ul>";

// Show environment variables (for debugging)
echo "<h3>5. Environment Variables (checking for RESEND_API_KEY):</h3>";
echo "<pre>";
print_r($_ENV);
echo "</pre>";
?>
