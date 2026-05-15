<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

$jwtKeyDir = dirname(__DIR__).'/var/jwt-test';
$jwtPrivate = $jwtKeyDir.'/private.pem';
$jwtPublic = $jwtKeyDir.'/public.pem';

if (!is_file($jwtPrivate) || !is_file($jwtPublic)) {
    if (!is_dir($jwtKeyDir)) {
        mkdir($jwtKeyDir, 0700, true);
    }

    $resource = openssl_pkey_new([
        'private_key_bits' => 4096,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($resource, $privatePem);
    $publicPem = openssl_pkey_get_details($resource)['key'];

    file_put_contents($jwtPrivate, $privatePem);
    file_put_contents($jwtPublic, $publicPem);
    chmod($jwtPrivate, 0600);
}
