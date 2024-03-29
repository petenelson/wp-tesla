#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

ssh-keyscan -H petenelson.io >> ~/.ssh/known_hosts

sudo apt update
sudo apt install -y rsync

ls -la
rsync -vrxc --delete ./ circleci-deploy@petenelson.io:/var/www/wordpress/petenelson.io/wp-content/plugins/wp-tesla/ --exclude-from=./bin/rsync-excludes.txt

# Stop printing commands to screen
set +x
