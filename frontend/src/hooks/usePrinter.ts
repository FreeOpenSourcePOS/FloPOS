'use client';

import { useEffect } from 'react';
import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { printerService, type PrinterStatus, type PrinterInfo, type PrintMode } from '@/lib/printer/PrinterService';
import { buildReceiptBytes, type ReceiptOptions } from '@/lib/printer/receipt-encoder';
import { buildGstBillBytes, type GstBillOptions } from '@/lib/printer/gst-bill-encoder';
import { buildKotBytes, type KotOptions } from '@/lib/printer/kot-encoder';
import type { Bill, Tenant, Order } from '@/lib/types';

type PrintModeType = 'receipt' | 'gst' | 'kot';
type PaperWidth = 58 | 80;

interface PrinterState {
  status: PrinterStatus;
  deviceInfo: PrinterInfo | null;
  lastError: string | null;
  lastPrintedBytes: Uint8Array | null;
  printMode: PrintModeType;
  paperWidth: PaperWidth;
  printMethod: PrintMode;

  connect: () => Promise<void>;
  disconnect: () => Promise<void>;
  printBill: (bill: Bill, tenant: Pick<Tenant, 'business_name' | 'currency'>, opts?: ReceiptOptions) => Promise<void>;
  printGstBill: (bill: Bill, tenant: Pick<Tenant, 'business_name' | 'currency'>, opts?: GstBillOptions) => Promise<void>;
  printKot: (order: Order, opts?: KotOptions) => Promise<void>;
  setPrintMode: (mode: PrintModeType) => void;
  setPaperWidth: (width: PaperWidth) => void;
  setPrintMethod: (method: PrintMode) => void;
  clearError: () => void;
  downloadLastReceipt: () => void;
  copyLastReceiptHex: () => Promise<void>;
}

export const usePrinterStore = create<PrinterState>()(
  persist(
    (set, get) => ({
      status: 'disconnected',
      deviceInfo: null,
      lastError: null,
      lastPrintedBytes: null,
      printMode: 'receipt',
      paperWidth: 58,
      printMethod: 'escpos',

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
          set({ lastPrintedBytes: bytes });
          
          if (get().printMethod === 'escpos') {
            await printerService.print(bytes);
          } else {
            throw new Error('Browser print mode - use printViaBrowser instead');
          }
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
          set({ lastPrintedBytes: bytes });
          
          if (get().printMethod === 'escpos') {
            await printerService.print(bytes);
          } else {
            throw new Error('Browser print mode - use printViaBrowser instead');
          }
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
          set({ lastPrintedBytes: bytes });
          
          if (get().printMethod === 'escpos') {
            await printerService.print(bytes);
          } else {
            throw new Error('Browser print mode - use printViaBrowser instead');
          }
        } catch (err) {
          set({ lastError: (err as Error).message });
          throw err;
        }
      },

      setPrintMode: (mode) => set({ printMode: mode }),
      setPaperWidth: (width) => set({ paperWidth: width }),
      setPrintMethod: (method) => {
        printerService.setPrintMode(method);
        set({ printMethod: method, lastError: null });
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
      partialize: (state) => ({ printMode: state.printMode, paperWidth: state.paperWidth, printMethod: state.printMethod }),
    }
  )
);

export function usePrinterStatusSync(): void {
  const store = usePrinterStore();

  useEffect(() => {
    usePrinterStore.setState({
      status: printerService.status,
      deviceInfo: printerService.deviceInfo,
    });

    const unsub = printerService.onStatusChange((status, info) => {
      usePrinterStore.setState({
        status,
        deviceInfo: info ?? printerService.deviceInfo,
      });
    });

    return unsub;
  }, []);
}
