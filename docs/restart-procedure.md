# Restart Procedure for Frontend Changes

After making code changes to the frontend, follow this procedure to see them reflected.

## The Working Script (Run This After Every Change)

```bash
# Full restart - kills servers, clears cache, rebuilds, restarts everything

# 1. Kill all Next.js and Caddy processes
pkill -f "next" 2>/dev/null
pkill -f "caddy" 2>/dev/null
sleep 2

# 2. Clear Next.js cache (IMPORTANT - removes stale build files)
rm -rf /var/www/flopos/frontend/.next

# 3. Build the frontend (production build)
cd /var/www/flopos/frontend && npm run build
```

Then open a **new incognito window** and test. The production build is required for the new code to work.

## Why This Works

- `pkill -f "next"` - Kills any running Next.js servers
- `rm -rf .next` - Deletes the compiled build cache (critical!)
- `npm run build` - Creates a fresh production build with new code
- Incognito window - Bypasses browser cache that holds old chunk file names

## Quick Reference

| Action | Command |
|--------|---------|
| Kill servers | `pkill -f "next" 2>/dev/null` |
| Clear cache | `rm -rf /var/www/flopos/frontend/.next` |
| Build | `cd /var/www/flopos/frontend && npm run build` |
| Start server | `cd /var/www/flopos/frontend && npm run start &` |
| Restart Caddy | `systemctl restart caddy` |

## Verify It's Working

```bash
# Check server is running on port 3000
ss -tlnp | grep 3000

# Test locally
curl -s http://localhost:3000/products | head -5
```

## Browser Testing

After running the script above, always use **incognito/private mode** to test:
- Chrome: `Ctrl+Shift+N`
- Firefox: `Ctrl+Shift+P`

This ensures you're not seeing cached old code.

## If Still Not Working

1. Clear browser cache completely (Ctrl+Shift+Delete)
2. Hard refresh (Ctrl+Shift+R)
3. Or try a different browser

## Important Notes

- `npm run dev` does NOT work for production builds - must run `npm run build` then `npm run start`
- The Caddy proxy serves from port 3000 - it must be running
- Always use incognito for testing to avoid stale browser cache
- The `.next` folder must be deleted before each rebuild