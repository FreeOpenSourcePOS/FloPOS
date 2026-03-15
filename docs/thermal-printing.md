# Thermal Printing — Flo POS

## How it works

The browser handles printing directly. The cloud server is never in the print path — it only provides the bill data as JSON. The browser converts that data into ESC/POS bytes and sends them to the locally-connected printer.

```
[Cloud Server]                    [POS Device — PC / Tablet / Mac]
  Laravel API   ──── HTTP ────▶   Chrome browser
  (bill data)                         │
                                       │ Web Serial API
                                       ▼
                                  Thermal Printer
                                  (USB or Bluetooth)
```

This is the standard architecture for web-based POS systems. It means:
- No printer drivers needed on the server
- The printer can be USB or Bluetooth — anything that exposes a serial port
- Works in Chrome and Edge 89+ on Windows, macOS, Linux, and Android


---

## Key files

| File | Purpose |
|---|---|
| `src/lib/printer/PrinterService.ts` | Web Serial API driver. Handles connect, disconnect, print, and mock mode. Exported as a singleton (`printerService`). |
| `src/lib/printer/receipt-encoder.ts` | Converts a `Bill` object from the Laravel API into a raw ESC/POS `Uint8Array` using `@point-of-sale/receipt-printer-encoder`. |
| `src/hooks/usePrinter.ts` | Zustand store that wraps the service for React. Persists `mockMode` to localStorage. Exposes `printBill`, `connect`, `disconnect`, `toggleMock`, `downloadLastReceipt`, `copyLastReceiptHex`. |
| `src/components/pos/PrinterStatus.tsx` | Dropdown button in the POS toolbar. Shows connection status and exposes all printer actions to the user. |
| `scripts/printer-dev.sh` | macOS dev script. Creates a virtual serial port via `socat` and streams incoming ESC/POS bytes as a live hex dump. |


---

## Development testing (no physical printer)

You have two options.

### Option A — Mock Mode (simplest, no setup)

1. Open the POS page in Chrome.
2. Click the printer button (top-right of the product grid).
3. Select **Enable Mock Mode**. The button turns purple.
4. Complete a payment as normal.
5. Open the printer dropdown again — a new section appears:
   - **Download .bin** — saves the raw ESC/POS binary to your Downloads folder
   - **Copy hex to clipboard** — copies space-separated uppercase hex
6. Upload the `.bin` file (or paste the hex) into an online ESC/POS viewer to see the rendered receipt.

No hardware, no scripts, no setup required.

---

### Option B — Virtual serial port via socat (macOS)

This runs on your **Mac** (the machine where Chrome is open), not the server.

**One-time setup:**
```bash
brew install socat
```

**Each testing session:**
```bash
# From the project root on your Mac (copy the script from the server first)
scp root@your-server:/var/www/flopos/scripts/printer-dev.sh ~/printer-dev.sh
chmod +x ~/printer-dev.sh
~/printer-dev.sh
```

The script will print something like:
```
┌─────────────────────────────────────────────────────────┐
│  Virtual printer ready                                  │
│  BROWSER PORT : /dev/ttys004                            │
│  Output file  : will be saved to ~/Downloads/           │
│  In Chrome → POS → Printer dropdown → Select Serial     │
│  Port → choose the port shown above.                    │
│  Press Ctrl+C to stop and save the .bin file.           │
└─────────────────────────────────────────────────────────┘
```

**In Chrome:**
1. Open the POS page.
2. Printer dropdown → **Select Serial Port**.
3. Pick `/dev/ttys004` (or whichever port the script printed).
4. Status turns green: **Printer Ready**.
5. Complete a payment — ESC/POS bytes stream into the terminal as a hex dump:

```
00000000  1b 40 1b 61 01 1d 21 11  46 6c 6f 20 50 4f 53 0a  |.@.a..!.Flo POS.|
00000010  1b 61 00 1d 21 00 42 69  6c 6c 20 23 30 30 31 0a  |.a..!.Bill #001.|
...
```

6. Press `Ctrl+C` — the raw `.bin` is saved to `~/Downloads/receipt-<timestamp>.bin`.
7. Upload the `.bin` to an online ESC/POS viewer to see the rendered receipt.

**What shows in the serial port picker?**
The picker shows all serial ports on your Mac — including Bluetooth-paired devices (headphones, speakers). Ignore those. When a real USB printer is connected, it appears as `/dev/cu.usbserial-XXXX` or `/dev/cu.usbmodem-XXXX`.


---

## Production setup (restaurant)

### What the restaurant needs

| Requirement | Detail |
|---|---|
| POS device | Any PC, Mac, tablet, or Android device |
| Browser | Chrome or Edge 89+ |
| Printer connection | USB (recommended) or Bluetooth |
| Server | Nothing — the server is already in the cloud |

### Supported printers

Any thermal printer that exposes a USB-serial or Bluetooth-serial interface. Common models:

- **Epson TM-T20 / TM-T88** (USB) — most common restaurant printer
- **Star TSP143 / TSP654** (USB or Ethernet)
- **Xprinter XP-58 / XP-80** (USB) — budget option
- **Epson TM-P20 / TM-P80** (Bluetooth) — portable

### First-time connection

1. Plug the printer into the POS device via USB (or pair via Bluetooth).
2. Open Chrome and navigate to the POS page.
3. Click the printer button → **Select Serial Port**.
4. The browser shows a picker with available ports — select the printer.
5. Status turns green: **Printer Ready**.
6. Chrome remembers the port. On next visit, click **Select Serial Port** again — the printer appears immediately without needing to re-pick it (Chrome remembers granted ports).

### Paper width

The receipt encoder defaults to **58 mm** paper (32 characters wide). If the restaurant uses 80 mm rolls, this can be changed by passing `{ paperWidth: 80 }` as an option to `buildReceiptBytes` in `receipt-encoder.ts`.

### Receipt layout

```
       Flo POS
   Table: Table 3
    John Smith
   Bill #00123
  7 Mar 2026 14:32
--------------------------------
Burger           ฿120.00
  + Extra cheese
  2 × ฿60.00
Fries             ฿45.00
Cola              ฿35.00
--------------------------------
Subtotal         ฿200.00
Tax (7%)          ฿14.00
================================
TOTAL            ฿214.00

Cash             ฿300.00
Change            ฿86.00

  Thank you for your visit!
```


---

## Printer status indicators

| Icon | Colour | Meaning |
|---|---|---|
| Printer | Grey | No printer selected |
| Spinner | Amber | Connecting… |
| Printer ✓ | Green | Ready to print |
| Printer ✗ | Red | Error — see dropdown for details |
| Flask | Purple | Mock mode active |


---

## Troubleshooting

**Printer not showing in the picker**
- Make sure the USB cable is plugged in before opening the picker.
- Try a different USB port or cable.
- On Windows, install the printer's USB driver first (Epson and Star provide these).

**"Web Serial API is not supported"**
- Must use Chrome or Edge 89+. Safari and Firefox do not support Web Serial.

**Status goes red after selecting the port**
- The port may be in use by another application (e.g., the printer's own utility software). Close it and try again.

**Nothing prints but status is green**
- Verify the correct port is selected (especially if multiple USB devices are connected).
- Enable Mock Mode, complete a payment, download the `.bin`, and open it in an online viewer to confirm the receipt bytes are being generated correctly.

**Receipt cuts in the wrong place / extra blank lines**
- The encoder sends 3 blank lines before the cut command to ensure the receipt feeds out fully before cutting. This is normal.

**Bluetooth printer not connecting**
- Pair the printer with the POS device first via system Bluetooth settings.
- Then open the serial port picker — the printer appears as `cu.PrinterName` (macOS) or `COM#` (Windows).
