#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

./vendor/bin/phpunit

# Stop printing commands to screen
set +x
