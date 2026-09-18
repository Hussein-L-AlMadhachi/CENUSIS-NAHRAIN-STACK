# HTTPS/SSL Setup Guide

This guide shows you how to enable HTTPS for your CENUSIS-Ops application.

## Quick Overview

Your nginx configuration supports **optional HTTPS**. You can run with:
- **HTTP only** (default - works out of the box)
- **HTTP + HTTPS** (both protocols)
- **HTTPS only** (redirect HTTP to HTTPS)

## Option 1: Self-Signed Certificate (Testing/Development)

Use this for testing or internal networks where you don't need a trusted certificate.

### Step 1: Generate Self-Signed Certificate

```bash
# Create SSL directory
mkdir -p ssl

# Generate certificate (valid for 365 days)
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout ssl/cert.key \
  -out ssl/cert.crt \
  -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"

# Set proper permissions
chmod 600 ssl/cert.key
chmod 644 ssl/cert.crt
```

### Step 2: Enable HTTPS in nginx.conf

Edit `frontend/nginx.conf` and uncomment the HTTPS server block (lines 46-108):

```nginx
# Remove the # from the beginning of these lines:
server {
    listen 443 ssl http2;
    server_name localhost;
    # ... rest of the HTTPS configuration
}
```

### Step 3: Enable HTTPS in docker-compose.yml

Edit `docker-compose.yml` and uncomment:

```yaml
frontend:
  ports:
    - "80:80"
    - "443:443"  # Uncomment this line
  volumes:  # Uncomment this entire section
    - ./ssl/cert.crt:/etc/nginx/ssl/cert.crt:ro
    - ./ssl/cert.key:/etc/nginx/ssl/cert.key:ro
```

### Step 4: Restart Services

```bash
docker-compose down
docker-compose up -d --build
```

### Step 5: Access via HTTPS

```
https://localhost
https://your-server-ip
```

**Note:** Browsers will show a security warning because the certificate is self-signed. Click "Advanced" → "Proceed" to continue.

---

## Option 2: Let's Encrypt (Production)

Use this for production deployments with a real domain name.

### Prerequisites

- A registered domain name (e.g., `example.com`)
- Domain DNS pointing to your server's public IP
- Ports 80 and 443 open in firewall

### Step 1: Install Certbot

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install certbot

# CentOS/RHEL
sudo yum install certbot
```

### Step 2: Stop Docker (temporarily)

```bash
docker-compose down
```

### Step 3: Get Certificate

```bash
# Replace with your domain
sudo certbot certonly --standalone -d your-domain.com -d www.your-domain.com

# Follow the prompts
# Certificates will be saved to: /etc/letsencrypt/live/your-domain.com/
```

### Step 4: Update nginx.conf

Edit `frontend/nginx.conf`:

1. Uncomment the HTTPS server block
2. Update the SSL certificate paths:

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;  # Change this
    
    # Use Let's Encrypt certificates
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    
    # ... rest of config
}
```

3. In the HTTP server block, uncomment the redirect:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    
    # Redirect all HTTP to HTTPS
    return 301 https://$host$request_uri;
}
```

### Step 5: Update docker-compose.yml

```yaml
frontend:
  ports:
    - "80:80"
    - "443:443"  # Uncomment
  volumes:  # Uncomment and use Let's Encrypt path
    - /etc/letsencrypt:/etc/letsencrypt:ro
```

### Step 6: Update .env

```bash
# Edit .env file
NGINX_HOST=your-domain.com
```

### Step 7: Restart Services

```bash
docker-compose up -d --build
```

### Step 8: Set Up Auto-Renewal

Let's Encrypt certificates expire after 90 days. Set up auto-renewal:

```bash
# Test renewal
sudo certbot renew --dry-run

# Add to crontab for automatic renewal
sudo crontab -e

# Add this line (runs twice daily):
0 0,12 * * * certbot renew --quiet --post-hook "docker-compose -f /path/to/docker-compose.yml restart frontend"
```

---

## Option 3: Custom Certificate (Enterprise)

If you have a certificate from a commercial CA (e.g., DigiCert, Comodo):

### Step 1: Prepare Certificate Files

```bash
mkdir -p ssl
# Copy your certificate files
cp your-certificate.crt ssl/cert.crt
cp your-private-key.key ssl/cert.key
chmod 600 ssl/cert.key
chmod 644 ssl/cert.crt
```

### Step 2: Follow Option 1 Steps 2-5

Use the same steps as the self-signed certificate option.

---

## Testing HTTPS

### Test SSL Configuration

```bash
# Test from command line
curl -k https://localhost

# Check certificate details
openssl s_client -connect localhost:443 -servername localhost

# Test SSL strength (requires ssllabs-scan or testssl.sh)
docker run --rm -ti drwetter/testssl.sh https://your-domain.com
```

### Browser Testing

1. Open `https://your-server-ip` or `https://your-domain.com`
2. Click the padlock icon in the address bar
3. Verify certificate details

---

## Configuration Options

### Force HTTPS (Redirect HTTP to HTTPS)

In `frontend/nginx.conf`, uncomment in the HTTP server block:

```nginx
server {
    listen 80;
    server_name localhost;
    
    # Uncomment this line to force HTTPS
    return 301 https://$host$request_uri;
}
```

### HTTP and HTTPS Both Active

Keep both server blocks active without the redirect. Users can access via either protocol.

### HTTPS Only

1. Enable HTTPS server block
2. Add redirect in HTTP server block
3. Only expose port 443 in docker-compose.yml:

```yaml
ports:
  # - "80:80"  # Comment out HTTP
  - "443:443"
```

---

## Firewall Configuration

### Allow HTTPS Traffic

```bash
# Ubuntu/Debian
sudo ufw allow 443/tcp
sudo ufw status

# CentOS/RHEL
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

---

## Troubleshooting

### Certificate Errors

```bash
# Check certificate validity
openssl x509 -in ssl/cert.crt -text -noout

# Check certificate and key match
openssl x509 -noout -modulus -in ssl/cert.crt | openssl md5
openssl rsa -noout -modulus -in ssl/cert.key | openssl md5
# The MD5 hashes should match
```

### nginx Won't Start

```bash
# Check nginx configuration
docker-compose exec frontend nginx -t

# View nginx error logs
docker-compose logs frontend

# Common issues:
# - Certificate file paths incorrect
# - Certificate files not mounted in container
# - Permission issues with certificate files
```

### Browser Shows "Not Secure"

**Self-signed certificates:**
- This is expected - browsers don't trust self-signed certs
- Click "Advanced" → "Proceed" to continue
- Or add certificate to browser's trusted certificates

**Let's Encrypt certificates:**
- Check domain DNS is correct
- Verify certificate is not expired: `sudo certbot certificates`
- Check certificate chain is complete

### Port 443 Not Accessible

```bash
# Check if nginx is listening
sudo netstat -tlnp | grep :443

# Check firewall
sudo ufw status

# Check docker port mapping
docker-compose ps
```

---

## Security Best Practices

1. **Use Strong Ciphers:**
   Already configured in nginx.conf with modern ciphers

2. **Enable HSTS:**
   Already configured - forces browsers to use HTTPS

3. **Keep Certificates Updated:**
   Set up auto-renewal for Let's Encrypt

4. **Use TLS 1.2+:**
   Already configured - TLS 1.0 and 1.1 are disabled

5. **Protect Private Keys:**
   ```bash
   chmod 600 ssl/cert.key
   ```

6. **Regular Security Scans:**
   ```bash
   # Use SSL Labs
   # https://www.ssllabs.com/ssltest/
   
   # Or testssl.sh
   docker run --rm -ti drwetter/testssl.sh https://your-domain.com
   ```

---

## Quick Reference

### Enable HTTPS (Self-Signed)

```bash
# 1. Generate certificate
mkdir -p ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout ssl/cert.key -out ssl/cert.crt \
  -subj "/CN=localhost"

# 2. Edit frontend/nginx.conf - uncomment HTTPS server block

# 3. Edit docker-compose.yml - uncomment port 443 and volumes

# 4. Restart
docker-compose down
docker-compose up -d --build
```

### Enable HTTPS (Let's Encrypt)

```bash
# 1. Get certificate
docker-compose down
sudo certbot certonly --standalone -d your-domain.com

# 2. Edit frontend/nginx.conf - uncomment HTTPS server block
#    Update certificate paths to Let's Encrypt

# 3. Edit docker-compose.yml - uncomment port 443 and Let's Encrypt volume

# 4. Restart
docker-compose up -d --build

# 5. Set up auto-renewal
sudo crontab -e
# Add: 0 0,12 * * * certbot renew --quiet
```

### Disable HTTPS (Back to HTTP Only)

```bash
# 1. Edit frontend/nginx.conf - comment out HTTPS server block

# 2. Edit docker-compose.yml - comment out port 443 and volumes

# 3. Restart
docker-compose down
docker-compose up -d --build
```

---

## Summary

✅ **HTTP works by default** (no configuration needed)
✅ **HTTPS is optional** (uncomment configuration when ready)
✅ **Self-signed certificates** for testing/development
✅ **Let's Encrypt** for free production certificates
✅ **Commercial certificates** supported
✅ **Flexible configuration** (HTTP only, HTTPS only, or both)

Your nginx configuration is ready for HTTPS - just follow the steps above when you're ready to enable it!
