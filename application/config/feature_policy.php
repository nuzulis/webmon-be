<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['feature_policy'] = [

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

    'ITJEN' => [
        'APP008' => [
            'allow' => [
                'dashboard.*',
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

    'KEU' => [
        'APP008' => [
            'allow' => [
                'dashboard.*',
                'pnbp.*'
            ],
            'scope' => 'national'
        ]
    ],

    'LAB' => [
        '*' => [
            'allow'     => [
                'dashboard.*',
                'tindakan.*',
            ],
            'scope'     => 'national',
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
