#!/bin/bash

# CENUSIS-Ops Docker Health Check Script
# This script checks if all services are running correctly

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo "======================================"
echo "CENUSIS-Ops Health Check"
echo "======================================"
echo ""

# Function to check if a service is running
check_service() {
    local service=$1
    local status=$(docker-compose ps -q $service 2>/dev/null)
    
    if [ -z "$status" ]; then
        echo -e "${RED}✗${NC} $service: Not running"
        return 1
    else
        local health=$(docker inspect --format='{{.State.Health.Status}}' $(docker-compose ps -q $service) 2>/dev/null || echo "unknown")
        if [ "$health" = "healthy" ] || [ "$health" = "unknown" ]; then
            echo -e "${GREEN}✓${NC} $service: Running"
            return 0
        else
            echo -e "${YELLOW}⚠${NC} $service: Running but unhealthy"
            return 1
        fi
    fi
}

# Function to check HTTP endpoint
check_http() {
    local name=$1
    local url=$2
    
    if curl -sf "$url" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} $name: Responding"
        return 0
    else
        echo -e "${RED}✗${NC} $name: Not responding"
        return 1
    fi
}

# Function to check PostgreSQL
check_postgres() {
    if docker-compose exec -T postgres pg_isready -U dev -d cenusis_ops > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} PostgreSQL: Ready"
        return 0
    else
        echo -e "${RED}✗${NC} PostgreSQL: Not ready"
        return 1
    fi
}

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Error: docker-compose is not installed${NC}"
    exit 1
fi

# Check services
echo -e "${BLUE}Checking Docker Services:${NC}"
check_service postgres
check_service backend
check_service frontend
echo ""

# Check PostgreSQL
echo -e "${BLUE}Checking Database:${NC}"
check_postgres
echo ""

# Check HTTP endpoints
echo -e "${BLUE}Checking HTTP Endpoints:${NC}"
check_http "Frontend (Port 80)" "http://localhost"
check_http "Backend API" "http://localhost:3000/api/public"
echo ""

# Check disk usage
echo -e "${BLUE}Docker Disk Usage:${NC}"
docker system df
echo ""

# Show container stats
echo -e "${BLUE}Container Resource Usage:${NC}"
docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}" $(docker-compose ps -q) 2>/dev/null || echo "No containers running"
echo ""

# Summary
echo "======================================"
echo -e "${GREEN}Health Check Complete${NC}"
echo "======================================"
echo ""
echo "To view logs: docker-compose logs -f"
echo "To restart:   docker-compose restart"
echo ""
