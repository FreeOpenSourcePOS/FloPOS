# FloPos - Self-Hosted POS System

## Vision

**"You Own Your Data"** - A zero-knowledge, self-hosted Point of Sale system where merchants have complete control over their data. No mandatory cloud dependency. No data harvesting.

## Architecture

### Desktop App (Electron)
- **Location**: `/var/www/flopos/desktop/`
- **Stack**: Electron + TypeScript + SQLite (local)
- **Distribution**: Windows, macOS, Linux via electron-builder

### Key Principles
1. **Local-First**: All data stays on merchant's machine
2. **No Backend Required**: Works completely offline
3. **KDS via WebSocket**: Kitchen Display System on local network
4. **Optional Cloud Sync**: Only for paid features (loyalty, Zomato, etc.)

---

## Feature Tiers

### FREE (Self-Hosted)
All core POS features - no monthly fees, no cloud required.

| Feature | Description |
|---------|-------------|
| POS Operations | Order taking, billing, table management |
| Product Catalog | Categories, products, modifiers, add-ons |
| Staff Management | Roles, PIN login, permissions |
| Kitchen Display | Web-based KDS via local WebSocket |
| Tax Calculations | India GST (CGST+SGST/IGST), Thailand VAT |
| Receipt Printing | ESC/POS network printing (port 9100) |
| Reports | Sales, X/Z reports, daily summaries |
| Database | SQLite with backup/restore |
| Desktop App | Windows, macOS, Linux |

### PAID (Cloud-Connected)
Subscription-based features requiring cloud sync.

| Feature | Description | Notes |
|---------|-------------|-------|
| **Loyalty Program** | Points ledger, expiration | 60-day retention |
| **Zomato Integration** | Order sync, menu push | API required |
| **Swiggy Integration** | Future | Planned |
| **Cloud Backup | E2E encrypted | Client-side encryption |
| **Multi-Device Sync** | Tablet + phone KDS | Local network |
| **Analytics Dashboard** | Cloud-hosted insights | Future |

---

## Support & Subscription Model

### Support Calls (Free Tier)
- **2 Free Support Calls** per installation
- Tracked in flo-admin portal
- Additional calls require paid support pack

### Subscription Tracking (flo-admin)

```
Merchant POS Installation
├── Subscription Plan (free/paid)
├── Support Calls Used / Remaining
├── Account Manager (mapped)
├── Reseller (if applicable)
└── Billing Cycle
```

### flo-admin Portal Additions

New tables needed:
```sql
-- Merchant subscriptions
CREATE TABLE merchant_subscriptions (
  id TEXT PRIMARY KEY,
  merchant_id TEXT NOT NULL,  -- References their business
  plan_type TEXT DEFAULT 'free', -- free, starter, professional, enterprise
  status TEXT DEFAULT 'active',
  started_at TEXT,
  expires_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Support call tracking
CREATE TABLE support_calls (
  id TEXT PRIMARY KEY,
  merchant_id TEXT NOT NULL,
  called_at TEXT DEFAULT CURRENT_TIMESTAMP,
  issue_type TEXT, -- technical, billing, feature_request
  duration_minutes INTEGER,
  notes TEXT,
  resolution TEXT,
  created_by TEXT  -- Account manager who handled
);

-- Account managers
CREATE TABLE account_managers (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  email TEXT UNIQUE,
  phone TEXT,
  is_active INTEGER DEFAULT 1
);

-- Resellers
CREATE TABLE resellers (
  id TEXT PRIMARY KEY,
  company_name TEXT NOT NULL,
  contact_name TEXT,
  email TEXT UNIQUE,
  phone TEXT,
  commission_percent REAL DEFAULT 10,
  is_active INTEGER DEFAULT 1
);

-- Merchant to account manager mapping
CREATE TABLE merchant_account_managers (
  merchant_id TEXT PRIMARY KEY,
  account_manager_id TEXT,
  reseller_id TEXT,
  assigned_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_manager_id) REFERENCES account_managers(id),
  FOREIGN KEY (reseller_id) REFERENCES resellers(id)
);
```

---

## Current Status

### Completed
- ✅ SQLite database schema (15+ tables)
- ✅ REST API server (port 3001)
- ✅ All core endpoints tested and working:
  - `/api/auth/login` - JWT authentication
  - `/api/categories` - CRUD
  - `/api/products` - CRUD
  - `/api/orders` - CRUD with items
  - `/api/customers` - CRUD
  - `/api/tables` - CRUD
  - `/api/settings` - Key-value store
  - `/api/reports/sales` - Daily sales
  - `/api/bills` - Bills with payments
  - `/api/kitchen-stations` - KDS stations
  - `/api/addon-groups` - Product addons
  - `/api/staff` - Staff management
  - `/api/kds` - Kitchen display WebSocket
- ✅ Tax service (India GST, Thailand VAT)
- ✅ KDS WebSocket service
- ✅ Thermal printer service (ESC/POS)
- ✅ Electron desktop app (packaged for Linux AppImage)
- ✅ **PHP backend REMOVED** - replaced by Node.js/Electron
- ✅ flo-admin new pages created:
  - `/subscription` - Manage merchant subscriptions
  - `/support` - Log and track support calls
  - `/team` - Account managers management
  - `/resellers` - Reseller management

### In Progress
- ⏳ Build Windows/macOS installers
- ⏳ Test on actual hardware (printers, etc.)

### Pending (Next Phase)
- 🔲 Loyalty points cloud sync
- 🔲 Zomato integration API
- 🔲 E2E encrypted cloud backup
- 🔲 API implementation in cloud backend (app.flopos.com)

---

## Technical Notes

### Database
- **Engine**: SQLite via `better-sqlite3`
- **Location**: `./flopos.db` (local to app)
- **WAL Mode**: Enabled for concurrent reads

### API Authentication
- **Method**: JWT (jsonwebtoken)
- **Expiry**: 24 hours
- **Header**: `Authorization: Bearer <token>`

### Tax Calculation
```typescript
// India GST
CGST = amount * (taxRate / 2)
SGST = amount * (taxRate / 2)  // Intra-state
IGST = amount * taxRate        // Inter-state

// Thailand VAT
VAT = amount * (7 / 100)
```

### KDS (Kitchen Display System)
- **Protocol**: WebSocket (`ws://localhost:3001/kds`)
- **Pairing**: Station ID based
- **Updates**: Real-time order status broadcasts

### Printer Communication
- **Protocol**: TCP (port 9100)
- **Format**: ESC/POS commands
- **Types**: Receipt printers, KOT printers

---

## Migration from PHP/Laravel

### Old Stack (Being Removed)
- `/var/www/flopos/backend/` - PHP/Laravel
- PostgreSQL database
- Composer dependencies
- Multi-tenant architecture

### New Stack
- `/var/www/flopos/desktop/` - Electron/Node.js
- SQLite (local)
- No external dependencies for core operation

### Data That MUST Stay Local
- Transactions (orders, bills)
- Inventory (products, categories)
- Customer PII
- Printer configurations
- Staff credentials

### Data That Can Sync (Paid)
- Loyalty points (encrypted)
- Aggregated sales data (non-PII)
- Menu data (for Zomato/Swiggy)

---

## File Structure

```
/var/www/flopos/
├── backend/                    # PHP/Laravel (TO BE REMOVED)
│   ├── app/Http/Controllers/
│   ├── app/Models/
│   ├── app/Services/
│   └── database/migrations/
├── frontend/                  # Next.js (reference only)
└── desktop/                   # Electron app (ACTIVE)
    ├── main/                 # Main process
    │   ├── index.ts          # Electron entry
    │   ├── db.ts             # SQLite setup
    │   ├── server.ts         # Express + WebSocket
    │   ├── routes/           # API routes
    │   ├── services/        # Tax, KDS
    │   └── printers/        # Thermal printing
    ├── renderer/             # Static HTML for production
    ├── dist/                 # Compiled JS
    └── release/             # Built installers
```

---

## Next Steps

1. **Remove PHP backend** once all endpoints are migrated
2. **Update flo-admin** with subscription tables
3. **Build production installers** for distribution
4. **Set up cloud sync** for loyalty (paid feature)
5. **Implement Zomato API** integration
