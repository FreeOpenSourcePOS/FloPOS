# Flo POS - Backend API

Multi-tenant Point of Sale system for restaurants, salons, and retail shops in India and Thailand.

## Tech Stack

- **Laravel 12.50.0** (PHP 8.4.17)
- **PostgreSQL 16.11** (Separate database per tenant)
- **Redis 7.0.15** (Cache, sessions, queues)
- **Caddy 2.10.2** (Web server with auto SSL)

## Features

### Multi-Tenancy Architecture
- **Separate PostgreSQL database per tenant** for complete data isolation
- Dynamic database connection switching via middleware
- Tenant-aware models and services
- Zero cross-tenant data contamination

### Main Database Tables
- users - Email OTP authentication with phone numbers
- tenants - Business accounts with subscription management
- subscriptions - Razorpay integration for billing
- global_customers - Privacy-compliant cross-tenant customer identity
- otp_verifications - Email OTP codes with expiration
- tenant_user - Multi-tenant user access with roles

### Tenant Database Tables (Per Business)
- categories & products - Product catalog with variants/modifiers
- addon_groups & addons - Product customizations
- kitchen_stations - KDS zones for restaurants
- tables - Restaurant table management
- customers - Tenant-specific customer database
- orders & order_items - Order lifecycle management
- bills - Payment processing with multiple methods
- staff - Employee management with roles

## Installation & Setup

View full installation guide in INSTALL.md

## API Endpoints

### Health Check
\`\`\`
GET /api/health
\`\`\`

### Database Statistics
\`\`\`
GET /api/stats
\`\`\`

## Development

\`\`\`bash
# Start development server
php artisan serve --host=0.0.0.0 --port=8000

# Run migrations
php artisan migrate --path=database/migrations/main --database=pgsql

# Clear cache
php artisan cache:clear
\`\`\`

## License

Proprietary - All rights reserved
