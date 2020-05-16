#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

# Set up WordPress installation.
export WP_DEVELOP_DIR=/tmp/wordpress/
export WP_VERSION=5.4.1

mkdir -p $WP_DEVELOP_DIR

# Use the Git mirror of WordPress.
git clone --depth=1 --branch="$WP_VERSION" git://develop.git.wordpress.org/ $WP_DEVELOP_DIR

# Set up WordPress configuration.
pushd $WP_DEVELOP_DIR
echo $WP_DEVELOP_DIR

cp wp-tests-config-sample.php wp-tests-config.php

sed -i "s/youremptytestdbnamehere/wordpress_test/" wp-tests-config.php
sed -i "s/yourusernamehere/root/" wp-tests-config.php
sed -i "s/yourpasswordhere//" wp-tests-config.php

sudo apt update
sudo apt install -y default-mysql-client

# Create WordPress database.
mysql -e 'CREATE DATABASE wordpress_test;' -hlocalhost -uroot

# Switch back to the plugin dir
popd

# Stop printing commands to screen
set +x
