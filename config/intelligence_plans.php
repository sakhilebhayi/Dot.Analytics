<?php

/*
|--------------------------------------------------------------------------
| What a person may keep, and what a paid plan could add
|--------------------------------------------------------------------------
| Everything published in the library is free to read, for ever, signed in or not. A plan may add convenience (more things
| to watch, longer history of alerts) but never access to a figure or development: nothing here is checked when a page is
| served, and no plan can unlock data that a source's licence does not let us sell. There is no billing on this platform
| yet, so every person is on the free plan and no price is shown anywhere.
*/

return [

    'plans' => [
        'free' => [
            'name' => 'Free',
            'watches' => 25,
            'saved' => 100,
            'alerts_kept' => 200,
            'digest' => ['off', 'daily', 'weekly'],
        ],
        // Designed, not on sale. Shown on the plans page as "not available yet" so no one is promised what does not exist.
        'plus' => [
            'name' => 'Plus',
            'for_sale' => false,
            'watches' => 150,
            'saved' => 1000,
            'alerts_kept' => 2000,
            'digest' => ['off', 'daily', 'weekly'],
        ],
    ],

    // What is never gated, for any plan. The plans page prints this list and a test checks nothing else can be added to a plan.
    'always_free' => [
        'Every figure, chart and its working',
        'Every published development and briefing',
        'Search and the question box',
        'Downloading the values behind any figure',
        'The sources page and how figures are labelled',
    ],
];
