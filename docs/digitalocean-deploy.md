# One-Click Deploy to DigitalOcean

Deploy Flo POS to a DigitalOcean Droplet with a single click!

## Quick Deploy Button

[![Deploy to DigitalOcean](https://www.deploytodo.com/button.svg)](https://digitalocean.com/community/projects/flo-pos)

Or use this link:
```
https://marketplace.digitalocean.com/action?config=eyJwcm92aWRlciI6IkRvcGtXaXphcmQiLCJzZXJ2ZXIiOiJGbyBQT1MiLCJpbWFnZSI6ImxhcnZlbC1cLzEuMCIsInJlZ2lvbiI6ImFzaWEtc291dGhlYXN0MSIsInNpemUiOiJzbGFiZS0yZ3MiLCJzdGFjawI6ImRlYmlhbi0xMSIsImNzcyI6IiIsInBocFZlcnNpb24iOiIxNCIsImRhdGFiYXNlIjoicG9zdGdyZXNsLTE2Iiwic3NoIjp0cnVlLCJwdWJsaWNLZXkiOm51bGx9
```

## Manual Deployment

### Option 1: Using User Data

1. Create a new Droplet on DigitalOcean
2. Select **Ubuntu 24.04 LTS** or **Debian 12**
3. Choose a plan (minimum 2GB RAM recommended)
4. In "Add optional scripts", paste the contents of `scripts/do-install.sh`
5. Create the droplet

### Option 2: Using the Install Script

SSH into your droplet and run:

```bash
curl -s https://raw.githubusercontent.com/FreeOpenSourcePOS/FloPOS/main/scripts/do-install.sh | sh
```

### Option 3: Docker Deployment

```bash
# Clone the repo
git clone https://github.com/FreeOpenSourcePOS/FloPOS.git
cd FloPOS

# Start with Docker
docker-compose up -d

# Access at http://your-server-ip
```

## What's Included

The one-click installation includes:

- ✅ **PHP 8.4** with required extensions
- ✅ **PostgreSQL 16** database
- ✅ **Redis** for caching and queues
- ✅ **Nginx** web server
- ✅ **Flo POS** latest version
- ✅ **SSL certificate** (if domain provided)

## Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| RAM | 2 GB | 4 GB |
| CPU | 1 vCPU | 2 vCPU |
| Storage | 25 GB | 50 GB |

## Post-Installation

1. **Access Flo POS**: Open your server IP in a browser
2. **Register**: Create your account and business
3. **Configure**: Set up products, categories, tables

## Support

- Issues: https://github.com/FreeOpenSourcePOS/FloPOS/issues
- Documentation: https://github.com/FreeOpenSourcePOS/FloPOS#readme

## Get $200 Free Credit

Use our referral link to get $200 free credit for 60 days:
👉 https://m.do.co/c/6abf2aadc639
