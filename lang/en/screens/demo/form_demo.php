<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Forms',
    'icon' => '📝',
    'actions' => [
        'submit' => 'Submit Form',
    ],
    'email' => [
        'label' => 'Email',
        'placeholder' => 'Enter your email',
    ],
    'instruction' => 'Fill out the form below (all fields are required):',
    'name' => [
        'label' => 'Name',
        'placeholder' => 'Enter your name',
    ],
    'result' => [
        'errors' => 'Please fix the errors above',
        'initial' => 'Fill the form to continue',
        'success' => 'Form submitted successfully!

Name: :name
Email: :email',
    ],
    'title' => 'Form Component Demo',
    'toast' => [
        'correct_errors' => 'Please correct the errors in the form',
    ],
    'validation' => [
        'email_invalid' => 'Email is invalid',
        'email_required' => 'Email is required',
        'name_min' => 'Name must be at least 2 characters',
        'name_required' => 'Name is required',
    ],
];
