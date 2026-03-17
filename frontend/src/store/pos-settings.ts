import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export type PaperSize = 'thermal58' | 'thermal80' | 'a4' | 'a5';
export type PrinterPrintMode = 'escpos' | 'browser';

interface PosSettingsState {
  showProductImages: boolean;
  customerMandatory: boolean;
  phoneDigits: number;
  // Printer settings
  printerPaperSize: PaperSize;
  printerEnabled: boolean;
  printerPrintMode: PrinterPrintMode;
  autoPrintKot: boolean;
  autoPrintBill: boolean;
  whatsappShareEnabled: boolean;
  // Web print settings
  defaultPrintMode: 'thermal' | 'web';
  webPrintSize: PaperSize;
  includeGstOnBill: boolean;
  // Actions
  setShowProductImages: (show: boolean) => void;
  setCustomerMandatory: (mandatory: boolean) => void;
  setPhoneDigits: (digits: number) => void;
  setPrinterPaperSize: (size: PaperSize) => void;
  setPrinterEnabled: (enabled: boolean) => void;
  setPrinterPrintMode: (mode: PrinterPrintMode) => void;
  setAutoPrintKot: (enabled: boolean) => void;
  setAutoPrintBill: (enabled: boolean) => void;
  setWhatsappShareEnabled: (enabled: boolean) => void;
  setDefaultPrintMode: (mode: 'thermal' | 'web') => void;
  setWebPrintSize: (size: PaperSize) => void;
  setIncludeGstOnBill: (include: boolean) => void;
}

export const usePosSettingsStore = create<PosSettingsState>()(
  persist(
    (set) => ({
      showProductImages: true,
      customerMandatory: false,
      phoneDigits: 10,
      // Printer defaults
      printerPaperSize: 'thermal58',
      printerEnabled: false,
      printerPrintMode: 'escpos',
      autoPrintKot: false,
      autoPrintBill: false,
      whatsappShareEnabled: true,
      // Web print defaults
      defaultPrintMode: 'thermal',
      webPrintSize: 'a4',
      includeGstOnBill: false,
      // Actions
      setShowProductImages: (show) => set({ showProductImages: show }),
      setCustomerMandatory: (mandatory) => set({ customerMandatory: mandatory }),
      setPhoneDigits: (digits) => set({ phoneDigits: digits }),
      setPrinterPaperSize: (size) => set({ printerPaperSize: size }),
      setPrinterEnabled: (enabled) => set({ printerEnabled: enabled }),
      setPrinterPrintMode: (mode) => set({ printerPrintMode: mode }),
      setAutoPrintKot: (enabled) => set({ autoPrintKot: enabled }),
      setAutoPrintBill: (enabled) => set({ autoPrintBill: enabled }),
      setWhatsappShareEnabled: (enabled) => set({ whatsappShareEnabled: enabled }),
      setDefaultPrintMode: (mode) => set({ defaultPrintMode: mode }),
      setWebPrintSize: (size) => set({ webPrintSize: size }),
      setIncludeGstOnBill: (include) => set({ includeGstOnBill: include }),
    }),
    { name: 'pos-settings' }
  )
);
