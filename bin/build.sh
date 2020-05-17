#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

composer install
npm install
npm run build

# Stop printing commands to screen
set +x
