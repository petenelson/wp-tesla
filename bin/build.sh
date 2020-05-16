#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

sudo apt update # PHP CircleCI 2.0 Configuration File# PHP CircleCI 2.0 Configuration File sudo apt install zlib1g-dev libsqlite3-dev

composer install
npm install
npm run build

# Stop printing commands to screen
set +x
