<?php

/*
|--------------------------------------------------------------------------
| How a development is filed under an industry
|--------------------------------------------------------------------------
| Plain word lists, so anyone can see why an item is where it is. A match in the headline counts for more than one in a
| summary. Nothing is filed by a model, and an item with no match is simply unfiled rather than guessed at.
*/

return [

    'industries' => [
        'mining-and-minerals' => ['mining', 'miner', 'mine ', 'mines', 'gold', 'platinum', 'coal', 'iron ore', 'copper', 'nickel', 'lithium', 'minerals', 'critical minerals'],
        'agriculture' => ['agricultur', 'farm', 'crop', 'harvest', 'livestock', 'fertili', 'grain', 'maize', 'wheat', 'food security', 'fisher'],
        'construction' => ['construction', 'building permits', 'housing starts', 'infrastructure', 'cement'],
        'manufacturing' => ['manufactur', 'factory', 'factories', 'industrial production', 'steel', 'supply chain'],
        'technology' => ['technology', 'artificial intelligence', ' ai ', 'software', 'semiconductor', 'cyber', 'digital', 'startup', 'data centre', 'data center'],
        'finance-and-banking' => ['bank', 'interest rate', 'monetary policy', 'inflation', 'credit', 'lending', 'financial stability', 'insurance', 'capital markets', 'central bank'],
        'energy' => ['energy', 'oil', 'natural gas', 'electricity', 'power grid', 'fuel', 'opec', 'petroleum', 'nuclear'],
        'logistics-and-transport' => ['logistics', 'shipping', 'freight', 'port ', 'ports', 'rail', 'aviation', 'airline', 'trucking', 'transport'],
        'retail-and-commerce' => ['retail', 'consumer spending', 'consumer prices', 'e-commerce', 'wholesale', 'shoppers'],
        'healthcare' => ['health', 'hospital', 'pharmaceutical', 'medicine', 'vaccine', 'medical'],
        'education' => ['education', 'university', 'school', 'skills', 'training'],
        'real-estate' => ['real estate', 'property', 'housing', 'mortgage', 'rent '],
        'telecommunications' => ['telecom', 'mobile network', 'broadband', '5g', 'spectrum', 'fibre', 'satellite'],
        'professional-services' => ['consulting', 'accounting', 'legal services', 'audit', 'professional services'],
        'hospitality-and-tourism' => ['tourism', 'tourist', 'hotel', 'hospitality', 'travel demand', 'restaurant'],
        'automotive' => ['automotive', 'vehicle', 'car sales', 'electric vehicle', 'carmaker', 'car maker'],
        'renewable-energy' => ['renewable', 'solar', 'wind power', 'wind farm', 'hydropower', 'green hydrogen', 'clean energy'],
    ],

    // Places named in a headline or summary besides the country names themselves.
    'places' => [
        'ZAF' => ['south africa', 'south african', 'johannesburg', 'cape town', 'pretoria', 'sarb', 'rand '],
        'NGA' => ['nigeria', 'nigerian', 'lagos', 'abuja'],
        'KEN' => ['kenya', 'kenyan', 'nairobi'],
        'EGY' => ['egypt', 'egyptian', 'cairo'],
        'GBR' => ['united kingdom', 'british', 'britain', 'uk '],
        'USA' => ['united states', 'u.s.', 'american', 'federal reserve', 'washington'],
        'CHN' => ['china', 'chinese', 'beijing'],
        'IND' => ['india', 'indian '],
        'DEU' => ['germany', 'german '],
        'SSF' => ['sub-saharan', 'across africa', 'african union', 'pan-african', 'african countries', 'african economies'],
    ],
];
