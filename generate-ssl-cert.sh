#!/bin/bash

# Generate Self-Signed SSL Certificate for CENUSIS-Ops
# This script creates a self-signed certificate for testing/development

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "======================================"
echo "SSL Certificate Generator"
echo "======================================"
echo ""

# Get domain/hostname from user
read -p "Enter domain name or IP (default: localhost): " DOMAIN
DOMAIN=${DOMAIN:-localhost}

# Get validity period
read -p "Certificate validity in days (default: 365): " DAYS
DAYS=${DAYS:-365}

# Create SSL directory
echo ""
echo -e "${BLUE}Creating SSL directory...${NC}"
mkdir -p ssl

# Generate certificate
echo -e "${BLUE}Generating self-signed certificate...${NC}"
openssl req -x509 -nodes -days $DAYS -newkey rsa:2048 \
  -keyout ssl/cert.key \
  -out ssl/cert.crt \
  -subj "/C=US/ST=State/L=City/O=CENUSIS-Ops/CN=$DOMAIN"

# Set permissions
chmod 600 ssl/cert.key
chmod 644 ssl/cert.crt

echo -e "${GREEN}✓ Certificate generated successfully!${NC}"
echo ""

# Show certificate info
echo -e "${BLUE}Certificate Information:${NC}"
openssl x509 -in ssl/cert.crt -text -noout | grep -A 2 "Subject:"
openssl x509 -in ssl/cert.crt -text -noout | grep -A 2 "Validity"

echo ""
echo "======================================"
echo -e "${GREEN}Next Steps:${NC}"
echo "======================================"
echo ""
echo "1. Edit frontend/nginx.conf:"
echo "   - Uncomment the HTTPS server block (lines 46-108)"
echo ""
echo "2. Edit docker-compose.yml:"
echo "   - Uncomment port 443"
echo "   - Uncomment the SSL volumes section"
echo ""
echo "3. Restart Docker:"
echo "   docker-compose down"
echo "   docker-compose up -d --build"
echo ""
echo "4. Access via HTTPS:"
echo "   https://$DOMAIN"
echo ""
echo -e "${YELLOW}Note: Browsers will show a security warning because"
echo "the certificate is self-signed. This is normal for"
echo "development/testing.${NC}"
echo ""
echo "For production, use Let's Encrypt instead."
echo "See HTTPS-SETUP.md for details."
echo ""
