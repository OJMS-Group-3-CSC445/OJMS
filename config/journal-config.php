<?php
declare(strict_types=1);

/**
 * Public Journal & Editorial Office Configuration.
 * Keep sensitive credentials (DB passwords, SMTP keys) in a separate private config file.
 * Note: Confirm the email, phone, and postal address below with the project owner
 * before treating them as final official contact details.
 */
return [
    'editorial_office' => [
        'name'          => 'Journal of Applied Scholarship Editorial Office',
        'email'         => 'bennadeyemi@ojms.org',
        'phone_display' => '+234 (0) 800 123 4567',
        'phone_href'    => '+2348001234567',
        'address_lines' => [
            'P.M.B 2240, Abeokuta, Ogun State, Alabata',
            'Road, Abeokuta, Ogun State, 110111, Nigeria',
        ],
    ],
];