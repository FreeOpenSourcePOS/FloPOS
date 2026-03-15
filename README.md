# Flo POS - Open Source Point of Sale System

<p align="center">
  <img src="https://img.shields.io/badge/license-MIT-green.svg" alt="License">
  <img src="https://img.shields.io/github/stars/FreeOpenSourcePOS/FloPOS" alt="Stars">
  <img src="https://img.shields.io/github/contributors/FreeOpenSourcePOS/FloPOS" alt="Contributors">
</p>

Flo POS is a multi-tenant Point of Sale system designed for restaurants, salons, and retail shops in India and Thailand. Now open source and free for everyone to use!

## 🌟 Join the Movement

This project is more than just code—it's a movement to provide **free, open-source POS software** to millions of small businesses in India and Southeast Asia who cannot afford expensive commercial solutions.

### Why Open Source?

- **Democratize Access**: Every small restaurant, salon, and shop deserves professional-grade POS software
- **Community-Driven**: Features built by people who actually use the software
- **Transparent & Secure**: Full visibility into the codebase
- **Free Forever**: No vendor lock-in, no expensive licenses

### How You Can Contribute

We need developers, designers, testers, and advocates!

| Contribution Type | How to Help |
|-------------------|-------------|
| **🐛 Bug Reports** | Open an issue with steps to reproduce |
| **💡 Feature Requests** | Suggest features that would help your business |
| **🛠️ Code Contributions** | Fork, build, and submit PRs |
| **🧪 Testing** | Test features and report bugs |
| **📖 Documentation** | Improve docs, tutorials, guides |
| **🌐 Translations** | Add support for more languages |
| **⭐ Star the Repo** | Help us get visibility |

**Join our community:** [Discord](https://discord.gg/flopos) | [Telegram](https://t.me/flopos)

## 📦 Monorepo Structure

```
flopos/
├── backend/              # Laravel 12 API (PHP 8.4)
├── frontend/            # Next.js 16 Web App (React 19, TypeScript)
├── docs/                # Documentation
├── .github/             # CI/CD workflows
└── docker-compose.yml   # Development environment
```

## 🚀 Tech Stack

### Backend (Laravel API)
- **Laravel 12.50.0** (PHP 8.4.17)
- **PostgreSQL 16.11** (Separate database per tenant)
- **Redis 7.0.15** (Cache, sessions, queues)
- **Laravel Reverb** (WebSocket for real-time KDS)

### Frontend (Next.js PWA)
- **Next.js 16.1** (App Router)
- **React 19.2** with TypeScript 5.7
- **Tailwind CSS 4.0**
- **shadcn/ui** components
- **PWA** support (Add to Home Screen)

## 🏁 Quick Start

### Option 1: Docker Compose (Recommended)

```bash
# Clone the repository
git clone https://github.com/FreeOpenSourcePOS/FloPOS.git
cd FloPOS

# Start all services
docker-compose up -d

# Access the app
# Frontend: http://localhost:3000
# Backend API: http://localhost:8000
# PostgreSQL: localhost:5432
# Redis: localhost:6379
```

### Option 2: Manual Setup

#### Prerequisites
```bash
# Ubuntu 24.04 LTS
- PHP 8.4+ with extensions: pgsql, redis, mbstring, xml, curl, zip
- Composer 2.9+
- PostgreSQL 16+
- Redis 7+
- Node.js 22 LTS
- pnpm 9+
```

#### Backend Setup

```bash
# Clone and setup backend
git clone https://github.com/FreeOpenSourcePOS/FloPOS.git
cd FloPOS/backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Setup database (edit .env with your DB credentials)
php artisan migrate --path=database/migrations/main
php artisan db:seed --class=DatabaseSeeder

# Start the API server
php artisan serve --host=0.0.0.0 --port=8000
```

#### Frontend Setup

```bash
# Navigate to frontend
cd ../frontend

# Install dependencies
pnpm install

# Copy environment file
cp .env.example .env.local

# Start development server
pnpm dev
```

#### Access the Application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000

## 🚀 Running in Production

### Using Docker

```bash
# Production build
docker-compose -f docker-compose.yml up -d --build

# Or use the production compose file
docker-compose -f docker-compose.prod.yml up -d
```

### Manual Production Setup

```bash
# Backend
cd backend
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Use a process manager (Supervisor, PM2, etc.)
php artisan queue:work --sleep=3 --tries=3 --max-time=3600

# Frontend
cd frontend
pnpm install
pnpm build
pnpm start

# Use Caddy, Nginx with SSL
```

### Environment Variables

Key production variables:

```env
# Backend (.env)
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_HOST=postgres
DB_DATABASE=flopos_main
DB_USERNAME=your_user
DB_PASSWORD=strong_password

REDIS_HOST=redis

# Frontend (.env.local)
NEXT_PUBLIC_API_URL=https://your-domain.com/api
```

## 📱 Mobile App (PWA)

The frontend is a Progressive Web App (PWA). To install:

1. Open http://localhost:3000 in Chrome/Edge (mobile or desktop)
2. Click the install icon in the address bar
3. Or use "Add to Home Screen" on mobile

The PWA works offline for viewing orders and can sync when back online.

## 📚 Documentation

- **[Feature Documentation](./docs/FEATURES.md)** — Comprehensive guide to all features
- [Backend API Documentation](./backend/README.md)
- [Frontend Documentation](./frontend/README.md)
- [Database Schema](./docs/database-schema.md)
- [Deployment Guide](./docs/deployment.md)

## 🏗️ Architecture

### Multi-Tenancy
- **Separate PostgreSQL database per tenant** for complete data isolation
- Dynamic database connection switching via middleware
- Tenant-aware models and services
- Zero cross-tenant data contamination

### Database Schema

#### Main Database (6 tables)
- `users` - Email OTP authentication
- `tenants` - Business accounts
- `subscriptions` - Billing
- `global_customers` - Cross-tenant customer identity
- `otp_verifications` - Email OTP codes
- `tenant_user` - Multi-tenant access control

#### Tenant Databases (10 tables per business)
- `categories` & `products` - Product catalog
- `addon_groups` & `addons` - Product customizations
- `kitchen_stations` - KDS zones
- `tables` - Restaurant table management
- `customers` - Tenant-specific customers
- `orders` & `order_items` - Order management
- `bills` - Payment processing
- `staff` - Employee management

## 🎨 Features

### Core POS
- [x] Multi-tenant architecture
- [x] JWT authentication with OTP
- [x] Product management (CRUD + stock)
- [x] POS screen with cart, order types, table selection
- [x] Billing & payment (cash, card, UPI, wallet)
- [x] Dashboard with analytics

### Restaurant Features
- [x] Table management with auto-status
- [x] Kitchen Display System (KDS)
- [x] Order lifecycle tracking
- [x] Dine-in / Takeaway / Delivery modes
- [x] Thermal printer support (ESCPOS)
- [x] KOT printing

### Premium Features
- [x] Staff management with roles
- [x] Customer management with loyalty points
- [x] WhatsApp bill sharing
- [x] Low stock alerts

## 🛠️ Development

### Run Tests

```bash
# Backend
cd backend
php artisan test

# Frontend
cd frontend
pnpm test
```

### Code Style

```bash
# Backend
cd backend
composer lint        # If available
php artisan pint    # Code style fix

# Frontend
cd frontend
pnpm lint           # ESLint
pnpm typecheck      # TypeScript
```

## 📝 License

This project is open source under the **MIT License**. See [LICENSE](./LICENSE) for details.

You are free to:
- ✅ Use for personal or commercial purposes
- ✅ Modify and distribute
- ✅ Use privately or publicly
- ✅ Use for any purpose

## 🤝 Support & Community

- **GitHub Issues**: https://github.com/FreeOpenSourcePOS/FloPOS/issues
- **Discord**: https://discord.gg/flopos
- **Telegram**: https://t.me/flopos

## 🙏 Acknowledgments

Built with ❤️ using:
- Laravel (PHP)
- Next.js (React)
- PostgreSQL
- Redis
- Tailwind CSS

---

<p align="center">
  <strong>Help us bring professional POS software to every small business!</strong><br>
  ⭐ Star us on GitHub | 🐛 Report bugs | 💡 Suggest features | 📢 Share with others
</p>
