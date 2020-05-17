#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

export WP_DEVELOP_DIR=/tmp/wordpress/

cat /tmp/wordpress/wp-tests-config.php

# Verify mysql
# mysql -e 'show databases;' -h 127.0.0.1 -uroot --password=""

./vendor/bin/phpunit --verbose

# Stop printing commands to screen
set +x
