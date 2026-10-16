<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Modals',
    'icon' => '🖼️',
    'actions' => [
        'open_confirmation' => 'Open Confirmation Dialog',
        'open_error' => 'Open Error Dialog',
        'open_timeout_with_button' => 'Open Timeout Dialog (10 sec)',
        'open_timeout_without_button' => 'Open Timeout Without Button',
        'settings' => 'Settings',
        'open_user_modal' => 'Open Screen as Modal',
    ],
    'auto_close_dialog' => [
        'message' => 'This dialog will close automatically in:',
        'title' => 'Auto close',
    ],
    'confirm_dialog' => [
        'cancel_label' => 'No, Cancel',
        'confirm_label' => 'Yes, Proceed',
        'message' => 'Are you sure you want to proceed with this action?',
        'title' => 'Confirm Action',
    ],
    'error_dialog' => [
        'message' => 'Could not connect to the server.
Please verify your internet connection and try again.',
        'title' => 'Connection Error',
    ],
    'instruction' => 'Click the button below to open a confirmation dialog:',
    'result' => [
        'cancelled' => 'Action cancelled by user',
        'confirmed' => 'Action confirmed! Type: :type',
        'user_saved' => 'User saved from modal: :name (:role, :email)',
    ],
    'user_modal' => [
        'title' => 'User Form (Screen Modal)',
        'description' => 'This screen was opened as a modal overlay via openModal().',
        'name_label' => 'Full Name',
        'name_placeholder' => 'e.g. John Doe',
        'name_required' => 'The name field is required',
        'email_label' => 'Email Address',
        'email_placeholder' => 'e.g. john@example.com',
        'role_label' => 'User Role',
        'cancel' => 'Cancel',
        'submit' => 'Save and Return',
    ],
    'settings_dialog' => [
        'message' => 'Do you want to reset settings?
This action cannot be undone.',
        'title' => 'Settings',
    ],
    'success_dialog' => [
        'message' => 'Settings were reset successfully.',
        'title' => 'Done!',
    ],
    'timeout_dialog' => [
        'message' => 'This message will auto-close in:',
        'title' => 'Temporary Notification',
    ],
    'title' => 'Modal Component Demo',
];
