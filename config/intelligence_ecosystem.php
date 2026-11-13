<?php

/*
| How the library points to InfoDot and the platforms around it. Every address here was checked on 2026-11-06 and is checked
| again each day by `intelligence:collect`. A platform that stops answering is left out of the pages until it answers again.
| Descriptions say what a business can do there, in the platform's own terms, and promise nothing more.
*/

return [

    'infodot' => 'https://infodot.co.za',

    // The directory's own category for each industry. Where InfoDot has none, a word search is used instead.
    'directory' => [
        'mining-and-minerals' => ['category' => 'mining-and-natural-resources'],
        'agriculture' => ['category' => 'agriculture-and-farming'],
        'construction' => ['category' => 'construction-and-engineering'],
        'manufacturing' => ['category' => 'manufacturing'],
        'technology' => ['category' => 'technology-and-software'],
        'finance-and-banking' => ['category' => 'finance-and-accounting'],
        'logistics-and-transport' => ['category' => 'transport-and-logistics'],
        'retail-and-commerce' => ['category' => 'retail-and-wholesale'],
        'healthcare' => ['category' => 'healthcare'],
        'education' => ['category' => 'education-and-training'],
        'real-estate' => ['category' => 'property-and-real-estate'],
        'professional-services' => ['category' => 'professional-services'],
        'hospitality-and-tourism' => ['category' => 'hospitality-and-tourism'],
        'energy' => ['q' => 'energy'],
        'telecommunications' => ['q' => 'telecommunications'],
        'automotive' => ['q' => 'automotive'],
        'renewable-energy' => ['q' => 'solar'],
    ],

    'platforms' => [
        ['key' => 'infodot', 'name' => 'InfoDot', 'url' => 'https://infodot.co.za', 'does' => 'Practical business guidance from people who have done it, and a directory of South African businesses to find and contact.'],
        ['key' => 'agents', 'name' => 'Dot.Agents', 'url' => 'https://agents.infodot.co.za', 'does' => 'Agents that do a defined piece of work for a business and stop for a person before anything consequential.'],
        ['key' => 'files', 'name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'does' => 'Business document storage with sharing and version history.'],
        ['key' => 'docs', 'name' => 'Dot.docs', 'url' => 'https://doc.infodot.co.za', 'does' => 'Documents written and edited together.'],
        ['key' => 'sheet', 'name' => 'Dot.Sheet', 'url' => 'https://sheets.infodot.co.za', 'does' => 'Spreadsheets with formulas, charts and sharing.'],
        ['key' => 'forms', 'name' => 'Dot.Forms', 'url' => 'https://forms.infodot.co.za', 'does' => 'Forms that collect information and carry it into work.'],
        ['key' => 'tasks', 'name' => 'Dot.Tasks', 'url' => 'https://tasks.infodot.co.za', 'does' => 'The daily work of a team: who does what, by when.'],
        ['key' => 'projects', 'name' => 'Dot.Projects', 'url' => 'https://projects.infodot.co.za', 'does' => 'Planning and governing projects: schedules, risks, costs and decisions.'],
        ['key' => 'hr', 'name' => 'Dot.HR', 'url' => 'https://hr.infodot.co.za', 'does' => 'People work: hiring, onboarding, time and records.'],
        ['key' => 'finance', 'name' => 'Dot.Finance', 'url' => 'https://finance.infodot.co.za', 'does' => 'Managing a business’s money.'],
        ['key' => 'mines', 'name' => 'Dot.Mines', 'url' => 'https://mines.infodot.co.za', 'does' => 'Intelligence for mining operations.'],
        ['key' => 'notify', 'name' => 'Dot.Notify', 'url' => 'https://notify.infodot.co.za', 'does' => 'Business notifications that reach the right person.'],
        ['key' => 'memory', 'name' => 'Dot.Memory', 'url' => 'https://memory.infodot.co.za', 'does' => 'A record of how a business behaves and what it has decided.'],
    ],
];
