#!/bin/bash

#########################################################
# Optional: Install NVM for specific user
# Use this if you need multiple Node.js versions
#########################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}→ $1${NC}"
}

# Get target user (default: www-data)
TARGET_USER=${1:-www-data}

print_info "Installing NVM for user: $TARGET_USER"

# Get user's home directory
USER_HOME=$(eval echo ~$TARGET_USER)

if [ ! -d "$USER_HOME" ]; then
    print_error "Home directory not found for $TARGET_USER"
    exit 1
fi

print_info "Home directory: $USER_HOME"

# Install NVM for the user
sudo -u $TARGET_USER bash << 'EOF'
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash

# Load NVM
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

# Install Node.js versions
nvm install 16
nvm install 18  
nvm install 20
nvm install 21

# Set default
nvm alias default 20

# Install PM2 for each version
for version in 16 18 20 21; do
    nvm use $version
    npm install -g pm2
done

nvm use 20
EOF

# Add NVM to shell profile
cat >> "$USER_HOME/.bashrc" << 'EOF'

# NVM Configuration
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
[ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"
EOF

print_success "NVM installed for $TARGET_USER"
print_info "To use NVM as $TARGET_USER:"
echo "  sudo -u $TARGET_USER -i"
echo "  nvm use 16  # Switch to Node 16"
echo "  nvm use 20  # Switch to Node 20"
