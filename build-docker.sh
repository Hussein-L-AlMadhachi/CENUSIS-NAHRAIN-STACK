#!/bin/bash

# CENUSIS-Ops Docker Build and Export Script
# This script builds the Docker images and saves them for distribution

set -e  # Exit on error

echo "======================================"
echo "CENUSIS-Ops Docker Build Script"
echo "======================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get version from user or use default
VERSION=${1:-latest}
echo -e "${BLUE}Building version: ${VERSION}${NC}"
echo ""

# Build images
echo -e "${YELLOW}Step 1: Building Docker images...${NC}"
docker-compose build --no-cache

# Tag images
echo ""
echo -e "${YELLOW}Step 2: Tagging images...${NC}"
docker tag ced-backend:latest cenusis-backend:${VERSION}
docker tag ced-frontend:latest cenusis-frontend:${VERSION}
echo -e "${GREEN}✓ Images tagged${NC}"

# Create output directory
OUTPUT_DIR="./docker-images"
mkdir -p ${OUTPUT_DIR}

# Save images
echo ""
echo -e "${YELLOW}Step 3: Saving images to tar files...${NC}"

echo "  - Saving backend image..."
docker save -o ${OUTPUT_DIR}/cenusis-backend-${VERSION}.tar cenusis-backend:${VERSION}

echo "  - Saving frontend image..."
docker save -o ${OUTPUT_DIR}/cenusis-frontend-${VERSION}.tar cenusis-frontend:${VERSION}

echo "  - Saving PostgreSQL image..."
docker pull postgres:16-alpine
docker save -o ${OUTPUT_DIR}/postgres-16-alpine.tar postgres:16-alpine

echo "  - Creating combined archive..."
docker save -o ${OUTPUT_DIR}/cenusis-all-${VERSION}.tar \
    cenusis-backend:${VERSION} \
    cenusis-frontend:${VERSION} \
    postgres:16-alpine

echo -e "${GREEN}✓ Images saved to ${OUTPUT_DIR}/${NC}"

# Calculate sizes
echo ""
echo -e "${YELLOW}Image sizes:${NC}"
ls -lh ${OUTPUT_DIR}/*.tar | awk '{print "  " $9 ": " $5}'

# Create load script
echo ""
echo -e "${YELLOW}Step 4: Creating load script...${NC}"
cat > ${OUTPUT_DIR}/load-images.sh << 'EOF'
#!/bin/bash

# CENUSIS-Ops Docker Image Loader
# This script loads the Docker images on a new machine

set -e

echo "======================================"
echo "Loading CENUSIS-Ops Docker Images"
echo "======================================"
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if docker-compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "Warning: docker-compose is not installed. You'll need it to run the application."
fi

# Load the combined image
if [ -f "cenusis-all-*.tar" ]; then
    echo "Loading all images from combined archive..."
    docker load -i cenusis-all-*.tar
    echo "✓ All images loaded successfully!"
else
    echo "Loading individual images..."
    
    if [ -f "cenusis-backend-*.tar" ]; then
        echo "  - Loading backend image..."
        docker load -i cenusis-backend-*.tar
    fi
    
    if [ -f "cenusis-frontend-*.tar" ]; then
        echo "  - Loading frontend image..."
        docker load -i cenusis-frontend-*.tar
    fi
    
    if [ -f "postgres-16-alpine.tar" ]; then
        echo "  - Loading PostgreSQL image..."
        docker load -i postgres-16-alpine.tar
    fi
    
    echo "✓ Images loaded successfully!"
fi

echo ""
echo "======================================"
echo "Next Steps:"
echo "======================================"
echo "1. Copy the docker-compose.yml file to your deployment directory"
echo "2. Run: docker-compose up -d"
echo "3. Initialize the database:"
echo "   docker-compose exec backend node ./dist/cli/create.js"
echo "   docker-compose exec backend node ./dist/cli/admin.js"
echo ""
echo "For more information, see README.docker.md"
EOF

chmod +x ${OUTPUT_DIR}/load-images.sh
echo -e "${GREEN}✓ Load script created${NC}"

# Create README
cat > ${OUTPUT_DIR}/README.txt << EOF
CENUSIS-Ops Docker Images
=========================

This directory contains Docker images for the CENUSIS-Ops application.

Files:
------
- cenusis-all-${VERSION}.tar       : Combined archive with all images (recommended)
- cenusis-backend-${VERSION}.tar   : Backend service image only
- cenusis-frontend-${VERSION}.tar  : Frontend service image only
- postgres-16-alpine.tar           : PostgreSQL database image
- load-images.sh                   : Script to load images on target machine

Quick Start:
-----------
1. Copy this entire directory to your target machine
2. Run: ./load-images.sh
3. Copy docker-compose.yml from the project root
4. Run: docker-compose up -d

For detailed instructions, see README.docker.md in the project root.

Version: ${VERSION}
Built: $(date)
EOF

echo ""
echo -e "${GREEN}======================================"
echo "✓ Build Complete!"
echo "======================================${NC}"
echo ""
echo "Docker images have been saved to: ${OUTPUT_DIR}/"
echo ""
echo "To distribute:"
echo "  1. Copy the '${OUTPUT_DIR}' directory to your target machine"
echo "  2. Copy 'docker-compose.yml' to the same location"
echo "  3. Run './load-images.sh' on the target machine"
echo "  4. Run 'docker-compose up -d'"
echo ""
echo "Or compress for transfer:"
echo "  tar -czf cenusis-docker-${VERSION}.tar.gz ${OUTPUT_DIR}/ docker-compose.yml README.docker.md"
echo ""
