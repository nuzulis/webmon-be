<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['feature_policy'] = [

    // =====================
    // SUPER ADMIN
    // =====================
    'SA' => [
        '*' => [
           'allow' => [
                'dashboard.*',
                'operasional.*',
                'tindakan.*',
                'pnbp.*',
                'profiling.*',
                'penugasan.*',
                'preborder.*',
                'preborder.priornoticedetail.view',
                'preborder.ecertdetail*',
                'caricepat.*'
            ],
            'scope' => 'national'
        ]
    ],

    // =====================
    // ADMIN NASIONAL
    // =====================
    'ADM-KP' => [
        '*' => [
            'allow' => [
                 'dashboard.*',
                'operasional.*',
                'tindakan.*',
                'pnbp.*',
                'profiling.*',
                'penugasan.*',
                'preborder.*',
            ],
            'scope' => 'national'
        ]
    ],

    // =====================
    // ADMIN UPT
    // =====================
    'ADM-UPT' => [
        'APP001' => [
            'allow' => [
                'dashboard.*',
                'operasional.*',
                'tindakan.*',
                'pnbp.*',
                'profiling.*',
                'penugasan.*',
                'preborder.*',

            ],
            'scope' => 'upt'
        ]
    ],

    // =====================
    // PETUGAS TEKNIS
    // =====================
    'AP' => [
        'APP001' => [
            'allow' => [
                'operasional.*',
                'tindakan.*',
                'preborder.*'
            ],
            'scope' => 'upt'
        ]
    ],

    'SPVT' => [
        'APP001' => [
            'allow' => [
                'dashboard.*',
                'operasional.*',
                'tindakan.*',
                'pnbp.*',
                'profiling.*',
                'penugasan.*',
                'preborder.*'
            ],
            'scope' => 'upt'
        ]
    ],

    // =====================
    // GKM (KHUSUS)
    // =====================
    'GKM' => [
        // GKM di APP_ID (Operasional)
        'APP001' => [
            'allow' => [
                'dashboard.*',
                'operasional.*',
                'tindakan.*',
                'preborder.*',
                'profiling.*'
            ],
            'scope' => 'upt'
        ],

        // GKM di APP_ID2 (Pengawasan / Keuangan)
        'APP008' => [
            'allow' => [
                'dashboard.*',
                'tindakan.*',
                'pnbp.*',
                'profiling.*'
            ],
            'scope' => 'national'
        ]
    ],

    // =====================
    // PENGAWAS NASIONAL
    // =====================
    'ITJEN' => [
        'APP008' => [
            'allow' => [
                'tindakan.*',
                'pnbp.*'
            ],
            'scope' => 'national'
        ]
    ],

    'BPK' => [
        'APP008' => [
            'allow' => [
                'tindakan.pelepasan',
                'pnbp.*'
            ],
            'scope' => 'national'
        ]
    ],

    // =====================
    // BUK
    // =====================
    'KEU' => [
        'APP008' => [
            'allow' => [
                'pnbp.*'
            ],
            'scope' => 'national'
        ]
    ],
    // =====================
    // KEDEPUTIAANNN
    // =====================
    'DEP-KH' => [
    '*' => [
        'allow'     => [
            'dashboard.*',
            'operasional.*',
            'tindakan.*',
            'preborder.*'
        ],
        'scope'     => 'karantina',
        'karantina' => 'kh'
    ]
    ],

    'DEP-KI' => [
        '*' => [
            'allow'     => [
                'dashboard.*',
                'operasional.*',
                'tindakan.*',
                'preborder.*'
            ],
            'scope'     => 'karantina',
            'karantina' => 'ki'
        ]
    ],

    'DEP-KT' => [
        '*' => [
            'allow'     => [
                'dashboard.*',
                'operasional.*',
                'tindakan.*',
                'preborder.*'
            ],
            'scope'     => 'karantina',
            'karantina' => 'kt'
        ]
    ],

];
