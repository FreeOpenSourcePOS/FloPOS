/**
 * PrinterService — Web Serial API thermal printer driver with mock mode.
 *
 * Real usage:  await printerService.connect();
 *              await printerService.print(bytes);
 *
 * Mock usage:  printerService.setMock(true);
 *              await printerService.print(bytes);  // hex-dumps to console, no port needed
 *
 * macOS dev setup (no physical printer):
 *   brew install socat
 *   socat -d -d pty,raw,echo=0 pty,raw,echo=0
 *   # Note the two /dev/ttys### paths printed.
 *   # Connect the browser to the first one.
 *   # Read ESC/POS bytes from the second one:
 *   cat /dev/ttys005 > /tmp/receipt.bin   (then upload to an online ESC/POS viewer)
 *   # or live hex view:
 *   cat /dev/ttys005 | xxd
 */

export type PrinterStatus =
  | 'disconnected'
  | 'connecting'
  | 'connected'
  | 'error'
  | 'mock';

export interface PrinterInfo {
  vendorId: number;
  productId: number;
  manufacturerName?: string;
  productName?: string;
}

type StatusListener = (status: PrinterStatus, info?: PrinterInfo) => void;

class PrinterService {
  private port: SerialPort | null = null;
  private mockMode = false;
  private _status: PrinterStatus = 'disconnected';
  private listeners: Set<StatusListener> = new Set();

  // ---------------------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------------------

  get isMock(): boolean {
    return this.mockMode;
  }

  get status(): PrinterStatus {
    return this._status;
  }

  get deviceInfo(): PrinterInfo | null {
    if (!this.port) return null;
    const info = this.port.getInfo();
    return {
      vendorId: info.usbVendorId ?? 0,
      productId: info.usbProductId ?? 0,
    };
  }

  setMock(enabled: boolean): void {
    this.mockMode = enabled;
    if (enabled) {
      this.port = null;
      this.setStatus('mock');
    } else {
      this.setStatus('disconnected');
    }
  }

  onStatusChange(listener: StatusListener): () => void {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  /**
   * Opens the browser's serial port picker and connects.
   * Must be called from a user-gesture handler (click, etc.).
   */
  async connect(): Promise<void> {
    if (this.mockMode) return;

    if (!navigator.serial) {
      throw new Error(
        'Web Serial API is not supported in this browser. Use Chrome or Edge 89+.'
      );
    }

    this.setStatus('connecting');

    try {
      this.port = await navigator.serial.requestPort();
    } catch (err: unknown) {
      // User dismissed the picker — treat as disconnected, not an error.
      if (err instanceof DOMException && err.name === 'NotFoundError') {
        this.setStatus('disconnected');
        return;
      }
      this.setStatus('error');
      throw new Error(`Port selection failed: ${(err as Error).message}`);
    }

    try {
      await this.port.open({ baudRate: 9600 });
    } catch (err) {
      this.port = null;
      this.setStatus('error');
      throw new Error(`Could not open port: ${(err as Error).message}`);
    }

    this.setStatus('connected', this.deviceInfo ?? undefined);
    navigator.serial.addEventListener('disconnect', this.handleDisconnect);
  }

  async disconnect(): Promise<void> {
    navigator.serial?.removeEventListener('disconnect', this.handleDisconnect);
    if (this.port) {
      try {
        await this.port.close();
      } catch {
        // Ignore — port may already be gone.
      }
      this.port = null;
    }
    this.setStatus(this.mockMode ? 'mock' : 'disconnected');
  }

  /**
   * Send a raw ESC/POS byte array to the printer.
   * In mock mode the bytes are hex-dumped to the console instead.
   */
  async print(data: Uint8Array): Promise<void> {
    if (this.mockMode) {
      this.mockPrint(data);
      return;
    }

    if (!this.port || this._status !== 'connected') {
      throw new Error('Printer is not connected. Call connect() first.');
    }

    if (!this.port.writable) {
      throw new Error('Serial port is not writable.');
    }

    const writer = this.port.writable.getWriter();
    try {
      await writer.write(data);
    } finally {
      writer.releaseLock();
    }
  }

  // ---------------------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------------------

  private setStatus(status: PrinterStatus, info?: PrinterInfo): void {
    this._status = status;
    this.listeners.forEach((l) => l(status, info));
  }

  private handleDisconnect = (event: Event): void => {
    const e = event as Event & { port: SerialPort };
    if (e.port === this.port) {
      this.port = null;
      this.setStatus('disconnected');
    }
  };

  private mockPrint(data: Uint8Array): void {
    const hex = Array.from(data)
      .map((b) => b.toString(16).padStart(2, '0').toUpperCase())
      .join(' ');

    console.groupCollapsed(`[PrinterService MOCK] ${data.length} bytes`);
    console.log('Hex dump:\n' + chunkString(hex, 48).join('\n'));
    console.log('ASCII (printable chars):\n' + toAsciiPreview(data));
    console.groupEnd();
    console.info('[PrinterService MOCK] Print cycle complete (simulated).');
  }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function chunkString(str: string, size: number): string[] {
  const chunks: string[] = [];
  for (let i = 0; i < str.length; i += size) {
    chunks.push(str.slice(i, i + size));
  }
  return chunks;
}

function toAsciiPreview(data: Uint8Array): string {
  return Array.from(data)
    .map((b) => (b >= 0x20 && b < 0x7f ? String.fromCharCode(b) : '.'))
    .join('');
}

// Export a singleton so all components share the same port handle.
export const printerService = new PrinterService();
