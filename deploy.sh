#!/bin/bash
# Telegram Bot Deployment Script for Render.com

echo "🚀 Telegram Bot Deployment Started..."

# Create necessary files if they don't exist
touch users.json movies.csv bot_log.txt error.log

# Set permissions
chmod 777 users.json movies.csv bot_log.txt error.log

# Check if files are empty and add default content
if [ ! -s users.json ]; then
    echo '{"users": {}, "owner_id": 1080317415, "bot_username": "@MNA_2_Bot", "last_updated": ""}' > users.json
fi

if [ ! -s movies.csv ]; then
    echo 'movie-name,message_id,channel_username
The Family Man S01 2019,69,@EntertainmentTadka786
The Family Man S02 2022,67,@EntertainmentTadka786
The Family Man S03 2025,73,@EntertainmentTadka786' > movies.csv
fi

if [ ! -s bot_log.txt ]; then
    echo '# Telegram Bot Log File
# Created: 2026-01-18
# Bot: @MNA_2_Bot
# Owner: 1080317415

[2026-01-18 00:00:00] Log file initialized
[2026-01-18 00:00:00] Bot ready for deployment on Render.com' > bot_log.txt
fi

echo "✅ All files checked and created"
echo "📁 Files in directory:"
ls -la
