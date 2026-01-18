<?php
$bot_token = '8083858449:AAHg-B6wzXmyshFzB1D4VKEYAAKfol4BV0Y';
$webhook_url = 'https://mna-bot-18th-january-2026.onrender.com/';

// Delete existing webhook
$delete_url = "https://api.telegram.org/bot$bot_token/deleteWebhook";
$ch = curl_init($delete_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

echo "Delete result: " . $result . "<br><br>";

sleep(1);

// Set new webhook
$set_url = "https://api.telegram.org/bot$bot_token/setWebhook?url=$webhook_url";
$ch = curl_init($set_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

echo "Set webhook result: " . $result . "<br><br>";

// Get webhook info
$info_url = "https://api.telegram.org/bot$bot_token/getWebhookInfo";
$ch = curl_init($info_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

echo "Webhook info: " . $result;
?>
