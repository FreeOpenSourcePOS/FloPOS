'use client';

import { useState, useEffect } from 'react';
import { useAuthStore } from '@/store/auth';
import { usePosSettingsStore, type PaperSize, type BillTemplate } from '@/store/pos-settings';
import { usePrinterStore } from '@/hooks/usePrinter';
import { Settings, Building2, Globe, CreditCard, Monitor, Users, Gift, Printer, Share2, FileText } from 'lucide-react';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import api from '@/lib/api';
import toast from 'react-hot-toast';

// ---------------------------------------------------------------------------
// ASCII previews for template cards
// ---------------------------------------------------------------------------

const CLASSIC_PREVIEW = `  [STORE NAME]
  Table: T1
---------------
Item      Qty Rate Amt
---------------
Burger      1   99   99
  + Sauce
>> No onions
---------------
Subtotal       99
Tax             6
===============
TOTAL         105
Cash          105
---------------
GSTN: xxx  Bill#1
123 Main St`;

const COMPACT_PREVIEW = `  STORE NAME
-----------
Bill #1    12:30
-----------
Burger           99
  2 x 49.50
-----------
TOTAL            99
Cash             99
-----------
  Thank you!`;

const DETAILED_PREVIEW = `  [STORE NAME]
GSTIN: 22XXXXX
  TAX INVOICE
-----------
Bill#1   1 Jan 24
Cust: John
-----------
Item   Qty Rate Amt
Burger   1  99  99
-----------
Subtotal (excl.)  93
CGST @3%           3
SGST @3%           3
===============
TOTAL            99`;

interface TemplateCard {
  id: BillTemplate;
  name: string;
  description: string;
  preview: string;
}

const TEMPLATE_CARDS: TemplateCard[] = [
  {
    id: 'classic',
    name: 'Classic',
    description: 'Rich layout with 4-column item table, addon details, and full totals. Best for dine-in.',
    preview: CLASSIC_PREVIEW,
  },
  {
    id: 'compact',
    name: 'Compact',
    description: 'Minimal, fast layout. One line per item. Ideal for quick service and takeaway.',
    preview: COMPACT_PREVIEW,
  },
  {
    id: 'detailed',
    name: 'Detailed (GST)',
    description: 'Full GST compliance with GSTIN header, TAX INVOICE label, and per-rate tax breakdown.',
    preview: DETAILED_PREVIEW,
  },
];

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

export default function SettingsPage() {
  const { currentTenant, user } = useAuthStore();
  const posSettings = usePosSettingsStore();
  const { printMethod, setPrintMethod } = usePrinterStore();
  const [loyaltyDays, setLoyaltyDays] = useState(365);
  const [savingLoyalty, setSavingLoyalty] = useState(false);
  const [gstin, setGstin] = useState('');
  const [businessAddress, setBusinessAddress] = useState('');
  const [businessPhone, setBusinessPhone] = useState('');
  const [savingBusiness, setSavingBusiness] = useState(false);

  useEffect(() => {
    api.get('/settings/tax').then((res) => {
      if (res.data.loyalty_expiry_days) setLoyaltyDays(Number(res.data.loyalty_expiry_days));
      if (res.data.gstin) {
        setGstin(res.data.gstin);
        posSettings.setBillGstin(res.data.gstin);
      }
      if (res.data.business_address) {
        setBusinessAddress(res.data.business_address);
        posSettings.setBillAddress(res.data.business_address);
      }
      if (res.data.business_phone) {
        setBusinessPhone(res.data.business_phone);
        posSettings.setBillPhone(res.data.business_phone);
      }
    }).catch(() => {});
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const saveLoyalty = async () => {
    setSavingLoyalty(true);
    try {
      await api.put('/settings/loyalty', { loyalty_expiry_days: loyaltyDays });
      toast.success('Loyalty settings saved');
    } catch {
      toast.error('Failed to save');
    } finally {
      setSavingLoyalty(false);
    }
  };

  const saveBusinessInfo = async () => {
    setSavingBusiness(true);
    try {
      await api.put('/settings/business', {
        gstin,
        business_address: businessAddress,
        business_phone: businessPhone,
      });
      posSettings.setBillGstin(gstin);
      posSettings.setBillAddress(businessAddress);
      posSettings.setBillPhone(businessPhone);
      toast.success('Business info saved');
    } catch {
      toast.error('Failed to save');
    } finally {
      setSavingBusiness(false);
    }
  };

  const paperSizeOptions: { value: PaperSize; label: string }[] = [
    { value: 'thermal58', label: '2.5" (58mm)' },
    { value: 'thermal80', label: '3.5" (80mm)' },
    { value: 'a4', label: 'A4 Paper' },
    { value: 'a5', label: 'A5 Paper' },
  ];

  return (
    <div>
      <div className="flex items-center gap-3 mb-6">
        <Settings size={28} className="text-brand" />
        <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
      </div>

      <Tabs defaultValue="general">
        <TabsList className="mb-6">
          <TabsTrigger value="general">General</TabsTrigger>
          <TabsTrigger value="printing">Printing</TabsTrigger>
          <TabsTrigger value="bill-template">Bill Template</TabsTrigger>
        </TabsList>

        {/* ================================================================
            TAB: General
        ================================================================ */}
        <TabsContent value="general">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Business Info */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <Building2 size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">Business Information</h2>
              </div>
              <div className="space-y-3">
                <div>
                  <p className="text-sm text-gray-500">Business Name</p>
                  <p className="font-medium text-gray-900">{currentTenant?.business_name}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Business Type</p>
                  <p className="font-medium text-gray-900 capitalize">{currentTenant?.business_type}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Slug</p>
                  <p className="font-medium text-gray-900">{currentTenant?.slug}</p>
                </div>
              </div>
            </div>

            {/* Locale */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <Globe size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">Locale Settings</h2>
              </div>
              <div className="space-y-3">
                <div>
                  <p className="text-sm text-gray-500">Country</p>
                  <p className="font-medium text-gray-900">{currentTenant?.country === 'IN' ? 'India' : 'Thailand'}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Currency</p>
                  <p className="font-medium text-gray-900">{currentTenant?.currency}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Timezone</p>
                  <p className="font-medium text-gray-900">{currentTenant?.timezone}</p>
                </div>
              </div>
            </div>

            {/* Subscription */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <CreditCard size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">Subscription</h2>
              </div>
              <div className="space-y-3">
                <div>
                  <p className="text-sm text-gray-500">Plan</p>
                  <p className="font-medium text-gray-900 capitalize">{currentTenant?.plan}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Status</p>
                  <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                    currentTenant?.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                  }`}>
                    {currentTenant?.status}
                  </span>
                </div>
              </div>
            </div>

            {/* POS Display */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <Monitor size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">POS Display</h2>
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-gray-900">Show Product Images</p>
                  <p className="text-sm text-gray-500">Display product images in the POS grid</p>
                </div>
                <button
                  onClick={() => posSettings.setShowProductImages(!posSettings.showProductImages)}
                  className={`relative w-11 h-6 rounded-full transition-colors ${
                    posSettings.showProductImages ? 'bg-brand' : 'bg-gray-300'
                  }`}
                >
                  <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${
                    posSettings.showProductImages ? 'translate-x-5' : 'translate-x-0'
                  }`} />
                </button>
              </div>
            </div>

            {/* POS Workflow */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <Users size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">POS Workflow</h2>
              </div>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">Customer Mandatory</p>
                    <p className="text-sm text-gray-500">Require customer selection before placing an order</p>
                  </div>
                  <button
                    onClick={() => posSettings.setCustomerMandatory(!posSettings.customerMandatory)}
                    className={`relative w-11 h-6 rounded-full transition-colors ${
                      posSettings.customerMandatory ? 'bg-brand' : 'bg-gray-300'
                    }`}
                  >
                    <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${
                      posSettings.customerMandatory ? 'translate-x-5' : 'translate-x-0'
                    }`} />
                  </button>
                </div>
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <div>
                      <p className="font-medium text-gray-900">Phone Number Digits</p>
                      <p className="text-sm text-gray-500">Required digit count for phone validation (e.g. 10 for India)</p>
                    </div>
                  </div>
                  <input
                    type="number"
                    min={7}
                    max={15}
                    value={posSettings.phoneDigits}
                    onChange={(e) => posSettings.setPhoneDigits(parseInt(e.target.value) || 10)}
                    className="w-20 px-3 py-1.5 text-sm border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-brand"
                  />
                </div>
              </div>
            </div>

            {/* Loyalty */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <Gift size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">Loyalty Program</h2>
              </div>
              <div className="space-y-4">
                <div>
                  <p className="font-medium text-gray-900">Points Expiry</p>
                  <p className="text-sm text-gray-500 mb-2">Number of days before earned loyalty points expire</p>
                  <div className="flex items-center gap-3">
                    <input
                      type="number"
                      min={1}
                      max={3650}
                      value={loyaltyDays}
                      onChange={(e) => setLoyaltyDays(parseInt(e.target.value) || 365)}
                      className="w-24 px-3 py-1.5 text-sm border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-brand"
                    />
                    <span className="text-sm text-gray-500">days</span>
                    <button
                      onClick={saveLoyalty}
                      disabled={savingLoyalty}
                      className="px-4 py-1.5 text-sm bg-brand text-white rounded-lg hover:opacity-90 disabled:opacity-50"
                    >
                      {savingLoyalty ? 'Saving...' : 'Save'}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            {/* Account */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <h2 className="font-semibold text-gray-900 mb-4">Account</h2>
              <div className="space-y-3">
                <div>
                  <p className="text-sm text-gray-500">Name</p>
                  <p className="font-medium text-gray-900">{user?.name}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Email</p>
                  <p className="font-medium text-gray-900">{user?.email}</p>
                </div>
              </div>
            </div>
          </div>
        </TabsContent>

        {/* ================================================================
            TAB: Printing
        ================================================================ */}
        <TabsContent value="printing">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Printer Settings */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <Printer size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">Printing</h2>
              </div>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">Enable Printer</p>
                    <p className="text-sm text-gray-500">Connect to thermal printer via USB/Bluetooth</p>
                  </div>
                  <button
                    onClick={() => posSettings.setPrinterEnabled(!posSettings.printerEnabled)}
                    className={`relative w-11 h-6 rounded-full transition-colors ${
                      posSettings.printerEnabled ? 'bg-brand' : 'bg-gray-300'
                    }`}
                  >
                    <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${
                      posSettings.printerEnabled ? 'translate-x-5' : 'translate-x-0'
                    }`} />
                  </button>
                </div>

                <div>
                  <p className="font-medium text-gray-900 mb-2">Paper Size</p>
                  <select
                    value={posSettings.printerPaperSize}
                    onChange={(e) => posSettings.setPrinterPaperSize(e.target.value as PaperSize)}
                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-brand"
                  >
                    {paperSizeOptions.map((opt) => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <p className="font-medium text-gray-900 mb-2">Print Method</p>
                  <select
                    value={printMethod}
                    onChange={(e) => setPrintMethod(e.target.value as 'escpos' | 'browser')}
                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-brand"
                  >
                    <option value="escpos">ESCPOS (USB Thermal Printer)</option>
                    <option value="browser">Browser Print (any printer)</option>
                  </select>
                  <p className="text-xs text-gray-500 mt-1">
                    {printMethod === 'escpos'
                      ? 'Direct USB printing via WebUSB — connect the printer from the POS toolbar'
                      : 'Opens the browser print dialog — works with any printer on this computer'}
                  </p>
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">Auto-print KOT</p>
                    <p className="text-sm text-gray-500">Print KOT when order is placed</p>
                  </div>
                  <button
                    onClick={() => posSettings.setAutoPrintKot(!posSettings.autoPrintKot)}
                    className={`relative w-11 h-6 rounded-full transition-colors ${
                      posSettings.autoPrintKot ? 'bg-brand' : 'bg-gray-300'
                    }`}
                  >
                    <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${
                      posSettings.autoPrintKot ? 'translate-x-5' : 'translate-x-0'
                    }`} />
                  </button>
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">Auto-print Bill</p>
                    <p className="text-sm text-gray-500">Print bill when payment is completed</p>
                  </div>
                  <button
                    onClick={() => posSettings.setAutoPrintBill(!posSettings.autoPrintBill)}
                    className={`relative w-11 h-6 rounded-full transition-colors ${
                      posSettings.autoPrintBill ? 'bg-brand' : 'bg-gray-300'
                    }`}
                  >
                    <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${
                      posSettings.autoPrintBill ? 'translate-x-5' : 'translate-x-0'
                    }`} />
                  </button>
                </div>

                <div>
                  <p className="font-medium text-gray-900 mb-2">Web Print Size (A4/A5)</p>
                  <select
                    value={posSettings.webPrintSize}
                    onChange={(e) => posSettings.setWebPrintSize(e.target.value as PaperSize)}
                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-brand"
                  >
                    <option value="a4">A4 (Default)</option>
                    <option value="a5">A5</option>
                  </select>
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">Include GST Details</p>
                    <p className="text-sm text-gray-500">Show GSTIN and tax breakdown on bills</p>
                  </div>
                  <button
                    onClick={() => posSettings.setIncludeGstOnBill(!posSettings.includeGstOnBill)}
                    className={`relative w-11 h-6 rounded-full transition-colors ${
                      posSettings.includeGstOnBill ? 'bg-brand' : 'bg-gray-300'
                    }`}
                  >
                    <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${
                      posSettings.includeGstOnBill ? 'translate-x-5' : 'translate-x-0'
                    }`} />
                  </button>
                </div>
              </div>
            </div>

            {/* WhatsApp Sharing */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <Share2 size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">WhatsApp Sharing</h2>
              </div>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">Enable WhatsApp Share</p>
                    <p className="text-sm text-gray-500">Send bill details via WhatsApp after payment</p>
                  </div>
                  <button
                    onClick={() => posSettings.setWhatsappShareEnabled(!posSettings.whatsappShareEnabled)}
                    className={`relative w-11 h-6 rounded-full transition-colors ${
                      posSettings.whatsappShareEnabled ? 'bg-brand' : 'bg-gray-300'
                    }`}
                  >
                    <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${
                      posSettings.whatsappShareEnabled ? 'translate-x-5' : 'translate-x-0'
                    }`} />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </TabsContent>

        {/* ================================================================
            TAB: Bill Template
        ================================================================ */}
        <TabsContent value="bill-template">
          <div className="space-y-6">
            {/* Template picker */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <FileText size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">Choose Template</h2>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {TEMPLATE_CARDS.map((card) => {
                  const isSelected = posSettings.billTemplate === card.id;
                  return (
                    <button
                      key={card.id}
                      onClick={() => posSettings.setBillTemplate(card.id)}
                      className={`text-left rounded-xl border-2 p-4 transition-all ${
                        isSelected
                          ? 'border-brand bg-brand/5'
                          : 'border-gray-200 hover:border-gray-300 bg-white'
                      }`}
                    >
                      <p className="font-semibold text-gray-900 mb-2">{card.name}</p>
                      <pre className="font-mono text-[9px] leading-tight text-gray-600 bg-gray-50 p-2 rounded overflow-hidden mb-3 whitespace-pre">
                        {card.preview}
                      </pre>
                      <p className="text-xs text-gray-500">{card.description}</p>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Footer message */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <h2 className="font-semibold text-gray-900 mb-4">Footer Message</h2>
              <div>
                <label htmlFor="footer-message" className="block text-sm font-medium text-gray-700 mb-1">
                  Footer Message
                </label>
                <textarea
                  id="footer-message"
                  rows={2}
                  placeholder="e.g. Thank you for visiting!"
                  value={posSettings.billFooterMessage}
                  onChange={(e) => posSettings.setBillFooterMessage(e.target.value)}
                  className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-brand resize-none"
                />
                <p className="text-xs text-gray-400 mt-1">Printed at the bottom of every bill</p>
              </div>
            </div>

            {/* Store Details for Bills */}
            <div className="bg-white rounded-xl border border-gray-100 p-6">
              <div className="flex items-center gap-2 mb-4">
                <Building2 size={20} className="text-gray-500" />
                <h2 className="font-semibold text-gray-900">Store Details for Bills</h2>
              </div>
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">GSTIN Number</p>
                  <input
                    type="text"
                    value={gstin}
                    onChange={(e) => setGstin(e.target.value.toUpperCase())}
                    placeholder="22AAAAA0000A1Z5"
                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div>
                  <p className="text-sm text-gray-500 mb-1">Business Address</p>
                  <textarea
                    value={businessAddress}
                    onChange={(e) => setBusinessAddress(e.target.value)}
                    placeholder="123 Main Street, City, State - 123456"
                    rows={2}
                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div>
                  <p className="text-sm text-gray-500 mb-1">Phone Number</p>
                  <input
                    type="text"
                    value={businessPhone}
                    onChange={(e) => setBusinessPhone(e.target.value)}
                    placeholder="+91 9876543210"
                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <button
                  onClick={saveBusinessInfo}
                  disabled={savingBusiness}
                  className="px-4 py-2 text-sm bg-brand text-white rounded-lg hover:opacity-90 disabled:opacity-50"
                >
                  {savingBusiness ? 'Saving...' : 'Save'}
                </button>
              </div>
            </div>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
}
