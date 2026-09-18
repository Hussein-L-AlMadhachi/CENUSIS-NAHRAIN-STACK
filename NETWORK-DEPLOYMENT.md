# Network/Production Deployment Guide

## Overview

Your Docker setup is **production-ready** and **works over the network**! Here's how it's configured:

## Architecture

```
                    Internet/Network
                           │
                           │ Port 80 (HTTP)
                           │ Port 443 (HTTPS - optional)
                           ▼
                  ┌─────────────────┐
                  │  Nginx (Port 80) │ ← ONLY exposed service
                  │   Frontend       │
                  └────────┬─────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        │ /api/*           │ Static files     │
        ▼                  ▼                  │
┌──────────────┐    ┌──────────────┐         │
│   Backend    │    │   React App  │         │
│  (Port 3000) │    │   (served)   │         │
└──────┬───────┘    └──────────────┘         │
       │                                      │
       │ Port 3306                            │
       ▼                                      │
┌──────────────┐                              │
│    MySQL     │                              │
│  (Database)  │                              │
└──────────────┘                              │
                                              │
└─────────────────────────────────────────────┘
         Internal Docker Network
         (NOT accessible from outside)
```

## Security Features

1. **Only Nginx is exposed** (Port 80)
2. **Backend is NOT directly accessible** from the network
3. **Database is NOT directly accessible** from the network
4. **All communication goes through Nginx** reverse proxy
5. **Internal Docker network** for service communication

## Network Access

### How It Works

When you run `docker-compose up -d`:

- **Port 80 binds to `0.0.0.0`** (all network interfaces)
- This means the app is accessible from:
  - ✅ `http://localhost` (local machine)
  - ✅ `http://192.168.1.X` (local network - your server's IP)
  - ✅ `http://your-domain.com` (internet - if you have a domain)

### Example Access Points

If your server IP is `192.168.1.100`:

```bash
# From the server itself
http://localhost

# From another computer on the same network
http://192.168.1.100

# From the internet (if you have a public IP/domain)
http://your-public-ip
http://your-domain.com
```

## Deployment Steps

### 1. Find Your Server's IP Address

```bash
# On the server, find your IP
ip addr show | grep inet

# Or simpler
hostname -I
```

### 2. Configure Environment Variables

```bash
# Copy the example file
cp .env.example .env

# Edit the file
nano .env
```

Update these values:
```bash
# IMPORTANT: Change this!
JWT_SECRET=$(openssl rand -base64 32)

# Database credentials
DB_PASSWORD=your-secure-password

# Your server's IP or domain
NGINX_HOST=192.168.1.100  # or your domain name
```

### 3. Start the Services

```bash
# Build and start
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f
```

### 4. Initialize Database

```bash
docker-compose exec backend php ./cli/create.php
docker-compose exec backend php ./cli/admin.php
```

### 5. Test Network Access

From another computer on your network:

```bash
# Replace with your server's IP
curl http://192.168.1.100

# Or open in browser
# http://192.168.1.100
```

## Firewall Configuration

### Allow Port 80 (HTTP)

```bash
# Ubuntu/Debian with UFW
sudo ufw allow 80/tcp
sudo ufw enable

# CentOS/RHEL with firewalld
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload

# Check firewall status
sudo ufw status  # Ubuntu/Debian
sudo firewall-cmd --list-all  # CentOS/RHEL
```

### For HTTPS (Port 443)

```bash
# Ubuntu/Debian
sudo ufw allow 443/tcp

# CentOS/RHEL
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

## Debugging Network Access

### 1. Check if Docker is listening

```bash
# Should show nginx on 0.0.0.0:80
sudo netstat -tlnp | grep :80

# Or with ss
sudo ss -tlnp | grep :80
```

### 2. Test from the server itself

```bash
# Should return HTML
curl http://localhost

# Test backend API through nginx
curl http://localhost/api/public
```

### 3. Check Docker network

```bash
# List networks
docker network ls

# Inspect the network
docker network inspect ced_cenusis-network
```

### 4. Check container connectivity

```bash
# From frontend container, test backend
docker-compose exec frontend curl http://backend:3000/api/public

# From backend container, test database
docker-compose exec backend nc -zv mysql 3306
```

## Common Issues

### Issue: Can't access from other computers

**Solution:**
```bash
# 1. Check firewall
sudo ufw status

# 2. Check Docker is listening on all interfaces
sudo netstat -tlnp | grep :80
# Should show: 0.0.0.0:80 (not 127.0.0.1:80)

# 3. Check if containers are running
docker-compose ps

# 4. Check nginx logs
docker-compose logs frontend
```

### Issue: Backend returns 502 Bad Gateway

**Solution:**
```bash
# Check backend is running
docker-compose ps backend

# Check backend logs
docker-compose logs backend

# Restart backend
docker-compose restart backend
```

### Issue: Need to access database directly

**Solution:**
Uncomment the mysql ports in `docker-compose.yml`:
```yaml
mysql:
  ports:
    - "3306:3306"
```

Then restart:
```bash
docker-compose up -d
```

## Production Checklist

- [ ] Set strong `JWT_SECRET` in `.env`
- [ ] Change database password
- [ ] Set `NGINX_HOST` to your domain/IP
- [ ] Configure firewall (allow port 80/443)
- [ ] Set up HTTPS/SSL certificates (see SSL guide below)
- [ ] Set up database backups
- [ ] Configure log rotation
- [ ] Set up monitoring
- [ ] Test from external network

## Setting Up HTTPS/SSL

### Option 1: Let's Encrypt (Free)

1. Install certbot:
```bash
sudo apt install certbot python3-certbot-nginx
```

2. Get certificate:
```bash
sudo certbot --nginx -d your-domain.com
```

3. Update `docker-compose.yml`:
```yaml
frontend:
  ports:
    - "80:80"
    - "443:443"
  volumes:
    - /etc/letsencrypt:/etc/letsencrypt:ro
```

4. Update nginx config to use SSL

### Option 2: Self-Signed Certificate (Testing)

```bash
# Generate certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout nginx-selfsigned.key \
  -out nginx-selfsigned.crt

# Mount in docker-compose.yml
frontend:
  volumes:
    - ./nginx-selfsigned.crt:/etc/nginx/ssl/cert.crt:ro
    - ./nginx-selfsigned.key:/etc/nginx/ssl/cert.key:ro
```

## Accessing from Different Networks

### Local Network (LAN)
```
http://192.168.1.100
```

### Internet (WAN)
You need:
1. **Public IP address** or **Domain name**
2. **Port forwarding** on your router (forward port 80 to your server)
3. **Dynamic DNS** (if your IP changes)

Example port forwarding:
- External Port: 80
- Internal IP: 192.168.1.100
- Internal Port: 80

## Monitoring

### Check service health
```bash
./health-check.sh
```

### View real-time logs
```bash
docker-compose logs -f
```

### Check resource usage
```bash
docker stats
```

## Backup Strategy

### Database Backup
```bash
# Backup
docker-compose exec mysql mysqldump -u dev -pcenusis_ops cenusis_ops > backup-$(date +%Y%m%d).sql

# Restore
docker-compose exec -T mysql mysql -u dev -pcenusis_ops cenusis_ops < backup-20260217.sql
```

### Full System Backup
```bash
# Stop services
docker-compose down

# Backup volumes
docker run --rm -v cenusis-operations_mysql_data:/data -v $(pwd):/backup \
  alpine tar czf /backup/mysql-data-backup.tar.gz /data

# Restart services
docker-compose up -d
```

## Summary

✅ **Your setup IS production-ready**
✅ **Works over the network by default**
✅ **Only nginx is exposed (secure)**
✅ **Backend and database are internal only**
✅ **Ready for HTTPS with minimal changes**

The key points:
- Port 80 is bound to `0.0.0.0` (all interfaces)
- Nginx handles all external traffic
- Backend/Database are only accessible via internal Docker network
- This is the **correct and secure** way to deploy
