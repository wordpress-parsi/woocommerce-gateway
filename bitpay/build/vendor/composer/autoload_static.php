<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit18ed11808f5e57998aa43b0ef56b8b58
{
    public static $files = array (
        '7b11c4dc42b3b3023073cb14e519683c' => __DIR__ . '/..' . '/ralouphie/getallheaders/src/getallheaders.php',
        '6e3fae29631ef280660b3cdad06f25a8' => __DIR__ . '/..' . '/symfony/deprecation-contracts/function.php',
        '320cde22f66dd4f5d3fd621d3e88b98f' => __DIR__ . '/..' . '/symfony/polyfill-ctype/bootstrap.php',
        '37a3dc5111fe8f707ab4c132ef1dbc62' => __DIR__ . '/..' . '/guzzlehttp/guzzle/src/functions_include.php',
    );

    public static $prefixLengthsPsr4 = array (
        'B' => 
        array (
            'BitPayVendor\\Symfony\\Polyfill\\Ctype\\' => 36,
            'BitPayVendor\\Symfony\\Component\\Yaml\\' => 36,
            'BitPayVendor\\Psr\\Http\\Message\\' => 30,
            'BitPayVendor\\Psr\\Http\\Client\\' => 29,
            'BitPayVendor\\JsonMapper\\' => 24,
            'BitPayVendor\\GuzzleHttp\\Psr7\\' => 29,
            'BitPayVendor\\GuzzleHttp\\Promise\\' => 32,
            'BitPayVendor\\GuzzleHttp\\' => 24,
            'BitPayVendor\\BitPaySDK\\' => 23,
            'BitPayVendor\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'BitPayVendor\\Symfony\\Polyfill\\Ctype\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-ctype',
        ),
        'BitPayVendor\\Symfony\\Component\\Yaml\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/yaml',
        ),
        'BitPayVendor\\Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-factory/src',
            1 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'BitPayVendor\\Psr\\Http\\Client\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-client/src',
        ),
        'BitPayVendor\\JsonMapper\\' => 
        array (
            0 => __DIR__ . '/..' . '/netresearch/jsonmapper/src/JsonMapper',
        ),
        'BitPayVendor\\GuzzleHttp\\Psr7\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/psr7/src',
        ),
        'BitPayVendor\\GuzzleHttp\\Promise\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/promises/src',
        ),
        'BitPayVendor\\GuzzleHttp\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/guzzle/src',
        ),
        'BitPayVendor\\BitPaySDK\\' => 
        array (
            0 => __DIR__ . '/..' . '/bitpay/sdk/src/BitPaySDK',
        ),
        'BitPayVendor\\' => 
        array (
            0 => __DIR__ . '/..' . '/bitpay/key-utils/src',
        ),
    );

    public static $classMap = array (
        'BitPayVendor\\BitPayLib\\BitPayCancelOrder' => __DIR__ . '/../..' . '/BitPayLib/class-bitpaycancelorder.php',
        'BitPayVendor\\BitPayLib\\BitPayCart' => __DIR__ . '/../..' . '/BitPayLib/class-bitpaycart.php',
        'BitPayVendor\\BitPayLib\\BitPayCheckoutTransactions' => __DIR__ . '/../..' . '/BitPayLib/class-bitpaycheckouttransactions.php',
        'BitPayVendor\\BitPayLib\\BitPayClientFactory' => __DIR__ . '/../..' . '/BitPayLib/class-bitpayclientfactory.php',
        'BitPayVendor\\BitPayLib\\BitPayInvoiceCreate' => __DIR__ . '/../..' . '/BitPayLib/class-bitpayinvoicecreate.php',
        'BitPayVendor\\BitPayLib\\BitPayIpnProcess' => __DIR__ . '/../..' . '/BitPayLib/class-bitpayipnprocess.php',
        'BitPayVendor\\BitPayLib\\BitPayLogger' => __DIR__ . '/../..' . '/BitPayLib/class-bitpaylogger.php',
        'BitPayVendor\\BitPayLib\\BitPayPages' => __DIR__ . '/../..' . '/BitPayLib/class-bitpaypages.php',
        'BitPayVendor\\BitPayLib\\BitPayPaymentSettings' => __DIR__ . '/../..' . '/BitPayLib/class-bitpaypaymentsettings.php',
        'BitPayVendor\\BitPayLib\\BitPayPluginSetup' => __DIR__ . '/../..' . '/BitPayLib/class-bitpaypluginsetup.php',
        'BitPayVendor\\BitPayLib\\WcGatewayBitpay' => __DIR__ . '/../..' . '/BitPayLib/class-wcgatewaybitpay.php',
        'BitPayVendor\\BitPayLib\\WpDbHelper' => __DIR__ . '/../..' . '/BitPayLib/trait-wpdbhelper.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit18ed11808f5e57998aa43b0ef56b8b58::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit18ed11808f5e57998aa43b0ef56b8b58::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit18ed11808f5e57998aa43b0ef56b8b58::$classMap;

        }, null, ClassLoader::class);
    }
}
