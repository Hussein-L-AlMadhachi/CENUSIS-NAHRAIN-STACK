# 🐳 Docker Setup Complete!

Your CENUSIS-Ops application is now fully Dockerized! Here's what has been created:

## 📦 Created Files

### Core Docker Files
- ✅ `docker-compose.yml` - Orchestrates MySQL, Backend, and Frontend
- ✅ `backend/Dockerfile` - PHP 8.3 fpm + nginx backend image
- ✅ `frontend/Dockerfile` - Multi-stage build for React frontend with nginx
- ✅ `frontend/nginx.conf` - Nginx configuration for serving React and proxying API

### Configuration Files
- ✅ `.env.example` - Environment variables template
- ✅ `.dockerignore` - Root level ignore file
- ✅ `backend/.dockerignore` - Backend specific ignore file
- ✅ `frontend/.dockerignore` - Frontend specific ignore file

### Scripts
- ✅ `build-docker.sh` - Build and export images for distribution
- ✅ `health-check.sh` - Check if all services are healthy

### Documentation
- ✅ `README.docker.md` - Comprehensive Docker documentation
- ✅ `DOCKER-QUICKREF.md` - Quick reference guide
- ✅ `DOCKER-SETUP-SUMMARY.md` - This file

### Code Updates
- ✅ Updated `backend/src/db.ts` to use environment variables

## 🚀 Quick Start

### Option 1: Run Locally with Docker

```bash
# Start all services
docker-compose up -d

# Initialize database
docker-compose exec backend php ./cli/create.php
docker-compose exec backend php ./cli/admin.php

# Access the app
open http://localhost
```

The admin script seeds `admin` and `superadmin` accounts with the default
password `change-me-123` (override via `DEFAULT_ADMIN_PASSWORD` /
`DEFAULT_SUPERADMIN_PASSWORD`). These accounts are forced to change their
password on first login.

### Option 2: Build for Distribution

```bash
# Build and export images
./build-docker.sh v1.0.0

# This creates docker-images/ directory with:
# - All images as tar files
# - Load script for target machine
# - README with instructions

# Create distribution package
tar -czf cenusis-v1.0.0.tar.gz docker-images/ docker-compose.yml README.docker.md
```

## 🏗️ Architecture

```
┌─────────────────────────────────────────────┐
│              User Browser                    │
└──────────────────┬──────────────────────────┘
                   │ HTTP :80
                   ▼
┌─────────────────────────────────────────────┐
│         Nginx (Frontend Container)          │
│  - Serves React static files                │
│  - Proxies /api/* to backend                │
└──────────────────┬──────────────────────────┘
                   │ /api/* → :4000
                   ▼
┌─────────────────────────────────────────────┐
│        PHP (Backend Container)              │
│  - PHP 8.3 (nginx + php-fpm)                │
│  - REST API endpoints                       │
│  - Business logic                           │
└──────────────────┬──────────────────────────┘
                   │ :3306
                   ▼
┌─────────────────────────────────────────────┐
│       MySQL (Database Container)       │
│  - Persistent data storage                  │
│  - Volume mounted for data persistence      │
└─────────────────────────────────────────────┘
```

## 📋 Services

| Service    | Port | Description                          |
|------------|------|--------------------------------------|
| Frontend   | 80   | React app served by nginx            |
| Backend    | 4000 | PHP API server                       |
| MySQL      | 3306 | Database server                      |

## 🔧 Common Commands

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f

# Rebuild after code changes
docker-compose up -d --build

# Check health
./health-check.sh

# Access database
docker-compose exec mysql mysql -u dev -pcenusis_ops cenusis_ops

# Backup database
docker-compose exec mysql mysqldump -u dev -pcenusis_ops cenusis_ops > backup.sql

# Restore database
docker-compose exec -T mysql mysql -u dev -pcenusis_ops cenusis_ops < backup.sql
```

## 📤 Distribution Workflow

1. **Build images:**
   ```bash
   ./build-docker.sh v1.0.0
   ```

2. **Create package:**
   ```bash
   tar -czf cenusis-v1.0.0.tar.gz docker-images/ docker-compose.yml README.docker.md
   ```

3. **On target machine:**
   ```bash
   tar -xzf cenusis-v1.0.0.tar.gz
   cd docker-images
   ./load-images.sh
   cd ..
   docker-compose up -d
   ```

4. **Initialize:**
   ```bash
   docker-compose exec backend php ./cli/create.php
   docker-compose exec backend php ./cli/admin.php
   ```

## 🔒 Security Notes

Before deploying to production:

1. **Change JWT Secret:**
   ```bash
   cp .env.example .env
   # Edit .env and set a secure JWT_SECRET
   openssl rand -base64 32  # Generate a secure secret
   ```

2. **Change Database Password:**
   - Update in `.env` file
   - Update in `docker-compose.yml`

3. **Set up HTTPS:**
   - Add SSL certificates
   - Update nginx configuration
   - Use Let's Encrypt for free certificates

4. **Configure Firewall:**
   - Only expose port 80/443 publicly
   - Keep ports 4000 and 3306 internal

## 🐛 Troubleshooting

### Services won't start
```bash
# Check logs
docker-compose logs

# Check if ports are in use
sudo lsof -i :80
sudo lsof -i :4000
sudo lsof -i :3306
```

### Backend can't connect to database
```bash
# Check database is healthy
docker-compose ps mysql

# Restart backend
docker-compose restart backend
```

### Frontend shows errors
```bash
# Check backend is running
docker-compose ps backend

# Check nginx logs
docker-compose logs frontend
```

### Clean slate
```bash
docker-compose down -v
docker system prune -a
docker-compose up -d --build
```

## 📚 Documentation

- **Quick Reference:** `DOCKER-QUICKREF.md` - Most common commands
- **Full Guide:** `README.docker.md` - Comprehensive documentation
- **This Summary:** `DOCKER-SETUP-SUMMARY.md` - Overview and quick start

## ✅ What's Configured

- ✅ Multi-stage Docker builds for optimized image sizes
- ✅ MySQL with persistent volume storage
- ✅ Nginx with gzip compression and security headers
- ✅ API proxy from nginx to backend
- ✅ Health checks for database
- ✅ Environment variable configuration
- ✅ Production-ready setup
- ✅ Easy distribution workflow
- ✅ Automatic restart policies

## 🎯 Next Steps

1. **Test locally:**
   ```bash
   docker-compose up -d
   ./health-check.sh
   ```

2. **Initialize database:**
   ```bash
   docker-compose exec backend php ./cli/create.php
   docker-compose exec backend php ./cli/admin.php
   ```

3. **Access the application:**
   - Frontend: http://localhost
   - Backend API: http://localhost:4000/api

4. **For production deployment:**
   - Review security checklist in `README.docker.md`
   - Set up environment variables
   - Configure HTTPS/SSL
   - Set up backups

## 💡 Tips

- Use `./health-check.sh` to verify all services are running
- Check `docker-compose logs -f` if something isn't working
- The database data persists in a Docker volume even after `docker-compose down`
- Use `docker-compose down -v` to completely reset (WARNING: deletes all data)
- Images are optimized with multi-stage builds for smaller size

---

**Need help?** Check the documentation files or run `./health-check.sh` to diagnose issues.

**Ready to deploy?** See the "Distribution Workflow" section above!
