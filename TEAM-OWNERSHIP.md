# SOMI UI Team Ownership

The frontend is split by role to reduce merge conflicts.

```text
src/
├── features/
│   ├── customer/   # Customer UI
│   │   └── CustomerApp.tsx
│   ├── shipper/    # Shipper UI
│   │   └── ShipperApp.tsx
│   ├── admin/      # Admin UI
│   │   └── AdminApp.tsx
│   └── auth/       # Shared authentication/legal flow
├── shared/         # Shared UI/domain/i18n/config
└── App.tsx         # Thin router/orchestrator only
```

## Team rule

- Customer work: edit `src/features/customer/**`.
- Shipper work: edit `src/features/shipper/**`.
- Admin work: edit `src/features/admin/**`.
- Shared components/i18n/domain: coordinate before editing.
- Avoid editing `src/App.tsx` for normal role UI work.
- Integration changes to `App.tsx` should be small and agreed by the team.

## Suggested Git branches

```text
feature/customer-*
feature/shipper-*
feature/admin-*
```

This keeps most commits isolated to one feature folder and reduces conflict risk when three people work in parallel.
