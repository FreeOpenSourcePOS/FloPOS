/**
 * usePrinter — Zustand store that wraps PrinterService.
 *
 * Components subscribe to `status`, `deviceInfo`, and `mockMode` and call
 * `connect`, `disconnect`, `print`, and `toggleMock` through this hook.
 */

'use client';

import { useEffect } from 'react';
import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { printerService, type PrinterStatus, type PrinterInfo } from '@/lib/printer/PrinterService';
import { buildReceiptBytes, type ReceiptOptions } from '@/lib/printer/receipt-encoder';
import { buildGstBillBytes, type GstBillOptions } from '@/lib/printer/gst-bill-encoder';
import { buildKotBytes, type KotOptions } from '@/lib/printer/kot-encoder';
import type { Bill, Tenant, Order } from '@/lib/types';

type PrintMode = 'receipt' | 'gst' | 'kot';
type PaperWidth = 58 | 80;

interface PrinterState {
  status: PrinterStatus;
  deviceInfo: PrinterInfo | null;
  mockMode: boolean;
  lastError: string | null;
  lastPrintedBytes: Uint8Array | null;
  printMode: PrintMode;
  paperWidth: PaperWidth;

  // Actions
  connect: () => Promise<void>;
  disconnect: () => Promise<void>;
  printBill: (bill: Bill, tenant: Pick<Tenant, 'business_name' | 'currency'>, opts?: ReceiptOptions) => Promise<void>;
  printGstBill: (bill: Bill, tenant: Pick<Tenant, 'business_name' | 'currency'>, opts?: GstBillOptions) => Promise<void>;
  printKot: (order: Order, opts?: KotOptions) => Promise<void>;
  setPrintMode: (mode: PrintMode) => void;
  setPaperWidth: (width: PaperWidth) => void;
  toggleMock: () => void;
  clearError: () => void;
  downloadLastReceipt: () => void;
  copyLastReceiptHex: () => Promise<void>;
}

export const usePrinterStore = create<PrinterState>()(
  persist(
    (set, get) => ({
      status: printerService.isMock ? 'mock' : 'disconnected',
      deviceInfo: null,
      mockMode: false,
      lastError: null,
      lastPrintedBytes: null,
      printMode: 'receipt',
      paperWidth: 58,

      connect: async () => {
        set({ lastError: null });
        try {
          await printerService.connect();
        } catch (err) {
          set({ lastError: (err as Error).message });
        }
      },

      disconnect: async () => {
        await printerService.disconnect();
      },

      printBill: async (bill, tenant, opts) => {
        set({ lastError: null });
        try {
          const { paperWidth } = get();
          const bytes = buildReceiptBytes(bill, tenant, { ...opts, paperWidth });
          if (get().mockMode) set({ lastPrintedBytes: bytes });
          await printerService.print(bytes);
        } catch (err) {
          set({ lastError: (err as Error).message });
          throw err;
        }
      },

      printGstBill: async (bill, tenant, opts) => {
        set({ lastError: null });
        try {
          const { paperWidth } = get();
          const bytes = buildGstBillBytes(bill, tenant, { ...opts, paperWidth });
          if (get().mockMode) set({ lastPrintedBytes: bytes });
          await printerService.print(bytes);
        } catch (err) {
          set({ lastError: (err as Error).message });
          throw err;
        }
      },

      printKot: async (order, opts) => {
        set({ lastError: null });
        try {
          const { paperWidth } = get();
          const bytes = buildKotBytes(order, { ...opts, paperWidth });
          if (get().mockMode) set({ lastPrintedBytes: bytes });
          await printerService.print(bytes);
        } catch (err) {
          set({ lastError: (err as Error).message });
          throw err;
        }
      },

      setPrintMode: (mode) => set({ printMode: mode }),
      setPaperWidth: (width) => set({ paperWidth: width }),

      toggleMock: () => {
        const next = !get().mockMode;
        printerService.setMock(next);
        set({ mockMode: next, status: next ? 'mock' : 'disconnected', deviceInfo: null, lastError: null });
      },

      clearError: () => set({ lastError: null }),

      downloadLastReceipt: () => {
        const bytes = get().lastPrintedBytes;
        if (!bytes) return;
        const blob = new Blob([bytes.buffer as ArrayBuffer], { type: 'application/octet-stream' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'receipt.bin';
        a.click();
        URL.revokeObjectURL(url);
      },

      copyLastReceiptHex: async () => {
        const bytes = get().lastPrintedBytes;
        if (!bytes) return;
        const hex = Array.from(bytes)
          .map((b) => b.toString(16).padStart(2, '0').toUpperCase())
          .join(' ');
        await navigator.clipboard.writeText(hex);
      },
    }),
    {
      name: 'flo-printer-settings',
      partialize: (state) => ({ mockMode: state.mockMode, printMode: state.printMode, paperWidth: state.paperWidth }),
      onRehydrateStorage: () => (state) => {
        if (state?.mockMode) {
          printerService.setMock(true);
        }
      },
    }
  )
);

/**
 * Subscribe the store to live status updates from the PrinterService singleton.
 */
export function usePrinterStatusSync(): void {
  const store = usePrinterStore();

  useEffect(() => {
    usePrinterStore.setState({
      status: printerService.status,
      deviceInfo: printerService.deviceInfo,
      mockMode: printerService.isMock,
    });

    const unsub = printerService.onStatusChange((status, info) => {
      usePrinterStore.setState({
        status,
        deviceInfo: info ?? printerService.deviceInfo,
      });
    });

    return unsub;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
}
