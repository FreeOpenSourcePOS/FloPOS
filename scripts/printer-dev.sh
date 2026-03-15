#!/usr/bin/env bash
# printer-dev.sh
#
# Creates a virtual serial port pair via socat, then listens for ESC/POS bytes
# and displays them as a live pretty hex dump (hexdump -C style).
# The raw bytes are also saved to a .bin file for use with online ESC/POS viewers.
#
# Prerequisites:
#   brew install socat
#
# Usage:
#   chmod +x scripts/printer-dev.sh
#   ./scripts/printer-dev.sh
#
# Then in Chrome (POS page):
#   Printer dropdown → Select Serial Port → pick the port shown below as BROWSER PORT

set -euo pipefail

# Check socat is installed
if ! command -v socat &>/dev/null; then
  echo "ERROR: socat not found. Install it with:  brew install socat"
  exit 1
fi

# Temp dir for port symlinks and output
TMPDIR_DEV=$(mktemp -d)
BROWSER_PORT="$TMPDIR_DEV/printer-browser"  # browser connects here
READER_PORT="$TMPDIR_DEV/printer-reader"    # this script reads here
OUTFILE="$TMPDIR_DEV/receipt-$(date +%H%M%S).bin"

cleanup() {
  echo ""
  echo "Stopping…"
  kill "$SOCAT_PID" 2>/dev/null || true
  wait "$SOCAT_PID" 2>/dev/null || true
  if [[ -f "$OUTFILE" && -s "$OUTFILE" ]]; then
    FINAL="$HOME/Downloads/receipt-$(date +%Y%m%d-%H%M%S).bin"
    cp "$OUTFILE" "$FINAL"
    echo "Receipt saved → $FINAL"
    echo "Upload that file to an online ESC/POS viewer to see the rendered receipt."
  fi
  rm -rf "$TMPDIR_DEV"
}
trap cleanup EXIT INT TERM

# Start socat: links two virtual serial ports together
socat pty,link="$BROWSER_PORT",raw,echo=0 \
      pty,link="$READER_PORT",raw,echo=0 &
SOCAT_PID=$!

# Give socat a moment to create the symlinks
sleep 0.3

# Resolve the real /dev/ttys### path Chrome will show in the picker
REAL_PORT=$(readlink -f "$BROWSER_PORT" 2>/dev/null || readlink "$BROWSER_PORT")

echo "┌─────────────────────────────────────────────────────────┐"
echo "│  Virtual printer ready                                  │"
echo "├─────────────────────────────────────────────────────────┤"
echo "│  BROWSER PORT : $REAL_PORT"
echo "│  Output file  : will be saved to ~/Downloads/           │"
echo "├─────────────────────────────────────────────────────────┤"
echo "│  In Chrome → POS → Printer dropdown → Select Serial     │"
echo "│  Port → choose the port shown above.                    │"
echo "├─────────────────────────────────────────────────────────┤"
echo "│  Press Ctrl+C to stop and save the .bin file.           │"
echo "└─────────────────────────────────────────────────────────┘"
echo ""
echo "Waiting for ESC/POS data…"
echo ""

# Stream bytes from the reader port:
#   - tee saves a raw .bin copy
#   - hexdump -C gives the pretty annotated hex + ASCII view
cat "$READER_PORT" | tee "$OUTFILE" | hexdump -C
