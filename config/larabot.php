<?php

return [
    // The bot will not work in any channel listed here.
    'excludedChannels' => [
        'frontporch'
    ],
    'cacheKeys'        => [
        'docs' => [
            'version' => 'docs.versions',
            'header'  => 'docs.%s.headers',
            'sub'     => 'docs.%s.%s.subs',
        ]
    ]
];