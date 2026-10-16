<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Email Verified',
    'icon' => '',
    'actions' => [
        'go_to_home' => 'Back to Home',
        'go_to_login' => 'Go to Login',
        'resend' => 'Request New Link',
    ],
    'already_verified' => [
        'card_description' => 'Your account is already active. You can sign in normally.',
        'card_title' => 'Email Verified',
        'message' => 'Your email has already been verified',
    ],
    'error' => [
        'card_description' => 'The verification link may have expired or is invalid. Please request a new verification link.',
        'card_title' => 'Verification Failed',
        'message' => 'Error verifying email',
    ],
    'errors' => [
        'expired' => 'The verification link has expired. Request a new one.',
        'generic' => 'Could not verify the email. The link may have expired or is invalid.',
        'invalid_params' => 'Invalid verification link. Required parameters are missing.',
    ],
    'failed_title' => 'Verification Failed',
    'go_to_login' => 'Go to Login',
    'loading' => 'Verifying your email...',
    'request_new_link' => 'Please log in to request a new verification link',
    'title' => 'Verification Complete',
    'toast' => [
        'redirect_to_login' => 'Please sign in to request a new verification link',
    ],
    'verified' => [
        'card_description' => 'Your account has been activated successfully. You can now sign in and use all available services.',
        'card_title' => 'Verification Complete',
        'message' => 'Your email has been verified successfully',
        'subtitle' => 'You can now access all platform features',
    ],
    'verified_title' => 'Email Verified',
];
