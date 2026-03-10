<?php

return [

    'paths' => ['api/*', 'assets/*'], // allow your api and your assets folder

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:3000'], // only your React app

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
