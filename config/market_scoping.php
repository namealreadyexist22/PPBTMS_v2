<?php

/*
 * GPPB Market Scoping Checklist (RA 12009, Section 10 of the IRR; NGPA standard form).
 * Agency information and the project overview come from the PPMP itself; the user fills
 * the period, the activities conducted, and each parameter (Yes / No / N/A + recommendation).
 * Edit the labels here if the GPPB form wording changes.
 */
return [
    'activities' => [
        'consultations' => 'Consultations with suppliers, contractors, consultants, professional associations or industry groups',
        'summits'       => 'Participation in summits, fora or conferences',
        'reports'       => 'Review of technical, financial or market / scientific reports',
        'brochures'     => 'Review of product or service brochures, marketing materials, industry journals and publications',
    ],

    'parameters' => [
        'cost'           => 'Cost estimation (Approved Budget for the Contract)',
        'specifications' => 'Project design and technical specifications / scope of work',
        'criteria'       => 'Technical and selection criteria',
        'delivery'       => 'Delivery lead time',
        'storage'        => 'Storage or warehousing requirements',
        'practices'      => 'Related industry practices',
        'liability'      => 'Defects Liability Period (infrastructure) / warranty',
        'other'          => 'Other relevant market information',
    ],

    'answers' => ['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'],

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
