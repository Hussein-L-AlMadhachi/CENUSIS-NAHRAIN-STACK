# CENUSIS-Ops Docker Quick Reference

## Quick Start

```bash
# 1. Build and start all services
docker-compose up -d

# 2. Initialize database
docker-compose exec backend php ./cli/create.php

# 3. Create admin user
docker-compose exec backend php ./cli/admin.php
```

Access the app at: **http://localhost**

---

## Build Images for Distribution

```bash
# Build and export all images
./build-docker.sh

# Or with version tag
./build-docker.sh v1.0.0
```

This creates a `docker-images/` directory with:
- `cenusis-all-*.tar` - All images in one file (easiest)
- Individual image files
- `load-images.sh` - Script to load images on another machine

---

## Common Commands

### Start/Stop
```bash
docker-compose up -d          # Start all services
docker-compose down           # Stop all services
docker-compose restart        # Restart all services
```

### View Logs
```bash
docker-compose logs -f                # All services
docker-compose logs -f backend        # Backend only
docker-compose logs -f frontend       # Frontend only
docker-compose logs -f mysql       # Database only
```

### Check Status
```bash
docker-compose ps             # List running containers
docker-compose top            # Show running processes
```

### Execute Commands
```bash
# Access backend shell
docker-compose exec backend sh

# Access database
docker-compose exec mysql mysql -u dev -pcenusis_ops cenusis_ops

# Run backend scripts
docker-compose exec backend php ./cli/create.php
docker-compose exec backend php ./cli/admin.php
docker-compose exec backend php ./cli/alter.php
```

---

## Rebuild After Code Changes

```bash
# Rebuild and restart
docker-compose up -d --build

# Or rebuild specific service
docker-compose up -d --build backend
docker-compose up -d --build frontend
```

---

## Database Operations

### Backup
```bash
docker-compose exec mysql mysqldump -u dev -pcenusis_ops cenusis_ops > backup.sql
```

### Restore
```bash
docker-compose exec -T mysql mysql -u dev -pcenusis_ops cenusis_ops < backup.sql
```

### Reset Database
```bash
docker-compose down -v        # Remove volumes (deletes data!)
docker-compose up -d          # Start fresh
docker-compose exec backend php ./cli/create.php
```

---

## Distribution

### Create distribution package
```bash
./build-docker.sh v1.0.0
tar -czf cenusis-v1.0.0.tar.gz docker-images/ docker-compose.yml README.docker.md
```

### On target machine
```bash
# Extract
tar -xzf cenusis-v1.0.0.tar.gz

# Load images
cd docker-images
./load-images.sh

# Start application
cd ..
docker-compose up -d

# Initialize
docker-compose exec backend php ./cli/create.php
docker-compose exec backend php ./cli/admin.php
```

---

## Troubleshooting

### Port already in use
```bash
# Check what's using the port
sudo lsof -i :80
sudo lsof -i :3000
sudo lsof -i :3306

# Change ports in docker-compose.yml
```

### Backend can't connect to database
```bash
# Check database is healthy
docker-compose ps mysql

# Check database logs
docker-compose logs mysql

# Restart backend
docker-compose restart backend
```

### Frontend shows 502 Bad Gateway
```bash
# Check backend is running
docker-compose ps backend

# Check backend logs
docker-compose logs backend

# Restart services
docker-compose restart backend frontend
```

### Clean everything and start fresh
```bash
docker-compose down -v
docker system prune -a
docker-compose up -d --build
```

---

## Access Points

- **Frontend**: http://localhost
- **Backend API**: http://localhost:3000/api
- **MySQL**: localhost:3306

---

## File Structure

```
CED/
├── docker-compose.yml          # Main orchestration file
├── .env.example                # Environment variables template
├── build-docker.sh             # Build and export script
├── README.docker.md            # Detailed documentation
├── DOCKER-QUICKREF.md          # This file
├── backend/
│   ├── Dockerfile              # Backend image definition
│   └── .dockerignore           # Files to exclude
└── frontend/
    ├── Dockerfile              # Frontend image definition
    ├── nginx.conf              # Nginx configuration
    └── .dockerignore           # Files to exclude
```

---

## Security Checklist for Production

- [ ] Change `JWT_SECRET` in `.env`
- [ ] Change MySQL password
- [ ] Set up HTTPS/SSL certificates
- [ ] Configure firewall rules
- [ ] Set up database backups
- [ ] Review nginx security headers
- [ ] Use Docker secrets for sensitive data
- [ ] Keep images updated

---

For more details, see **README.docker.md**
