# SOMI Grab Delivery — PHP + TypeScript (Modular UI)

Frontend-only project. React and Vite are not used. PHP renders the pages; HTML/CSS and JavaScript/TypeScript implement the interface. There is no backend API or database.

## Run locally

Requirements: PHP 8+, Node.js/npm (only for TypeScript compilation), and optionally XAMPP.

### XAMPP
1. Copy this folder into `C:\xampp\htdocs\SOMI-Grab-Delivery-PHP`.
2. Start Apache.
3. Open `http://localhost/SOMI-Grab-Delivery-PHP/`.

### PHP development server
From this directory:

```bash
php -S localhost:8000
```

Open `http://localhost:8000`.

## Routes
- `/?page=home`, `how`, `customers`, `shippers`, `pricing`: public pages
- `/?page=auth`: shared sign-in/signup demo
- `/?page=customer`: Customer workspace
- `/?page=shipper` and `/?page=shipper&view=...`: Shipper workspace/views
- `/?page=admin`: Admin workspace

## Module ownership

```text
app/
  Views/
    shared/       # public website + shared authentication (FROZEN)
    customer/     # Customer UI
    shipper/      # Shipper UI
    admin/        # Admin UI
public/assets/
  css/
    app.css       # current shared design baseline (FROZEN)
    modules/
      customer.css
      shipper.css
      admin.css
  js/
    app.js        # current existing interaction runtime; preserve behavior
resources/ts/
  app.ts          # existing typed helpers
  modules/
    shared/       # shared contracts/utilities; coordinate before changes
    customer/     # Customer-only TypeScript
    shipper/      # Shipper-only TypeScript
    admin/        # Admin-only TypeScript
```

## Collaboration rules

- Shared UI is frozen: do not change `app.css`, public pages, shared auth, navigation, language selector, or global interactions without explicit approval.
- Module owners should edit only their own `app/Views/<module>/`, `public/assets/css/modules/<module>.css`, and `resources/ts/modules/<module>/`.
- Scope CSS to the module root (`.customer-workspace`, `.shipper-workspace`, `.admin-workspace`). Avoid global element selectors and `!important` overrides.
- Do not change `index.php`, routing, shared templates, or shared runtime without coordinating with the integrator.
- Preserve existing functions and demo behavior; no functionality may be removed without approval.

## TypeScript
The browser runtime remains in `public/assets/js/app.js` to preserve current behavior. Module TypeScript entrypoints are created as safe extension points and do not yet replace the existing runtime. Compile using the TypeScript compiler available in your environment. No React or Vite is used.

## Limitations
This is a frontend demo only. Authentication, OTP, eKYC, payment, delivery tracking, chat persistence, and admin authorization are not connected to a backend/database.
