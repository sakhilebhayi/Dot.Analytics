<?php

/*
|--------------------------------------------------------------------------
| Metric catalogue: the snapshot contract
|--------------------------------------------------------------------------
| The ONLY metrics Dot.Analytics accepts, per real Dot platform (keys are the
| platform keys of config/ecosystem.php). A platform posts a signed daily
| snapshot to POST /api/v1/ingest/{platform}; keys not listed here are dropped,
| values of the wrong kind are rejected. docs/metrics-contract.md is the
| human-readable version of this file.
|
| Metric fields:
|   label  Shown on tiles and in alerts.
|   type   count     non-negative whole number
|          cents     non-negative whole number of minor currency units; the
|                    snapshot must also carry `currency`
|          quantity  non-negative number (e.g. kilograms)
|          rating    number between `min` and `max`
|   good   Which direction of change is favourable: up, down or neutral. Only
|          used to colour a delta; the numbers are never altered by it.
|
| The special key `currency` (an ISO 4217 code such as ZAR) is not a metric: it
| names the currency of every `*_cents` key in the same snapshot.
*/

return [

    // The only snapshot period accepted so far.
    'periods' => ['day'],

    // A latest value older than this many periods is shown as stale, and never
    // used for anomaly detection.
    'stale_after_periods' => 2,

    // A snapshot may not claim to be taken further in the future than this.
    'max_clock_skew_seconds' => 300,

    'types' => [
        'count' => ['integer' => true, 'min' => 0, 'min_scale' => 1.0],
        'cents' => ['integer' => true, 'min' => 0, 'min_scale' => 1.0, 'money' => true],
        'quantity' => ['integer' => false, 'min' => 0, 'min_scale' => 1.0],
        'rating' => ['integer' => false, 'min' => 0, 'max' => 5, 'min_scale' => 0.1],
    ],

    'anomaly' => [
        // Flag the latest point when it is more than this many median absolute
        // deviations away from the median of the prior points.
        'mad_threshold' => (float) env('ANALYTICS_ANOMALY_MAD', 3.5),
        // At least this many prior daily points are required before anything is flagged.
        'min_prior_points' => 7,
        // Only prior points inside this many days before the latest point count.
        'window_days' => 30,
        // A scale below 1% of the median is treated as 1% of the median, so a
        // flat history does not turn every wobble into an alert.
        'min_scale_ratio' => 0.01,
    ],

    'platforms' => [

        'tasks' => [
            'label' => 'Dot.Tasks',
            'engine' => 'operational',
            'metrics' => [
                'open' => ['label' => 'Open tasks', 'type' => 'count', 'good' => 'down'],
                'overdue' => ['label' => 'Overdue tasks', 'type' => 'count', 'good' => 'down'],
                'completed_7d' => ['label' => 'Tasks completed, 7 days', 'type' => 'count', 'good' => 'up'],
                'created_7d' => ['label' => 'Tasks created, 7 days', 'type' => 'count', 'good' => 'neutral'],
            ],
        ],

        'auction' => [
            'label' => 'Dot.Auction',
            'engine' => 'business',
            'metrics' => [
                'active' => ['label' => 'Active auctions', 'type' => 'count', 'good' => 'neutral'],
                'ended_7d' => ['label' => 'Auctions ended, 7 days', 'type' => 'count', 'good' => 'neutral'],
                'gmv_7d_cents' => ['label' => 'Gross sales, 7 days', 'type' => 'cents', 'good' => 'up'],
                'bids_7d' => ['label' => 'Bids placed, 7 days', 'type' => 'count', 'good' => 'up'],
            ],
        ],

        'ehail' => [
            'label' => 'Dot.Ehail',
            'engine' => 'operational',
            'metrics' => [
                'rides_requested_7d' => ['label' => 'Rides requested, 7 days', 'type' => 'count', 'good' => 'up'],
                'rides_completed_7d' => ['label' => 'Rides completed, 7 days', 'type' => 'count', 'good' => 'up'],
                'rides_expired_7d' => ['label' => 'Rides expired unanswered, 7 days', 'type' => 'count', 'good' => 'down'],
                'drivers_online' => ['label' => 'Drivers online', 'type' => 'count', 'good' => 'up'],
                'avg_rating' => ['label' => 'Average driver rating', 'type' => 'rating', 'good' => 'up'],
            ],
        ],

        'hr' => [
            'label' => 'Dot.HR',
            'engine' => 'people',
            'metrics' => [
                'headcount' => ['label' => 'Headcount', 'type' => 'count', 'good' => 'neutral'],
                'on_leave_today' => ['label' => 'On leave today', 'type' => 'count', 'good' => 'neutral'],
                'pending_leave_requests' => ['label' => 'Pending leave requests', 'type' => 'count', 'good' => 'down'],
            ],
        ],

        'farms' => [
            'label' => 'Dot.Farms',
            'engine' => 'agriculture',
            'metrics' => [
                'active_cycles' => ['label' => 'Active crop cycles', 'type' => 'count', 'good' => 'neutral'],
                'harvests_30d' => ['label' => 'Harvests, 30 days', 'type' => 'count', 'good' => 'up'],
                'harvested_kg_30d' => ['label' => 'Harvested, 30 days (kg)', 'type' => 'quantity', 'good' => 'up'],
            ],
        ],

        'billing' => [
            'label' => 'Dot.Billing',
            'engine' => 'financial',
            'metrics' => [
                'mrr_cents' => ['label' => 'Monthly recurring revenue', 'type' => 'cents', 'good' => 'up'],
                'open_invoices' => ['label' => 'Open invoices', 'type' => 'count', 'good' => 'neutral'],
                'overdue_invoices' => ['label' => 'Overdue invoices', 'type' => 'count', 'good' => 'down'],
                'overdue_cents' => ['label' => 'Overdue amount', 'type' => 'cents', 'good' => 'down'],
            ],
        ],

        'emall' => [
            'label' => 'Dot.Emall',
            'engine' => 'retail',
            'metrics' => [
                'orders_7d' => ['label' => 'Orders, 7 days', 'type' => 'count', 'good' => 'up'],
                'revenue_7d_cents' => ['label' => 'Revenue, 7 days', 'type' => 'cents', 'good' => 'up'],
                'low_stock_products' => ['label' => 'Low-stock products', 'type' => 'count', 'good' => 'down'],
            ],
        ],

        'tutor' => [
            'label' => 'Dot.Tutor',
            'engine' => 'operational',
            'metrics' => [
                'sessions_7d' => ['label' => 'Sessions booked, 7 days', 'type' => 'count', 'good' => 'up'],
                'completed_7d' => ['label' => 'Sessions completed, 7 days', 'type' => 'count', 'good' => 'up'],
                'active_tutors' => ['label' => 'Active tutors', 'type' => 'count', 'good' => 'neutral'],
            ],
        ],

        'pulse' => [
            'label' => 'Dot.Pulse',
            'engine' => 'community',
            'metrics' => [
                'posts_7d' => ['label' => 'Posts, 7 days', 'type' => 'count', 'good' => 'neutral'],
                'active_users_7d' => ['label' => 'Active users, 7 days', 'type' => 'count', 'good' => 'up'],
                'held_posts' => ['label' => 'Posts held for review', 'type' => 'count', 'good' => 'down'],
            ],
        ],

        'notify' => [
            'label' => 'Dot.Notify',
            'engine' => 'data',
            'metrics' => [
                'sent_7d' => ['label' => 'Messages sent, 7 days', 'type' => 'count', 'good' => 'neutral'],
                'failed_7d' => ['label' => 'Messages failed, 7 days', 'type' => 'count', 'good' => 'down'],
                'suppressed_7d' => ['label' => 'Messages suppressed, 7 days', 'type' => 'count', 'good' => 'neutral'],
            ],
        ],

        'design' => [
            'label' => 'Dot.Design',
            'engine' => 'operational',
            'metrics' => [
                'projects' => ['label' => 'Design projects', 'type' => 'count', 'good' => 'neutral'],
                'assets' => ['label' => 'Design assets', 'type' => 'count', 'good' => 'neutral'],
            ],
        ],

    ],

];
