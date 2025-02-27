<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita6b9e5285d298bd5447ca7e7ceb62374
{
    public static $files = array (
        '7c2e5f2f2156db7146816b4522d1f5cd' => __DIR__ . '/../..' . '/parse_everything.php',
    );

    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'ParseInc\\' => 9,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ParseInc\\' => 
        array (
            0 => __DIR__ . '/../..' . '/inc',
        ),
    );

    public static $prefixesPsr0 = array (
        'p' => 
        array (
            'phpQuery' => 
            array (
                0 => __DIR__ . '/..' . '/coderockr/php-query/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita6b9e5285d298bd5447ca7e7ceb62374::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita6b9e5285d298bd5447ca7e7ceb62374::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInita6b9e5285d298bd5447ca7e7ceb62374::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
