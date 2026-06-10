<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Simulate delivery
    |--------------------------------------------------------------------------
    |
    | When true, the sender marks notifications as Delivered immediately after
    | a successful Sent transition, as if a provider webhook had confirmed
    | delivery. Real deployments should leave this off and rely on inbound
    | webhook handlers to advance the status.
    |
    */

    'simulate_delivery' => (bool) env('NOTIFICATIONS_SIMULATE_DELIVERY', false),

];
