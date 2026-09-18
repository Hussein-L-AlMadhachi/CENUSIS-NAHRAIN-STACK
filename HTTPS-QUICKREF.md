# HTTPS Quick Reference

## how to deploy with HTTPS

### Option A: Self-Signed Certificate (Testing)

```bash
# 1. Generate certificate
./generate-ssl-cert.sh

# 2. Enable HTTPS in config files
# Edit frontend/nginx.conf - uncomment HTTPS server block (line 46)
# Edit docker-compose.yml - uncomment port 443 and volumes

# 3. Restart
docker-compose down && docker-compose up -d --build
```

### Option B: Let's Encrypt (Production)

```bash
# 1. Get certificate (requires domain name)
docker-compose down
sudo certbot certonly --standalone -d your-domain.com

# 2. Update nginx.conf to use Let's Encrypt paths
# Edit frontend/nginx.conf - uncomment HTTPS block, update cert paths

# 3. Enable in docker-compose.yml
# Uncomment port 443 and Let's Encrypt volume

# 4. Restart
docker-compose up -d --build
```

---

## 📝 Configuration Checklist

### frontend/nginx.conf

- [ ] Uncomment HTTPS server block (lines 46-108)
- [ ] Update `server_name` to your domain
- [ ] Update SSL certificate paths if using Let's Encrypt
- [ ] Optional: Uncomment HTTP→HTTPS redirect (line 9)

### docker-compose.yml

- [ ] Uncomment port `443:443`
- [ ] Uncomment volumes section for SSL certificates
- [ ] Choose: self-signed OR Let's Encrypt volume path

---

## 🔑 Certificate Paths

### Self-Signed (Testing)
```yaml
volumes:
  - ./ssl/cert.crt:/etc/nginx/ssl/cert.crt:ro
  - ./ssl/cert.key:/etc/nginx/ssl/cert.key:ro
```

### Let's Encrypt (Production)
```yaml
volumes:
  - /etc/letsencrypt:/etc/letsencrypt:ro
```

```nginx
ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
```

---

## 🧪 Testing

```bash
# Test HTTPS locally
curl -k https://localhost

# Check certificate
openssl s_client -connect localhost:443

# Verify nginx config
docker-compose exec frontend nginx -t

# View logs
docker-compose logs frontend
```

---

## 🔄 HTTP Redirect Options

### Both HTTP and HTTPS (Default)
No redirect - users can use either protocol

### Force HTTPS
Uncomment in `frontend/nginx.conf` (line 9):
```nginx
return 301 https://$host$request_uri;
```

### HTTPS Only
Comment out port 80 in `docker-compose.yml`

---

## 🛠️ Common Commands

```bash
# Generate self-signed cert
./generate-ssl-cert.sh

# Restart with new config
docker-compose down && docker-compose up -d --build

# Check if HTTPS is working
curl -k https://localhost

# View certificate details
openssl x509 -in ssl/cert.crt -text -noout

# Test SSL strength
docker run --rm -ti drwetter/testssl.sh https://your-domain.com
```

---

## 🐛 Troubleshooting

### nginx won't start
```bash
docker-compose logs frontend
docker-compose exec frontend nginx -t
```

### Certificate errors
```bash
# Check cert and key match
openssl x509 -noout -modulus -in ssl/cert.crt | openssl md5
openssl rsa -noout -modulus -in ssl/cert.key | openssl md5
```

### Port 443 not accessible
```bash
sudo ufw allow 443/tcp
sudo netstat -tlnp | grep :443
```

---

## 📚 Full Documentation

See **HTTPS-SETUP.md** for:
- Detailed setup instructions
- Let's Encrypt auto-renewal
- Security best practices
- Advanced configuration

---

## ⚡ Quick Enable (Self-Signed)

```bash
# One-liner to enable HTTPS with self-signed cert
./generate-ssl-cert.sh && \
sed -i 's/# server {/server {/g; s/#     /    /g' frontend/nginx.conf && \
sed -i 's/# - "443:443"/- "443:443"/g; s/#   - \.\/ssl/  - \.\/ssl/g' docker-compose.yml && \
docker-compose down && docker-compose up -d --build
```

**Note:** This is for testing only. For production, use Let's Encrypt!
