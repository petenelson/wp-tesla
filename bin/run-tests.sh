#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

export WP_DEVELOP_DIR=/tmp/wordpress/

./vendor/bin/phpunit

# Stop printing commands to screen
set +x
