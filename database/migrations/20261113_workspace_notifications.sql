SET LOCAL search_path TO somi, public;

-- NULL retains legacy rows whose originating workspace cannot be established.
-- Such rows are kept for review and excluded from personal workspace feeds.
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS recipient_role VARCHAR(20)
    CHECK (recipient_role IN ('CUSTOMER','SHIPPER','ADMIN'));
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS opened_role VARCHAR(20)
    CHECK (opened_role IN ('CUSTOMER','SHIPPER'));

UPDATE support_tickets t SET opened_role='CUSTOMER'
FROM orders o WHERE t.opened_role IS NULL AND t.order_id=o.order_id AND t.opened_by=o.customer_id;
UPDATE support_tickets t SET opened_role='SHIPPER'
WHERE t.opened_role IS NULL AND EXISTS (
    SELECT 1 FROM order_assignments a WHERE a.order_id=t.order_id AND a.shipper_id=t.opened_by
);
UPDATE support_tickets t SET opened_role=r.role_code
FROM user_roles ur JOIN roles r USING(role_id)
WHERE t.opened_role IS NULL AND ur.user_id=t.opened_by AND r.role_code IN ('CUSTOMER','SHIPPER')
AND NOT EXISTS (SELECT 1 FROM user_roles other JOIN roles other_role USING(role_id)
    WHERE other.user_id=t.opened_by AND other_role.role_code IN ('CUSTOMER','SHIPPER') AND other_role.role_code<>r.role_code);

UPDATE notifications SET recipient_role='SHIPPER'
WHERE recipient_role IS NULL AND notification_type IN ('OfferCreated','PayoutSucceeded');
UPDATE notifications n SET recipient_role=CASE WHEN n.user_id=t.opened_by THEN t.opened_role ELSE 'ADMIN' END
FROM support_replies reply JOIN support_tickets t USING(ticket_id)
WHERE n.recipient_role IS NULL AND n.notification_type='TicketReply' AND n.dedup_key='reply:'||reply.reply_id::text;
UPDATE notifications n SET recipient_role=t.opened_role
FROM support_tickets t WHERE n.recipient_role IS NULL AND n.notification_type='TICKET_STATUS_CHANGED'
AND n.user_id=t.opened_by AND split_part(n.message_vi,' → ',1)=t.ticket_code;

UPDATE notifications n SET recipient_role='CUSTOMER'
FROM orders o WHERE n.recipient_role IS NULL AND n.order_id=o.order_id AND n.user_id=o.customer_id
AND n.notification_type IN ('OrderCreated','PaymentConfirmed','OrderCancelled','OrderDelivered',
    'OrderReturning','OrderReturned','RefundSucceeded','ShipperAssigned','OrderPickedUp','OrderDelivering',
    'NoShipperFound','ChatMessage','DEMO');
UPDATE notifications n SET recipient_role='SHIPPER'
WHERE n.recipient_role IS NULL AND n.notification_type IN ('ShipperAssigned','OrderCancelled','OrderPickedUp',
    'OrderDelivering','OrderDelivered','OrderReturning','OrderReturned','ChatMessage','DEMO')
AND EXISTS (SELECT 1 FROM order_assignments a WHERE a.order_id=n.order_id AND a.shipper_id=n.user_id);
UPDATE notifications n SET recipient_role=r.role_code
FROM user_roles ur JOIN roles r USING(role_id)
WHERE n.recipient_role IS NULL AND n.order_id IS NULL AND ur.user_id=n.user_id
AND NOT EXISTS (SELECT 1 FROM user_roles other WHERE other.user_id=n.user_id AND other.role_id<>ur.role_id);

CREATE INDEX IF NOT EXISTS ix_notifications_workspace_feed ON notifications(user_id,recipient_role,notification_id DESC);
CREATE INDEX IF NOT EXISTS ix_notifications_workspace_unread ON notifications(user_id,recipient_role) WHERE read_at IS NULL;
CREATE INDEX IF NOT EXISTS ix_support_tickets_workspace ON support_tickets(opened_by,opened_role,ticket_id DESC);
