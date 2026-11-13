BEGIN;
SET LOCAL search_path TO somi, public;

-- KYC listing and audited private-image reads both require these permissions.
INSERT INTO permissions (permission_code, description) VALUES
    ('shipper.verify', 'Review shipper identity documents and applications.'),
    ('storage.read_support', 'Audited private file reads for support and KYC')
ON CONFLICT (permission_code) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r CROSS JOIN permissions p
WHERE r.role_code = 'ADMIN'
  AND p.permission_code IN ('shipper.verify', 'storage.read_support')
ON CONFLICT DO NOTHING;

COMMIT;
