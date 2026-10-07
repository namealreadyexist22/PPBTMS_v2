<?php

return [
    // Unit cost from which goods are Capital Outlay (property, plant and equipment); below it,
    // durable goods are Semi-Expendable (consumables stay MOOE). Used to suggest the allotment class.
    'capitalization_threshold' => 50000,

    // Job Request types
    'jr_types' => [
        'repairs'     => 'Repairs and Maintenance',
        'pakyaw'      => 'Pakyaw',
        'semi'        => 'Semi-Expendable Property',
        'office'      => 'Office Supplies',
        'medlab'      => 'Med. & Lab Supplies',
        'agri'        => "Agric'l Supplies",
        'rental'      => 'Office Rental',
        'printing'    => 'Printing & Binding',
        'gasoline'    => 'Gasoline, Oil & Lubricants',
        'travel'      => 'Travelling Expenses',
        'trainings'   => 'Trainings & Seminars',
        'other_mooe'  => 'Other MOOE',
        'co'          => 'Capital Outlay',
    ],

    // Shown at the top right of a PR / JR: FOR BIDDING when a line's mode is competitive bidding
    'bidding_mode_codes' => ['CB'],
];
