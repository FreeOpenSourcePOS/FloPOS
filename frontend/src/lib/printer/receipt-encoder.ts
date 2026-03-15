/**
 * receipt-encoder.ts
 *
 * Converts a Flo POS Bill (+ its nested Order) into a raw ESC/POS Uint8Array
 * using `esc-pos-encoder`.
 *
 * Install the package first:
 *   npm install esc-pos-encoder
 *   npm install --save-dev @types/esc-pos-encoder   # if types aren't bundled
 *
 * The encoder targets 58 mm paper (32 chars wide) by default.
 * Pass { paperWidth: 80 } (48 chars) for 80 mm rolls.
 */

import ReceiptPrinterEncoder from '@point-of-sale/receipt-printer-encoder';
import type { Bill, Tenant } from '@/lib/types';

export interface ReceiptOptions {
  /** 58 mm (32 chars) or 80 mm (48 chars). Default: 58 */
  paperWidth?: 58 | 80;
  /** Show a "Thank you" footer line. Default: true */
  showFooter?: boolean;
  /** Extra line of custom text printed below the footer. */
  footerNote?: string;
}

const CHARS: Record<58 | 80, number> = { 58: 32, 80: 48 };

/**
 * Build a receipt byte array from a fully-loaded Bill object.
 * The Bill must have `order` (with `items`) populated.
 */
export function buildReceiptBytes(
  bill: Bill,
  tenant: Pick<Tenant, 'business_name' | 'currency'>,
  opts: ReceiptOptions = {}
): Uint8Array {
  const { paperWidth = 58, showFooter = true, footerNote } = opts;
  const cols = CHARS[paperWidth];
  const currency = tenant.currency ?? '';
  const order = bill.order;

  const enc = new ReceiptPrinterEncoder({ columns: cols });

  // ── Header ────────────────────────────────────────────────────────────────
  enc
    .initialize()
    .align('center')
    .bold(true)
    .width(2)
    .height(2)
    .text(truncate(tenant.business_name, 16))
    .width(1)
    .height(1)
    .bold(false)
    .newline();

  if (order?.table?.name) {
    enc.text(`Table: ${order.table.name}`).newline();
  }
  if (order?.customer?.name) {
    enc.text(order.customer.name).newline();
  }

  enc
    .text(`Bill #${bill.bill_number}`)
    .newline()
    .text(formatDate(bill.order?.created_at))
    .newline()
    .align('left')
    .rule({ style: 'single' });

  // ── Line Items ─────────────────────────────────────────────────────────────
  const items = order?.items ?? [];
  for (const item of items) {
    const name = truncate(item.product_name, cols - 10);
    const price = formatAmount(item.total, currency);
    enc.text(padRow(name, price, cols)).newline();

    // Addons indented under the item
    if (item.addons && item.addons.length > 0) {
      for (const addon of item.addons) {
        const addonLabel = truncate(`  + ${addon.name}`, cols - 8);
        const addonPrice =
          addon.price && Number(addon.price) > 0
            ? formatAmount(Number(addon.price) * item.quantity, currency)
            : '';
        enc.text(padRow(addonLabel, addonPrice, cols)).newline();
      }
    }

    // Qty × unit price on the next line if qty > 1
    if (item.quantity > 1) {
      enc
        .align('right')
        .size('small')
        .text(
          `${item.quantity} × ${formatAmount(item.unit_price, currency)}`
        )
        .size('normal')
        .align('left')
        .newline();
    }
  }

  enc.rule({ style: 'single' });

  // ── Totals ─────────────────────────────────────────────────────────────────
  const totals: [string, string][] = [
    ['Subtotal', formatAmount(bill.subtotal, currency)],
  ];

  if (Number(bill.discount_amount) > 0) {
    totals.push(['Discount', `-${formatAmount(bill.discount_amount, currency)}`]);
  }
  if (Number(bill.tax_amount) > 0) {
    totals.push(['Tax', formatAmount(bill.tax_amount, currency)]);
  }
  if (Number(bill.service_charge) > 0) {
    totals.push(['Service charge', formatAmount(bill.service_charge, currency)]);
  }
  if (Number(bill.delivery_charge) > 0) {
    totals.push(['Delivery', formatAmount(bill.delivery_charge, currency)]);
  }

  for (const [label, value] of totals) {
    enc.text(padRow(label, value, cols)).newline();
  }

  enc.rule({ style: 'double' });
  enc
    .bold(true)
    .text(padRow('TOTAL', formatAmount(bill.total, currency), cols))
    .bold(false)
    .newline();

  // ── Payment Details ────────────────────────────────────────────────────────
  if (bill.payment_details && bill.payment_details.length > 0) {
    enc.newline();
    for (const p of bill.payment_details) {
      enc
        .text(padRow(capitalise(p.method), formatAmount(p.amount, currency), cols))
        .newline();
    }
  }

  // ── Footer ─────────────────────────────────────────────────────────────────
  if (showFooter) {
    enc
      .newline()
      .align('center')
      .text('Thank you for your visit!')
      .newline();

    if (footerNote) {
      enc.text(truncate(footerNote, cols)).newline();
    }
  }

  enc.newline().newline().newline().cut();

  return enc.encode();
}

// ---------------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------------

function padRow(left: string, right: string, cols: number): string {
  const gap = cols - left.length - right.length;
  return gap > 0 ? left + ' '.repeat(gap) + right : left.slice(0, cols - right.length - 1) + ' ' + right;
}

function truncate(str: string, max: number): string {
  return str.length > max ? str.slice(0, max - 1) + '…' : str;
}

function formatAmount(value: number | string, currency: string): string {
  return `${currency}${Number(value).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function capitalise(str: string): string {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDate(iso?: string): string {
  if (!iso) return '';
  try {
    return new Date(iso).toLocaleString('en', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return iso;
  }
}
