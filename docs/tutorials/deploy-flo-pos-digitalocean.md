---
title: How to Deploy Flo POS on DigitalOcean
description: A step-by-step guide to deploying an open-source Point of Sale system on DigitalOcean
fee: false
disable_comments: false
tags:
  -Flo POS
  -POS
  -DigitalOcean
  -Restaurant
  -Laravel
  -Next.js
---

## Introduction

[Flo POS](https://github.com/FreeOpenSourcePOS/FloPOS) is an open-source Point of Sale system designed for restaurants, salons, and retail shops in India and Thailand. In this tutorial, we'll deploy it to DigitalOcean with a one-click install script.

## Prerequisites

- A [DigitalOcean account](https://m.do.co/c/6abf2aadc639) (use this link for $200 free credit)
- Basic understanding of the Linux command line

## Step 1: Create a DigitalOcean Droplet

1. Log in to [DigitalOcean](https://cloud.digitalocean.com)
2. Click **Create** → **Droplets**
3. Choose **Ubuntu 24.04 LTS** or **Debian 12**
4. Select a plan:
   - **Basic**: $4/month (for testing)
   - **Standard**: $8/month (recommended for production)
5. Choose a datacenter region closest to you
6. Add your SSH key (optional but recommended)
7. Click **Create Droplet**

## Step 2: Deploy Flo POS

Once your Droplet is ready, SSH into it and run:

```bash
curl -s https://raw.githubusercontent.com/FreeOpenSourcePOS/FloPOS/main/scripts/do-install.sh | sh
```

Or with a domain:

```bash
DOMAIN=pos.yourdomain.com ADMIN_EMAIL=you@example.com curl -s https://raw.githubusercontent.com/FreeOpenSourcePOS/FloPOS/main/scripts/do-install.sh | sh
```

The script will automatically:
- Install PHP 8.4 with all required extensions
- Install PostgreSQL 16
- Install Redis
- Install Nginx
- Clone the Flo POS repository
- Configure the database
- Set up the queue worker

## Step 3: Access Flo POS

After installation completes (usually 5-10 minutes):

- **Frontend**: `http://YOUR_DROPLET_IP`
- **API**: `http://YOUR_DROPLET_IP:8000`

## Step 4: Configure SSL (Optional)

If you have a domain:

```bash
certbot --nginx -d yourdomain.com
```

## Step 5: Set Up Your Business

1. Open Flo POS in your browser
2. Register a new account
3. Create your business profile
4. Add products and categories
5. Set up tables (for restaurants)

## What Flo POS Includes

- **Multi-tenant POS** for restaurants, salons, and retail
- **Kitchen Display System (KDS)**
- **Thermal printer support** (ESCPOS)
- **WhatsApp bill sharing**
- **Loyalty points system**
- **GST billing** (India)
- **PWA** - works on mobile/tablet

## Troubleshooting

### Check Service Status
```bash
systemctl status flopos-worker
systemctl status nginx
```

### View Logs
```bash
journalctl -u flopos-worker -f
tail -f /var/log/nginx/error.log
```

### Restart Services
```bash
systemctl restart flopos-worker
systemctl restart nginx
```

## Cost Breakdown

| Resource | Monthly Cost |
|----------|-------------|
| Droplet (2GB RAM) | $12/month |
| SSL Certificate | Free (Let's Encrypt) |
| Domain | ~$10/year |
| **Total** | ~$12/month |

## Get $200 Free Credit

Use this referral link to get $200 free credit for 60 days:
👉 [https://m.do.co/c/6abf2aadc639](https://m.do.co/c/6abf2aadc639)

## Conclusion

Flo POS is now deployed on DigitalOcean! You have a fully functional POS system that you can use for your restaurant, salon, or retail business - completely free and open source.

For more details, visit the [GitHub repository](https://github.com/FreeOpenSourcePOS/FloPOS).

---

*This tutorial was contributed to the DigitalOcean Community.*
