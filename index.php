<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Render.com ke environment se values lo ya default use karo
$bot_token = getenv('BOT_TOKEN') ?: '8083858449:AAHg-B6wzXmyshFzB1D4VKEYAAKfol4BV0Y';
define('BOT_TOKEN', $bot_token);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Environment variables se settings
$owner_id = getenv('OWNER_ID') ?: 1080317415;
$group_id = getenv('GROUP_ID') ?: -1003083386043;

define('OWNER_ID', (int)$owner_id);
define('GROUP_ID', (int)$group_id);
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('LOG_FILE', 'bot_log.txt');

// Delay time (seconds)
define('DELAY_BETWEEN_FORWARDS', 2);

// Channels aur groups ke IDs (fixed for your setup)
$channels = [
    '@EntertainmentTadka786' => -1003181705395,
    '@ETBackup' => -1002964109368,
    '@threater_print_movies' => -1002831605258,
    'Backup Channel 2' => -1002337293281,
    'Private Channel' => -1003251791991
];

// Function API call ke liye
function apiRequest($method, $parameters = []) {
    $url = API_URL . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        logMessage("CURL Error: " . curl_error($ch));
    }
    curl_close($ch);
    return json_decode($result, true);
}

// Progress bar display ke liye
function showProgressBar($done, $total, $size = 30) {
    $percent = ($done / $total);
    $bar = floor($percent * $size);
    $progress_bar = "[" . str_repeat("=", $bar);
    if ($bar < $size) {
        $progress_bar .= ">";
        $progress_bar .= str_repeat(" ", $size - $bar);
    } else {
        $progress_bar .= "=";
    }
    $progress_bar .= "] " . round($percent * 100, 2) . "%";
    return $progress_bar;
}

// CSV file read karne ka function
function readCSV($filename) {
    $movies = [];
    if (file_exists($filename)) {
        $file = fopen($filename, 'r');
        if ($file) {
            fgetcsv($file); // Header skip karo
            while (($row = fgetcsv($file)) !== false) {
                if (count($row) >= 3) {
                    $movies[] = [
                        'name' => trim($row[0]),
                        'message_id' => intval(trim($row[1])),
                        'channel_username' => trim($row[2])
                    ];
                }
            }
            fclose($file);
        }
    }
    return $movies;
}

// Users.json handle karne ka function
function readUsers() {
    if (file_exists(USERS_FILE)) {
        $content = file_get_contents(USERS_FILE);
        return json_decode($content, true) ?: ['users' => []];
    }
    return ['users' => []];
}

function saveUsers($data) {
    $data['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($data, JSON_PRETTY_PRINT));
    chmod(USERS_FILE, 0666); // Write permissions ensure karo
}

// Logging ke liye function
function logMessage($message) {
    $time = date('Y-m-d H:i:s');
    $log_message = "[$time] $message\n";
    
    // Console pe bhi print karo (Render logs ke liye)
    echo $log_message;
    
    // File me bhi save karo
    file_put_contents(LOG_FILE, $log_message, FILE_APPEND);
    chmod(LOG_FILE, 0666); // Write permissions ensure karo
}

// Main function webhook handle karne ke liye
function processMessage($update) {
    global $channels;
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        
        // Users database me save karo
        $users = readUsers();
        if (!isset($users['users'][$user_id])) {
            $users['users'][$user_id] = [
                'id' => $user_id,
                'username' => isset($message['from']['username']) ? $message['from']['username'] : '',
                'first_name' => isset($message['from']['first_name']) ? $message['from']['first_name'] : '',
                'last_seen' => date('Y-m-d H:i:s')
            ];
            saveUsers($users);
        }
        
        // Sirf owner hi commands use kar sakta hai
        if ($user_id != OWNER_ID) {
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ Tu owner nahi hai! Sirf owner hi commands use kar sakta hai.\n\n👤 Owner ID: " . OWNER_ID
            ]);
            return;
        }
        
        // Commands handle karna
        if (strpos($text, '/start') === 0) {
            $response = "🚀 *MNA Forward Bot Started!*\n\n";
            $response .= "*Host:* Render.com\n";
            $response .= "*Status:* Running ✅\n\n";
            $response .= "*Commands:*\n";
            $response .= "/forward_all - CSV se sab movies forward karo\n";
            $response .= "/status - Bot status dekho\n";
            $response .= "/help - Help message\n";
            $response .= "/webhook - Webhook set/reset karo\n\n";
            $response .= "*Channels Configured:* " . count($channels) . "\n";
            $response .= "*Group ID:* " . GROUP_ID;
            
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $response,
                'parse_mode' => 'Markdown'
            ]);
        }
        elseif (strpos($text, '/forward_all') === 0) {
            forwardAllMovies($chat_id);
        }
        elseif (strpos($text, '/status') === 0) {
            $movies = readCSV(CSV_FILE);
            $users = readUsers();
            $response = "📊 *Bot Status*\n\n";
            $response .= "🌐 *Hosting:* Render.com\n";
            $response .= "📁 Movies in CSV: " . count($movies) . "\n";
            $response .= "👥 Registered Users: " . count($users['users']) . "\n";
            $response .= "📺 Channels: " . count($channels) . "\n";
            $response .= "👤 Owner ID: " . OWNER_ID . "\n";
            $response .= "👥 Group ID: " . GROUP_ID . "\n";
            $response .= "🤖 Bot: @MNA_2_Bot\n";
            $response .= "🕒 Last Updated: " . ($users['last_updated'] ?? 'Never');
            
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $response,
                'parse_mode' => 'Markdown'
            ]);
        }
        elseif (strpos($text, '/webhook') === 0) {
            $webhook_result = setWebhook();
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $webhook_result,
                'parse_mode' => 'Markdown'
            ]);
        }
        elseif (strpos($text, '/help') === 0) {
            $help_text = "🆘 *Help Guide*\n\n";
            $help_text .= "1. *CSV Format:*\n";
            $help_text .= "   movie-name,message_id,channel_username\n";
            $help_text .= "   Example: The Family Man S01 2019,69,@EntertainmentTadka786\n\n";
            $help_text .= "2. *Auto Forward:*\n";
            $help_text .= "   /forward_all - Sab movies forward ho jayengi\n\n";
            $help_text .= "3. *Delay Time:* " . DELAY_BETWEEN_FORWARDS . " seconds\n\n";
            $help_text .= "4. *Webhook:*\n";
            $help_text .= "   /webhook - Webhook reset karo\n\n";
            $help_text .= "🌐 *Hosted on:* Render.com\n";
            $help_text .= "📞 Developer: @EntertainmentTadkaBot";
            
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $help_text,
                'parse_mode' => 'Markdown'
            ]);
        }
    }
}

// Sab movies forward karne ka function
function forwardAllMovies($chat_id) {
    $movies = readCSV(CSV_FILE);
    $total_movies = count($movies);
    
    if ($total_movies == 0) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ CSV file khali hai ya nahi mili!"
        ]);
        return;
    }
    
    // Start message
    $start_msg = apiRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "🔄 *Forwarding Started!*\n\nTotal Movies: $total_movies\nDelay: " . DELAY_BETWEEN_FORWARDS . "s\n\n0/$total_movies [                    ] 0%",
        'parse_mode' => 'Markdown'
    ]);
    
    if (!$start_msg['ok']) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Starting message send nahi kar paya. Bot check karo."
        ]);
        return;
    }
    
    $start_msg_id = $start_msg['result']['message_id'];
    $success_count = 0;
    $failed_count = 0;
    $failed_movies = [];
    
    foreach ($movies as $index => $movie) {
        $current = $index + 1;
        $progress = showProgressBar($current, $total_movies);
        
        // Progress update
        apiRequest('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $start_msg_id,
            'text' => "🔄 *Forwarding...*\n\nMovie: {$movie['name']}\nProgress: $current/$total_movies\n$progress\n\n✅ Success: $success_count\n❌ Failed: $failed_count",
            'parse_mode' => 'Markdown'
        ]);
        
        // Forward message to group
        $result = forwardMessageToGroup($movie['channel_username'], $movie['message_id']);
        
        if ($result['ok']) {
            $success_count++;
            logMessage("SUCCESS: Forwarded '{$movie['name']}' to group");
        } else {
            $failed_count++;
            $failed_movies[] = $movie['name'];
            logMessage("FAILED: Could not forward '{$movie['name']}'. Error: " . json_encode($result));
        }
        
        // Delay between forwards
        if ($current < $total_movies) {
            sleep(DELAY_BETWEEN_FORWARDS);
        }
    }
    
    // Final report
    $report = "✅ *Forwarding Complete!*\n\n";
    $report .= "📊 *Results:*\n";
    $report .= "Total Movies: $total_movies\n";
    $report .= "✅ Successfully Forwarded: $success_count\n";
    $report .= "❌ Failed: $failed_count\n\n";
    
    if ($failed_count > 0) {
        $report .= "📝 *Failed Movies:*\n";
        foreach ($failed_movies as $movie_name) {
            $report .= "• $movie_name\n";
        }
        $report .= "\n*Note:* Channel access check karo ya message ID verify karo.";
    }
    
    $report .= "\n⏱️ Process completed at: " . date('Y-m-d H:i:s');
    
    apiRequest('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $start_msg_id,
        'text' => $report,
        'parse_mode' => 'Markdown'
    ]);
}

// Single message forward karne ka function
function forwardMessageToGroup($source_channel, $message_id) {
    // Channel username se chat_id nikalna
    $channel_map = [
        '@EntertainmentTadka786' => -1003181705395,
        '@ETBackup' => -1002964109368,
        '@threater_print_movies' => -1002831605258
    ];
    
    $source_chat_id = $channel_map[$source_channel] ?? $source_channel;
    
    // Forward message
    return apiRequest('forwardMessage', [
        'chat_id' => GROUP_ID,
        'from_chat_id' => $source_chat_id,
        'message_id' => $message_id,
        'disable_notification' => true
    ]);
}

// Webhook setup check
function setWebhook() {
    // Current URL get karo
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $webhook_url = $protocol . '://' . $host . '/';
    
    logMessage("Setting webhook to: $webhook_url");
    
    // Pehle existing webhook delete karo
    apiRequest('deleteWebhook');
    
    // Naya webhook set karo
    $result = apiRequest('setWebhook', [
        'url' => $webhook_url,
        'max_connections' => 40,
        'drop_pending_updates' => true
    ]);
    
    if ($result['ok']) {
        $message = "✅ *Webhook Set Successfully!*\n\n";
        $message .= "🌐 *URL:* `$webhook_url`\n";
        $message .= "🤖 *Bot:* @MNA_2_Bot\n";
        $message .= "✅ *Status:* Active\n";
        $message .= "🕒 *Time:* " . date('Y-m-d H:i:s');
        
        logMessage("Webhook set successfully to: $webhook_url");
        return $message;
    } else {
        $message = "❌ *Webhook Setup Failed!*\n\n";
        $message .= "📛 *Error:* " . ($result['description'] ?? 'Unknown error') . "\n";
        $message .= "🔗 *URL:* $webhook_url\n";
        $message .= "🆘 *Help:* Check Render.com logs for details";
        
        logMessage("Webhook failed: " . json_encode($result));
        return $message;
    }
}

// Main execution
logMessage("Script started at: " . date('Y-m-d H:i:s'));

// Input get karo
$input = file_get_contents("php://input");

if (!empty($input)) {
    $update = json_decode($input, true);
    
    if ($update) {
        // Update process karo
        processMessage($update);
        logMessage("Update processed successfully");
    } else {
        logMessage("Invalid JSON received");
        http_response_code(400);
        echo "Invalid JSON";
    }
} else {
    // Agar koi update nahi aaya (direct URL open kiya)
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>🤖 MNA Forward Bot</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; }
            .status { background: #e8f4fc; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .btn:hover { background: #2980b9; }
            .success { color: #27ae60; }
            .error { color: #e74c3c; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
            th { background: #f2f2f2; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🤖 MNA Forward Bot</h1>
            
            <div class='status'>
                <h3>🌐 Bot Status</h3>
                <p><strong>Status:</strong> <span class='success'>Running ✅</span></p>
                <p><strong>Host:</strong> Render.com</p>
                <p><strong>Owner:</strong> " . OWNER_ID . "</p>
                <p><strong>Bot:</strong> @MNA_2_Bot</p>
                <p><strong>Developer:</strong> @EntertainmentTadkaBot</p>
                <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
            
            <h3>⚙️ Quick Actions</h3>
            <a href='?setwebhook=true' class='btn'>Set Webhook</a>
            <a href='?checkstatus=true' class='btn'>Check Status</a>
            <a href='movies.csv' class='btn' target='_blank'>View CSV</a>
            
            <h3>📊 System Info</h3>
            <p>PHP Version: " . phpversion() . "</p>
            <p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>
            <p>Memory Usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB</p>";
    
    if (isset($_GET['setwebhook'])) {
        echo "<div class='status'>";
        echo "<h4>Webhook Setup Result:</h4>";
        echo "<pre>" . htmlspecialchars(setWebhook()) . "</pre>";
        echo "</div>";
    }
    
    // CSV content display
    if (file_exists(CSV_FILE)) {
        echo "<h3>🎬 CSV Content Preview:</h3>";
        echo "<table>";
        echo "<tr><th>Movie Name</th><th>Message ID</th><th>Channel</th></tr>";
        
        $movies = readCSV(CSV_FILE);
        foreach ($movies as $movie) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($movie['name']) . "</td>";
            echo "<td>" . $movie['message_id'] . "</td>";
            echo "<td>" . htmlspecialchars($movie['channel_username']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p>Total Movies: " . count($movies) . "</p>";
    }
    
    echo "<hr>
            <h3>📖 Instructions:</h3>
            <ol>
                <li>Bot ko Telegram me /start command bhejo</li>
                <li>/forward_all se movies forward karo</li>
                <li>CSV file update karne ke liye Render.com dashboard use karo</li>
                <li>Logs ke liye Render.com ke logs section me jao</li>
            </ol>
            
            <p class='error'><strong>Note:</strong> Sirf owner (ID: " . OWNER_ID . ") hi commands use kar sakta hai.</p>
        </div>
    </body>
    </html>";
}

// Script end logging
logMessage("Script completed at: " . date('Y-m-d H:i:s'));
?>