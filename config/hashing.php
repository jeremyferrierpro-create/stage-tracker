<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hashing Driver
    |--------------------------------------------------------------------------
    |
    | T-004 impose Argon2id (SECURITY §3.1). Bcrypt reste disponible pour
    | d'éventuelles vérifications d'anciens hachages.
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => true,
    ],

    'rehash_on_login' => true,

];
