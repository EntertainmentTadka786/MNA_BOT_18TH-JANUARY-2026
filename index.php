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

// File existence check and creation - IMPORTANT FOR RENDER.COM
function ensureFilesExist() {
    $files = [
        'users.json' => '{"users": {}, "owner_id": ' . OWNER_ID . ', "bot_username": "@MNA_2_Bot", "last_updated": ""}',
        'movies.csv' => "movie-name,message_id,channel_username\nThe Family Man S01 2019,69,@EntertainmentTadka786\nThe Family Man S02 2022,67,@EntertainmentTadka786\nThe Family Man S03 2025,73,@EntertainmentTadka786",
        'bot_log.txt' => "# Telegram Bot Log File\n# Created: " . date('Y-m-d') . "\n# Bot: @MNA_2_Bot\n# Owner: " . OWNER_ID . "\n\n[" . date('Y-m-d H:i:s') . "] Log file initialized\n",
        'error.log' => "# Error Log\n"
    ];
    
    foreach ($files as $filename => $default_content) {
        if (!file_exists($filename)) {
            file_put_contents($filename, $default_content);
            chmod($filename, 0666);
            logMessage("Created missing file: $filename");
        }
        
        // Ensure writable permissions
        if (file_exists($filename)) {
            @chmod($filename, 0666);
        }
    }
}

// Call this function at the start
ensureFilesExist();

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
    @chmod(USERS_FILE, 0666); // Write permissions ensure karo
}

// Logging ke liye function
function logMessage($message) {
    $time = date('Y-m-d H:i:s');
    $log_message = "[$time] $message\n";
    
    // Console pe bhi print karo (Render logs ke liye)
    echo $log_message;
    
    // File me bhi save karo
    file_put_contents(LOG_FILE, $log_message, FILE_APPEND);
    @chmod(LOG_FILE, 0666); // Write permissions ensure karo
}

// Main function webhook handle karne ke liye
function processMessage($update) {
    global $channels;
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        
        logMessage("Message received from user $user_id: $text");
        
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
            logMessage("New user registered: $user_id");
        }
        
        // Sirf owner hi commands use kar sakta hai
        if ($user_id != OWNER_ID) {
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ Tu owner nahi hai! Sirf owner hi commands use kar sakta hai.\n\n👤 Owner ID: " . OWNER_ID
            ]);
            logMessage("Unauthorized access attempt by user $user_id");
            return;
        }
        
        // Commands handle karna
        if (strpos($text, '/start') === 0) {
            $response = "🚀 *MNA Forward Bot Started!*\n\n";
            $response .= "*Host:* Render.com\n";
            $response .= "*Status:* Running ✅\n";
            $response .= "*Port:* 8080\n\n";
            $response .= "*Commands:*\n";
            $response .= "/forward_all - CSV se sab movies forward karo\n";
            $response .= "/status - Bot status dekho\n";
            $response .= "/help - Help message\n";
            $response .= "/webhook - Webhook set/reset karo\n";
            $response .= "/logs - Last 10 logs dekho\n\n";
            $response .= "*Channels Configured:* " . count($channels) . "\n";
            $response .= "*Group ID:* " . GROUP_ID;
            
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $response,
                'parse_mode' => 'Markdown'
            ]);
            logMessage("Start command executed by owner");
        }
        elseif (strpos($text, '/forward_all') === 0) {
            logMessage("Forward all command received");
            forwardAllMovies($chat_id);
        }
        elseif (strpos($text, '/status') === 0) {
            $movies = readCSV(CSV_FILE);
            $users = readUsers();
            $log_size = file_exists(LOG_FILE) ? round(filesize(LOG_FILE) / 1024, 2) : 0;
            
            $response = "📊 *Bot Status*\n\n";
            $response .= "🌐 *Hosting:* Render.com\n";
            $response .= "🟢 *Status:* Active\n";
            $response .= "📁 Movies in CSV: " . count($movies) . "\n";
            $response .= "👥 Registered Users: " . count($users['users']) . "\n";
            $response .= "📺 Channels: " . count($channels) . "\n";
            $response .= "📊 Log Size: " . $log_size . " KB\n";
            $response .= "👤 Owner ID: " . OWNER_ID . "\n";
            $response .= "👥 Group ID: " . GROUP_ID . "\n";
            $response .= "🤖 Bot: @MNA_2_Bot\n";
            $response .= "🕒 Last Updated: " . ($users['last_updated'] ?? 'Never');
            
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $response,
                'parse_mode' => 'Markdown'
            ]);
            logMessage("Status command executed");
        }
        elseif (strpos($text, '/webhook') === 0) {
            $webhook_result = setWebhook();
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $webhook_result,
                'parse_mode' => 'Markdown'
            ]);
            logMessage("Webhook command executed");
        }
        elseif (strpos($text, '/logs') === 0) {
            $logs = getRecentLogs(10);
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "📝 *Recent Logs (Last 10):*\n\n" . $logs,
                'parse_mode' => 'Markdown'
            ]);
            logMessage("Logs command executed");
        }
        elseif (strpos($text, '/help') === 0) {
            $help_text = "🆘 *Help Guide*\n\n";
            $help_text .= "1. *CSV Format:*\n";
            $help_text .= "   movie-name,message_id,channel_username\n";
            $help_text .= "   Example: The Family Man S01 2019,69,@EntertainmentTadka786\n\n";
            $help_text .= "2. *Auto Forward:*\n";
            $help_text .= "   /forward_all - Sab movies forward ho jayengi\n\n";
            $help_text .= "3. *Delay Time:* " . DELAY_BETWEEN_FORWARDS . " seconds\n\n";
            $help_text .= "4. *Commands:*\n";
            $help_text .= "   /start - Bot start karo\n";
            $help_text .= "   /status - Bot status dekho\n";
            $help_text .= "   /webhook - Webhook reset karo\n";
            $help_text .= "   /logs - Recent logs dekho\n";
            $help_text .= "   /help - Help dekho\n\n";
            $help_text .= "🌐 *Hosted on:* Render.com (Port 8080)\n";
            $help_text .= "📞 Developer: @EntertainmentTadkaBot";
            
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $help_text,
                'parse_mode' => 'Markdown'
            ]);
            logMessage("Help command executed");
        }
        elseif (strpos($text, '/addmovie') === 0) {
            // Add movie command: /addmovie Movie Name,message_id,channel_username
            $parts = explode(',', substr($text, 10));
            if (count($parts) >= 3) {
                $movie_name = trim($parts[0]);
                $message_id = intval(trim($parts[1]));
                $channel = trim($parts[2]);
                
                addMovieToCSV($movie_name, $message_id, $channel);
                
                apiRequest('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "✅ Movie added successfully!\n\n*Name:* $movie_name\n*Message ID:* $message_id\n*Channel:* $channel",
                    'parse_mode' => 'Markdown'
                ]);
                logMessage("Movie added: $movie_name");
            } else {
                apiRequest('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "❌ Format galat hai!\n\nUse: /addmovie Movie Name,message_id,channel_username\nExample: /addmovie The Family Man S01,69,@EntertainmentTadka786",
                    'parse_mode' => 'Markdown'
                ]);
            }
        }
        elseif (strpos($text, '/listmovies') === 0) {
            $movies = readCSV(CSV_FILE);
            if (count($movies) > 0) {
                $response = "🎬 *Movies List:*\n\n";
                foreach ($movies as $index => $movie) {
                    $response .= ($index + 1) . ". *{$movie['name']}*\n";
                    $response .= "   📝 Message ID: {$movie['message_id']}\n";
                    $response .= "   📺 Channel: {$movie['channel_username']}\n\n";
                }
                $response .= "Total: " . count($movies) . " movies";
            } else {
                $response = "❌ No movies found in CSV file!";
            }
            
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $response,
                'parse_mode' => 'Markdown'
            ]);
            logMessage("Listmovies command executed");
        }
        else {
            // Unknown command
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ Unknown command!\n\nUse /help for available commands.",
                'parse_mode' => 'Markdown'
            ]);
        }
    }
}

// Function to get recent logs
function getRecentLogs($count = 10) {
    if (!file_exists(LOG_FILE)) {
        return "No logs found!";
    }
    
    $logs = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $recent_logs = array_slice($logs, -$count);
    
    return implode("\n", $recent_logs);
}

// Function to add movie to CSV
function addMovieToCSV($name, $message_id, $channel) {
    $file = fopen(CSV_FILE, 'a');
    if ($file) {
        // Check if file is empty
        $size = filesize(CSV_FILE);
        if ($size == 0) {
            fputcsv($file, ['movie-name', 'message_id', 'channel_username']);
        }
        
        fputcsv($file, [$name, $message_id, $channel]);
        fclose($file);
        @chmod(CSV_FILE, 0666);
        return true;
    }
    return false;
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
        logMessage("CSV file empty during forward_all");
        return;
    }
    
    logMessage("Starting to forward $total_movies movies");
    
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
        logMessage("Failed to send start message: " . json_encode($start_msg));
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
            $error_desc = $result['description'] ?? 'Unknown error';
            logMessage("FAILED: Could not forward '{$movie['name']}'. Error: $error_desc");
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
    
    logMessage("Forwarding completed. Success: $success_count, Failed: $failed_count");
}

// Single message forward karne ka function
function forwardMessageToGroup($source_channel, $message_id) {
    // Channel username se chat_id nikalna
    $channel_map = [
        '@EntertainmentTadka786' => -1003181705395,
        '@ETBackup' => -1002964109368,
        '@threater_print_movies' => -1002831605258,
        '@Backup_Channel_2' => -1002337293281,
        '@Private_Channel' => -1003251791991
    ];
    
    // Agar @ sign nahi hai to add karo
    if (strpos($source_channel, '@') !== 0) {
        $source_channel = '@' . $source_channel;
    }
    
    $source_chat_id = $channel_map[$source_channel] ?? $source_channel;
    
    logMessage("Forwarding from: $source_channel (ID: $source_chat_id), Message ID: $message_id");
    
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
    
    // Check if port is specified
    $port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '';
    $host_with_port = $port && $port != 80 && $port != 443 ? "$host:$port" : $host;
    
    $webhook_url = $protocol . '://' . $host_with_port . '/';
    
    logMessage("Setting webhook to: $webhook_url");
    
    // Pehle existing webhook delete karo
    apiRequest('deleteWebhook', ['drop_pending_updates' => true]);
    sleep(1); // Thoda wait karo
    
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
logMessage("========================================");
logMessage("Script started at: " . date('Y-m-d H:i:s'));
logMessage("Bot Token: " . substr(BOT_TOKEN, 0, 10) . "...");
logMessage("Owner ID: " . OWNER_ID);
logMessage("Group ID: " . GROUP_ID);
logMessage("========================================");

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
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            
            .container {
                background: white;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                max-width: 900px;
                width: 100%;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            h1 {
                color: #333;
                font-size: 2.5rem;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 15px;
            }
            
            h1 .emoji {
                font-size: 3rem;
            }
            
            .tagline {
                color: #666;
                font-size: 1.1rem;
                margin-bottom: 20px;
            }
            
            .status-card {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
                padding: 25px;
                border-radius: 15px;
                margin-bottom: 30px;
            }
            
            .status-card h3 {
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .info-item {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                border-left: 5px solid #667eea;
            }
            
            .info-item h4 {
                color: #333;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .btn-group {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                justify-content: center;
                margin: 30px 0;
            }
            
            .btn {
                padding: 15px 30px;
                border: none;
                border-radius: 50px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .btn-success {
                background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
                color: white;
            }
            
            .btn-warning {
                background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
                color: white;
            }
            
            .btn-danger {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
            }
            
            .btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            
            th {
                background: #667eea;
                color: white;
                padding: 15px;
                text-align: left;
            }
            
            td {
                padding: 15px;
                border-bottom: 1px solid #eee;
            }
            
            tr:hover {
                background: #f8f9fa;
            }
            
            .success {
                color: #4CAF50;
                font-weight: 600;
            }
            
            .error {
                color: #f44336;
                font-weight: 600;
            }
            
            .instructions {
                background: #e8f4fc;
                padding: 25px;
                border-radius: 15px;
                margin-top: 30px;
            }
            
            .instructions h3 {
                margin-bottom: 15px;
                color: #2c3e50;
            }
            
            .instructions ol {
                margin-left: 20px;
            }
            
            .instructions li {
                margin-bottom: 10px;
                color: #555;
            }
            
            .footer {
                text-align: center;
                margin-top: 30px;
                color: #666;
                font-size: 0.9rem;
            }
            
            @media (max-width: 768px) {
                .container {
                    padding: 20px;
                }
                
                h1 {
                    font-size: 2rem;
                }
                
                .btn-group {
                    flex-direction: column;
                }
                
                .btn {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>
                    <span class='emoji'>🤖</span>
                    MNA Forward Bot
                </h1>
                <p class='tagline'>Multi-Channel Telegram Forwarding Bot</p>
            </div>
            
            <div class='status-card'>
                <h3><span>🌐</span> Bot Status</h3>
                <div class='info-grid'>
                    <div class='info-item'>
                        <h4><span>🟢</span> Status</h4>
                        <p class='success'>Running ✅</p>
                    </div>
                    <div class='info-item'>
                        <h4><span>🌍</span> Host</h4>
                        <p>Render.com</p>
                    </div>
                    <div class='info-item'>
                        <h4><span>👤</span> Owner</h4>
                        <p>" . OWNER_ID . "</p>
                    </div>
                    <div class='info-item'>
                        <h4><span>🤖</span> Bot</h4>
                        <p>@MNA_2_Bot</p>
                    </div>
                    <div class='info-item'>
                        <h4><span>👨‍💻</span> Developer</h4>
                        <p>@EntertainmentTadkaBot</p>
                    </div>
                    <div class='info-item'>
                        <h4><span>🕒</span> Time</h4>
                        <p>" . date('Y-m-d H:i:s') . "</p>
                    </div>
                </div>
            </div>
            
            <div class='btn-group'>";
    
    if (isset($_GET['setwebhook'])) {
        echo "<div class='status-card'>
                    <h3><span>⚙️</span> Webhook Setup Result:</h3>
                    <pre style='background: white; padding: 15px; border-radius: 10px; color: #333;'>" . htmlspecialchars(setWebhook()) . "</pre>
                </div>";
    }
    
    echo "        <a href='?setwebhook=true' class='btn btn-primary'>
                    <span>⚙️</span>
                    Set Webhook
                </a>
                <a href='?checkstatus=true' class='btn btn-success'>
                    <span>📊</span>
                    Check Status
                </a>
                <a href='movies.csv' class='btn btn-warning' target='_blank'>
                    <span>📁</span>
                    View CSV
                </a>
                <a href='https://t.me/MNA_2_Bot' class='btn btn-danger' target='_blank'>
                    <span>🤖</span>
                    Open Bot
                </a>
            </div>";
    
    // CSV content display
    if (file_exists(CSV_FILE)) {
        echo "<h3 style='margin: 30px 0 15px 0; color: #333;'><span>🎬</span> CSV Content Preview:</h3>";
        echo "<table>";
        echo "<thead>
                <tr>
                    <th>Movie Name</th>
                    <th>Message ID</th>
                    <th>Channel</th>
                </tr>
              </thead>
              <tbody>";
        
        $movies = readCSV(CSV_FILE);
        foreach ($movies as $movie) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($movie['name']) . "</strong></td>";
            echo "<td>" . $movie['message_id'] . "</td>";
            echo "<td>" . htmlspecialchars($movie['channel_username']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "<p style='text-align: center; margin-top: 10px; color: #666;'>Total Movies: " . count($movies) . "</p>";
    }
    
    echo "    <div class='instructions'>
                <h3><span>📖</span> Instructions:</h3>
                <ol>
                    <li>Bot ko Telegram me /start command bhejo</li>
                    <li>/forward_all se movies forward karo</li>
                    <li>/addmovie se new movie add karo: /addmovie Movie Name,message_id,channel_username</li>
                    <li>/listmovies se sab movies dekho</li>
                    <li>CSV file update karne ke liye Render.com dashboard use karo</li>
                    <li>Logs ke liye Render.com ke logs section me jao</li>
                    <li>Webhook reset karne ke liye /webhook command ya 'Set Webhook' button use karo</li>
                </ol>
            </div>
            
            <div class='footer'>
                <p><strong>Note:</strong> Sirf owner (ID: " . OWNER_ID . ") hi commands use kar sakta hai.</p>
                <p style='margin-top: 10px;'>Built for Render.com Docker Deployment | Port: 8080</p>
            </div>
        </div>
        
        <script>
            // Auto refresh status if checkstatus parameter present
            if (window.location.search.includes('checkstatus=true')) {
                setTimeout(() => {
                    alert('Bot is running smoothly! ✅');
                }, 500);
            }
            
            // Button animations
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (this.getAttribute('href') === '#') {
                        e.preventDefault();
                        this.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 200);
                    }
                });
            });
        </script>
    </body>
    </html>";
}

// Script end logging
logMessage("========================================");
logMessage("Script completed at: " . date('Y-m-d H:i:s'));
logMessage("Memory Usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB");
logMessage("========================================");
?>
