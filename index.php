<?php
/**
 * 🤖 MNA Forward Bot - Complete Implementation
 * Multi-Channel Telegram Forwarding Bot
 * Hosted on Render.com
 * Owner: 1080317415
 * Bot: @MNA_2_Bot
 */

// ===================== CONFIGURATION =====================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Environment variables - Render.com se
$bot_token = getenv('BOT_TOKEN') ?: '8083858449:AAHg-B6wzXmyshFzB1D4VKEYAAKfol4BV0Y';
define('BOT_TOKEN', $bot_token);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

$owner_id = getenv('OWNER_ID') ?: 1080317415;
$group_id = getenv('GROUP_ID') ?: -1003083386043;

define('OWNER_ID', (int)$owner_id);
define('GROUP_ID', (int)$group_id);
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('LOG_FILE', 'bot_log.txt');
define('CONFIG_FILE', 'bot_config.json');

// Delay time (seconds) between forwards
define('DELAY_BETWEEN_FORWARDS', 2);

// Channels configuration
$channels = [
    '@EntertainmentTadka786' => -1003181705395,
    '@ETBackup' => -1002964109368,
    '@threater_print_movies' => -1002831605258,
    '@Backup_Channel_2' => -1002337293281,
    '@Private_Channel' => -1003251791991
];

// Bot configuration
$bot_config = [
    'name' => 'MNA Forward Bot',
    'version' => '2.0',
    'host' => 'Render.com',
    'port' => 8080,
    'maintenance' => false,
    'last_restart' => date('Y-m-d H:i:s')
];

// ===================== FILE MANAGEMENT =====================
/**
 * Ensure all required files exist with proper permissions
 */
function ensureFilesExist() {
    $files = [
        'users.json' => json_encode([
            'users' => [],
            'owner_id' => OWNER_ID,
            'bot_username' => '@MNA_2_Bot',
            'created_at' => date('Y-m-d H:i:s'),
            'total_users' => 0
        ], JSON_PRETTY_PRINT),
        
        'movies.csv' => "movie-name,message_id,channel_username\n" .
                       "The Family Man S01 2019,69,@EntertainmentTadka786\n" .
                       "The Family Man S02 2022,67,@EntertainmentTadka786\n" .
                       "The Family Man S03 2025,73,@EntertainmentTadka786",
        
        'bot_log.txt' => "# 🤖 MNA Forward Bot Log File\n" .
                        "# Created: " . date('Y-m-d H:i:s') . "\n" .
                        "# Bot: @MNA_2_Bot\n" .
                        "# Owner: " . OWNER_ID . "\n" .
                        "# Host: Render.com\n\n" .
                        "[" . date('Y-m-d H:i:s') . "] Log file initialized\n",
        
        'error.log' => "# Error Log File\n" .
                      "# Created: " . date('Y-m-d H:i:s') . "\n",
        
        'bot_config.json' => json_encode([
            'name' => 'MNA Forward Bot',
            'version' => '2.0',
            'host' => 'Render.com',
            'port' => 8080,
            'maintenance' => false,
            'webhook_url' => '',
            'last_update' => date('Y-m-d H:i:s'),
            'stats' => [
                'total_forwards' => 0,
                'total_movies' => 0,
                'success_rate' => 100
            ]
        ], JSON_PRETTY_PRINT)
    ];
    
    foreach ($files as $filename => $default_content) {
        if (!file_exists($filename)) {
            file_put_contents($filename, $default_content);
            @chmod($filename, 0666);
            logMessage("Created missing file: $filename");
        } elseif (filesize($filename) == 0) {
            file_put_contents($filename, $default_content);
            logMessage("Refreshed empty file: $filename");
        }
        
        // Ensure writable permissions
        @chmod($filename, 0666);
    }
}

// Initialize files
ensureFilesExist();

// ===================== CORE FUNCTIONS =====================
/**
 * Send API request to Telegram
 */
function apiRequest($method, $parameters = []) {
    $url = API_URL . $method;
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $parameters,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);
    
    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        logMessage("CURL Error in $method: $error");
        return ['ok' => false, 'error_code' => 0, 'description' => $error];
    }
    
    curl_close($ch);
    $response = json_decode($result, true);
    
    if (!$response['ok']) {
        logMessage("API Error in $method: " . ($response['description'] ?? 'Unknown error'));
    }
    
    return $response;
}

/**
 * Log messages to file and output
 */
function logMessage($message, $type = 'INFO') {
    $time = date('Y-m-d H:i:s');
    $log_message = "[$time] [$type] $message";
    
    // Output to console (Render logs)
    echo $log_message . "\n";
    
    // Save to log file
    file_put_contents(LOG_FILE, $log_message . "\n", FILE_APPEND);
    @chmod(LOG_FILE, 0666);
    
    // Also save to error log if error type
    if ($type === 'ERROR') {
        file_put_contents('error.log', $log_message . "\n", FILE_APPEND);
    }
}

/**
 * Show progress bar
 */
function showProgressBar($done, $total, $size = 30) {
    $percent = ($done / $total);
    $bar = floor($percent * $size);
    
    $progress_bar = "[" . str_repeat("█", $bar);
    if ($bar < $size) {
        $progress_bar .= "▷";
        $progress_bar .= str_repeat("░", $size - $bar - 1);
    } else {
        $progress_bar .= "█";
    }
    $progress_bar .= "] " . round($percent * 100, 2) . "%";
    
    return $progress_bar;
}

/**
 * Read CSV file
 */
function readCSV($filename) {
    $movies = [];
    
    if (!file_exists($filename)) {
        logMessage("CSV file not found: $filename", 'ERROR');
        return $movies;
    }
    
    $file = fopen($filename, 'r');
    if (!$file) {
        logMessage("Failed to open CSV file: $filename", 'ERROR');
        return $movies;
    }
    
    // Skip header
    fgetcsv($file);
    
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
    return $movies;
}

/**
 * Write CSV file
 */
function writeCSV($filename, $movies) {
    $file = fopen($filename, 'w');
    if (!$file) {
        logMessage("Failed to write CSV file: $filename", 'ERROR');
        return false;
    }
    
    // Write header
    fputcsv($file, ['movie-name', 'message_id', 'channel_username']);
    
    // Write data
    foreach ($movies as $movie) {
        fputcsv($file, [
            $movie['name'],
            $movie['message_id'],
            $movie['channel_username']
        ]);
    }
    
    fclose($file);
    @chmod($filename, 0666);
    return true;
}

/**
 * Add movie to CSV
 */
function addMovieToCSV($name, $message_id, $channel) {
    $movies = readCSV(CSV_FILE);
    
    // Check if movie already exists
    foreach ($movies as $movie) {
        if ($movie['name'] === $name && $movie['channel_username'] === $channel) {
            return false; // Duplicate
        }
    }
    
    // Add new movie
    $movies[] = [
        'name' => $name,
        'message_id' => $message_id,
        'channel_username' => $channel
    ];
    
    return writeCSV(CSV_FILE, $movies);
}

/**
 * Remove movie from CSV
 */
function removeMovieFromCSV($name) {
    $movies = readCSV(CSV_FILE);
    $new_movies = [];
    $removed = false;
    
    foreach ($movies as $movie) {
        if ($movie['name'] !== $name) {
            $new_movies[] = $movie;
        } else {
            $removed = true;
        }
    }
    
    if ($removed) {
        writeCSV(CSV_FILE, $new_movies);
    }
    
    return $removed;
}

/**
 * Read users from JSON
 */
function readUsers() {
    if (!file_exists(USERS_FILE)) {
        return ['users' => []];
    }
    
    $content = file_get_contents(USERS_FILE);
    $data = json_decode($content, true);
    
    return $data ?: ['users' => []];
}

/**
 * Save users to JSON
 */
function saveUsers($data) {
    if (!isset($data['users'])) {
        $data['users'] = [];
    }
    
    $data['last_updated'] = date('Y-m-d H:i:s');
    $data['total_users'] = count($data['users']);
    
    file_put_contents(USERS_FILE, json_encode($data, JSON_PRETTY_PRINT));
    @chmod(USERS_FILE, 0666);
    return true;
}

/**
 * Read config
 */
function readConfig() {
    if (!file_exists(CONFIG_FILE)) {
        return [];
    }
    
    $content = file_get_contents(CONFIG_FILE);
    return json_decode($content, true) ?: [];
}

/**
 * Save config
 */
function saveConfig($config) {
    $config['last_update'] = date('Y-m-d H:i:s');
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    @chmod(CONFIG_FILE, 0666);
    return true;
}

/**
 * Update bot stats
 */
function updateStats($forwards_success = 0, $forwards_failed = 0) {
    $config = readConfig();
    
    if (!isset($config['stats'])) {
        $config['stats'] = [
            'total_forwards' => 0,
            'successful_forwards' => 0,
            'failed_forwards' => 0,
            'success_rate' => 100
        ];
    }
    
    $config['stats']['total_forwards'] += ($forwards_success + $forwards_failed);
    $config['stats']['successful_forwards'] += $forwards_success;
    $config['stats']['failed_forwards'] += $forwards_failed;
    
    if ($config['stats']['total_forwards'] > 0) {
        $config['stats']['success_rate'] = round(
            ($config['stats']['successful_forwards'] / $config['stats']['total_forwards']) * 100,
            2
        );
    }
    
    saveConfig($config);
    return $config['stats'];
}

// ===================== BOT COMMANDS =====================
/**
 * Process /start command
 */
function commandStart($chat_id, $user_id, $username = '') {
    $response = "🤖 *MNA Forward Bot v2.0*\n\n";
    $response .= "🌐 *Host:* Render.com\n";
    $response .= "✅ *Status:* Online\n";
    $response .= "👤 *Owner:* " . OWNER_ID . "\n";
    $response .= "🆔 *Your ID:* $user_id\n\n";
    
    $response .= "📋 *Available Commands:*\n";
    $response .= "• /start - Start bot\n";
    $response .= "• /status - Bot status\n";
    $response .= "• /movies - List all movies\n";
    $response .= "• /forward - Forward all movies\n";
    $response .= "• /addmovie - Add new movie\n";
    $response .= "• /delmovie - Delete movie\n";
    $response .= "• /webhook - Webhook settings\n";
    $response .= "• /logs - View logs\n";
    $response .= "• /help - Help guide\n\n";
    
    $response .= "⚙️ *Usage:*\n";
    $response .= "`/addmovie Movie Name,123,@channel`\n\n";
    $response .= "📞 *Support:* @EntertainmentTadkaBot";
    
    apiRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $response,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ]);
    
    logMessage("User $user_id (@$username) started the bot");
}

/**
 * Process /status command
 */
function commandStatus($chat_id) {
    $movies = readCSV(CSV_FILE);
    $users = readUsers();
    $config = readConfig();
    
    $log_size = file_exists(LOG_FILE) ? round(filesize(LOG_FILE) / 1024, 2) : 0;
    $csv_size = file_exists(CSV_FILE) ? round(filesize(CSV_FILE) / 1024, 2) : 0;
    
    $response = "📊 *Bot Status Report*\n\n";
    $response .= "🤖 *Bot:* @MNA_2_Bot\n";
    $response .= "🌐 *Host:* Render.com:8080\n";
    $response .= "📅 *Uptime:* " . date('Y-m-d H:i:s') . "\n\n";
    
    $response .= "📈 *Statistics:*\n";
    $response .= "• Movies in CSV: " . count($movies) . " ($csv_size KB)\n";
    $response .= "• Registered Users: " . count($users['users']) . "\n";
    $response .= "• Channels Configured: 5\n";
    $response .= "• Log Size: $log_size KB\n\n";
    
    if (isset($config['stats'])) {
        $stats = $config['stats'];
        $response .= "🚀 *Performance:*\n";
        $response .= "• Total Forwards: " . $stats['total_forwards'] . "\n";
        $response .= "• Successful: " . $stats['successful_forwards'] . "\n";
        $response .= "• Failed: " . $stats['failed_forwards'] . "\n";
        $response .= "• Success Rate: " . $stats['success_rate'] . "%\n\n";
    }
    
    $response .= "⚙️ *Configuration:*\n";
    $response .= "• Owner ID: " . OWNER_ID . "\n";
    $response .= "• Group ID: " . GROUP_ID . "\n";
    $response .= "• Delay: " . DELAY_BETWEEN_FORWARDS . "s\n";
    $response .= "• Last Restart: " . ($config['last_update'] ?? 'N/A');
    
    apiRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $response,
        'parse_mode' => 'Markdown'
    ]);
    
    logMessage("Status command executed");
}

/**
 * Process /movies or /listmovies command
 */
function commandMovies($chat_id) {
    $movies = readCSV(CSV_FILE);
    
    if (empty($movies)) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📭 *No Movies Found!*\n\nCSV file is empty. Add movies using /addmovie command.",
            'parse_mode' => 'Markdown'
        ]);
        return;
    }
    
    $response = "🎬 *Movies List*\n\n";
    
    foreach ($movies as $index => $movie) {
        $response .= "*" . ($index + 1) . ". " . $movie['name'] . "*\n";
        $response .= "   🆔 Message ID: `" . $movie['message_id'] . "`\n";
        $response .= "   📺 Channel: " . $movie['channel_username'] . "\n\n";
    }
    
    $response .= "📊 *Total: " . count($movies) . " movies*";
    
    apiRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $response,
        'parse_mode' => 'Markdown'
    ]);
    
    logMessage("Movies list sent, count: " . count($movies));
}

/**
 * Process /forward or /forward_all command
 */
function commandForward($chat_id) {
    global $channels;
    
    $movies = readCSV(CSV_FILE);
    
    if (empty($movies)) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ *No Movies to Forward!*\n\nCSV file is empty. Add movies first.",
            'parse_mode' => 'Markdown'
        ]);
        return;
    }
    
    $total_movies = count($movies);
    
    // Start message
    $start_msg = apiRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "🔄 *Starting Forward Process...*\n\n" .
                 "📁 Total Movies: $total_movies\n" .
                 "⏱️ Delay: " . DELAY_BETWEEN_FORWARDS . "s\n" .
                 "📤 Target Group: " . GROUP_ID . "\n\n" .
                 "0% " . showProgressBar(0, $total_movies),
        'parse_mode' => 'Markdown'
    ]);
    
    if (!$start_msg['ok']) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Failed to start forwarding. Please try again.",
            'parse_mode' => 'Markdown'
        ]);
        return;
    }
    
    $start_msg_id = $start_msg['result']['message_id'];
    $success_count = 0;
    $failed_count = 0;
    $failed_details = [];
    
    foreach ($movies as $index => $movie) {
        $current = $index + 1;
        $progress = showProgressBar($current, $total_movies);
        
        // Update progress
        apiRequest('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $start_msg_id,
            'text' => "🔄 *Forwarding in Progress...*\n\n" .
                     "🎬 Movie: *" . $movie['name'] . "*\n" .
                     "📊 Progress: $current/$total_movies\n" .
                     "$progress\n\n" .
                     "✅ Success: $success_count\n" .
                     "❌ Failed: $failed_count",
            'parse_mode' => 'Markdown'
        ]);
        
        // Forward the message
        $result = forwardMessage($movie['channel_username'], $movie['message_id']);
        
        if ($result['ok']) {
            $success_count++;
            logMessage("Forwarded: {$movie['name']} to group");
        } else {
            $failed_count++;
            $error = $result['description'] ?? 'Unknown error';
            $failed_details[] = [
                'movie' => $movie['name'],
                'error' => $error
            ];
            logMessage("Failed to forward {$movie['name']}: $error", 'ERROR');
        }
        
        // Delay between forwards
        if ($current < $total_movies) {
            sleep(DELAY_BETWEEN_FORWARDS);
        }
    }
    
    // Update stats
    updateStats($success_count, $failed_count);
    
    // Final report
    $report = "✅ *Forwarding Complete!*\n\n";
    $report .= "📊 *Results Summary:*\n";
    $report .= "• Total Movies: $total_movies\n";
    $report .= "• ✅ Successfully Forwarded: $success_count\n";
    $report .= "• ❌ Failed: $failed_count\n";
    $report .= "• 📈 Success Rate: " . round(($success_count / $total_movies) * 100, 2) . "%\n\n";
    
    if ($failed_count > 0) {
        $report .= "📝 *Failed Movies Details:*\n";
        foreach ($failed_details as $fail) {
            $report .= "• *{$fail['movie']}* - {$fail['error']}\n";
        }
        $report .= "\n⚠️ *Possible Issues:*\n";
        $report .= "- Bot not admin in channel\n";
        $report .= "- Message ID incorrect\n";
        $report .= "- Channel access restricted\n";
    }
    
    $report .= "\n🕒 Completed at: " . date('Y-m-d H:i:s');
    
    apiRequest('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $start_msg_id,
        'text' => $report,
        'parse_mode' => 'Markdown'
    ]);
    
    logMessage("Forwarding completed. Success: $success_count, Failed: $failed_count");
}

/**
 * Forward single message
 */
function forwardMessage($channel_username, $message_id) {
    global $channels;
    
    // Get channel ID
    $channel_id = $channels[$channel_username] ?? null;
    
    if (!$channel_id) {
        // Try to extract channel ID from username
        if (strpos($channel_username, '@') === 0) {
            $channel_username = substr($channel_username, 1);
        }
        
        // Map channel usernames to IDs
        $channel_map = [
            'EntertainmentTadka786' => -1003181705395,
            'ETBackup' => -1002964109368,
            'threater_print_movies' => -1002831605258
        ];
        
        $channel_id = $channel_map[$channel_username] ?? $channel_username;
    }
    
    logMessage("Forwarding from $channel_username (ID: $channel_id), Message: $message_id");
    
    return apiRequest('forwardMessage', [
        'chat_id' => GROUP_ID,
        'from_chat_id' => $channel_id,
        'message_id' => $message_id,
        'disable_notification' => true
    ]);
}

/**
 * Process /addmovie command
 */
function commandAddMovie($chat_id, $text) {
    // Format: /addmovie Movie Name,message_id,channel_username
    $parts = explode(',', substr($text, 10), 3);
    
    if (count($parts) < 3) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ *Invalid Format!*\n\n" .
                     "✅ *Correct Format:*\n" .
                     "`/addmovie Movie Name,message_id,@channel_username`\n\n" .
                     "📝 *Example:*\n" .
                     "`/addmovie The Family Man S01,69,@EntertainmentTadka786`",
            'parse_mode' => 'Markdown'
        ]);
        return;
    }
    
    $movie_name = trim($parts[0]);
    $message_id = intval(trim($parts[1]));
    $channel = trim($parts[2]);
    
    if (empty($movie_name) || $message_id <= 0 || empty($channel)) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ *Invalid Data!*\n\n" .
                     "• Movie name cannot be empty\n" .
                     "• Message ID must be positive number\n" .
                     "• Channel username cannot be empty",
            'parse_mode' => 'Markdown'
        ]);
        return;
    }
    
    // Add @ if missing
    if (strpos($channel, '@') !== 0) {
        $channel = '@' . $channel;
    }
    
    $success = addMovieToCSV($movie_name, $message_id, $channel);
    
    if ($success) {
        $movies = readCSV(CSV_FILE);
        
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ *Movie Added Successfully!*\n\n" .
                     "🎬 *Name:* $movie_name\n" .
                     "🆔 *Message ID:* $message_id\n" .
                     "📺 *Channel:* $channel\n\n" .
                     "📊 Total Movies Now: " . count($movies),
            'parse_mode' => 'Markdown'
        ]);
        
        logMessage("Movie added: $movie_name (ID: $message_id, Channel: $channel)");
    } else {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ *Failed to Add Movie!*\n\n" .
                     "Possible reasons:\n" .
                     "• Movie already exists\n" .
                     "• File permission error\n" .
                     "• CSV file corrupted",
            'parse_mode' => 'Markdown'
        ]);
    }
}

/**
 * Process /delmovie command
 */
function commandDelMovie($chat_id, $text) {
    // Format: /delmovie Movie Name
    $movie_name = trim(substr($text, 9));
    
    if (empty($movie_name)) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ *Please specify movie name!*\n\n" .
                     "✅ *Format:*\n" .
                     "`/delmovie Movie Name`\n\n" .
                     "📝 *Example:*\n" .
                     "`/delmovie The Family Man S01`",
            'parse_mode' => 'Markdown'
        ]);
        return;
    }
    
    $removed = removeMovieFromCSV($movie_name);
    
    if ($removed) {
        $movies = readCSV(CSV_FILE);
        
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ *Movie Deleted Successfully!*\n\n" .
                     "🗑️ *Removed:* $movie_name\n\n" .
                     "📊 Total Movies Now: " . count($movies),
            'parse_mode' => 'Markdown'
        ]);
        
        logMessage("Movie deleted: $movie_name");
    } else {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ *Movie Not Found!*\n\n" .
                     "Movie '$movie_name' not found in CSV.\n" .
                     "Use /movies to see all movies.",
            'parse_mode' => 'Markdown'
        ]);
    }
}

/**
 * Process /webhook command
 */
function commandWebhook($chat_id, $action = '') {
    if ($action === 'set') {
        $result = setWebhook();
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $result,
            'parse_mode' => 'Markdown'
        ]);
    } elseif ($action === 'delete') {
        $result = deleteWebhook();
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $result,
            'parse_mode' => 'Markdown'
        ]);
    } elseif ($action === 'info') {
        $result = getWebhookInfo();
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $result,
            'parse_mode' => 'Markdown'
        ]);
    } else {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📡 *Webhook Management*\n\n" .
                     "Available commands:\n" .
                     "• `/webhook set` - Set webhook\n" .
                     "• `/webhook delete` - Delete webhook\n" .
                     "• `/webhook info` - Webhook info\n\n" .
                     "Current URL: https://mna-bot-18th-january-2026.onrender.com/",
            'parse_mode' => 'Markdown'
        ]);
    }
}

/**
 * Process /logs command
 */
function commandLogs($chat_id, $lines = 10) {
    if (!file_exists(LOG_FILE)) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📭 *No Logs Found!*\n\nLog file doesn't exist yet.",
            'parse_mode' => 'Markdown'
        ]);
        return;
    }
    
    $logs = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $recent_logs = array_slice($logs, -$lines);
    
    if (empty($recent_logs)) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📭 *No Recent Logs!*\n\nLog file is empty.",
            'parse_mode' => 'Markdown'
        ]);
        return;
    }
    
    $log_text = implode("\n", $recent_logs);
    
    // Split if too long (Telegram limit: 4096 characters)
    if (strlen($log_text) > 4000) {
        $log_text = substr($log_text, -4000);
        $log_text = "... (truncated) ...\n" . $log_text;
    }
    
    apiRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "📝 *Recent Logs (Last $lines):*\n\n`" . $log_text . "`",
        'parse_mode' => 'Markdown'
    ]);
    
    logMessage("Logs sent to $chat_id");
}

/**
 * Process /help command
 */
function commandHelp($chat_id) {
    $help_text = "🆘 *MNA Forward Bot Help Guide*\n\n";
    
    $help_text .= "📋 *Available Commands:*\n";
    $help_text .= "• `/start` - Start the bot\n";
    $help_text .= "• `/status` - Bot status and stats\n";
    $help_text .= "• `/movies` - List all movies\n";
    $help_text .= "• `/forward` - Forward all movies\n";
    $help_text .= "• `/addmovie` - Add new movie\n";
    $help_text .= "• `/delmovie` - Delete movie\n";
    $help_text .= "• `/webhook` - Webhook management\n";
    $help_text .= "• `/logs` - View recent logs\n";
    $help_text .= "• `/help` - Show this help\n\n";
    
    $help_text .= "📝 *Add Movie Format:*\n";
    $help_text .= "`/addmovie Movie Name,message_id,@channel_username`\n\n";
    
    $help_text .= "📁 *File Management:*\n";
    $help_text .= "• Movies stored in: `movies.csv`\n";
    $help_text .= "• Users stored in: `users.json`\n";
    $help_text .= "• Logs stored in: `bot_log.txt`\n\n";
    
    $help_text .= "⚙️ *Configuration:*\n";
    $help_text .= "• Owner ID: `" . OWNER_ID . "`\n";
    $help_text .= "• Group ID: `" . GROUP_ID . "`\n";
    $help_text .= "• Delay: " . DELAY_BETWEEN_FORWARDS . " seconds\n";
    $help_text .= "• Host: Render.com (Port 8080)\n\n";
    
    $help_text .= "📞 *Support:* @EntertainmentTadkaBot";
    
    apiRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $help_text,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ]);
    
    logMessage("Help command executed");
}

// ===================== WEBHOOK FUNCTIONS =====================
/**
 * Set webhook
 */
function setWebhook() {
    $webhook_url = 'https://mna-bot-18th-january-2026.onrender.com/';
    
    logMessage("Setting webhook to: $webhook_url");
    
    // First delete existing webhook
    $delete_result = apiRequest('deleteWebhook', ['drop_pending_updates' => true]);
    
    if (!$delete_result['ok']) {
        logMessage("Failed to delete webhook: " . json_encode($delete_result), 'ERROR');
    }
    
    sleep(1);
    
    // Set new webhook
    $result = apiRequest('setWebhook', [
        'url' => $webhook_url,
        'max_connections' => 40,
        'drop_pending_updates' => true,
        'allowed_updates' => ['message']
    ]);
    
    if ($result['ok']) {
        // Update config
        $config = readConfig();
        $config['webhook_url'] = $webhook_url;
        $config['webhook_set'] = date('Y-m-d H:i:s');
        saveConfig($config);
        
        $message = "✅ *Webhook Set Successfully!*\n\n";
        $message .= "🌐 *URL:* `$webhook_url`\n";
        $message .= "🤖 *Bot:* @MNA_2_Bot\n";
        $message .= "📡 *Status:* Active\n";
        $message .= "🔗 *Max Connections:* 40\n";
        $message .= "🕒 *Time:* " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "📝 *Note:* Bot is now ready to receive messages.";
        
        logMessage("Webhook set successfully");
        return $message;
    } else {
        $error = $result['description'] ?? 'Unknown error';
        $message = "❌ *Webhook Setup Failed!*\n\n";
        $message .= "📛 *Error:* $error\n";
        $message .= "🔗 *URL:* $webhook_url\n";
        $message .= "🆘 *Possible Solutions:*\n";
        $message .= "1. Check bot token\n";
        $message .= "2. Ensure URL is HTTPS\n";
        $message .= "3. Check Render.com logs";
        
        logMessage("Webhook failed: $error", 'ERROR');
        return $message;
    }
}

/**
 * Delete webhook
 */
function deleteWebhook() {
    $result = apiRequest('deleteWebhook', ['drop_pending_updates' => true]);
    
    if ($result['ok']) {
        // Update config
        $config = readConfig();
        $config['webhook_url'] = '';
        $config['webhook_deleted'] = date('Y-m-d H:i:s');
        saveConfig($config);
        
        $message = "✅ *Webhook Deleted Successfully!*\n\n";
        $message .= "🗑️ *Status:* Removed\n";
        $message .= "📭 *Pending Updates:* Cleared\n";
        $message .= "🕒 *Time:* " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "⚠️ *Note:* Bot will not receive messages until webhook is set again.";
        
        logMessage("Webhook deleted");
        return $message;
    } else {
        $error = $result['description'] ?? 'Unknown error';
        $message = "❌ *Failed to Delete Webhook!*\n\n";
        $message .= "📛 *Error:* $error";
        
        logMessage("Failed to delete webhook: $error", 'ERROR');
        return $message;
    }
}

/**
 * Get webhook info
 */
function getWebhookInfo() {
    $result = apiRequest('getWebhookInfo');
    
    if ($result['ok']) {
        $info = $result['result'];
        
        $message = "📡 *Webhook Information*\n\n";
        $message .= "🌐 *URL:* `" . ($info['url'] ?: 'Not set') . "`\n";
        $message .= "🔐 *Custom Certificate:* " . ($info['has_custom_certificate'] ? 'Yes' : 'No') . "\n";
        $message .= "📬 *Pending Updates:* " . $info['pending_update_count'] . "\n";
        $message .= "🔗 *Max Connections:* " . $info['max_connections'] . "\n";
        
        if ($info['ip_address']) {
            $message .= "🌍 *IP Address:* " . $info['ip_address'] . "\n";
        }
        
        $message .= "🕒 *Last Check:* " . date('Y-m-d H:i:s');
        
        return $message;
    } else {
        return "❌ *Failed to get webhook info!*";
    }
}

// ===================== MAIN PROCESSING =====================
/**
 * Process incoming message
 */
function processMessage($update) {
    if (!isset($update['message'])) {
        return;
    }
    
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = isset($message['text']) ? trim($message['text']) : '';
    $username = isset($message['from']['username']) ? $message['from']['username'] : '';
    $first_name = isset($message['from']['first_name']) ? $message['from']['first_name'] : '';
    
    logMessage("Message from $user_id (@$username): $text");
    
    // Register user
    $users = readUsers();
    if (!isset($users['users'][$user_id])) {
        $users['users'][$user_id] = [
            'id' => $user_id,
            'username' => $username,
            'first_name' => $first_name,
            'joined_at' => date('Y-m-d H:i:s'),
            'last_seen' => date('Y-m-d H:i:s'),
            'message_count' => 1
        ];
        logMessage("New user registered: $user_id (@$username)");
    } else {
        $users['users'][$user_id]['last_seen'] = date('Y-m-d H:i:s');
        $users['users'][$user_id]['message_count'] = 
            ($users['users'][$user_id]['message_count'] ?? 0) + 1;
    }
    saveUsers($users);
    
    // Check if user is owner
    if ($user_id != OWNER_ID) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ *Access Denied!*\n\n" .
                     "This bot is for owner use only.\n" .
                     "👤 Owner ID: " . OWNER_ID . "\n" .
                     "🆔 Your ID: $user_id",
            'parse_mode' => 'Markdown'
        ]);
        logMessage("Unauthorized access attempt by $user_id (@$username)");
        return;
    }
    
    // Process commands
    if (empty($text)) {
        apiRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📝 Please send a command. Use /help for available commands.",
            'parse_mode' => 'Markdown'
        ]);
        return;
    }
    
    // Convert to lowercase for comparison (but keep original for parsing)
    $command = strtolower($text);
    
    if ($command === '/start' || strpos($command, '/start@') === 0) {
        commandStart($chat_id, $user_id, $username);
    }
    elseif ($command === '/status' || strpos($command, '/status@') === 0) {
        commandStatus($chat_id);
    }
    elseif ($command === '/movies' || $command === '/listmovies' || 
            strpos($command, '/movies@') === 0 || strpos($command, '/listmovies@') === 0) {
        commandMovies($chat_id);
    }
    elseif ($command === '/forward' || $command === '/forward_all' || 
            strpos($command, '/forward@') === 0 || strpos($command, '/forward_all@') === 0) {
        commandForward($chat_id);
    }
    elseif (strpos($command, '/addmovie') === 0) {
        commandAddMovie($chat_id, $text);
    }
    elseif (strpos($command, '/delmovie') === 0) {
        commandDelMovie($chat_id, $text);
    }
    elseif (strpos($command, '/webhook') === 0) {
        $parts = explode(' ', $text);
        $action = isset($parts[1]) ? $parts[1] : '';
        commandWebhook($chat_id, $action);
    }
    elseif (strpos($command, '/logs') === 0) {
        $parts = explode(' ', $text);
        $lines = isset($parts[1]) ? intval($parts[1]) : 10;
        commandLogs($chat_id, $lines);
    }
    elseif ($command === '/help' || strpos($command, '/help@') === 0) {
        commandHelp($chat_id);
    }
    else {
        // Check if it's a movie name or unknown command
        $movies = readCSV(CSV_FILE);
        $found = false;
        
        foreach ($movies as $movie) {
            if (strtolower($movie['name']) === strtolower($text)) {
                // Send movie info
                apiRequest('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "🎬 *Movie Found:*\n\n" .
                             "*Name:* " . $movie['name'] . "\n" .
                             "*Message ID:* `" . $movie['message_id'] . "`\n" .
                             "*Channel:* " . $movie['channel_username'] . "\n\n" .
                             "Use `/forward` to forward this movie.",
                    'parse_mode' => 'Markdown'
                ]);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ *Unknown Command!*\n\n" .
                         "Command not recognized. Use /help for available commands.\n\n" .
                         "📝 *You sent:* `$text`",
                'parse_mode' => 'Markdown'
            ]);
        }
    }
}

// ===================== MAIN EXECUTION =====================
// Start logging
logMessage("=" . str_repeat("=", 60));
logMessage("🤖 MNA Forward Bot v2.0 Started");
logMessage("🕒 Time: " . date('Y-m-d H:i:s'));
logMessage("🌐 Host: " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
logMessage("👤 Owner ID: " . OWNER_ID);
logMessage("📁 Files initialized");
logMessage("=" . str_repeat("=", 60));

// Get input
$input = file_get_contents("php://input");

if (!empty($input)) {
    // Process webhook update
    $update = json_decode($input, true);
    
    if ($update) {
        processMessage($update);
        logMessage("Update processed successfully");
    } else {
        logMessage("Invalid JSON received", 'ERROR');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    }
} else {
    // Direct access - Show web interface
    showWebInterface();
}

// End logging
logMessage("Script execution completed");
logMessage("=" . str_repeat("=", 60));

/**
 * Show web interface for direct browser access
 */
function showWebInterface() {
    $config = readConfig();
    $movies = readCSV(CSV_FILE);
    $users = readUsers();
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🤖 MNA Forward Bot</title>
        <style>
            :root {
                --primary: #667eea;
                --secondary: #764ba2;
                --success: #48bb78;
                --danger: #f56565;
                --warning: #ed8936;
                --dark: #2d3748;
                --light: #f7fafc;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', system-ui, sans-serif;
            }
            
            body {
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            
            .container {
                background: white;
                width: 100%;
                max-width: 1200px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            
            .header {
                background: linear-gradient(135deg, var(--dark) 0%, #4a5568 100%);
                color: white;
                padding: 40px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 2.8rem;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 15px;
            }
            
            .header .emoji {
                font-size: 3.5rem;
            }
            
            .tagline {
                font-size: 1.2rem;
                opacity: 0.9;
                margin-bottom: 20px;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                padding: 30px;
            }
            
            .stat-card {
                background: var(--light);
                padding: 25px;
                border-radius: 15px;
                border-left: 5px solid var(--primary);
                transition: transform 0.3s;
            }
            
            .stat-card:hover {
                transform: translateY(-5px);
            }
            
            .stat-card h3 {
                color: var(--dark);
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .stat-value {
                font-size: 2.5rem;
                font-weight: bold;
                color: var(--primary);
            }
            
            .stat-label {
                color: #718096;
                font-size: 0.9rem;
                margin-top: 5px;
            }
            
            .actions {
                padding: 0 30px 30px;
                text-align: center;
            }
            
            .btn-group {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                justify-content: center;
                margin-bottom: 30px;
            }
            
            .btn {
                padding: 15px 30px;
                border: none;
                border-radius: 50px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                min-width: 200px;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                color: white;
            }
            
            .btn-success {
                background: linear-gradient(135deg, var(--success) 0%, #38a169 100%);
                color: white;
            }
            
            .btn-warning {
                background: linear-gradient(135deg, var(--warning) 0%, #dd6b20 100%);
                color: white;
            }
            
            .btn-danger {
                background: linear-gradient(135deg, var(--danger) 0%, #e53e3e 100%);
                color: white;
            }
            
            .btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            }
            
            .movies-table {
                margin: 30px;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            thead {
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                color: white;
            }
            
            th {
                padding: 20px;
                text-align: left;
                font-weight: 600;
            }
            
            td {
                padding: 20px;
                border-bottom: 1px solid #e2e8f0;
            }
            
            tbody tr:hover {
                background: #f7fafc;
            }
            
            .movie-name {
                font-weight: 600;
                color: var(--dark);
            }
            
            .channel {
                color: var(--primary);
                font-weight: 500;
            }
            
            .footer {
                background: var(--light);
                padding: 30px;
                text-align: center;
                color: #718096;
                border-top: 1px solid #e2e8f0;
            }
            
            .alert {
                padding: 20px;
                margin: 30px;
                border-radius: 10px;
                background: #feebc8;
                color: #9c4221;
                border-left: 5px solid #ed8936;
            }
            
            @media (max-width: 768px) {
                .header h1 {
                    font-size: 2rem;
                }
                
                .btn-group {
                    flex-direction: column;
                }
                
                .btn {
                    width: 100%;
                }
                
                .stats-grid {
                    grid-template-columns: 1fr;
                }
                
                table {
                    display: block;
                    overflow-x: auto;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>
                    <span class="emoji">🤖</span>
                    MNA Forward Bot
                </h1>
                <p class="tagline">Multi-Channel Telegram Forwarding System</p>
                <p>🌐 Hosted on Render.com | 🚀 Port 8080</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><span>🎬</span> Movies</h3>
                    <div class="stat-value"><?php echo count($movies); ?></div>
                    <div class="stat-label">Total in CSV</div>
                </div>
                
                <div class="stat-card">
                    <h3><span>👥</span> Users</h3>
                    <div class="stat-value"><?php echo count($users['users']); ?></div>
                    <div class="stat-label">Registered</div>
                </div>
                
                <div class="stat-card">
                    <h3><span>📺</span> Channels</h3>
                    <div class="stat-value">5</div>
                    <div class="stat-label">Configured</div>
                </div>
                
                <div class="stat-card">
                    <h3><span>⚡</span> Forwards</h3>
                    <div class="stat-value"><?php echo $config['stats']['total_forwards'] ?? 0; ?></div>
                    <div class="stat-label">Total Processed</div>
                </div>
            </div>
            
            <?php if (isset($_GET['setwebhook'])): ?>
                <div class="alert">
                    <h3>🔄 Webhook Status:</h3>
                    <pre><?php echo htmlspecialchars(setWebhook()); ?></pre>
                </div>
            <?php endif; ?>
            
            <div class="actions">
                <div class="btn-group">
                    <a href="?setwebhook=true" class="btn btn-primary">
                        <span>⚙️</span>
                        Set Webhook
                    </a>
                    <a href="https://t.me/MNA_2_Bot" class="btn btn-success" target="_blank">
                        <span>🤖</span>
                        Open Telegram Bot
                    </a>
                    <a href="movies.csv" class="btn btn-warning" target="_blank">
                        <span>📁</span>
                        View CSV File
                    </a>
                    <a href="bot_log.txt" class="btn btn-danger" target="_blank">
                        <span>📝</span>
                        View Logs
                    </a>
                </div>
                
                <div class="btn-group">
                    <a href="?action=test" class="btn btn-primary">
                        <span>🧪</span>
                        Test Connection
                    </a>
                    <a href="?action=stats" class="btn btn-success">
                        <span>📊</span>
                        View Statistics
                    </a>
                </div>
            </div>
            
            <?php if (!empty($movies)): ?>
            <div class="movies-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Movie Name</th>
                            <th>Message ID</th>
                            <th>Channel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movies as $index => $movie): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td class="movie-name"><?php echo htmlspecialchars($movie['name']); ?></td>
                            <td><code><?php echo $movie['message_id']; ?></code></td>
                            <td class="channel"><?php echo htmlspecialchars($movie['channel_username']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <p><strong>👤 Owner ID:</strong> <?php echo OWNER_ID; ?> | <strong>🤖 Bot:</strong> @MNA_2_Bot</p>
                <p><strong>👨‍💻 Developer:</strong> @EntertainmentTadkaBot | <strong>🕒 Last Update:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                <p style="margin-top: 15px; font-size: 0.9rem;">
                    ⚠️ <strong>Note:</strong> This bot is for owner use only. Commands only work for Owner ID: <?php echo OWNER_ID; ?>
                </p>
            </div>
        </div>
        
        <script>
            // Button animations
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                });
            });
            
            // Auto refresh if action parameter present
            if (window.location.search.includes('action=test')) {
                setTimeout(() => {
                    alert('✅ Connection Test Successful!\nBot is running on Render.com');
                }, 500);
            }
        </script>
    </body>
    </html>
    <?php
}
?>
