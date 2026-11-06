<?php

use App\Services\Intelligence\Fetchers\EiaFetcher;
use App\Services\Intelligence\Fetchers\FeedFetcher;
use App\Services\Intelligence\Fetchers\SdmxCsvFetcher;
use App\Services\Intelligence\Fetchers\WorldBankFetcher;

/*
|--------------------------------------------------------------------------
| Where public intelligence comes from
|--------------------------------------------------------------------------
| A source is configuration. Each carries the licence it is used under and what that allows, read from the publisher's own
| terms on 2026-11-06 (docs/programme/R38_RESEARCH.md). A source we want but may not use yet is listed with `waiting`, so the
| sources page says so instead of leaving it out. Nothing here is a licence opinion; counsel decides before launch.
*/

$sdmx = fn (string $url, array $params = []) => ['url' => $url, 'params' => $params];

return [

    'sources' => [
        [
            'key' => 'world-bank', 'name' => 'World Bank Indicators', 'kind' => 'statistics', 'fetcher' => WorldBankFetcher::class,
            'homepage' => 'https://data.worldbank.org', 'terms' => 'https://www.worldbank.org/en/about/legal/terms-of-use-for-datasets',
            'licence' => 'CC BY 4.0 (each series is checked; others are not shown)',
            'attribution' => 'The World Bank: World Development Indicators. Licensed under CC BY 4.0. Data owners of third-party series are named in each series.',
            'summary' => true, 'commercial' => true, 'restrictions' => 'Series under other licences are skipped. Some series stop years ago; the year is always shown.',
            'hosts' => ['api.worldbank.org'], 'every' => 10080, 'stale_hours' => 24 * 28,
        ],
        [
            'key' => 'oecd', 'name' => 'OECD Key Short-Term Economic Indicators', 'kind' => 'statistics', 'fetcher' => SdmxCsvFetcher::class,
            'homepage' => 'https://data-explorer.oecd.org', 'terms' => 'https://www.oecd.org/en/about/terms-conditions.html',
            'licence' => 'OECD terms: any use including commercial, with credit',
            'attribution' => 'OECD (2026), Key Short-Term Economic Indicators, OECD Data Explorer. Data are provided by the OECD under its terms of use.',
            'summary' => true, 'commercial' => true, 'restrictions' => 'Third-party ownership noted in the dataset must be respected.',
            'hosts' => ['sdmx.oecd.org'], 'every' => 1440, 'stale_hours' => 24 * 50,
        ],
        [
            'key' => 'ecb-fx', 'name' => 'European Central Bank reference rates', 'kind' => 'statistics', 'fetcher' => SdmxCsvFetcher::class,
            'homepage' => 'https://data.ecb.europa.eu', 'terms' => 'https://www.ecb.europa.eu/services/disclaimer/html/index.en.html',
            'licence' => 'ECB: free use if accurate and the ECB is cited',
            'attribution' => 'Source: European Central Bank. Rates are a daily reference and not for trading.',
            'summary' => true, 'commercial' => true, 'restrictions' => 'Where sold, buyers must be told the figures are free from the ECB. Changes to the data must be stated.',
            'hosts' => ['data-api.ecb.europa.eu'], 'every' => 1440, 'stale_hours' => 24 * 5,
        ],
        [
            'key' => 'bis', 'name' => 'Bank for International Settlements statistics', 'kind' => 'statistics', 'fetcher' => SdmxCsvFetcher::class,
            'homepage' => 'https://data.bis.org', 'terms' => 'https://www.bis.org/terms_conditions.htm',
            'licence' => 'BIS: unrestricted if the BIS is cited and no endorsement is implied',
            'attribution' => 'Source: Bank for International Settlements.',
            'summary' => true, 'commercial' => true, 'restrictions' => 'No extra charge may be made for including BIS data in a paid product.',
            'hosts' => ['stats.bis.org'], 'every' => 10080, 'stale_hours' => 24 * 21,
        ],
        [
            'key' => 'eia', 'name' => 'US Energy Information Administration', 'kind' => 'statistics', 'fetcher' => EiaFetcher::class,
            'homepage' => 'https://www.eia.gov', 'terms' => 'https://www.eia.gov/about/copyrights_reuse.php',
            'licence' => 'US government work, public domain',
            'attribution' => 'Source: U.S. Energy Information Administration.',
            'summary' => true, 'commercial' => true, 'restrictions' => null,
            'hosts' => ['api.eia.gov'], 'every' => 1440, 'stale_hours' => 24 * 5, 'needs_key' => 'intelligence.keys.eia',
        ],
        [
            'key' => 'federal-reserve', 'name' => 'Federal Reserve Board press releases', 'kind' => 'newsroom', 'fetcher' => FeedFetcher::class,
            'homepage' => 'https://www.federalreserve.gov/newsevents/pressreleases.htm', 'terms' => 'https://www.federalreserve.gov/disclaimer.htm',
            'licence' => 'Public domain unless marked; cite the Board', 'attribution' => 'Source: Board of Governors of the Federal Reserve System.',
            'summary' => true, 'commercial' => true, 'restrictions' => 'Not framed. Third-party material is excluded.',
            'hosts' => ['www.federalreserve.gov'], 'every' => 60, 'stale_hours' => 24 * 14,
            'settings' => ['feed' => 'https://www.federalreserve.gov/feeds/press_all.xml', 'kind' => 'announcement'],
        ],
        [
            'key' => 'ecb-press', 'name' => 'European Central Bank press releases', 'kind' => 'newsroom', 'fetcher' => FeedFetcher::class,
            'homepage' => 'https://www.ecb.europa.eu/press/pr/html/index.en.html', 'terms' => 'https://www.ecb.europa.eu/services/disclaimer/html/index.en.html',
            'licence' => 'ECB: free use if accurate and cited', 'attribution' => 'Source: European Central Bank.',
            'summary' => false, 'commercial' => true, 'restrictions' => 'Headline and link only: the feed carries titles.',
            'hosts' => ['www.ecb.europa.eu'], 'every' => 60, 'stale_hours' => 24 * 14,
            'settings' => ['feed' => 'https://www.ecb.europa.eu/rss/press.html', 'kind' => 'announcement'],
        ],
        [
            'key' => 'european-commission', 'name' => 'European Commission press corner', 'kind' => 'newsroom', 'fetcher' => FeedFetcher::class,
            'homepage' => 'https://ec.europa.eu/commission/presscorner/home/en', 'terms' => 'https://commission.europa.eu/legal-notice_en',
            'licence' => 'CC BY 4.0, third-party material excluded', 'attribution' => 'Source: European Commission. Reuse under CC BY 4.0.',
            'summary' => true, 'commercial' => true, 'restrictions' => 'Third-party content in a release is not covered.',
            'hosts' => ['ec.europa.eu'], 'every' => 60, 'stale_hours' => 24 * 14,
            'settings' => ['feed' => 'https://ec.europa.eu/commission/presscorner/api/rss?language=en', 'kind' => 'announcement'],
        ],
        [
            'key' => 'govuk-treasury', 'name' => 'HM Treasury (GOV.UK)', 'kind' => 'newsroom', 'fetcher' => FeedFetcher::class,
            'homepage' => 'https://www.gov.uk/government/organisations/hm-treasury', 'terms' => 'https://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/',
            'licence' => 'Open Government Licence v3.0', 'attribution' => 'Contains public sector information licensed under the Open Government Licence v3.0.',
            'summary' => true, 'commercial' => true, 'restrictions' => null,
            'hosts' => ['www.gov.uk'], 'every' => 120, 'stale_hours' => 24 * 14,
            'settings' => ['feed' => 'https://www.gov.uk/government/organisations/hm-treasury.atom', 'kind' => 'regulatory'],
        ],
        [
            'key' => 'govuk-trade', 'name' => 'Department for Business and Trade (GOV.UK)', 'kind' => 'newsroom', 'fetcher' => FeedFetcher::class,
            'homepage' => 'https://www.gov.uk/government/organisations/department-for-business-and-trade', 'terms' => 'https://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/',
            'licence' => 'Open Government Licence v3.0', 'attribution' => 'Contains public sector information licensed under the Open Government Licence v3.0.',
            'summary' => true, 'commercial' => true, 'restrictions' => null,
            'hosts' => ['www.gov.uk'], 'every' => 120, 'stale_hours' => 24 * 14,
            'settings' => ['feed' => 'https://www.gov.uk/government/organisations/department-for-business-and-trade.atom', 'kind' => 'regulatory'],
        ],
        [
            'key' => 'apo-africa-newsroom', 'name' => 'APO Group Africa Newsroom (press releases)', 'kind' => 'newsroom', 'fetcher' => FeedFetcher::class,
            'homepage' => 'https://www.africa-newsroom.com', 'terms' => 'https://www.africa-newsroom.com/terms',
            'licence' => 'Distributed press releases, free to republish per the publisher', 'attribution' => 'Press release distributed by APO Group. The issuing organisation is responsible for its content.',
            'summary' => true, 'commercial' => true, 'restrictions' => 'These are releases written by the organisations themselves, not reporting. They are labelled as such.',
            'hosts' => ['www.africa-newsroom.com'], 'every' => 60, 'stale_hours' => 24 * 7,
            'settings' => ['feed' => 'https://www.africa-newsroom.com/africarc/rss', 'kind' => 'press-release'],
        ],
        [
            'key' => 'statssa-news', 'name' => 'Statistics South Africa releases', 'kind' => 'newsroom', 'fetcher' => FeedFetcher::class,
            'homepage' => 'https://www.statssa.gov.za', 'terms' => 'https://www.statssa.gov.za/?page_id=1854',
            'licence' => 'Free use with acknowledgement and a note on own processing', 'attribution' => 'Source: Statistics South Africa.',
            'summary' => true, 'commercial' => true, 'restrictions' => 'The site blocks automated access from our servers. Off until access is agreed with Stats SA.',
            'hosts' => ['www.statssa.gov.za'], 'every' => 120, 'stale_hours' => 24 * 14, 'enabled' => false,
            'settings' => ['feed' => 'https://www.statssa.gov.za/?feed=rss2', 'kind' => 'statistics-release'],
        ],
    ],

    // Wanted, not yet allowed. Listed so the sources page is honest about what is missing and why.
    'waiting' => [
        ['key' => 'world-bank-pink-sheet', 'name' => 'World Bank commodity prices (Pink Sheet)', 'kind' => 'statistics', 'homepage' => 'https://www.worldbank.org/en/research/commodity-markets', 'reason' => 'The licence for this file is not stated. We have asked the World Bank for confirmation. It would give monthly gold, platinum, copper, iron ore and coal prices.'],
        ['key' => 'sarb', 'name' => 'South African Reserve Bank', 'kind' => 'statistics', 'homepage' => 'https://www.resbank.co.za', 'reason' => 'All rights reserved without written consent. The policy rate is shown from the BIS meanwhile.'],
        ['key' => 'gov-za', 'name' => 'South African Government news', 'kind' => 'newsroom', 'homepage' => 'https://www.gov.za', 'reason' => 'Non-commercial use only. Commercial use needs written permission from the Government Communication and Information System.'],
        ['key' => 'imf', 'name' => 'International Monetary Fund', 'kind' => 'statistics', 'homepage' => 'https://www.imf.org', 'reason' => 'Commercial reuse and automated collection need permission. Its forecasts are the main reason to ask.'],
        ['key' => 'eskom', 'name' => 'Eskom Data Portal', 'kind' => 'statistics', 'homepage' => 'https://www.eskom.co.za/dataportal', 'reason' => 'Terms not yet confirmed and no automated feed. Hourly demand and generation would add a great deal for South Africa.'],
    ],

    // Series that come from the sources above and are not World Bank series. `settings` is what the fetcher needs.
    'series' => [
        ['key' => 'ecb.zar-eur', 'source' => 'ecb-fx', 'code' => 'EXR/D.ZAR.EUR.SP00.A', 'name' => 'Rand per euro, ECB reference rate', 'unit' => 'rand per euro', 'topic' => 'markets', 'frequency' => 'D', 'decimals' => 3, 'good' => 'neutral', 'stale_days' => 6, 'industries' => ['finance-and-banking'], 'caveat' => 'A daily reference rate set at 14:15 Central European Time, not a price to trade at.', 'definition' => 'The number of South African rand that buy one euro, as the European Central Bank publishes its daily reference rate.', 'settings' => $sdmx('https://data-api.ecb.europa.eu/service/data/EXR/D.ZAR.EUR.SP00.A', ['format' => 'csvdata', 'startPeriod' => '2023-01-01']) + ['geography' => 'ZAF']],
        ['key' => 'bis.policy-rate-za', 'source' => 'bis', 'code' => 'WS_CBPOL/D.ZA', 'name' => 'Central bank policy rate, South Africa', 'unit' => '% a year', 'topic' => 'finance', 'frequency' => 'D', 'decimals' => 2, 'good' => 'neutral', 'stale_days' => 14, 'industries' => ['finance-and-banking', 'real-estate'], 'caveat' => 'The South African Reserve Bank repo rate, as reported to the BIS.', 'definition' => 'The official policy rate of the South African Reserve Bank, at the end of each day, as collected by the Bank for International Settlements.', 'settings' => $sdmx('https://stats.bis.org/api/v1/data/WS_CBPOL/D.ZA', ['format' => 'csv', 'startPeriod' => '2022-01-01']) + ['geography' => 'ZAF']],
        ['key' => 'oecd.manufacturing-production-za', 'source' => 'oecd', 'code' => 'KEI/ZAF.M.PRVM.GR.C.Y.GY', 'name' => 'Manufacturing production, change on a year earlier', 'unit' => '% change on a year earlier', 'topic' => 'output', 'frequency' => 'M', 'decimals' => 1, 'good' => 'up', 'stale_days' => 75, 'industries' => ['manufacturing'], 'caveat' => 'Calendar and seasonally adjusted.', 'definition' => 'The change in the volume of manufacturing production in South Africa against the same month a year earlier, adjusted for calendar and seasonal effects.', 'settings' => $sdmx('https://sdmx.oecd.org/public/rest/data/OECD.SDD.STES,DSD_KEI@DF_KEI,/ZAF.M.PRVM.GR.C.Y.GY', ['format' => 'csvfilewithlabels', 'startPeriod' => '2018-01']) + ['geography' => 'ZAF']],
        ['key' => 'oecd.retail-volume-za', 'source' => 'oecd', 'code' => 'KEI/ZAF.M.TOVM.GR.G47.Y.GY', 'name' => 'Retail trade volume, change on a year earlier', 'unit' => '% change on a year earlier', 'topic' => 'commerce', 'frequency' => 'M', 'decimals' => 1, 'good' => 'up', 'stale_days' => 75, 'industries' => ['retail-and-commerce'], 'caveat' => 'Retail trade except motor vehicles. Calendar and seasonally adjusted.', 'definition' => 'The change in the volume of retail sales in South Africa, excluding motor vehicles, against the same month a year earlier.', 'settings' => $sdmx('https://sdmx.oecd.org/public/rest/data/OECD.SDD.STES,DSD_KEI@DF_KEI,/ZAF.M.TOVM.GR.G47.Y.GY', ['format' => 'csvfilewithlabels', 'startPeriod' => '2018-01']) + ['geography' => 'ZAF']],
        ['key' => 'oecd.car-registrations-za', 'source' => 'oecd', 'code' => 'KEI/ZAF.M.TOCAPA.GR.G45.Y.GY', 'name' => 'Passenger car registrations, change on a year earlier', 'unit' => '% change on a year earlier', 'topic' => 'commerce', 'frequency' => 'M', 'decimals' => 1, 'good' => 'up', 'stale_days' => 75, 'industries' => ['automotive'], 'caveat' => 'Registrations are a measure of new car demand, not of all vehicle sales.', 'definition' => 'The change in the number of new passenger cars registered in South Africa against the same month a year earlier.', 'settings' => $sdmx('https://sdmx.oecd.org/public/rest/data/OECD.SDD.STES,DSD_KEI@DF_KEI,/ZAF.M.TOCAPA.GR.G45.Y.GY', ['format' => 'csvfilewithlabels', 'startPeriod' => '2018-01']) + ['geography' => 'ZAF']],
        ['key' => 'eia.brent', 'source' => 'eia', 'code' => 'RBRTE', 'name' => 'Brent crude oil spot price', 'unit' => 'US$ per barrel', 'topic' => 'markets', 'frequency' => 'D', 'decimals' => 2, 'good' => 'neutral', 'stale_days' => 7, 'industries' => ['energy', 'logistics-and-transport'], 'caveat' => 'A daily spot price. The World Bank monthly average differs from the latest day.', 'definition' => 'The spot price of Brent crude oil, free on board, as the US Energy Information Administration reports it each trading day.', 'settings' => ['geography' => 'WLD']],
        ['key' => 'eia.wti', 'source' => 'eia', 'code' => 'RWTC', 'name' => 'WTI crude oil spot price', 'unit' => 'US$ per barrel', 'topic' => 'markets', 'frequency' => 'D', 'decimals' => 2, 'good' => 'neutral', 'stale_days' => 7, 'industries' => ['energy'], 'caveat' => 'A daily spot price.', 'definition' => 'The spot price of West Texas Intermediate crude oil at Cushing, Oklahoma, as the US Energy Information Administration reports it each trading day.', 'settings' => ['geography' => 'WLD']],
    ],
];
