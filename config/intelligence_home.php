<?php

/*
| The figures on the front page, in order. Each is [indicator key, place code]. A figure with no stored value is left out,
| never shown empty or invented. The first six that have a value are printed.
*/

return [
    'figures' => [
        ['bis.policy-rate-za', 'ZAF'],
        ['ecb.zar-eur', 'ZAF'],
        ['eia.brent', 'WLD'],
        ['oecd.manufacturing-production-za', 'ZAF'],
        ['oecd.retail-volume-za', 'ZAF'],
        ['wb.gdp-growth', 'ZAF'],
        ['wb.inflation', 'ZAF'],
        ['wb.unemployment', 'ZAF'],
        ['wb.gdp-growth', 'NGA'],
        ['wb.gdp-growth', 'KEN'],
    ],

    // Columns of the country matrix, in order.
    'matrix' => ['wb.gdp-growth', 'wb.inflation', 'wb.unemployment', 'wb.fdi'],

    // Countries in the matrix. Africa first, then the large economies people compare against.
    'matrix_countries' => ['ZAF', 'NGA', 'EGY', 'KEN', 'ETH', 'GHA', 'MAR', 'TZA', 'USA', 'CHN', 'IND', 'GBR', 'DEU', 'BRA'],
];
