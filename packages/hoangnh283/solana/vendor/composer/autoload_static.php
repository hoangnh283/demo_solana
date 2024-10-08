<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit93fcc42e67824f18235ffbe73e17c730
{
    public static $prefixLengthsPsr4 = array (
        'H' => 
        array (
            'Hoangnh283\\Solana\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Hoangnh283\\Solana\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit93fcc42e67824f18235ffbe73e17c730::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit93fcc42e67824f18235ffbe73e17c730::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit93fcc42e67824f18235ffbe73e17c730::$classMap;

        }, null, ClassLoader::class);
    }
}
