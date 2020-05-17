#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

apt-get update
apt install -y nodejs npm

composer install
npm install
npm run build

# Stop printing commands to screen
set +x
