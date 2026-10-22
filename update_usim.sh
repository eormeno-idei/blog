#!/bin/bash

clear
composer update idei/usim
php artisan vendor:publish --tag=usim-assets --force