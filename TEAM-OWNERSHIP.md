# SOMI UI Team Ownership

Project uses PHP views with separated CSS/JavaScript by role. Keep each contributor inside their owned module to reduce Git conflicts.

## Ownership map

- **Customer owner:** `app/Views/customer/**`, `public/assets/css/modules/customer.css`, customer-specific scripts only.
- **Shipper owner:** `app/Views/shipper/**`, `public/assets/css/modules/shipper.css`, `public/assets/js/shipper.js`.
- **Admin owner:** `app/Views/admin/**`, `public/assets/css/modules/admin.css`, admin-specific scripts only.
- **Integration owner:** `index.php`, `.htaccess`, `app/Views/shared/**`, `public/assets/css/app.css`, `public/assets/js/app.js`, and `package.json` if added later.

## Rules

1. Do not edit another role's folder or module assets without coordinating with its owner.
2. Keep role-specific CSS and JavaScript in the role's own files; do not add role-specific styles/handlers to shared files.
3. Changes to routing, shared authentication, global layout, or shared styles require an integration-owner review.
4. Avoid committing generated files or unrelated formatting changes. Review `git diff --name-only` before committing.
5. Use one branch per module: `feature/admin-*`, `feature/shipper-*`, `feature/customer-*`.
6. Merge through pull requests and test all three role entry points after integration.

This division reduces overlapping edits; it cannot guarantee zero conflicts if multiple branches edit the same integration file.
