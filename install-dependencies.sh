#!/bin/bash
#
# Installation script for X402 Paywall Plugin dependencies
# 
# This script installs the required PHP dependencies for the plugin.
#

set -e

echo "X402 Paywall - Dependency Installation Script"
echo "=============================================="
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Error: Composer is not installed."
    echo "Please install Composer from https://getcomposer.org/"
    exit 1
fi

# Check PHP version
php_version=$(php -r 'echo PHP_VERSION;')
required_version="8.1"

if [ "$(printf '%s\n' "$required_version" "$php_version" | sort -V | head -n1)" != "$required_version" ]; then
    echo "Error: PHP $required_version or higher is required. You have PHP $php_version"
    exit 1
fi

echo "✓ Composer found"
echo "✓ PHP $php_version (>= $required_version required)"
echo ""

# Try to install dependencies
echo "Installing dependencies..."
echo ""

if composer install --no-dev --optimize-autoloader; then
    echo ""
    echo "✓ Dependencies installed successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Activate the plugin in WordPress admin"
    echo "2. Configure your payment addresses in your user profile"
    echo "3. Start creating paywalls!"
else
    echo ""
    echo "⚠ Installation encountered issues."
    echo ""
    echo "If you see GitHub API rate limit errors, you have two options:"
    echo ""
    echo "Option 1: Create a GitHub token"
    echo "  1. Visit: https://github.com/settings/tokens/new?scopes=&description=Composer"
    echo "  2. Generate the token"
    echo "  3. Run: composer config -g github-oauth.github.com YOUR_TOKEN"
    echo "  4. Run this script again"
    echo ""
    echo "Option 2: Wait and try again later"
    echo "  GitHub resets API rate limits every hour"
    echo ""
    exit 1
fi
