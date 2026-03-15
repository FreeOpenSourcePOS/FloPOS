# Flo POS - Feature Documentation

> **Audience:** Admins & Developers
> **Last Updated:** February 2026
> **Status:** Active Development (Launch Target: April 1, 2026)

Flo POS is a multi-tenant Point of Sale system built for restaurants, salons, and retail businesses in India and Thailand. This document provides a comprehensive breakdown of every feature, how it works, and where the code lives.

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Multi-Tenancy](#multi-tenancy)
- [Authentication & Authorization](#authentication--authorization)
- [Product Catalog](#product-catalog)
- [Point of Sale (POS)](#point-of-sale-pos)
- [Order Management](#order-management)
- [Kitchen Display System (KDS)](#kitchen-display-system-kds)
- [Billing & Payments](#billing--payments)
- [Table Management](#table-management)
- [Customer Management](#customer-management)
- [Staff Management](#staff-management)
- [Dashboard & Analytics](#dashboard--analytics)
- [Settings](#settings)
- [API Reference (Summary)](#api-reference-summary)
- [Database Schema](#database-schema)
- [Frontend Architecture](#frontend-architecture)
- [Real-Time Updates](#real-time-updates)
- [Security](#security)
- [Roadmap](#roadmap)

---

## Architecture Overview

### Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend API | Laravel | 12.50.0 |
| Language | PHP | 8.4.17 |
| Frontend | Next.js (App Router) | 16.1.6 |
| UI Framework | React + TypeScript | 19.2.3 / 5.x |
| UI Components | shadcn/ui (Radix + Tailwind) | latest |
| Database | PostgreSQL | 16.11 |
| Cache/Sessions/Queues | Redis | 7.0.15 |
| Auth | JWT (tymon/jwt-auth) | 2.2.1 |
| State Management | Zustand | 5.0.11 |
| Web Server | Caddy | 2.10.2 |
| Icons | Lucide React | 563 icons |

### Monorepo Structure

```
flopos/
├── backend/                 # Laravel 12 API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Auth/       # AuthController, PasswordResetController, TenantController
│   │   │   │   └── Tenant/     # 9 resource controllers
│   │   │   └── Middleware/
│   │   │       └── TenantDatabaseSwitch.php
│   │   ├── Models/
│   │   │   ├── Main/           # User, Tenant, Subscription, GlobalCustomer, OtpVerification
│   │   │   └── Tenant/         # Category, Product, Order, Bill, Table, Customer, Staff, etc.
│   │   └── Services/
│   │       └── TenantService.php
│   ├── database/
│   │   └── migrations/
│   │       ├── main/           # 6 main DB migrations
│   │       └── tenant/         # 11 tenant DB migrations
│   └── routes/
│       └── api.php             # 70+ API endpoints
│
├── frontend/                # Next.js 16 App
│   └── src/
│       ├── app/
│       │   ├── auth/           # Login, Register pages
│       │   └── (dashboard)/    # 9 protected pages
│       ├── components/
│       │   ├── layout/         # AuthGuard, Sidebar
│       │   └── ui/             # 17 shadcn components
│       ├── store/              # auth.ts, cart.ts (Zustand)
│       └── lib/                # api.ts, types.ts, utils.ts
│
└── docs/                    # This documentation
```

---

## Multi-Tenancy

### How It Works

Flo POS uses a **database-per-tenant** isolation strategy. Each business (tenant) gets its own PostgreSQL database, ensuring complete data separation with zero cross-tenant contamination.

```
┌─────────────┐     ┌──────────────────┐
│ flopos_main  │     │  tenant_joes_cafe │
│              │     │                   │
│ users        │     │ categories        │
│ tenants      │     │ products          │
│ subscriptions│     │ orders            │
│ tenant_user  │     │ order_items       │
│ global_cust. │     │ bills             │
│ otp_verif.   │     │ tables            │
└─────────────┘     │ customers         │
                    │ staff             │
                    │ addon_groups      │
                    │ addons            │
                    │ kitchen_stations  │
                    └──────────────────┘
```

### Tenant Provisioning

When a new user registers, the system:

1. Creates a `User` record in the main database
2. Creates a `Tenant` record with business details
3. Links User ↔ Tenant in `tenant_user` pivot table (with role: `owner`)
4. **Provisions a new PostgreSQL database** named `tenant_{slug}`
5. **Runs all tenant migrations** on the new database
6. Returns a JWT token with the tenant pre-selected

**Code:** `backend/app/Services/TenantService.php`

```
TenantService::provisionDatabase(Tenant $tenant)
  → CREATE DATABASE tenant_{slug}
  → Run all migrations in database/migrations/tenant/
```

### Database Switching

Every API request to a tenant-scoped route goes through the `TenantDatabaseSwitch` middleware:

1. Extracts `tenant_id` from the JWT payload
2. Looks up the tenant (cached for 24 hours)
3. Dynamically reconfigures the `tenant` database connection
4. All Eloquent models using `protected $connection = 'tenant'` now query the correct database
5. After the response, the tenant context is cleared

**Code:** `backend/app/Http/Middleware/TenantDatabaseSwitch.php`

### Multi-Tenant Access

A single user can belong to multiple tenants (e.g., a business owner with multiple locations). The `tenant_user` pivot table stores:

| Field | Description |
|-------|-------------|
| `tenant_id` | Which business |
| `user_id` | Which user |
| `role` | owner, admin, manager, staff |
| `permissions` | JSON array of specific permissions |
| `is_active` | Whether access is currently active |

---

## Authentication & Authorization

### JWT-Based Authentication

All authentication uses JSON Web Tokens via `tymon/jwt-auth`.

| Setting | Value |
|---------|-------|
| Token TTL | 60 minutes |
| Refresh TTL | 14 days (20,160 minutes) |
| Algorithm | HMAC (symmetric) |
| Custom Claims | `tenant_id`, `tenant_slug`, `role` |

### Authentication Flow

```
1. REGISTER
   POST /api/auth/register
   Body: { name, email, password, password_confirmation,
           business_name, business_type, country }
   Response: { user, token, tenant }
   Side Effect: Provisions new tenant database

2. LOGIN
   POST /api/auth/login
   Body: { email, password }
   Response: { user, token, tenants[] }
   Note: Token does NOT have tenant_id yet

3. SELECT TENANT
   POST /api/auth/tenants/select
   Body: { tenant_id }
   Response: { token (new JWT with tenant_id claim) }
   Note: Required before accessing any tenant routes

4. ALL SUBSEQUENT REQUESTS
   Header: Authorization: Bearer <token-with-tenant-id>
   Middleware: auth:api + tenant
```

### Password Management

| Endpoint | Description |
|----------|-------------|
| `POST /api/auth/password/forgot` | Generates a 6-digit OTP, sends to email (5-min expiry) |
| `POST /api/auth/password/reset` | Validates OTP, sets new password |
| `POST /api/auth/password/change` | Authenticated user changes own password |

**Code:** `backend/app/Http/Controllers/Auth/PasswordResetController.php`

### Frontend Auth Guard

All dashboard routes are wrapped in `AuthGuard`, which:

1. Loads token from `localStorage` on mount
2. Validates token by calling `GET /api/auth/me`
3. Redirects to `/auth/login` if unauthenticated
4. Redirects to `/auth/login?select_tenant=true` if no tenant selected
5. Shows loading spinner during hydration

**Code:** `frontend/src/components/layout/AuthGuard.tsx`

### State Management

Auth state is managed via a Zustand store (`frontend/src/store/auth.ts`):

```
State: user, token, tenants[], currentTenant, loading
Actions: login(), register(), selectTenant(), logout(), loadFromStorage()
Persistence: localStorage keys "token" and "tenant"
```

---

## Product Catalog

### Categories

Hierarchical product categories with parent-child relationships.

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Category name |
| `slug` | string | URL-friendly identifier (unique) |
| `description` | text | Optional description |
| `parent_id` | FK | Parent category (null = root) |
| `sort_order` | integer | Display ordering |
| `is_active` | boolean | Visibility toggle |
| `color` | string | UI color code |
| `icon` | string | Icon identifier |

**Scopes:** `active()`, `root()`, `ordered()`
**API:** Full CRUD at `/api/categories`
**Code:** `backend/app/Models/Tenant/Category.php`, `backend/app/Http/Controllers/Tenant/CategoryController.php`

### Products

Full product management with inventory tracking, tax configuration, and addon support.

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Product name |
| `sku` | string | Stock Keeping Unit (unique) |
| `category_id` | FK | Parent category |
| `price` | decimal | Selling price |
| `cost_price` | decimal | Purchase/cost price |
| `tax_type` | enum | `inclusive`, `exclusive`, `exempt` |
| `tax_rate` | decimal | Tax percentage |
| `track_inventory` | boolean | Whether to track stock |
| `stock_quantity` | integer | Current stock level |
| `low_stock_threshold` | integer | Alert threshold |
| `is_active` | boolean | Available for sale |
| `available_online` | boolean | Available for online orders |
| `image_url` | string | Product image |
| `variants` | JSON | Size, color, etc. |
| `modifiers` | JSON | Customization options |

**Computed Properties:** `finalPrice`, `taxAmount`, `profitMargin`
**Stock Management:** `decreaseStock(qty)`, `increaseStock(qty)` — called automatically on order create/cancel
**Scopes:** `active()`, `inStock()`, `lowStock()`, `search(term)`

**API Endpoints:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/products` | List (filters: category, active, search, low_stock) |
| POST | `/api/products` | Create with optional addon groups |
| GET | `/api/products/{id}` | Show with category & addon groups |
| PUT | `/api/products/{id}` | Update product & addon associations |
| DELETE | `/api/products/{id}` | Delete product |
| POST | `/api/products/{id}/stock` | Update stock (actions: set, increase, decrease) |

**Frontend Page:** `frontend/src/app/(dashboard)/products/page.tsx`
- Table view of all products
- Modal form for create/edit
- Low stock highlighting
- Category display

### Addon Groups & Addons

Product customizations (e.g., "Choose your toppings", "Select size").

**Addon Group:**
| Field | Description |
|-------|-------------|
| `name` | Group name (e.g., "Extra Toppings") |
| `is_required` | Must customer select from this group? |
| `min_selection` | Minimum items to select |
| `max_selection` | Maximum items to select |

**Addon:**
| Field | Description |
|-------|-------------|
| `name` | Addon name (e.g., "Extra Cheese") |
| `price` | Additional price |

Products are linked to Addon Groups via a many-to-many pivot table (`addon_group_product`).

**API:** Full CRUD at `/api/addon-groups` with nested `/addons` endpoints
**Code:** `backend/app/Http/Controllers/Tenant/AddonGroupController.php`

---

## Point of Sale (POS)

The POS is the core transaction screen where orders are created.

### Features

- **Product browsing** — Search by name, filter by category
- **Shopping cart** — Add/remove products, adjust quantities
- **Addon selection** — Attach addons to line items
- **Order types** — `dine_in`, `takeaway`, `delivery`, `online`
- **Table assignment** — For dine-in orders, pick an available table
- **Guest count** — Track number of guests per table
- **Special instructions** — Per-item or per-order notes
- **Real-time subtotal** — Calculates as items are added

### Cart State (Zustand)

```typescript
// frontend/src/store/cart.ts
State: {
  items: CartItem[]       // Products with quantity, addons, instructions
  orderType: string       // dine_in | takeaway | delivery | online
  tableId: number | null  // For dine_in
  customerId: number | null
  guestCount: number      // Default: 1
}

Actions: addItem(), removeItem(), updateQuantity(), clearCart(),
         setOrderType(), setTableId(), setCustomerId(), setGuestCount()

Computed: subtotal(), itemCount()
```

### Order Creation Flow

```
User adds items to cart
  → Selects order type (dine_in/takeaway/delivery)
  → (If dine_in) Selects table from available tables
  → Sets guest count, special instructions
  → Clicks "Place Order"
  → POST /api/orders
    Backend:
      → Generates order number (YYYYMMDD0001)
      → Creates Order + OrderItems
      → Decreases product stock
      → Marks table as "occupied" (if dine_in)
      → Returns order with items
  → Cart is cleared
  → Tables refresh
```

**Frontend Page:** `frontend/src/app/(dashboard)/pos/page.tsx`
**Layout:** Split view — left side (product menu), right side (cart)

---

## Order Management

### Order Lifecycle

```
pending → preparing → ready → served → completed
                                  ↘ cancelled (at any point)
```

| Status | Description | Triggered By |
|--------|-------------|-------------|
| `pending` | Order placed, waiting for kitchen | Order creation |
| `preparing` | Kitchen has started cooking | Kitchen/staff action |
| `ready` | Food is ready for pickup/serving | Kitchen action |
| `served` | Food delivered to customer | Waiter action |
| `completed` | Order fully done, table freed | Auto on full payment or manual |
| `cancelled` | Order cancelled (stock restored) | Staff action (requires reason) |

### Order Fields

| Field | Description |
|-------|-------------|
| `order_number` | Auto-generated: `YYYYMMDD0001` format |
| `type` | `dine_in`, `takeaway`, `delivery`, `online` |
| `table_id` | Assigned table (dine_in only) |
| `customer_id` | Optional linked customer |
| `subtotal` | Sum of item totals |
| `tax_amount` | Calculated tax |
| `discount_amount` | Applied discount |
| `delivery_charge` | For delivery orders |
| `total` | Final amount |
| `guest_count` | Number of guests |
| `special_instructions` | Order-level notes |
| `created_by` | Staff who created the order |
| `served_by` | Staff who served |

### Order Item Fields

Each order item stores a **snapshot** of the product at time of order (denormalized for historical accuracy):

| Field | Description |
|-------|-------------|
| `product_name` | Name at time of order |
| `product_sku` | SKU at time of order |
| `unit_price` | Price at time of order |
| `quantity` | Number ordered |
| `addons` | JSON: selected addons with prices |
| `variant_selection` | JSON: selected variant |
| `special_instructions` | Per-item notes |
| `status` | Individual item status tracking |

### Cancellation

When an order is cancelled:
1. Stock is **restored** for all items (increaseStock)
2. Table is **freed** (marked as `available`)
3. `cancelled_at` timestamp is set
4. `cancellation_reason` is recorded

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/orders` | List with filters (status, type, table_id, today) |
| POST | `/api/orders` | Create order (auto-generates number, adjusts stock) |
| GET | `/api/orders/{id}` | Show with items, table, customer, bill |
| POST | `/api/orders/{id}/items` | Add items to existing order |
| PATCH | `/api/orders/{id}/status` | Advance or cancel order |
| GET | `/api/kitchen/orders` | Kitchen display (pending + preparing) |

**Frontend Page:** `frontend/src/app/(dashboard)/orders/page.tsx`
- Dual-pane layout: order list (left) + order detail (right)
- Filter tabs: Active, All, Pending, Preparing, Ready, Completed
- Status transition buttons
- Auto-refresh every 15 seconds

---

## Kitchen Display System (KDS)

A real-time display for kitchen staff showing incoming and in-progress orders.

### Features

- **Two-column layout:**
  - Left: **New Orders** (status: `pending`) — yellow header
  - Right: **Preparing** (status: `preparing`) — blue header with pulse animation
- **Auto-refresh:** Every 5 seconds
- **Per-order display:**
  - Order number and type badge
  - Table assignment
  - All items with quantities and special instructions
  - Time elapsed since order creation
- **Actions:**
  - Mark `pending` → `preparing` (kitchen starts cooking)
  - Mark `preparing` → `ready` (food is ready)
- **Real-time count** badges on section headers

### API

```
GET /api/kitchen/orders
→ Returns orders with status "pending" or "preparing"
→ Includes: items, table info, timestamps
```

**Frontend Page:** `frontend/src/app/(dashboard)/kitchen-display/page.tsx`

---

## Billing & Payments

### Bill Generation

Bills are created from completed/served orders.

```
POST /api/bills/generate
Body: { order_id, discount_type?, discount_value?, service_charge?, delivery_charge? }
→ Creates bill with auto-generated number (INV-YYYYMMDD0001)
→ Calculates: subtotal, tax, discounts, service charge, total
```

### Bill Fields

| Field | Description |
|-------|-------------|
| `bill_number` | Auto-generated: `INV-YYYYMMDD0001` |
| `subtotal` | Order subtotal |
| `tax_amount` | Calculated tax |
| `discount_amount` | Applied discount |
| `discount_type` | `percentage` or `fixed` |
| `discount_value` | Discount value (% or flat amount) |
| `discount_reason` | Why discount was applied |
| `service_charge` | Optional service charge |
| `delivery_charge` | For delivery orders |
| `total` | Final payable amount |
| `paid_amount` | Amount received |
| `balance` | Remaining amount |
| `payment_status` | `unpaid`, `partial`, `paid`, `refunded` |
| `payment_details` | JSON array of payment records |

### Payment Recording

```
POST /api/bills/{id}/payment
Body: { amount, method: "cash" | "card" | "upi" | "wallet" }
→ Adds to payment_details JSON array
→ Updates paid_amount and balance
→ Auto-updates payment_status (unpaid → partial → paid)
→ If fully paid, marks order as "completed"
```

**Supported payment methods:** `cash`, `card`, `upi`, `wallet`

### Discounts

```
POST /api/bills/{id}/discount
Body: { value, type: "percentage" | "fixed", reason? }
→ Recalculates total after discount
```

### Bill Actions

| Endpoint | Description |
|----------|-------------|
| `POST /api/bills/{id}/print` | Sets `printed_at` timestamp |
| WhatsApp sharing | Sets `whatsapp_sent_at` (planned) |

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/bills` | List with filters (status, today) |
| POST | `/api/bills/generate` | Generate bill from order |
| GET | `/api/bills/{id}` | Show with order, items, customer |
| POST | `/api/bills/{id}/payment` | Record payment |
| POST | `/api/bills/{id}/discount` | Apply discount |
| POST | `/api/bills/{id}/print` | Mark as printed |

---

## Table Management

### Table Features

- **Status tracking:** `available`, `occupied`, `reserved`, `maintenance`
- **Floor/section organization:** Tables grouped by floor and section
- **Capacity tracking:** Seats per table
- **Kitchen station assignment:** Link tables to kitchen stations
- **Position mapping:** `position_x`, `position_y` for floor plan view (planned)
- **QR code support:** For customer self-ordering (planned)
- **Auto-status updates:**
  - When an order is placed for a table → `occupied`
  - When an order is completed/cancelled → `available`

### Table Fields

| Field | Description |
|-------|-------------|
| `name` | Table identifier (e.g., "T1", "Patio 3") |
| `capacity` | Number of seats |
| `status` | available, occupied, reserved, maintenance |
| `floor` | Floor number/name |
| `section` | Section within floor |
| `kitchen_station_id` | Assigned kitchen station |
| `qr_code` | QR code data (planned) |
| `is_active` | Whether table is in use |

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tables` | List with filters (status, floor, section, active) |
| POST | `/api/tables` | Create table |
| GET | `/api/tables/{id}` | Show with current order |
| PUT | `/api/tables/{id}` | Update table |
| DELETE | `/api/tables/{id}` | Delete (only if available) |
| PATCH | `/api/tables/{id}/status` | Quick status change |

**Frontend Page:** `frontend/src/app/(dashboard)/tables/page.tsx`
- Grid view with color-coded status dots (green/red/yellow/gray)
- Quick status toggle buttons
- Create table via modal
- Auto-refresh every 10 seconds

---

## Customer Management

### Customer Features

- **Customer profiles** with contact info, address, preferences
- **Visit tracking:** Automatic counter incremented on each order
- **Spending tracking:** `total_spent`, `average_bill`, `last_visit_at`
- **VIP detection:** Customers with 10+ visits flagged as VIP
- **Search:** By name, phone number, or email
- **Preferences:** JSON field for dietary preferences, allergies, etc.
- **Notes:** Free-text field for staff notes

### Customer Fields

| Field | Description |
|-------|-------------|
| `phone` | Primary identifier (unique per tenant) |
| `country_code` | Phone country code (+91, +66) |
| `name` | Customer name |
| `email` | Optional email |
| `address`, `city`, `state`, `postal_code` | Delivery address |
| `date_of_birth`, `anniversary` | For personalized offers |
| `visits_count` | Auto-incremented on each order |
| `total_spent` | Running total |
| `average_bill` | Calculated average |
| `preferences` | JSON (dietary, allergies, etc.) |

### Global Customer Identity

A separate `global_customers` table in the main database provides cross-tenant customer identification using **phone number hashing** (SHA-256). This allows:

- Recognizing a customer across multiple businesses
- Privacy-compliant (hashed, not plaintext)
- Consent tracking (GDPR compliance)
- Aggregate visit and spending data

**Code:** `backend/app/Models/Main/GlobalCustomer.php`

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/customers` | Paginated list with search & VIP filter |
| POST | `/api/customers` | Create customer |
| GET | `/api/customers/{id}` | Show with last 10 orders |
| PUT | `/api/customers/{id}` | Update customer |
| DELETE | `/api/customers/{id}` | Delete customer |
| GET | `/api/customers-search` | Quick search (name, phone, email) |

**Frontend Page:** `frontend/src/app/(dashboard)/customers/page.tsx`

---

## Staff Management

### Staff Features

- **Role-based system:** manager, cashier, cook, waiter, delivery
- **Permission management:** JSON-based granular permissions
- **Salary tracking:** `hourly_rate` and `monthly_salary`
- **Employment lifecycle:** `joined_at`, `left_at`, active/inactive toggle
- **Unique employee codes** per tenant

### Staff Roles

| Role | Color (UI) | Typical Permissions |
|------|-----------|-------------------|
| Manager | Purple | Full access |
| Cashier | Blue | POS, billing, customers |
| Cook | Orange | Kitchen display, order status |
| Waiter | Green | Orders, tables, POS |
| Delivery | Default | Delivery orders |

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/staff` | List with role & active filters |
| POST | `/api/staff` | Create staff member |
| GET | `/api/staff/{id}` | Show details |
| PUT | `/api/staff/{id}` | Update staff |
| DELETE | `/api/staff/{id}` | Delete staff |
| POST | `/api/staff/{id}/deactivate` | Deactivate |
| POST | `/api/staff/{id}/reactivate` | Reactivate |

**Frontend Page:** `frontend/src/app/(dashboard)/staff/page.tsx`
- Card grid view with role-colored badges
- Active/inactive toggle
- Create via modal form

---

## Dashboard & Analytics

### Overview Cards

| Card | Data Source | Description |
|------|------------|-------------|
| Today's Revenue | Bills (today, paid) | Sum of paid bill totals |
| Today's Orders | Orders (today) | Count of today's orders |
| Active Orders | Orders (pending/preparing/ready) | Currently in-progress |
| Customers Served | Orders (today, completed) | Unique customers served |

### Sections

- **Recent Orders:** Last 5 orders with status badges, type, table, total
- **Recent Bills:** Latest bills with payment status (paid/partial/unpaid)
- **Currency-aware:** Displays `INR` or `THB` based on tenant settings

### Auto-Refresh

Dashboard data refreshes every **30 seconds** automatically.

**Frontend Page:** `frontend/src/app/(dashboard)/dashboard/page.tsx`

---

## Settings

Currently a **read-only** display of tenant configuration:

| Section | Fields |
|---------|--------|
| Business Information | business_name, business_type, slug |
| Locale Settings | country, currency, timezone |
| Subscription | plan, status (active/trial) |
| Account | user name, email |

**Frontend Page:** `frontend/src/app/(dashboard)/settings/page.tsx`

---

## API Reference (Summary)

### Base URL

```
Development: http://localhost:8000/api
LAN Access:  http://<server-ip>:8000/api
```

### Authentication Headers

```
Authorization: Bearer <jwt-token>
Content-Type: application/json
Accept: application/json
```

### Route Groups

| Group | Middleware | Prefix | Routes |
|-------|-----------|--------|--------|
| Health | none | `/` | 1 |
| Auth (public) | none | `/auth` | 4 |
| Auth (protected) | `auth:api` | `/auth` | 7 |
| Tenant resources | `auth:api`, `tenant` | `/` | 58+ |

### Full Endpoint Count

| Resource | Endpoints |
|----------|-----------|
| Authentication | 11 |
| Categories | 5 |
| Products | 6 |
| Addon Groups | 7 |
| Orders | 6 |
| Bills | 6 |
| Tables | 6 |
| Kitchen Stations | 5 |
| Customers | 6 |
| Staff | 7 |
| Health | 1 |
| **Total** | **70+** |

---

## Database Schema

### Main Database: `flopos_main` (6 tables)

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `users` | User accounts | email, phone, password, is_active |
| `tenants` | Business accounts | business_name, slug, database_name, business_type, country, plan, status |
| `tenant_user` | User ↔ Tenant pivot | role, permissions, is_active |
| `subscriptions` | Razorpay billing | plan, amount, status, period dates |
| `global_customers` | Cross-tenant customers | phone_hash (SHA-256), consent tracking |
| `otp_verifications` | Password reset OTPs | email, otp, purpose, expires_at |

### Tenant Databases: `tenant_{slug}` (11 tables each)

| Table | Purpose | Key Relationships |
|-------|---------|-------------------|
| `categories` | Product categories | self-referential (parent_id) |
| `products` | Product catalog | belongs to category |
| `addon_groups` | Product customizations | many-to-many with products |
| `addons` | Individual addons | belongs to addon_group |
| `addon_group_product` | Pivot table | product ↔ addon_group |
| `kitchen_stations` | KDS zones | has many tables |
| `tables` | Dine-in tables | belongs to kitchen_station |
| `customers` | Tenant customers | has many orders, bills |
| `orders` | Order records | has many items, has one bill |
| `order_items` | Order line items | belongs to order, product |
| `bills` | Payment records | belongs to order, customer |
| `staff` | Employee records | linked to user_id (optional) |

### Key Enums

| Enum | Values |
|------|--------|
| Business Type | `restaurant`, `salon`, `retail` |
| Order Type | `dine_in`, `takeaway`, `delivery`, `online` |
| Order Status | `pending`, `preparing`, `ready`, `served`, `completed`, `cancelled` |
| Payment Status | `unpaid`, `partial`, `paid`, `refunded` |
| Table Status | `available`, `occupied`, `reserved`, `maintenance` |
| Tax Type | `inclusive`, `exclusive`, `exempt` |
| Staff Role | `manager`, `cashier`, `cook`, `waiter`, `delivery` |
| Tenant Plan | `trial`, `basic`, `premium`, `enterprise` |
| Payment Method | `cash`, `card`, `upi`, `wallet` |

---

## Frontend Architecture

### Pages (11 total)

| Route | Page | Auth Required |
|-------|------|---------------|
| `/auth/login` | Login + Tenant Selection | No |
| `/auth/register` | Registration | No |
| `/dashboard` | Analytics Dashboard | Yes |
| `/pos` | Point of Sale | Yes |
| `/orders` | Order Management | Yes |
| `/products` | Product CRUD | Yes |
| `/tables` | Table Management | Yes |
| `/customers` | Customer Management | Yes |
| `/staff` | Staff Management | Yes |
| `/kitchen-display` | Kitchen Display System | Yes |
| `/settings` | Business Settings | Yes |

### UI Components (shadcn/ui)

17 components built on Radix UI + Tailwind CSS:

`Button`, `Card`, `Input`, `Label`, `Badge`, `Dialog`, `Select`, `Table`, `Tabs`, `Avatar`, `Dropdown Menu`, `Sheet`, `Sidebar`, `Separator`, `Tooltip`, `Skeleton`, `Sonner (Toasts)`

### Sidebar Navigation

Collapsible sidebar using shadcn's sidebar system:

- **Expanded:** Icon + label for each nav item
- **Collapsed:** Icon-only with tooltips
- **Toggle:** Click trigger button or `Ctrl+B` keyboard shortcut
- **Mobile:** Slides in as a sheet overlay
- **State:** Persisted via cookies

### Auto-Refresh Intervals

| Page | Interval | Data |
|------|----------|------|
| Dashboard | 30 seconds | Revenue, orders, bills |
| Orders | 15 seconds | Order list & details |
| Kitchen Display | 5 seconds | Pending & preparing orders |
| Tables | 10 seconds | Table statuses |

---

## Real-Time Updates

### Current Implementation

All real-time updates use **polling** (setInterval) at the intervals listed above.

### Planned (Not Yet Implemented)

- **Laravel Reverb** WebSocket server for true real-time
- Push notifications for order status changes
- Kitchen Display System live updates without polling
- Table status change broadcasts

---

## Security

| Feature | Implementation |
|---------|---------------|
| Data Isolation | Separate PostgreSQL database per tenant |
| Authentication | JWT with 60-minute expiry, 14-day refresh |
| Password Storage | bcrypt hashing (Laravel default) |
| Phone Privacy | SHA-256 hashing for global customer identity |
| CORS | Configured via Laravel HandleCors middleware |
| SQL Injection | Prevented by Eloquent ORM parameterized queries |
| XSS | React's built-in escaping + Next.js CSP headers |
| Token Validation | Every tenant request validates JWT + tenant access |
| OTP Security | 5-minute expiry, single use, IP tracking |
| Input Validation | Laravel FormRequest validation on all endpoints |

### Important Validation Pattern

For tenant database tables, validation rules must specify the connection:

```php
// Correct - queries tenant database
'category_id' => 'exists:tenant.categories,id'

// Wrong - would query main database
'category_id' => 'exists:categories,id'
```

---

## Roadmap

### Completed

- [x] Multi-tenant infrastructure (database-per-tenant)
- [x] JWT authentication (register, login, tenant select)
- [x] Password reset with OTP
- [x] 18 Eloquent models with relationships
- [x] 9 CRUD controllers (70+ routes)
- [x] Full order lifecycle (create → complete/cancel)
- [x] Billing with multi-payment support
- [x] Stock management (auto-adjust on order/cancel)
- [x] Kitchen Display System
- [x] Table management with auto-status
- [x] Customer management with visit/spend tracking
- [x] Staff management with roles
- [x] Next.js 16 frontend with 11 pages
- [x] shadcn/ui component migration
- [x] Zustand state management (auth + cart)
- [x] Dashboard with auto-refresh analytics

### In Progress

- [ ] Category management UI page
- [ ] Addon group management UI page
- [ ] Complete shadcn migration (remaining pages still use some raw HTML elements)
- [ ] Settings page edit functionality
- [ ] Bill management UI (payment recording interface)

### Planned

- [ ] WebSocket real-time updates (Laravel Reverb)
- [ ] Product image upload
- [ ] Reports & analytics (daily, weekly, monthly)
- [ ] Razorpay subscription integration
- [ ] WhatsApp bill sharing
- [ ] WebUSB thermal printing
- [ ] QR code table ordering
- [ ] Dark mode theme
- [ ] PWA support (Add to Home Screen)
- [ ] Multi-outlet support
- [ ] Data export (CSV/PDF)
- [ ] Push notifications
- [ ] Floor plan visual editor

### Launch Target

**April 1, 2026** — Initial launch with 12 migrated clients from legacy PHP system.

---

## Development Quick Reference

### Start Backend

```bash
cd /home/ubuntu/flopos/backend
php artisan serve --host=0.0.0.0 --port=8000
```

### Start Frontend

```bash
cd /home/ubuntu/flopos/frontend
npx next dev --hostname 0.0.0.0 -p 3001
```

### Test Credentials

```
Email: owner@flopos.test
Password: password123
```

### Key Files

| Purpose | Path |
|---------|------|
| API Routes | `backend/routes/api.php` |
| Tenant Middleware | `backend/app/Http/Middleware/TenantDatabaseSwitch.php` |
| Tenant Service | `backend/app/Services/TenantService.php` |
| Auth Store | `frontend/src/store/auth.ts` |
| Cart Store | `frontend/src/store/cart.ts` |
| API Client | `frontend/src/lib/api.ts` |
| Type Definitions | `frontend/src/lib/types.ts` |
| shadcn Config | `frontend/components.json` |

---

*This document is maintained by the development team. For questions, contact dev@flopos.com.*
