#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

composer install
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh | bash
nvm use 16
npm install
npm run build

# Stop printing commands to screen
set +x
