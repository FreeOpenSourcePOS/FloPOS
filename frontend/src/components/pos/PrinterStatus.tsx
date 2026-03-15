'use client';

/**
 * PrinterStatus — toolbar button that shows printer connection state and
 * exposes connect / disconnect / mock-toggle actions.
 *
 * Place it in the POS page header or sidebar header alongside other toolbar
 * icons.  Example:
 *
 *   <PrinterStatus currency={currency} />
 *
 * The `navigator.usb.requestDevice` picker is only opened on an explicit user
 * click, satisfying the browser's "transient user activation" requirement.
 */

import {
  Printer,
  PrinterCheck,
  PrinterX,
  Loader2,
  FlaskConical,
  Unplug,
  ChevronDown,
  Download,
  Clipboard,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePrinterStore, usePrinterStatusSync } from '@/hooks/usePrinter';
import type { PrinterStatus } from '@/lib/printer/PrinterService';
import toast from 'react-hot-toast';

// ---------------------------------------------------------------------------
// Visual config per status
// ---------------------------------------------------------------------------
const STATUS_CONFIG: Record<
  PrinterStatus,
  { label: string; color: string; Icon: React.ElementType }
> = {
  disconnected: {
    label: 'No Printer',
    color: 'text-gray-400',
    Icon: Printer,
  },
  connecting: {
    label: 'Connecting…',
    color: 'text-amber-500',
    Icon: Loader2,
  },
  connected: {
    label: 'Printer Ready',
    color: 'text-green-600',
    Icon: PrinterCheck,
  },
  error: {
    label: 'Printer Error',
    color: 'text-red-500',
    Icon: PrinterX,
  },
  mock: {
    label: 'Mock Mode',
    color: 'text-purple-500',
    Icon: FlaskConical,
  },
};

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------
export default function PrinterStatus() {
  // Sync live events from the PrinterService singleton into the store.
  usePrinterStatusSync();

  const {
    status, deviceInfo, mockMode, lastError, lastPrintedBytes,
    connect, disconnect, toggleMock, clearError,
    downloadLastReceipt, copyLastReceiptHex,
  } = usePrinterStore();

  const cfg = STATUS_CONFIG[status];
  const Icon = cfg.Icon;

  const handleConnect = async () => {
    clearError();
    await connect();
    if (usePrinterStore.getState().status === 'connected') {
      toast.success('Printer connected');
    } else if (usePrinterStore.getState().lastError) {
      toast.error(usePrinterStore.getState().lastError!);
    }
  };

  const handleDisconnect = async () => {
    await disconnect();
    toast('Printer disconnected');
  };

  const isConnected = status === 'connected';
  const isConnecting = status === 'connecting';

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className={`flex items-center gap-1.5 ${cfg.color} border-current/30`}
        >
          <Icon
            size={16}
            className={isConnecting ? 'animate-spin' : undefined}
          />
          <span className="hidden sm:inline text-xs font-medium">
            {cfg.label}
          </span>
          <ChevronDown size={12} className="text-gray-400" />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuLabel className="text-xs text-gray-500">
          Receipt Printer (Serial)
        </DropdownMenuLabel>

        {/* Device info when connected */}
        {isConnected && deviceInfo && (
          <div className="px-2 py-1.5 text-xs text-gray-500 border-b border-gray-100">
            <p className="font-medium text-gray-700 truncate">
              {deviceInfo.productName ?? 'Unknown device'}
            </p>
            <p>{deviceInfo.manufacturerName ?? `VID:${deviceInfo.vendorId.toString(16).toUpperCase()}`}</p>
          </div>
        )}

        {/* Error message */}
        {lastError && (
          <div className="px-2 py-1.5 text-xs text-red-600 bg-red-50 rounded mx-1 my-1">
            {lastError}
          </div>
        )}

        <DropdownMenuSeparator />

        {/* Connect — only when not already connected or in mock mode */}
        {!isConnected && !mockMode && (
          <DropdownMenuItem
            onClick={handleConnect}
            disabled={isConnecting}
            className="text-sm cursor-pointer"
          >
            <Printer size={14} className="mr-2" />
            {isConnecting ? 'Connecting…' : 'Select Serial Port'}
          </DropdownMenuItem>
        )}

        {/* Disconnect */}
        {isConnected && (
          <DropdownMenuItem
            onClick={handleDisconnect}
            className="text-sm cursor-pointer text-red-600 focus:text-red-600"
          >
            <Unplug size={14} className="mr-2" />
            Disconnect
          </DropdownMenuItem>
        )}

        <DropdownMenuSeparator />

        {/* Mock mode toggle */}
        <DropdownMenuItem
          onClick={toggleMock}
          className="text-sm cursor-pointer"
        >
          <FlaskConical size={14} className="mr-2 text-purple-500" />
          {mockMode ? 'Disable Mock Mode' : 'Enable Mock Mode'}
          {mockMode && (
            <span className="ml-auto text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded">
              ON
            </span>
          )}
        </DropdownMenuItem>

        {/* Capture actions — only visible in mock mode after a print */}
        {mockMode && lastPrintedBytes && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuLabel className="text-xs text-gray-500">
              Last receipt ({lastPrintedBytes.length} bytes)
            </DropdownMenuLabel>
            <DropdownMenuItem
              onClick={downloadLastReceipt}
              className="text-sm cursor-pointer"
            >
              <Download size={14} className="mr-2 text-purple-500" />
              Download .bin
            </DropdownMenuItem>
            <DropdownMenuItem
              onClick={async () => {
                await copyLastReceiptHex();
                toast.success('Hex copied — paste into an ESC/POS viewer');
              }}
              className="text-sm cursor-pointer"
            >
              <Clipboard size={14} className="mr-2 text-purple-500" />
              Copy hex to clipboard
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
