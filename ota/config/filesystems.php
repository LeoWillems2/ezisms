<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        /*
         * Bewijsstukken (blok 6). Bewust NIET de 'public'-disk: die is via de
         * public/storage-symlink zonder enige check opvraagbaar op het web, en
         * hier staan risicobeoordelingen en straks certificaten met
         * persoonsgegevens. Downloaden loopt via een route die de Gate
         * raadpleegt — zie implementatie/06 §7.
         */
        'bewijs' => [
            'driver' => 'local',
            'root' => storage_path('app/private/bewijsstukken'),
            'serve' => false,
            'throw' => false,
            /*
             * Expliciet, want de driver maakt "private" mappen standaard als
             * 0700. Dat was oorspronkelijk een echte storing: op de
             * ontwikkelmachine schreven leo én www-data in storage/, met ACL's
             * ertussen, en 0700 zet de ACL-mask op --- waardoor álle benoemde
             * entries wegvallen — ook die van www-data zelf.
             *
             * Sinds 13-08-2026 schrijft in élke omgeving één account (zie
             * README, "Schrijfrechten op storage/"), dus die storing kan niet
             * meer optreden en 0700 zou hier ook volstaan. Deze regel blijft
             * staan als vangnet: komt er ooit een tweede account bij — een
             * back-upagent, een testrunner onder een ander account — dan is
             * groepstoegang het verschil tussen werken en stilstaan, en dat
             * faalt anders pas zichtbaar op het moment dat iemand bij bewijs
             * moet. `other` blijft leeg; dit is bewijsmateriaal.
             */
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0660],
                'dir' => ['public' => 0755, 'private' => 0770],
            ],
        ],

        /*
         * Toetsbestanden (blok 10). Stonden tot 01e in `public/toetsen/` en
         * werden door nginx uitgeserveerd; sindsdien staan ze hier en gaat het
         * uitserveren door een route die er een CSP-sandbox omheen zet
         * (implementatie/01e §1.2). Dat is de hele reden van de verhuizing: een
         * toets is door een mens geleverde HTML mét JavaScript, en op de origin
         * van het ISMS draait die in de sessie van wie hem opent.
         *
         * Zelfde permissies als 'bewijs', en om dezelfde reden — inclusief de
         * kanttekening daar dat het sinds 13-08-2026 een vangnet is en geen
         * lopende storing meer. Zie README, "Schrijfrechten op storage/".
         */
        'toetsen' => [
            'driver' => 'local',
            'root' => storage_path('app/private/toetsen'),
            'serve' => false,
            'throw' => false,
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0660],
                'dir' => ['public' => 0755, 'private' => 0770],
            ],
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
