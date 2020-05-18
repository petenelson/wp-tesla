#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

export WP_DEVELOP_DIR=/tmp/wordpress/

# cat /tmp/wordpress/wp-tests-config.php

# echo "Updating APT Sources ..."
# sudo rm -rf /var/lib/apt/lists/*
# sudo sh -c "printf '\ndeb http://ftp.us.debian.org/debian sid main\n' >> /etc/apt/sources.list"
# apt-get -y update
# apt-get -y install libc6-dev libicu-dev libxml2-dev

apt-get update
-E docker-php-ext-install mysqli
apt-get install mysql-client

# Verify mysql
mysql -e 'show databases;' -h 127.0.0.1 -uroot --password=""

./vendor/bin/phpunit --verbose

# Stop printing commands to screen
set +x
