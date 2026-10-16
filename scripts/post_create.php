<?php
const GITHUB_FOLDER = '.github';
const USIM_INSTRUCTIONS_FILE = 'copilot-instructions.md';
const USIM_FRAMEWORK_REPOSITORY_URL = 'https://raw.githubusercontent.com/idei/usim-project/refs/heads/main/.github/';

function run($cmd) {
    echo "\n> $cmd\n";
    passthru($cmd, $exit);
    if ($exit !== 0) exit($exit);
}

function getCopilotInstructions() {
    $usimFrameworkRepositoryUrl = USIM_FRAMEWORK_REPOSITORY_URL;
    $instructionsUrl = $usimFrameworkRepositoryUrl .
        USIM_INSTRUCTIONS_FILE;
    echo "Fetching USIM Copilot instructions from oficial repository\n";
    $markdown = file_get_contents($instructionsUrl);
    if ($markdown === false) {
        echo "Error fetching instructions.\n";
        return null;
    }
    return $markdown;
}

function saveInstructionsToFile($markdown) {
    // Ensure .github directory exists
    if (!is_dir(GITHUB_FOLDER)) {
        mkdir(GITHUB_FOLDER, 0755, true);
    }
    echo "Current folder: " . getcwd() . "\n";
    $filename = GITHUB_FOLDER . '/' . USIM_INSTRUCTIONS_FILE;
    file_put_contents($filename, $markdown);
    echo "Instructions saved to $filename\n";
}

echo "\n=== USIM Project Bootstrap ===\n";

// .env
if (!file_exists('.env')) {
    copy('.env.example', '.env');
    echo ".env creado\n";
}

// key
run("php artisan key:generate");

// Install Spatie Laravel-Permission
run("composer require spatie/laravel-permission --no-interaction");
run('php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction');

// Enable teams support in permission config
$permissionConfigFile = 'config/permission.php';
$permissionConfig = file_get_contents($permissionConfigFile);
$permissionConfig = str_replace("'teams' => false,", "'teams' => true,", $permissionConfig);
$permissionConfig = str_replace("'team_foreign_key' => 'team_id',", "'team_foreign_key' => 'usim_unit_id',", $permissionConfig);
file_put_contents($permissionConfigFile, $permissionConfig);
echo "Teams support enabled in $permissionConfigFile\n";

// Remove default User.php model
$userModelFile = 'app/Models/User.php';
if (file_exists($userModelFile)) {
    unlink($userModelFile);
    echo "Default User model removed: $userModelFile\n";
}

// Install USIM dependencies and publish assets
run("composer require idei/usim -W");
run("php artisan usim:install");
    
// Fetch and save Copilot instructions
$markdown = getCopilotInstructions();
if ($markdown !== null) {
    saveInstructionsToFile($markdown);
} else {
    echo "Unable to fetch USIM Copilot instructions.\n";
}

// last step: instructions for the user
echo "\nProject Ready for USIM:\n";
echo "Start the server:\n";
echo "./start.sh [-r]\n";
echo "Note: -r, removes existing database and start fresh.\n";
