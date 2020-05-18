#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

export WP_DEVELOP_DIR=/tmp/wordpress/

# sudo apt-get update
sudo -E docker-php-ext-install mysqli
sudo apt-get install mysql-client

./vendor/bin/phpunit --verbose

# Stop printing commands to screen
set +x
