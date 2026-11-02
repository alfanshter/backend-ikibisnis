<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Frontend (ptpws.id) dan backend API (ikibisnis.ptpws.id) berada di domain
    | berbeda, sehingga browser memblokir request cross-origin (fetch/XHR)
    | yang tidak memiliki header CORS.
    |
    | Path 'storage/*' disertakan sebagai upaya tambahan, NAMUN file di
    | public/storage dilayani web server secara langsung (symlink) sehingga
    | tidak melewati middleware ini. Kanal yang andal untuk gambar PDF
    | adalah endpoint API: GET /api/settings/penawaran-pdf/image/{type}.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
