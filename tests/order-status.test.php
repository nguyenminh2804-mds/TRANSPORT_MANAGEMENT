<?php
require_once __DIR__ . '/../backend/app/models/Order.php';

$validTransitions = [
    ['PENDING', 'CONFIRMED'],
    ['PENDING', 'CANCELLED'],
    ['CONFIRMED', 'ASSIGNED'],
    ['ASSIGNED', 'PICKING_UP'],
    ['PICKING_UP', 'IN_TRANSIT'],
    ['IN_TRANSIT', 'DELIVERING'],
    ['DELIVERING', 'DELIVERED'],
    ['DELIVERING', 'CANCELLED']
];

foreach ($validTransitions as [$current, $next]) {
    if (!Order::canTransitionStatus($current, $next)) {
        throw new RuntimeException("Expected transition {$current} -> {$next}");
    }
}

$invalidTransitions = [
    ['PENDING', 'ASSIGNED'],
    ['PENDING', 'DELIVERED'],
    ['CONFIRMED', 'IN_TRANSIT'],
    ['ASSIGNED', 'DELIVERED'],
    ['DELIVERED', 'CANCELLED'],
    ['CANCELLED', 'PENDING'],
    ['CANCELED', 'PICKING_UP'],
    ['UNKNOWN', 'PENDING']
];

foreach ($invalidTransitions as [$current, $next]) {
    if (Order::canTransitionStatus($current, $next)) {
        throw new RuntimeException("Unexpected transition {$current} -> {$next}");
    }
}

echo "Order status transition tests passed.\n";
