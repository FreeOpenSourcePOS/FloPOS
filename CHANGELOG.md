# Changelog

All notable changes to Flo POS will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Thermal printer support (ESCPOS) for 58mm and 80mm paper
- KOT (Kitchen Order Ticket) printing
- Indian GST billing with CGST/SGST/IGST breakdown
- A4/A5 web-based printing
- WhatsApp bill sharing via wa.me
- Loyalty points system with wallet balance

### Changed
- Updated to Next.js 16
- Updated to Laravel 12
- Multi-tenant architecture with separate databases per tenant

## 2026-02-16 (v3) — Addon Management System

### Seed Data
- Created 4 addon groups: Spice Level (required), Extra Protein, Dressings, Toppings
- 15 individual addons with prices (Free to ₹120)
- 86 product-addon links across all relevant categories
- Set proper dietary types: veg, non_veg, egg, vegan across 39 products

### New: Addon Groups Management Page (`/addon-groups`)
- Full CRUD for addon groups (name, description, required/optional, min/max selection)
- Expandable groups showing individual addons
- Inline add/edit/delete for addons within each group (name + price)
- Required/Optional badges, addon count display

### Updated: Products Page (`/products`)
- Added Dietary Type dropdown (Veg, Non-Veg, Egg, Vegan, None) to create/edit form
- Added Addon Groups multi-select checkboxes to create/edit form
- Pre-populates both fields when editing existing product

### Updated: Sidebar
- Added "Addon Groups" nav link with Puzzle icon between Products and Tables

### Files Modified/Created

| File | Action |
|------|---------|
| `frontend/src/app/(dashboard)/addon-groups/page.tsx` | Created — full CRUD page |
| `frontend/src/app/(dashboard)/products/page.tsx` | Modified — added dietary_type + addon_group_ids to form |
| `frontend/src/components/layout/Sidebar.tsx` | Modified — added Addon Groups nav link |
| `frontend/public/sw.js` | Bumped cache to `flo-v7` |

---

## 2026-02-16 (v2) — Responsive Layout Fix

### Root Cause
The POS layout had a chain of unbounded flex containers. `SidebarInset` (shadcn) renders as a `<main>` with `flex flex-col flex-1`, but had no fixed height. The dashboard layout added another `<main>` inside it (invalid HTML). The POS page used `h-[calc(100vh-5rem)]` which didn't account for the sidebar squeezing the content area. The mobile Drawer wrapper was a flex child stealing layout space.

### Files Modified

| File | Changes |
|------|---------|
| `frontend/src/app/(dashboard)/layout.tsx` | Added `h-screen overflow-hidden` to `SidebarInset` to bound the flex column; Changed inner `<main>` to `<div>` (was invalid nested `<main>` tags); Kept `overflow-auto` on inner div |
| `frontend/src/app/(dashboard)/pos/page.tsx` | Changed outer container from `h-[calc(100vh-5rem)]` to `h-full` (parent is now bounded); Added `h-full flex flex-col` to product grid wrapper; Added `h-full` to desktop cart wrapper; Moved `<Drawer>` outside the flex container entirely (it renders via portal, doesn't need to be a flex child); Used React fragment `<>` as outer wrapper |
| `frontend/src/components/pos/ProductGrid.tsx` | Added `h-full overflow-hidden` to outer div for proper height constraint; Added `shrink-0` to search/category bar so it doesn't get compressed |
| `frontend/public/sw.js` | Bumped cache to `flo-v6` |

### How It Works Now
```
SidebarInset (h-screen, overflow-hidden, flex flex-col)
  └─ header (h-12, shrink-0)
  └─ div (flex-1, overflow-auto, p-4)
       └─ POS page (flex, h-full, overflow-hidden)
            ├─ ProductGrid wrapper (flex-1, h-full, flex flex-col)
            │   ├─ Search + Category tabs (shrink-0)
            │   └─ Product cards grid (flex-1, overflow-y-auto)
            └─ CartPanel wrapper (w-80, h-full, hidden on mobile)
       └─ Drawer (outside flex, renders via portal)
```

---

## 2026-02-16 (v1) — POS UI Fixes & Customer Workflow Enhancements

### Files Modified

| File | Changes |
|------|---------|
| `frontend/src/app/(dashboard)/pos/page.tsx` | Added customer mandatory validation before placing order |
| `frontend/src/components/pos/ProductGrid.tsx` | Fixed dietary badge positioning — changed from `absolute` overlay to inline `flex` next to product name when images are hidden; Added "customisable" tag on products that have addon groups |
| `frontend/src/components/pos/CustomerSearch.tsx` | Added phone digit validation using configurable `phoneDigits` setting; Auto-populates phone field when search query is numeric; Added proper error handling with toast notifications on customer creation; Phone input now strips non-digits and enforces max length |
| `frontend/src/store/pos-settings.ts` | Added `customerMandatory` (boolean, default false) and `phoneDigits` (number, default 10) settings with persist |
| `frontend/src/app/(dashboard)/settings/page.tsx` | Added "POS Workflow" settings card with Customer Mandatory toggle and Phone Number Digits input |

### Fixes

1. **Veg icon overlap**: When product images are hidden, the dietary badge (green/red/yellow dot) was positioned absolutely and overlapped the product name. Now rendered inline using flexbox next to the product name.

2. **Addon indicator**: Products with addon groups now show a small "customisable" tag next to the price, so users know they can customize before clicking.

### New Features

3. **Customer Mandatory setting**: New toggle in Settings → POS Workflow. When enabled, orders cannot be placed without selecting a customer first. Validation happens in `handlePlaceOrder()` with a toast error.

4. **Phone number digit validation**: Configurable in Settings → POS Workflow (default: 10 for India). The customer creation form:
   - Only accepts digits (strips non-numeric chars)
   - Enforces exact digit count before allowing creation
   - Shows placeholder with expected digit count

5. **Phone auto-populate**: When searching by a phone number (e.g. "9876543210") and clicking "New Customer", the phone field is automatically populated with the search query.

6. **Customer creation error handling**: Previously errors were silently ignored. Now displays toast notifications for validation errors and success confirmations.
