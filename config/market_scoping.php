<?php

/*
 * GPPB Market Scoping Checklist, as in the NGPA standard form (RA 12009, Section 10 of the IRR).
 * Agency information and the project overview come from the PPMP itself; the user fills
 * the period, the activities conducted, and each parameter (Yes / No / N/A + recommendation).
 * Edit the labels here if the GPPB form wording changes.
 */
return [
    // Section 3: [label, documentation (as may be applicable)]
    'activities' => [
        'consultations'  => ['Consultations with suppliers / contractors / consultants / professional associations or industry groups',
                             'Highlights of consultations or meetings / Proof of attendance / Reports / Summaries / Screenshots / Brochures / Publications / Price quotations / Canvass sheets / Market Analysis Report or similar document/s'],
        'summits'        => ['Participation in summits, fora, or conferences',
                             'Highlights of consultations or meetings / Proof of Attendance / Reports'],
        'reports'        => ['Review of technical, financial, or market/scientific reports',
                             'Reports / Summaries / Screenshots / Brochures / Publications, Market Analysis Report or similar document / Online Product Reviews'],
        'brochures'      => ['Review of product or service brochures, marketing materials, industry journals and publications or related materials',
                             'Reports / Summaries / Screenshots / Brochures / Publications / Online Product Reviews'],
        'price_sourcing' => ['Price sourcing for quotations or cost estimates from suppliers, contractors, or consultants',
                             'Price quotations / Canvass sheets / Online Product Reviews'],
        'philgeps'       => ['Use of data from PhilGEPS or agency websites',
                             'Reports / Summaries / Screenshots, Price quotations / Canvass sheets / PhilGEPS Postings / Online Product Reviews'],
    ],

    // Section 4 (parameters of Sec. 10.4 of the IRR): [label, guiding question]
    'parameters' => [
        'cost'           => ['Project Cost Estimate', 'Does the cost estimate align with current market prices?'],
        'specifications' => ['Project Design and Specification', 'Does available supplier/s meet technical and financial requirements?'],
        'criteria'       => ['Technical Criteria', 'Does the market support the proposed technical requirements?'],
        'delivery'       => ['Delivery Lead Time', 'Are the timelines for delivery feasible?'],
        'storage'        => ['Storage and Warehousing Requirements', 'Can the storage/warehousing needs be met considering specific conditions like temperature, humidity, and handling?'],
        'risks'          => ['Identified Risk/s', 'Were there any market risks identified? (e.g., limited suppliers, price volatility)'],
    ],

    'answers' => ['yes' => 'Yes', 'no' => 'No', 'na' => 'Not Applicable'],

    // Kinds of files that can be attached to a procurement project
    'attachment_kinds' => [
        'market_survey' => 'Market survey / price quotations',
        'specs'         => 'Technical specifications / TOR / scope of work',
        'engineering'   => 'Detailed engineering / plans',
        'other'         => 'Other supporting document',
    ],

    'max_file_kb'   => 10240,   // 10 MB per file
    'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'],
];
