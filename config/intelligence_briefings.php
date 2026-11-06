<?php

/*
| The editions. Each has one editorial purpose, stated on its page, and is built from stored figures and items only. Every
| line in an edition points at the figure or release it came from. An edition with nothing to say is not published.
*/

return [
    'editions' => [
        'global-business' => [
            'name' => 'Global business briefing',
            'purpose' => 'What central banks and regulators published today, and where key prices stand, in one page, so you do not have to read a dozen feeds.',
            'industries' => [], 'region' => null, 'figures' => [['eia.brent', 'WLD'], ['ecb.zar-eur', 'ZAF'], ['bis.policy-rate-za', 'ZAF']],
        ],
        'african-business' => [
            'name' => 'African business intelligence',
            'purpose' => 'Developments and figures for African economies, with South Africa as the deepest record.',
            'industries' => [], 'region' => 'Africa', 'figures' => [['wb.gdp-growth', 'ZAF'], ['wb.gdp-growth', 'NGA'], ['wb.gdp-growth', 'KEN'], ['wb.inflation', 'ZAF'], ['wb.inflation', 'NGA']],
        ],
        'mining-and-commodities' => [
            'name' => 'Mining and commodities briefing',
            'purpose' => 'What was published about mining, minerals and the energy that runs them, with the figures that bear on them.',
            'industries' => ['mining-and-minerals', 'energy'], 'region' => null, 'figures' => [['eia.brent', 'WLD'], ['wb.mineral-rents', 'ZAF'], ['wb.ores-exports', 'ZAF']],
        ],
        'small-business-outlook' => [
            'name' => 'Small business outlook',
            'purpose' => 'The prices, rates and spending a small business should watch this month, in plain words.',
            'industries' => ['retail-and-commerce', 'finance-and-banking', 'hospitality-and-tourism'], 'region' => null,
            'figures' => [['bis.policy-rate-za', 'ZAF'], ['wb.inflation', 'ZAF'], ['oecd.retail-volume-za', 'ZAF'], ['wb.lending-rate', 'ZAF'], ['ecb.zar-eur', 'ZAF']],
        ],
        'technology-and-innovation' => [
            'name' => 'Technology and innovation briefing',
            'purpose' => 'Technology developments published by governments, regulators and international bodies.',
            'industries' => ['technology', 'telecommunications'], 'region' => null, 'figures' => [['wb.internet-users', 'ZAF'], ['wb.rnd-spend', 'ZAF']],
        ],
        'economic-developments' => [
            'name' => 'Economic developments',
            'purpose' => 'Growth, prices, jobs and interest rates for the economies we cover best.',
            'industries' => [], 'region' => null, 'figures' => [['wb.gdp-growth', 'ZAF'], ['wb.inflation', 'ZAF'], ['wb.unemployment', 'ZAF'], ['bis.policy-rate-za', 'ZAF'], ['oecd.manufacturing-production-za', 'ZAF']],
        ],
    ],
];
