#!/bin/bash

clear

# Function to get a value from .env file (non-commented lines)
get_env_value() {
    local key="$1"
    grep "^$key=" .env 2>/dev/null | head -1 | cut -d'=' -f2- | sed 's/^"\(.*\)"$/\1/'
}

env_db=$(get_env_value "DB_CONNECTION")

if [[ "$env_db" == "mysql" ]]; then
    # Get the .env DB values that do not start with '#'
    db=$(get_env_value "DB_DATABASE")
    user=$(get_env_value "DB_USERNAME")
    pass=$(get_env_value "DB_PASSWORD")

    echo "Removing Database: $db with $user privileges"
    mysql -u "$user" -p"$pass" -e "DROP DATABASE IF EXISTS $db; CREATE DATABASE $db;"
fi

if [[ "$env_db" == "sqlite" ]]; then
    # Remove the database
    rm -f database/database.sqlite
fi

php artisan migrate --force

php artisan db:seed --no-interaction
php artisan usim:discover
php artisan usim:sync

# Clear cache before starting the server
echo "Clearing cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Open the browser with the application URL
app_url=$(get_env_value "APP_URL")

if [[ -n "$app_url" ]]; then
    echo "Opening browser with URL: $app_url"
    if [[ -n "$BROWSER" ]]; then
        "$BROWSER" "$app_url" 2>/dev/null || true
    elif command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$app_url"
    elif command -v open >/dev/null 2>&1; then
        open "$app_url"
    else
        echo "Could not detect the web browser to open. Please open the URL manually: $app_url"
    fi
else
    echo "APP_URL not found in .env file. Please open the application manually."
fi
