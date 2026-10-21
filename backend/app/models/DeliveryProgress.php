<?php
// Read-only presentation of existing order/trip/assignment relations. Never writes statuses or payments.
class DeliveryProgress
{
    public static function orderSelect():string
    {
        // orders.trip_id is written by the current assignment flow; use the bridge only
        // when the direct link is absent and the bridge identifies exactly one trip.
        $trip="COALESCE(NULLIF(o.trip_id,0),(SELECT CASE WHEN COUNT(DISTINCT x.trip_id)=1 THEN MIN(x.trip_id) ELSE NULL END FROM trip_orders x WHERE x.order_id=o.id))";
        $state="CASE WHEN o.status IN ('CANCELLED','CANCELED') THEN 'CANCELLED' WHEN t.status='COMPLETED' THEN 'DELIVERED' WHEN t.status='IN_PROGRESS' THEN 'IN_TRANSIT' WHEN t.status='PLANNED' AND a.status='assigned' AND a.accepted_at IS NOT NULL THEN 'PICKING_UP' WHEN t.id IS NOT NULL THEN 'CONFIRMED' WHEN o.status='DELIVERED' THEN 'DELIVERED' WHEN o.status IN ('CONFIRMED','PICKING_UP','IN_TRANSIT') THEN o.status ELSE 'PENDING' END";
        // Sender payment is settled at creation. COD is settled only after the
        // linked trip and its matching Driver assignment have completed.
        $codCompleted="COALESCE(t.status='COMPLETED' AND a.status='completed' AND a.completed_at IS NOT NULL,0)";
        $payment="CASE WHEN o.payment_method='SENDER_PAY' THEN 'PAID' WHEN o.payment_method='RECEIVER_PAY' THEN CASE WHEN ($codCompleted)=1 THEN 'PAID' ELSE 'UNPAID' END ELSE o.payment_status END";
        return "SELECT o.*,u.full_name AS customer_name,($trip) AS effective_trip_id,($state) AS display_status,($payment) AS display_payment_status FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN users u ON u.id=c.user_id LEFT JOIN trips t ON t.id=($trip) LEFT JOIN assignments a ON a.assignment_id=(SELECT a2.assignment_id FROM assignments a2 WHERE a2.trip_id=t.id AND a2.status IN ('assigned','completed') AND a2.driver_id=t.driver_id AND a2.vehicle_id=t.vehicle_id ORDER BY a2.assignment_id DESC LIMIT 1)";
    }
    public static function tripStatusSql():string
    {
        return "CASE WHEN t.status='PLANNED' AND EXISTS(SELECT 1 FROM assignments a WHERE a.trip_id=t.id AND a.status='assigned' AND a.accepted_at IS NOT NULL AND a.driver_id=t.driver_id AND a.vehicle_id=t.vehicle_id) THEN 'CONFIRMED' ELSE t.status END";
    }
    public static function orderCountSql(string $trip):string
    {
        if(!in_array($trip,['a.trip_id','t.id'],true))throw new LogicException('Invalid trip alias');
        return "SELECT COUNT(*) FROM orders linked_order WHERE linked_order.trip_id=$trip OR EXISTS(SELECT 1 FROM trip_orders order_link WHERE order_link.order_id=linked_order.id AND order_link.trip_id=$trip)";
    }
}
