<?php
header('Content-Type: text/plain');
echo "OK - Cron job executed at " . date('Y-m-d H:i:s') . "\n";
echo "Memory usage: " . memory_get_usage() . " bytes\n";
?>
