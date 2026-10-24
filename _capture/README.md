# Capture kit

Snapshots the **running** Swift Inventory app (HTML, screenshots, role access matrix) so the static
GitHub Pages clone can be matched against it page by page.

It is read-only: it only issues GET requests and opens menus/modals. It never confirms a delete or
submits a form (other than logging in). Everything stays in `_capture/` (git-ignored).

## 1. One-time setup

```powershell
cd "E:\Project Works\inventory-management\_capture"
npm run setup
```

This installs Playwright and a Chromium for it inside `_capture/` only. Your project's
`package.json` and `node_modules` are not touched. It needs internet once.

## 2. Before each capture

1. The app is running and reachable (e.g. `php artisan serve` -> `http://127.0.0.1:8000`).
2. `public/hot` exists in your project, so keep `npm run dev` running while capturing.
3. The database has the seeded demo data (`php artisan migrate:fresh --seed` gives the cleanest baseline).
   The seeded sales and purchases are what the Due and Account reports show.

Optional, for fuller coverage:

- **POS terminal:** log in as admin in your browser and open a cash shift (Sales -> Cash Shifts).
  Without an open shift the capture only records the "open a shift" gate.
- **Viewer role:** none is seeded. Create one so the viewer's sidebar and access are captured:

  ```powershell
  cd "E:\Project Works\inventory-management"
  php artisan tinker --execute="App\Models\User::forceCreate(['name'=>'Viewer User','email'=>'viewer@inventory.com','password'=>'password','role'=>'viewer','is_active'=>true,'email_verified_at'=>now()]);"
  ```

  Without it, the viewer pass is skipped with a warning. Nothing else breaks.
- **Records that don't exist yet** (a purchase return, a closed shift, an expense) can't be captured
  until you create one. The script lists what it could not find.

## 3. Run

PowerShell:

```powershell
cd "E:\Project Works\inventory-management\_capture"
$env:BASE_URL = "http://127.0.0.1:8000"   # change if your app uses another address
npm run capture
```

cmd.exe:

```bat
set BASE_URL=http://127.0.0.1:8000
npm run capture
```

It takes roughly 5 to 10 minutes. Output goes to `_capture\out\`.

### Options (environment variables)

| Variable | Default | Meaning |
|---|---|---|
| `BASE_URL` | `http://127.0.0.1:8000` | Where the app is served |
| `APP_PASSWORD` | `password` | Password for all seeded accounts |
| `TZ_ID` | `Asia/Dhaka` | Browser timezone used for rendering |
| `SKIP_DARK=1` | off | Skip the dark-mode pass |
| `SKIP_MOBILE=1` | off | Skip the 390px mobile pass |
| `SKIP_STATES=1` | off | Skip menu/modal/sidebar state shots |

## 4. What you get

```
out/
  auth-light/     login, register, forgot/reset password
  admin-light/    every page as admin: .html + .png (+ .snapshot.json = Livewire state)
  admin-dark/     the same pages in dark mode (.png)
  admin-mobile/   a few pages at 390px (.png)
  admin-states/   sidebar groups, collapsed sidebar, user menu, product search/filters/sort,
                  row menu, delete confirmation, POS search (.png)
  manager-light/  staff-light/  viewer-light/   dashboard per role (sidebar differs)
  manifest.json   status code, title, console errors per page + the role access matrix
```

Then tell Claude the capture is done. Claude reads `_capture/out` directly.

## Troubleshooting

- **"Cannot reach .../login"**: the app is not running, or `BASE_URL` is wrong.
- **Pages look unstyled in the screenshots**: `public/hot` exists but `npm run dev` is not running.
  Start it, or delete `public/hot` to serve the built assets instead.
- **Login fails for every role**: the demo accounts were changed; set `APP_PASSWORD`, or re-seed.
- **Too many login attempts**: Fortify limits logins to 5 per minute per account. Wait a minute and rerun.
