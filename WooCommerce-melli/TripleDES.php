<?php


define ('MCRYPT_ENCRYPT', 0);
define ('MCRYPT_DECRYPT', 1);
define ('MCRYPT_DEV_RANDOM', 0);
define ('MCRYPT_DEV_URANDOM', 1);
define ('MCRYPT_RAND', 2);
define ('MCRYPT_3DES', "tripledes");
define ('MCRYPT_ARCFOUR_IV', "arcfour-iv");
define ('MCRYPT_ARCFOUR', "arcfour");
define ('MCRYPT_BLOWFISH', "blowfish");
define ('MCRYPT_BLOWFISH_COMPAT', "blowfish-compat");
define ('MCRYPT_CAST_128', "cast-128");
define ('MCRYPT_CAST_256', "cast-256");
define ('MCRYPT_CRYPT', "crypt");
define ('MCRYPT_DES', "des");
define ('MCRYPT_ENIGNA', "crypt");
define ('MCRYPT_GOST', "gost");
define ('MCRYPT_LOKI97', "loki97");
define ('MCRYPT_PANAMA', "panama");
define ('MCRYPT_RC2', "rc2");
define ('MCRYPT_RIJNDAEL_128', "rijndael-128");
define ('MCRYPT_RIJNDAEL_192', "rijndael-192");
define ('MCRYPT_RIJNDAEL_256', "rijndael-256");
define ('MCRYPT_SAFER64', "safer-sk64");
define ('MCRYPT_SAFER128', "safer-sk128");
define ('MCRYPT_SAFERPLUS', "saferplus");
define ('MCRYPT_SERPENT', "serpent");
define ('MCRYPT_THREEWAY', "threeway");
define ('MCRYPT_TRIPLEDES', "tripledes");
define ('MCRYPT_TWOFISH', "twofish");
define ('MCRYPT_WAKE', "wake");
define ('MCRYPT_XTEA', "xtea");
define ('MCRYPT_IDEA', "idea");
define ('MCRYPT_MARS', "mars");
define ('MCRYPT_RC6', "rc6");
define ('MCRYPT_SKIPJACK', "skipjack");
define ('MCRYPT_MODE_CBC', "cbc");
define ('MCRYPT_MODE_CFB', "cfb");
define ('MCRYPT_MODE_ECB', "ecb");
define ('MCRYPT_MODE_NOFB', "nofb");
define ('MCRYPT_MODE_OFB', "ofb");
define ('MCRYPT_MODE_STREAM', "stream");
define('CRYPT_MODE_ECB', 1);
define('CRYPT_MODE_CBC', 2);
define('CRYPT_MODE_CFB', 3);
define('CRYPT_MODE_OFB', 4);
define('CRYPT_MODE_CTR', -1);
define('CRYPT_MODE_3CBC', -2);
define('CRYPT_MODE_STREAM', 5);
define('CRYPT_DES_ENCRYPT', 0);
define('CRYPT_DES_DECRYPT', 1);
define('CRYPT_ENGINE_MCRYPT', 2);
define('CRYPT_ENGINE_OPENSSL', 3);
define('CRYPT_DES_MODE_3CBC', -2);
define('CRYPT_ENGINE_INTERNAL', 1);
define('CRYPT_MODE_CBC3', CRYPT_MODE_CBC);
define('CRYPT_DES_MODE_CTR', CRYPT_MODE_CTR);
define('CRYPT_DES_MODE_ECB', CRYPT_MODE_ECB);
define('CRYPT_DES_MODE_CBC', CRYPT_MODE_CBC);
define('CRYPT_DES_MODE_CFB', CRYPT_MODE_CFB);
define('CRYPT_DES_MODE_OFB', CRYPT_MODE_OFB);
define('CRYPT_DES_MODE_CBC3', CRYPT_MODE_CBC3);

class Crypt_Base {
    /**
     * The Encryption Mode
     *
     * @see self::Crypt_Base()
     * @var int
     * @access private
     */
    var $mode;

    /**
     * The Block Length of the block cipher
     *
     * @var int
     * @access private
     */
    var $block_size = 16;

    /**
     * The Key
     *
     * @see self::setKey()
     * @var string
     * @access private
     */
    var $key = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";

    /**
     * The Initialization Vector
     *
     * @see self::setIV()
     * @var string
     * @access private
     */
    var $iv;

    /**
     * A "sliding" Initialization Vector
     *
     * @see self::enableContinuousBuffer()
     * @see self::_clearBuffers()
     * @var string
     * @access private
     */
    var $encryptIV;

    /**
     * A "sliding" Initialization Vector
     *
     * @see self::enableContinuousBuffer()
     * @see self::_clearBuffers()
     * @var string
     * @access private
     */
    var $decryptIV;

    /**
     * Continuous Buffer status
     *
     * @see self::enableContinuousBuffer()
     * @var bool
     * @access private
     */
    var $continuousBuffer = false;

    /**
     * Encryption buffer for CTR, OFB and CFB modes
     *
     * @see self::encrypt()
     * @see self::_clearBuffers()
     * @var array
     * @access private
     */
    var $enbuffer;

    /**
     * Decryption buffer for CTR, OFB and CFB modes
     *
     * @see self::decrypt()
     * @see self::_clearBuffers()
     * @var array
     * @access private
     */
    var $debuffer;

    /**
     * mcrypt resource for encryption
     *
     * The mcrypt resource can be recreated every time something needs to be created or it can be created just once.
     * Since mcrypt operates in continuous mode, by default, it'll need to be recreated when in non-continuous mode.
     *
     * @see self::encrypt()
     * @var resource
     * @access private
     */
    var $enmcrypt;

    /**
     * mcrypt resource for decryption
     *
     * The mcrypt resource can be recreated every time something needs to be created or it can be created just once.
     * Since mcrypt operates in continuous mode, by default, it'll need to be recreated when in non-continuous mode.
     *
     * @see self::decrypt()
     * @var resource
     * @access private
     */
    var $demcrypt;

    /**
     * Does the enmcrypt resource need to be (re)initialized?
     *
     * @see Crypt_Twofish::setKey()
     * @see Crypt_Twofish::setIV()
     * @var bool
     * @access private
     */
    var $enchanged = true;

    /**
     * Does the demcrypt resource need to be (re)initialized?
     *
     * @see Crypt_Twofish::setKey()
     * @see Crypt_Twofish::setIV()
     * @var bool
     * @access private
     */
    var $dechanged = true;

    /**
     * mcrypt resource for CFB mode
     *
     * mcrypt's CFB mode, in (and only in) buffered context,
     * is broken, so phpseclib implements the CFB mode by it self,
     * even when the mcrypt php extension is available.
     *
     * In order to do the CFB-mode work (fast) phpseclib
     * use a separate ECB-mode mcrypt resource.
     *
     * @link http://phpseclib.sourceforge.net/cfb-demo.phps
     * @see self::encrypt()
     * @see self::decrypt()
     * @see self::_setupMcrypt()
     * @var resource
     * @access private
     */
    var $ecb;

    /**
     * Optimizing value while CFB-encrypting
     *
     * Only relevant if $continuousBuffer enabled
     * and $engine == CRYPT_ENGINE_MCRYPT
     *
     * It's faster to re-init $enmcrypt if
     * $buffer bytes > $cfb_init_len than
     * using the $ecb resource furthermore.
     *
     * This value depends of the chosen cipher
     * and the time it would be needed for it's
     * initialization [by mcrypt_generic_init()]
     * which, typically, depends on the complexity
     * on its internaly Key-expanding algorithm.
     *
     * @see self::encrypt()
     * @var int
     * @access private
     */
    var $cfb_init_len = 600;

    /**
     * Does internal cipher state need to be (re)initialized?
     *
     * @see self::setKey()
     * @see self::setIV()
     * @see self::disableContinuousBuffer()
     * @var bool
     * @access private
     */
    var $changed = true;

    /**
     * Padding status
     *
     * @see self::enablePadding()
     * @var bool
     * @access private
     */
    var $padding = true;

    /**
     * Is the mode one that is paddable?
     *
     * @see self::Crypt_Base()
     * @var bool
     * @access private
     */
    var $paddable = false;

    /**
     * Holds which crypt engine internaly should be use,
     * which will be determined automatically on __construct()
     *
     * Currently available $engines are:
     * - CRYPT_ENGINE_OPENSSL  (very fast, php-extension: openssl, extension_loaded('openssl') required)
     * - CRYPT_ENGINE_MCRYPT   (fast, php-extension: mcrypt, extension_loaded('mcrypt') required)
     * - CRYPT_ENGINE_INTERNAL (slower, pure php-engine, no php-extension required)
     *
     * @see self::_setEngine()
     * @see self::encrypt()
     * @see self::decrypt()
     * @var int
     * @access private
     */
    var $engine;

    /**
     * Holds the preferred crypt engine
     *
     * @see self::_setEngine()
     * @see self::setPreferredEngine()
     * @var int
     * @access private
     */
    var $preferredEngine;

    /**
     * The mcrypt specific name of the cipher
     *
     * Only used if $engine == CRYPT_ENGINE_MCRYPT
     *
     * @link http://www.php.net/mcrypt_module_open
     * @link http://www.php.net/mcrypt_list_algorithms
     * @see self::_setupMcrypt()
     * @var string
     * @access private
     */
    var $cipher_name_mcrypt;

    /**
     * The openssl specific name of the cipher
     *
     * Only used if $engine == CRYPT_ENGINE_OPENSSL
     *
     * @link http://www.php.net/openssl-get-cipher-methods
     * @var string
     * @access private
     */
    var $cipher_name_openssl;

    /**
     * The openssl specific name of the cipher in ECB mode
     *
     * If OpenSSL does not support the mode we're trying to use (CTR)
     * it can still be emulated with ECB mode.
     *
     * @link http://www.php.net/openssl-get-cipher-methods
     * @var string
     * @access private
     */
    var $cipher_name_openssl_ecb;

    /**
     * The default salt used by setPassword()
     *
     * @see self::setPassword()
     * @var string
     * @access private
     */
    var $password_default_salt = 'phpseclib/salt';

    /**
     * The namespace used by the cipher for its constants.
     *
     * ie: AES.php is using CRYPT_AES_MODE_* for its constants
     *     so $const_namespace is AES
     *
     *     DES.php is using CRYPT_DES_MODE_* for its constants
     *     so $const_namespace is DES... and so on
     *
     * All CRYPT_<$const_namespace>_MODE_* are aliases of
     * the generic CRYPT_MODE_* constants, so both could be used
     * for each cipher.
     *
     * Example:
     * $aes = new Crypt_AES(CRYPT_AES_MODE_CFB); // $aes will operate in cfb mode
     * $aes = new Crypt_AES(CRYPT_MODE_CFB);     // identical
     *
     * @see self::Crypt_Base()
     * @var string
     * @access private
     */
    var $const_namespace;

    /**
     * The name of the performance-optimized callback function
     *
     * Used by encrypt() / decrypt()
     * only if $engine == CRYPT_ENGINE_INTERNAL
     *
     * @see self::encrypt()
     * @see self::decrypt()
     * @see self::_setupInlineCrypt()
     * @see self::$use_inline_crypt
     * @var Callback
     * @access private
     */
    var $inline_crypt;

    /**
     * Holds whether performance-optimized $inline_crypt() can/should be used.
     *
     * @see self::encrypt()
     * @see self::decrypt()
     * @see self::inline_crypt
     * @var mixed
     * @access private
     */
    var $use_inline_crypt;

    /**
     * If OpenSSL can be used in ECB but not in CTR we can emulate CTR
     *
     * @see self::_openssl_ctr_process()
     * @var bool
     * @access private
     */
    var $openssl_emulate_ctr = false;

    /**
     * Determines what options are passed to openssl_encrypt/decrypt
     *
     * @see self::isValidEngine()
     * @var mixed
     * @access private
     */
    var $openssl_options;

    /**
     * Has the key length explicitly been set or should it be derived from the key, itself?
     *
     * @see self::setKeyLength()
     * @var bool
     * @access private
     */
    var $explicit_key_length = false;

    /**
     * Don't truncate / null pad key
     *
     * @see self::_clearBuffers()
     * @var bool
     * @access private
     */
    var $skip_key_adjustment = false;

    /**
     * Default Constructor.
     *
     * Determines whether or not the mcrypt extension should be used.
     *
     * $mode could be:
     *
     * - CRYPT_MODE_ECB
     *
     * - CRYPT_MODE_CBC
     *
     * - CRYPT_MODE_CTR
     *
     * - CRYPT_MODE_CFB
     *
     * - CRYPT_MODE_OFB
     *
     * (or the alias constants of the chosen cipher, for example for AES: CRYPT_AES_MODE_ECB or CRYPT_AES_MODE_CBC ...)
     *
     * If not explicitly set, CRYPT_MODE_CBC will be used.
     *
     * @param int $mode
     * @access public
     */
    public function __construct($mode = CRYPT_MODE_CBC) {
        // $mode dependent settings
        switch ($mode) {
            case CRYPT_MODE_ECB:
                $this->paddable = true;
                $this->mode = CRYPT_MODE_ECB;
                break;
            case CRYPT_MODE_CTR:
            case CRYPT_MODE_CFB:
            case CRYPT_MODE_OFB:
            case CRYPT_MODE_STREAM:
                $this->mode = $mode;
                break;
            case CRYPT_MODE_CBC:
            default:
                $this->paddable = true;
                $this->mode = CRYPT_MODE_CBC;
        }

        $this->_setEngine();

        // Determining whether inline crypting can be used by the cipher
        if ($this->use_inline_crypt !== false) {
            $this->use_inline_crypt = version_compare(PHP_VERSION, '5.3.0') >= 0 || function_exists('create_function');
        }
    }

    /**
     * PHP4 compatible Default Constructor.
     *
     * @see self::__construct()
     * @param int $mode
     * @access public
     */
    public function Crypt_Base($mode = CRYPT_MODE_CBC)
    {
        $this->__construct($mode);
    }

    /**
     * Sets the initialization vector. (optional)
     *
     * SetIV is not required when CRYPT_MODE_ECB (or ie for AES: CRYPT_AES_MODE_ECB) is being used.  If not explicitly set, it'll be assumed
     * to be all zero's.
     *
     * @access public
     * @param string $iv
     * @internal Can be overwritten by a sub class, but does not have to be
     */
    public function setIV($iv)
    {
        if ($this->mode == CRYPT_MODE_ECB) {
            return;
        }

        $this->iv = $iv;
        $this->changed = true;
    }

    /**
     * Sets the key length.
     *
     * Keys with explicitly set lengths need to be treated accordingly
     *
     * @access public
     * @param int $length
     */
    public function setKeyLength($length)
    {
        $this->explicit_key_length = true;
        $this->changed = true;
        $this->_setEngine();
    }

    /**
     * Returns the current key length in bits
     *
     * @access public
     * @return int
     */
    public function getKeyLength()
    {
        return $this->key_length << 3;
    }

    /**
     * Returns the current block length in bits
     *
     * @access public
     * @return int
     */
    public function getBlockLength()
    {
        return $this->block_size << 3;
    }

    /**
     * Sets the key.
     *
     * The min/max length(s) of the key depends on the cipher which is used.
     * If the key not fits the length(s) of the cipher it will paded with null bytes
     * up to the closest valid key length.  If the key is more than max length,
     * we trim the excess bits.
     *
     * If the key is not explicitly set, it'll be assumed to be all null bytes.
     *
     * @access public
     * @param string $key
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function setKey($key)
    {
        if (!$this->explicit_key_length) {
            $this->setKeyLength(strlen($key) << 3);
            $this->explicit_key_length = false;
        }

        $this->key = $key;
        $this->changed = true;
        $this->_setEngine();
    }

    /**
     * Sets the password.
     *
     * Depending on what $method is set to, setPassword()'s (optional) parameters are as follows:
     *     {@link http://en.wikipedia.org/wiki/PBKDF2 pbkdf2} or pbkdf1:
     *         $hash, $salt, $count, $dkLen
     *
     *         Where $hash (default = sha1) currently supports the following hashes: see: Crypt/Hash.php
     *
     * @see Crypt/Hash.php
     * @param string $password
     * @param string $method
     * @return bool
     * @access public
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function setPassword($password, $method = 'pbkdf2')
    {
        $key = '';

        switch ($method) {
            default: // 'pbkdf2' or 'pbkdf1'
                $func_args = func_get_args();

                // Hash function
                $hash = isset($func_args[2]) ? $func_args[2] : 'sha1';

                // WPA and WPA2 use the SSID as the salt
                $salt = isset($func_args[3]) ? $func_args[3] : $this->password_default_salt;

                // RFC2898#section-4.2 uses 1,000 iterations by default
                // WPA and WPA2 use 4,096.
                $count = isset($func_args[4]) ? $func_args[4] : 1000;

                // Keylength
                if (isset($func_args[5]) && $func_args[5] > 0) {
                    $dkLen = $func_args[5];
                } else {
                    $dkLen = $method == 'pbkdf1' ? 2 * $this->key_length : $this->key_length;
                }

                switch (true) {
                    case $method == 'pbkdf1':
                        if (!class_exists('Crypt_Hash')) {
                            include_once 'Crypt/Hash.php';
                        }
                        $hashObj = new Crypt_Hash();
                        $hashObj->setHash($hash);
                        if ($dkLen > $hashObj->getLength()) {
                            user_error('Derived key too long');
                            return false;
                        }
                        $t = $password . $salt;
                        for ($i = 0; $i < $count; ++$i) {
                            $t = $hashObj->hash($t);
                        }
                        $key = substr($t, 0, $dkLen);

                        $this->setKey(substr($key, 0, $dkLen >> 1));
                        $this->setIV(substr($key, $dkLen >> 1));

                        return true;
                    // Determining if php[>=5.5.0]'s hash_pbkdf2() public function avail- and useable
                    case !function_exists('hash_pbkdf2'):
                    case !function_exists('hash_algos'):
                    case !in_array($hash, hash_algos()):
                        if (!class_exists('Crypt_Hash')) {
                            include_once 'Crypt/Hash.php';
                        }
                        $i = 1;
                        $hmac = new Crypt_Hash();
                        $hmac->setHash($hash);
                        $hmac->setKey($password);
                        while (strlen($key) < $dkLen) {
                            $f = $u = $hmac->hash($salt . pack('N', $i++));
                            for ($j = 2; $j <= $count; ++$j) {
                                $u = $hmac->hash($u);
                                $f^= $u;
                            }
                            $key.= $f;
                        }
                        $key = substr($key, 0, $dkLen);
                        break;
                    default:
                        $key = hash_pbkdf2($hash, $password, $salt, $count, $dkLen, true);
                }
        }

        $this->setKey($key);

        return true;
    }

    /**
     * Encrypts a message.
     *
     * $plaintext will be padded with additional bytes such that it's length is a multiple of the block size. Other cipher
     * implementations may or may not pad in the same manner.  Other common approaches to padding and the reasons why it's
     * necessary are discussed in the following
     * URL:
     *
     * {@link http://www.di-mgt.com.au/cryptopad.html http://www.di-mgt.com.au/cryptopad.html}
     *
     * An alternative to padding is to, separately, send the length of the file.  This is what SSH, in fact, does.
     * strlen($plaintext) will still need to be a multiple of the block size, however, arbitrary values can be added to make it that
     * length.
     *
     * @see self::decrypt()
     * @access public
     * @param string $plaintext
     * @return string $ciphertext
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function encrypt($plaintext)
    {
        if ($this->paddable) {
            $plaintext = $this->_pad($plaintext);
        }

        if ($this->engine === CRYPT_ENGINE_OPENSSL) {
            if ($this->changed) {
                $this->_clearBuffers();
                $this->changed = false;
            }
            switch ($this->mode) {
                case CRYPT_MODE_STREAM:
                    return openssl_encrypt($plaintext, $this->cipher_name_openssl, $this->key, $this->openssl_options);
                case CRYPT_MODE_ECB:
                    $result = openssl_encrypt($plaintext, $this->cipher_name_openssl, $this->key, $this->openssl_options);
                    return !defined('OPENSSL_RAW_DATA') ? substr($result, 0, -$this->block_size) : $result;
                case CRYPT_MODE_CBC:
                    $result = openssl_encrypt($plaintext, $this->cipher_name_openssl, $this->key, $this->openssl_options, $this->encryptIV);
                    if (!defined('OPENSSL_RAW_DATA')) {
                        $result = substr($result, 0, -$this->block_size);
                    }
                    if ($this->continuousBuffer) {
                        $this->encryptIV = substr($result, -$this->block_size);
                    }
                    return $result;
                case CRYPT_MODE_CTR:
                    return $this->_openssl_ctr_process($plaintext, $this->encryptIV, $this->enbuffer);
                case CRYPT_MODE_CFB:
                    // cfb loosely routines inspired by openssl's:
                    // {@link http://cvs.openssl.org/fileview?f=openssl/crypto/modes/cfb128.c&v=1.3.2.2.2.1}
                    $ciphertext = '';
                    if ($this->continuousBuffer) {
                        $iv = &$this->encryptIV;
                        $pos = &$this->enbuffer['pos'];
                    } else {
                        $iv = $this->encryptIV;
                        $pos = 0;
                    }
                    $len = strlen($plaintext);
                    $i = 0;
                    if ($pos) {
                        $orig_pos = $pos;
                        $max = $this->block_size - $pos;
                        if ($len >= $max) {
                            $i = $max;
                            $len-= $max;
                            $pos = 0;
                        } else {
                            $i = $len;
                            $pos+= $len;
                            $len = 0;
                        }
                        // ie. $i = min($max, $len), $len-= $i, $pos+= $i, $pos%= $blocksize
                        $ciphertext = substr($iv, $orig_pos) ^ $plaintext;
                        $iv = substr_replace($iv, $ciphertext, $orig_pos, $i);
                        $plaintext = substr($plaintext, $i);
                    }

                    $overflow = $len % $this->block_size;

                    if ($overflow) {
                        $ciphertext.= openssl_encrypt(substr($plaintext, 0, -$overflow) . str_repeat("\0", $this->block_size), $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
                        $iv = $this->_string_pop($ciphertext, $this->block_size);

                        $size = $len - $overflow;
                        $block = $iv ^ substr($plaintext, -$overflow);
                        $iv = substr_replace($iv, $block, 0, $overflow);
                        $ciphertext.= $block;
                        $pos = $overflow;
                    } elseif ($len) {
                        $ciphertext = openssl_encrypt($plaintext, $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
                        $iv = substr($ciphertext, -$this->block_size);
                    }

                    return $ciphertext;
                case CRYPT_MODE_OFB:
                    return $this->_openssl_ofb_process($plaintext, $this->encryptIV, $this->enbuffer);
            }
        }

        if ($this->engine === CRYPT_ENGINE_MCRYPT) {
            if ($this->changed) {
                $this->_setupMcrypt();
                $this->changed = false;
            }
            if ($this->enchanged) {
                @mcrypt_generic_init($this->enmcrypt, $this->key, $this->encryptIV);
                $this->enchanged = false;
            }

            // re: {@link http://phpseclib.sourceforge.net/cfb-demo.phps}
            // using mcrypt's default handing of CFB the above would output two different things.  using phpseclib's
            // rewritten CFB implementation the above outputs the same thing twice.
            if ($this->mode == CRYPT_MODE_CFB && $this->continuousBuffer) {
                $block_size = $this->block_size;
                $iv = &$this->encryptIV;
                $pos = &$this->enbuffer['pos'];
                $len = strlen($plaintext);
                $ciphertext = '';
                $i = 0;
                if ($pos) {
                    $orig_pos = $pos;
                    $max = $block_size - $pos;
                    if ($len >= $max) {
                        $i = $max;
                        $len-= $max;
                        $pos = 0;
                    } else {
                        $i = $len;
                        $pos+= $len;
                        $len = 0;
                    }
                    $ciphertext = substr($iv, $orig_pos) ^ $plaintext;
                    $iv = substr_replace($iv, $ciphertext, $orig_pos, $i);
                    $this->enbuffer['enmcrypt_init'] = true;
                }
                if ($len >= $block_size) {
                    if ($this->enbuffer['enmcrypt_init'] === false || $len > $this->cfb_init_len) {
                        if ($this->enbuffer['enmcrypt_init'] === true) {
                            @mcrypt_generic_init($this->enmcrypt, $this->key, $iv);
                            $this->enbuffer['enmcrypt_init'] = false;
                        }
                        $ciphertext.= @mcrypt_generic($this->enmcrypt, substr($plaintext, $i, $len - $len % $block_size));
                        $iv = substr($ciphertext, -$block_size);
                        $len%= $block_size;
                    } else {
                        while ($len >= $block_size) {
                            $iv = @mcrypt_generic($this->ecb, $iv) ^ substr($plaintext, $i, $block_size);
                            $ciphertext.= $iv;
                            $len-= $block_size;
                            $i+= $block_size;
                        }
                    }
                }

                if ($len) {
                    $iv = @mcrypt_generic($this->ecb, $iv);
                    $block = $iv ^ substr($plaintext, -$len);
                    $iv = substr_replace($iv, $block, 0, $len);
                    $ciphertext.= $block;
                    $pos = $len;
                }

                return $ciphertext;
            }

            $ciphertext = @mcrypt_generic($this->enmcrypt, $plaintext);

            if (!$this->continuousBuffer) {
                @mcrypt_generic_init($this->enmcrypt, $this->key, $this->encryptIV);
            }

            return $ciphertext;
        }

        if ($this->changed) {
            $this->_setup();
            $this->changed = false;
        }
        if ($this->use_inline_crypt) {
            $inline = $this->inline_crypt;
            return $inline('encrypt', $this, $plaintext);
        }

        $buffer = &$this->enbuffer;
        $block_size = $this->block_size;
        $ciphertext = '';
        switch ($this->mode) {
            case CRYPT_MODE_ECB:
                for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                    $ciphertext.= $this->_encryptBlock(substr($plaintext, $i, $block_size));
                }
                break;
            case CRYPT_MODE_CBC:
                $xor = $this->encryptIV;
                for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                    $block = substr($plaintext, $i, $block_size);
                    $block = $this->_encryptBlock($block ^ $xor);
                    $xor = $block;
                    $ciphertext.= $block;
                }
                if ($this->continuousBuffer) {
                    $this->encryptIV = $xor;
                }
                break;
            case CRYPT_MODE_CTR:
                $xor = $this->encryptIV;
                if (strlen($buffer['ciphertext'])) {
                    for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                        $block = substr($plaintext, $i, $block_size);
                        if (strlen($block) > strlen($buffer['ciphertext'])) {
                            $buffer['ciphertext'].= $this->_encryptBlock($xor);
                        }
                        $this->_increment_str($xor);
                        $key = $this->_string_shift($buffer['ciphertext'], $block_size);
                        $ciphertext.= $block ^ $key;
                    }
                } else {
                    for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                        $block = substr($plaintext, $i, $block_size);
                        $key = $this->_encryptBlock($xor);
                        $this->_increment_str($xor);
                        $ciphertext.= $block ^ $key;
                    }
                }
                if ($this->continuousBuffer) {
                    $this->encryptIV = $xor;
                    if ($start = strlen($plaintext) % $block_size) {
                        $buffer['ciphertext'] = substr($key, $start) . $buffer['ciphertext'];
                    }
                }
                break;
            case CRYPT_MODE_CFB:
                // cfb loosely routines inspired by openssl's:
                // {@link http://cvs.openssl.org/fileview?f=openssl/crypto/modes/cfb128.c&v=1.3.2.2.2.1}
                if ($this->continuousBuffer) {
                    $iv = &$this->encryptIV;
                    $pos = &$buffer['pos'];
                } else {
                    $iv = $this->encryptIV;
                    $pos = 0;
                }
                $len = strlen($plaintext);
                $i = 0;
                if ($pos) {
                    $orig_pos = $pos;
                    $max = $block_size - $pos;
                    if ($len >= $max) {
                        $i = $max;
                        $len-= $max;
                        $pos = 0;
                    } else {
                        $i = $len;
                        $pos+= $len;
                        $len = 0;
                    }
                    // ie. $i = min($max, $len), $len-= $i, $pos+= $i, $pos%= $blocksize
                    $ciphertext = substr($iv, $orig_pos) ^ $plaintext;
                    $iv = substr_replace($iv, $ciphertext, $orig_pos, $i);
                }
                while ($len >= $block_size) {
                    $iv = $this->_encryptBlock($iv) ^ substr($plaintext, $i, $block_size);
                    $ciphertext.= $iv;
                    $len-= $block_size;
                    $i+= $block_size;
                }
                if ($len) {
                    $iv = $this->_encryptBlock($iv);
                    $block = $iv ^ substr($plaintext, $i);
                    $iv = substr_replace($iv, $block, 0, $len);
                    $ciphertext.= $block;
                    $pos = $len;
                }
                break;
            case CRYPT_MODE_OFB:
                $xor = $this->encryptIV;
                if (strlen($buffer['xor'])) {
                    for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                        $block = substr($plaintext, $i, $block_size);
                        if (strlen($block) > strlen($buffer['xor'])) {
                            $xor = $this->_encryptBlock($xor);
                            $buffer['xor'].= $xor;
                        }
                        $key = $this->_string_shift($buffer['xor'], $block_size);
                        $ciphertext.= $block ^ $key;
                    }
                } else {
                    for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                        $xor = $this->_encryptBlock($xor);
                        $ciphertext.= substr($plaintext, $i, $block_size) ^ $xor;
                    }
                    $key = $xor;
                }
                if ($this->continuousBuffer) {
                    $this->encryptIV = $xor;
                    if ($start = strlen($plaintext) % $block_size) {
                        $buffer['xor'] = substr($key, $start) . $buffer['xor'];
                    }
                }
                break;
            case CRYPT_MODE_STREAM:
                $ciphertext = $this->_encryptBlock($plaintext);
                break;
        }

        return $ciphertext;
    }

    /**
     * Decrypts a message.
     *
     * If strlen($ciphertext) is not a multiple of the block size, null bytes will be added to the end of the string until
     * it is.
     *
     * @see self::encrypt()
     * @access public
     * @param string $ciphertext
     * @return string $plaintext
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function decrypt($ciphertext)
    {
        if ($this->paddable) {
            // we pad with chr(0) since that's what mcrypt_generic does.  to quote from {@link http://www.php.net/function.mcrypt-generic}:
            // "The data is padded with "\0" to make sure the length of the data is n * blocksize."
            $ciphertext = str_pad($ciphertext, strlen($ciphertext) + ($this->block_size - strlen($ciphertext) % $this->block_size) % $this->block_size, chr(0));
        }

        if ($this->engine === CRYPT_ENGINE_OPENSSL) {
            if ($this->changed) {
                $this->_clearBuffers();
                $this->changed = false;
            }
            switch ($this->mode) {
                case CRYPT_MODE_STREAM:
                    $plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, $this->openssl_options);
                    break;
                case CRYPT_MODE_ECB:
                    if (!defined('OPENSSL_RAW_DATA')) {
                        $ciphertext.= openssl_encrypt('', $this->cipher_name_openssl_ecb, $this->key, true);
                    }
                    $plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, $this->openssl_options);
                    break;
                case CRYPT_MODE_CBC:
                    if (!defined('OPENSSL_RAW_DATA')) {
                        $padding = str_repeat(chr($this->block_size), $this->block_size) ^ substr($ciphertext, -$this->block_size);
                        $ciphertext.= substr(openssl_encrypt($padding, $this->cipher_name_openssl_ecb, $this->key, true), 0, $this->block_size);
                        $offset = 2 * $this->block_size;
                    } else {
                        $offset = $this->block_size;
                    }
                    $plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, $this->openssl_options, $this->decryptIV);
                    if ($this->continuousBuffer) {
                        $this->decryptIV = substr($ciphertext, -$offset, $this->block_size);
                    }
                    break;
                case CRYPT_MODE_CTR:
                    $plaintext = $this->_openssl_ctr_process($ciphertext, $this->decryptIV, $this->debuffer);
                    break;
                case CRYPT_MODE_CFB:
                    // cfb loosely routines inspired by openssl's:
                    // {@link http://cvs.openssl.org/fileview?f=openssl/crypto/modes/cfb128.c&v=1.3.2.2.2.1}
                    $plaintext = '';
                    if ($this->continuousBuffer) {
                        $iv = &$this->decryptIV;
                        $pos = &$this->buffer['pos'];
                    } else {
                        $iv = $this->decryptIV;
                        $pos = 0;
                    }
                    $len = strlen($ciphertext);
                    $i = 0;
                    if ($pos) {
                        $orig_pos = $pos;
                        $max = $this->block_size - $pos;
                        if ($len >= $max) {
                            $i = $max;
                            $len-= $max;
                            $pos = 0;
                        } else {
                            $i = $len;
                            $pos+= $len;
                            $len = 0;
                        }
                        // ie. $i = min($max, $len), $len-= $i, $pos+= $i, $pos%= $this->blocksize
                        $plaintext = substr($iv, $orig_pos) ^ $ciphertext;
                        $iv = substr_replace($iv, substr($ciphertext, 0, $i), $orig_pos, $i);
                        $ciphertext = substr($ciphertext, $i);
                    }
                    $overflow = $len % $this->block_size;
                    if ($overflow) {
                        $plaintext.= openssl_decrypt(substr($ciphertext, 0, -$overflow), $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
                        if ($len - $overflow) {
                            $iv = substr($ciphertext, -$overflow - $this->block_size, -$overflow);
                        }
                        $iv = openssl_encrypt(str_repeat("\0", $this->block_size), $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
                        $plaintext.= $iv ^ substr($ciphertext, -$overflow);
                        $iv = substr_replace($iv, substr($ciphertext, -$overflow), 0, $overflow);
                        $pos = $overflow;
                    } elseif ($len) {
                        $plaintext.= openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
                        $iv = substr($ciphertext, -$this->block_size);
                    }
                    break;
                case CRYPT_MODE_OFB:
                    $plaintext = $this->_openssl_ofb_process($ciphertext, $this->decryptIV, $this->debuffer);
            }

            return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
        }

        if ($this->engine === CRYPT_ENGINE_MCRYPT) {
            $block_size = $this->block_size;
            if ($this->changed) {
                $this->_setupMcrypt();
                $this->changed = false;
            }
            if ($this->dechanged) {
                @mcrypt_generic_init($this->demcrypt, $this->key, $this->decryptIV);
                $this->dechanged = false;
            }

            if ($this->mode == CRYPT_MODE_CFB && $this->continuousBuffer) {
                $iv = &$this->decryptIV;
                $pos = &$this->debuffer['pos'];
                $len = strlen($ciphertext);
                $plaintext = '';
                $i = 0;
                if ($pos) {
                    $orig_pos = $pos;
                    $max = $block_size - $pos;
                    if ($len >= $max) {
                        $i = $max;
                        $len-= $max;
                        $pos = 0;
                    } else {
                        $i = $len;
                        $pos+= $len;
                        $len = 0;
                    }
                    // ie. $i = min($max, $len), $len-= $i, $pos+= $i, $pos%= $blocksize
                    $plaintext = substr($iv, $orig_pos) ^ $ciphertext;
                    $iv = substr_replace($iv, substr($ciphertext, 0, $i), $orig_pos, $i);
                }
                if ($len >= $block_size) {
                    $cb = substr($ciphertext, $i, $len - $len % $block_size);
                    $plaintext.= @mcrypt_generic($this->ecb, $iv . $cb) ^ $cb;
                    $iv = substr($cb, -$block_size);
                    $len%= $block_size;
                }
                if ($len) {
                    $iv = @mcrypt_generic($this->ecb, $iv);
                    $plaintext.= $iv ^ substr($ciphertext, -$len);
                    $iv = substr_replace($iv, substr($ciphertext, -$len), 0, $len);
                    $pos = $len;
                }

                return $plaintext;
            }

            $plaintext = @mdecrypt_generic($this->demcrypt, $ciphertext);

            if (!$this->continuousBuffer) {
                @mcrypt_generic_init($this->demcrypt, $this->key, $this->decryptIV);
            }

            return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
        }

        if ($this->changed) {
            $this->_setup();
            $this->changed = false;
        }
        if ($this->use_inline_crypt) {
            $inline = $this->inline_crypt;
            return $inline('decrypt', $this, $ciphertext);
        }

        $block_size = $this->block_size;

        $buffer = &$this->debuffer;
        $plaintext = '';
        switch ($this->mode) {
            case CRYPT_MODE_ECB:
                for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                    $plaintext.= $this->_decryptBlock(substr($ciphertext, $i, $block_size));
                }
                break;
            case CRYPT_MODE_CBC:
                $xor = $this->decryptIV;
                for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                    $block = substr($ciphertext, $i, $block_size);
                    $plaintext.= $this->_decryptBlock($block) ^ $xor;
                    $xor = $block;
                }
                if ($this->continuousBuffer) {
                    $this->decryptIV = $xor;
                }
                break;
            case CRYPT_MODE_CTR:
                $xor = $this->decryptIV;
                if (strlen($buffer['ciphertext'])) {
                    for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                        $block = substr($ciphertext, $i, $block_size);
                        if (strlen($block) > strlen($buffer['ciphertext'])) {
                            $buffer['ciphertext'].= $this->_encryptBlock($xor);
                            $this->_increment_str($xor);
                        }
                        $key = $this->_string_shift($buffer['ciphertext'], $block_size);
                        $plaintext.= $block ^ $key;
                    }
                } else {
                    for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                        $block = substr($ciphertext, $i, $block_size);
                        $key = $this->_encryptBlock($xor);
                        $this->_increment_str($xor);
                        $plaintext.= $block ^ $key;
                    }
                }
                if ($this->continuousBuffer) {
                    $this->decryptIV = $xor;
                    if ($start = strlen($ciphertext) % $block_size) {
                        $buffer['ciphertext'] = substr($key, $start) . $buffer['ciphertext'];
                    }
                }
                break;
            case CRYPT_MODE_CFB:
                if ($this->continuousBuffer) {
                    $iv = &$this->decryptIV;
                    $pos = &$buffer['pos'];
                } else {
                    $iv = $this->decryptIV;
                    $pos = 0;
                }
                $len = strlen($ciphertext);
                $i = 0;
                if ($pos) {
                    $orig_pos = $pos;
                    $max = $block_size - $pos;
                    if ($len >= $max) {
                        $i = $max;
                        $len-= $max;
                        $pos = 0;
                    } else {
                        $i = $len;
                        $pos+= $len;
                        $len = 0;
                    }
                    // ie. $i = min($max, $len), $len-= $i, $pos+= $i, $pos%= $blocksize
                    $plaintext = substr($iv, $orig_pos) ^ $ciphertext;
                    $iv = substr_replace($iv, substr($ciphertext, 0, $i), $orig_pos, $i);
                }
                while ($len >= $block_size) {
                    $iv = $this->_encryptBlock($iv);
                    $cb = substr($ciphertext, $i, $block_size);
                    $plaintext.= $iv ^ $cb;
                    $iv = $cb;
                    $len-= $block_size;
                    $i+= $block_size;
                }
                if ($len) {
                    $iv = $this->_encryptBlock($iv);
                    $plaintext.= $iv ^ substr($ciphertext, $i);
                    $iv = substr_replace($iv, substr($ciphertext, $i), 0, $len);
                    $pos = $len;
                }
                break;
            case CRYPT_MODE_OFB:
                $xor = $this->decryptIV;
                if (strlen($buffer['xor'])) {
                    for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                        $block = substr($ciphertext, $i, $block_size);
                        if (strlen($block) > strlen($buffer['xor'])) {
                            $xor = $this->_encryptBlock($xor);
                            $buffer['xor'].= $xor;
                        }
                        $key = $this->_string_shift($buffer['xor'], $block_size);
                        $plaintext.= $block ^ $key;
                    }
                } else {
                    for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                        $xor = $this->_encryptBlock($xor);
                        $plaintext.= substr($ciphertext, $i, $block_size) ^ $xor;
                    }
                    $key = $xor;
                }
                if ($this->continuousBuffer) {
                    $this->decryptIV = $xor;
                    if ($start = strlen($ciphertext) % $block_size) {
                        $buffer['xor'] = substr($key, $start) . $buffer['xor'];
                    }
                }
                break;
            case CRYPT_MODE_STREAM:
                $plaintext = $this->_decryptBlock($ciphertext);
                break;
        }
        return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
    }

    /**
     * OpenSSL CTR Processor
     *
     * PHP's OpenSSL bindings do not operate in continuous mode so we'll wrap around it. Since the keystream
     * for CTR is the same for both encrypting and decrypting this public function is re-used by both Crypt_Base::encrypt()
     * and Crypt_Base::decrypt(). Also, OpenSSL doesn't implement CTR for all of it's symmetric ciphers so this
     * public function will emulate CTR with ECB when necessary.
     *
     * @see self::encrypt()
     * @see self::decrypt()
     * @param string $plaintext
     * @param string $encryptIV
     * @param array $buffer
     * @return string
     * @access private
     */
    public function _openssl_ctr_process($plaintext, &$encryptIV, &$buffer)
    {
        $ciphertext = '';

        $block_size = $this->block_size;
        $key = $this->key;

        if ($this->openssl_emulate_ctr) {
            $xor = $encryptIV;
            if (strlen($buffer['ciphertext'])) {
                for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                    $block = substr($plaintext, $i, $block_size);
                    if (strlen($block) > strlen($buffer['ciphertext'])) {
                        $result = openssl_encrypt($xor, $this->cipher_name_openssl_ecb, $key, $this->openssl_options);
                        $result = !defined('OPENSSL_RAW_DATA') ? substr($result, 0, -$this->block_size) : $result;
                        $buffer['ciphertext'].= $result;
                    }
                    $this->_increment_str($xor);
                    $otp = $this->_string_shift($buffer['ciphertext'], $block_size);
                    $ciphertext.= $block ^ $otp;
                }
            } else {
                for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                    $block = substr($plaintext, $i, $block_size);
                    $otp = openssl_encrypt($xor, $this->cipher_name_openssl_ecb, $key, $this->openssl_options);
                    $otp = !defined('OPENSSL_RAW_DATA') ? substr($otp, 0, -$this->block_size) : $otp;
                    $this->_increment_str($xor);
                    $ciphertext.= $block ^ $otp;
                }
            }
            if ($this->continuousBuffer) {
                $encryptIV = $xor;
                if ($start = strlen($plaintext) % $block_size) {
                    $buffer['ciphertext'] = substr($key, $start) . $buffer['ciphertext'];
                }
            }

            return $ciphertext;
        }

        if (strlen($buffer['ciphertext'])) {
            $ciphertext = $plaintext ^ $this->_string_shift($buffer['ciphertext'], strlen($plaintext));
            $plaintext = substr($plaintext, strlen($ciphertext));

            if (!strlen($plaintext)) {
                return $ciphertext;
            }
        }

        $overflow = strlen($plaintext) % $block_size;
        if ($overflow) {
            $plaintext2 = $this->_string_pop($plaintext, $overflow); // ie. trim $plaintext to a multiple of $block_size and put rest of $plaintext in $plaintext2
            $encrypted = openssl_encrypt($plaintext . str_repeat("\0", $block_size), $this->cipher_name_openssl, $key, $this->openssl_options, $encryptIV);
            $temp = $this->_string_pop($encrypted, $block_size);
            $ciphertext.= $encrypted . ($plaintext2 ^ $temp);
            if ($this->continuousBuffer) {
                $buffer['ciphertext'] = substr($temp, $overflow);
                $encryptIV = $temp;
            }
        } elseif (!strlen($buffer['ciphertext'])) {
            $ciphertext.= openssl_encrypt($plaintext . str_repeat("\0", $block_size), $this->cipher_name_openssl, $key, $this->openssl_options, $encryptIV);
            $temp = $this->_string_pop($ciphertext, $block_size);
            if ($this->continuousBuffer) {
                $encryptIV = $temp;
            }
        }
        if ($this->continuousBuffer) {
            if (!defined('OPENSSL_RAW_DATA')) {
                $encryptIV.= openssl_encrypt('', $this->cipher_name_openssl_ecb, $key, $this->openssl_options);
            }
            $encryptIV = openssl_decrypt($encryptIV, $this->cipher_name_openssl_ecb, $key, $this->openssl_options);
            if ($overflow) {
                $this->_increment_str($encryptIV);
            }
        }

        return $ciphertext;
    }

    /**
     * OpenSSL OFB Processor
     *
     * PHP's OpenSSL bindings do not operate in continuous mode so we'll wrap around it. Since the keystream
     * for OFB is the same for both encrypting and decrypting this public function is re-used by both Crypt_Base::encrypt()
     * and Crypt_Base::decrypt().
     *
     * @see self::encrypt()
     * @see self::decrypt()
     * @param string $plaintext
     * @param string $encryptIV
     * @param array $buffer
     * @return string
     * @access private
     */
    public function _openssl_ofb_process($plaintext, &$encryptIV, &$buffer)
    {
        if (strlen($buffer['xor'])) {
            $ciphertext = $plaintext ^ $buffer['xor'];
            $buffer['xor'] = substr($buffer['xor'], strlen($ciphertext));
            $plaintext = substr($plaintext, strlen($ciphertext));
        } else {
            $ciphertext = '';
        }

        $block_size = $this->block_size;

        $len = strlen($plaintext);
        $key = $this->key;
        $overflow = $len % $block_size;

        if (strlen($plaintext)) {
            if ($overflow) {
                $ciphertext.= openssl_encrypt(substr($plaintext, 0, -$overflow) . str_repeat("\0", $block_size), $this->cipher_name_openssl, $key, $this->openssl_options, $encryptIV);
                $xor = $this->_string_pop($ciphertext, $block_size);
                if ($this->continuousBuffer) {
                    $encryptIV = $xor;
                }
                $ciphertext.= $this->_string_shift($xor, $overflow) ^ substr($plaintext, -$overflow);
                if ($this->continuousBuffer) {
                    $buffer['xor'] = $xor;
                }
            } else {
                $ciphertext = openssl_encrypt($plaintext, $this->cipher_name_openssl, $key, $this->openssl_options, $encryptIV);
                if ($this->continuousBuffer) {
                    $encryptIV = substr($ciphertext, -$block_size) ^ substr($plaintext, -$block_size);
                }
            }
        }

        return $ciphertext;
    }

    /**
     * phpseclib <-> OpenSSL Mode Mapper
     *
     * May need to be overwritten by classes extending this one in some cases
     *
     * @return int
     * @access private
     */
    public function _openssl_translate_mode()
    {
        switch ($this->mode) {
            case CRYPT_MODE_ECB:
                return 'ecb';
            case CRYPT_MODE_CBC:
                return 'cbc';
            case CRYPT_MODE_CTR:
                return 'ctr';
            case CRYPT_MODE_CFB:
                return 'cfb';
            case CRYPT_MODE_OFB:
                return 'ofb';
        }
    }

    /**
     * Pad "packets".
     *
     * Block ciphers working by encrypting between their specified [$this->]block_size at a time
     * If you ever need to encrypt or decrypt something that isn't of the proper length, it becomes necessary to
     * pad the input so that it is of the proper length.
     *
     * Padding is enabled by default.  Sometimes, however, it is undesirable to pad strings.  Such is the case in SSH,
     * where "packets" are padded with random bytes before being encrypted.  Unpad these packets and you risk stripping
     * away characters that shouldn't be stripped away. (SSH knows how many bytes are added because the length is
     * transmitted separately)
     *
     * @see self::disablePadding()
     * @access public
     */
    public function enablePadding()
    {
        $this->padding = true;
    }

    /**
     * Do not pad packets.
     *
     * @see self::enablePadding()
     * @access public
     */
    public function disablePadding()
    {
        $this->padding = false;
    }

    /**
     * Treat consecutive "packets" as if they are a continuous buffer.
     *
     * Say you have a 32-byte plaintext $plaintext.  Using the default behavior, the two following code snippets
     * will yield different outputs:
     *
     * <code>
     *    echo $rijndael->encrypt(substr($plaintext,  0, 16));
     *    echo $rijndael->encrypt(substr($plaintext, 16, 16));
     * </code>
     * <code>
     *    echo $rijndael->encrypt($plaintext);
     * </code>
     *
     * The solution is to enable the continuous buffer.  Although this will resolve the above discrepancy, it creates
     * another, as demonstrated with the following:
     *
     * <code>
     *    $rijndael->encrypt(substr($plaintext, 0, 16));
     *    echo $rijndael->decrypt($rijndael->encrypt(substr($plaintext, 16, 16)));
     * </code>
     * <code>
     *    echo $rijndael->decrypt($rijndael->encrypt(substr($plaintext, 16, 16)));
     * </code>
     *
     * With the continuous buffer disabled, these would yield the same output.  With it enabled, they yield different
     * outputs.  The reason is due to the fact that the initialization vector's change after every encryption /
     * decryption round when the continuous buffer is enabled.  When it's disabled, they remain constant.
     *
     * Put another way, when the continuous buffer is enabled, the state of the Crypt_*() object changes after each
     * encryption / decryption round, whereas otherwise, it'd remain constant.  For this reason, it's recommended that
     * continuous buffers not be used.  They do offer better security and are, in fact, sometimes required (SSH uses them),
     * however, they are also less intuitive and more likely to cause you problems.
     *
     * @see self::disableContinuousBuffer()
     * @access public
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function enableContinuousBuffer()
    {
        if ($this->mode == CRYPT_MODE_ECB) {
            return;
        }

        $this->continuousBuffer = true;

        $this->_setEngine();
    }

    /**
     * Treat consecutive packets as if they are a discontinuous buffer.
     *
     * The default behavior.
     *
     * @see self::enableContinuousBuffer()
     * @access public
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function disableContinuousBuffer()
    {
        if ($this->mode == CRYPT_MODE_ECB) {
            return;
        }
        if (!$this->continuousBuffer) {
            return;
        }

        $this->continuousBuffer = false;
        $this->changed = true;

        $this->_setEngine();
    }

    /**
     * Test for engine validity
     *
     * @see self::Crypt_Base()
     * @param int $engine
     * @access public
     * @return bool
     */
    public function isValidEngine($engine)
    {
        switch ($engine) {
            case CRYPT_ENGINE_OPENSSL:
                if ($this->mode == CRYPT_MODE_STREAM && $this->continuousBuffer) {
                    return false;
                }
                $this->openssl_emulate_ctr = false;
                $result = $this->cipher_name_openssl &&
                    extension_loaded('openssl') &&
                    // PHP 5.3.0 - 5.3.2 did not let you set IV's
                    version_compare(PHP_VERSION, '5.3.3', '>=');
                if (!$result) {
                    return false;
                }

                // prior to PHP 5.4.0 OPENSSL_RAW_DATA and OPENSSL_ZERO_PADDING were not defined. instead of expecting an integer
                // $options openssl_encrypt expected a boolean $raw_data.
                if (!defined('OPENSSL_RAW_DATA')) {
                    $this->openssl_options = true;
                } else {
                    $this->openssl_options = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING;
                }

                $methods = openssl_get_cipher_methods();
                if (in_array($this->cipher_name_openssl, $methods)) {
                    return true;
                }
                // not all of openssl's symmetric cipher's support ctr. for those
                // that don't we'll emulate it
                switch ($this->mode) {
                    case CRYPT_MODE_CTR:
                        if (in_array($this->cipher_name_openssl_ecb, $methods)) {
                            $this->openssl_emulate_ctr = true;
                            return true;
                        }
                }
                return false;
            case CRYPT_ENGINE_MCRYPT:
                return $this->cipher_name_mcrypt &&
                    extension_loaded('mcrypt') &&
                    in_array($this->cipher_name_mcrypt, @mcrypt_list_algorithms());
            case CRYPT_ENGINE_INTERNAL:
                return true;
        }

        return false;
    }

    /**
     * Sets the preferred crypt engine
     *
     * Currently, $engine could be:
     *
     * - CRYPT_ENGINE_OPENSSL  [very fast]
     *
     * - CRYPT_ENGINE_MCRYPT   [fast]
     *
     * - CRYPT_ENGINE_INTERNAL [slow]
     *
     * If the preferred crypt engine is not available the fastest available one will be used
     *
     * @see self::Crypt_Base()
     * @param int $engine
     * @access public
     */
    public function setPreferredEngine($engine)
    {
        switch ($engine) {
            //case CRYPT_ENGINE_OPENSSL:
            case CRYPT_ENGINE_MCRYPT:
            case CRYPT_ENGINE_OPENSSL:
                $this->preferredEngine = $engine;
                break;
            default:
                $this->preferredEngine = CRYPT_ENGINE_INTERNAL;
        }

        $this->_setEngine();
    }

    /**
     * Returns the engine currently being utilized
     *
     * @see self::_setEngine()
     * @access public
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * Sets the engine as appropriate
     *
     * @see self::Crypt_Base()
     * @access private
     */
    public function _setEngine()
    {
        $this->engine = null;

        $candidateEngines = array(
            $this->preferredEngine,
            CRYPT_ENGINE_OPENSSL,
            CRYPT_ENGINE_MCRYPT
        );
        foreach ($candidateEngines as $engine) {
            if ($this->isValidEngine($engine)) {
                $this->engine = $engine;
                break;
            }
        }
        if (!$this->engine) {
            $this->engine = CRYPT_ENGINE_INTERNAL;
        }

        if ($this->engine != CRYPT_ENGINE_MCRYPT && $this->enmcrypt) {
            // Closing the current mcrypt resource(s). _mcryptSetup() will, if needed,
            // (re)open them with the module named in $this->cipher_name_mcrypt
            @mcrypt_module_close($this->enmcrypt);
            @mcrypt_module_close($this->demcrypt);
            $this->enmcrypt = null;
            $this->demcrypt = null;

            if ($this->ecb) {
                @mcrypt_module_close($this->ecb);
                $this->ecb = null;
            }
        }

        $this->changed = true;
    }

    /**
     * Encrypts a block
     *
     * @access private
     * @param string $in
     * @return string
     * @internal Must be extended by the child Crypt_* class
     */
    public function _encryptBlock($in)
    {
        user_error((version_compare(PHP_VERSION, '5.0.0', '>=')  ? __METHOD__ : __FUNCTION__)  . '() must extend by class ' . get_class($this), E_USER_ERROR);
    }

    /**
     * Decrypts a block
     *
     * @access private
     * @param string $in
     * @return string
     * @internal Must be extended by the child Crypt_* class
     */
    public function _decryptBlock($in)
    {
        user_error((version_compare(PHP_VERSION, '5.0.0', '>=')  ? __METHOD__ : __FUNCTION__)  . '() must extend by class ' . get_class($this), E_USER_ERROR);
    }

    /**
     * Setup the key (expansion)
     *
     * Only used if $engine == CRYPT_ENGINE_INTERNAL
     *
     * @see self::_setup()
     * @access private
     * @internal Must be extended by the child Crypt_* class
     */
    public function _setupKey()
    {
        user_error((version_compare(PHP_VERSION, '5.0.0', '>=')  ? __METHOD__ : __FUNCTION__)  . '() must extend by class ' . get_class($this), E_USER_ERROR);
    }

    /**
     * Setup the CRYPT_ENGINE_INTERNAL $engine
     *
     * (re)init, if necessary, the internal cipher $engine and flush all $buffers
     * Used (only) if $engine == CRYPT_ENGINE_INTERNAL
     *
     * _setup() will be called each time if $changed === true
     * typically this happens when using one or more of following public methods:
     *
     * - setKey()
     *
     * - setIV()
     *
     * - disableContinuousBuffer()
     *
     * - First run of encrypt() / decrypt() with no init-settings
     *
     * @see self::setKey()
     * @see self::setIV()
     * @see self::disableContinuousBuffer()
     * @access private
     * @internal _setup() is always called before en/decryption.
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function _setup()
    {
        $this->_clearBuffers();
        $this->_setupKey();

        if ($this->use_inline_crypt) {
            $this->_setupInlineCrypt();
        }
    }

    /**
     * Setup the CRYPT_ENGINE_MCRYPT $engine
     *
     * (re)init, if necessary, the (ext)mcrypt resources and flush all $buffers
     * Used (only) if $engine = CRYPT_ENGINE_MCRYPT
     *
     * _setupMcrypt() will be called each time if $changed === true
     * typically this happens when using one or more of following public methods:
     *
     * - setKey()
     *
     * - setIV()
     *
     * - disableContinuousBuffer()
     *
     * - First run of encrypt() / decrypt()
     *
     * @see self::setKey()
     * @see self::setIV()
     * @see self::disableContinuousBuffer()
     * @access private
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function _setupMcrypt()
    {
        $this->_clearBuffers();
        $this->enchanged = $this->dechanged = true;

        if (!isset($this->enmcrypt)) {
            static $mcrypt_modes = array(
                CRYPT_MODE_CTR    => 'ctr',
                CRYPT_MODE_ECB    => MCRYPT_MODE_ECB,
                CRYPT_MODE_CBC    => MCRYPT_MODE_CBC,
                CRYPT_MODE_CFB    => 'ncfb',
                CRYPT_MODE_OFB    => MCRYPT_MODE_NOFB,
                CRYPT_MODE_STREAM => MCRYPT_MODE_STREAM,
            );

            $this->demcrypt = @mcrypt_module_open($this->cipher_name_mcrypt, '', $mcrypt_modes[$this->mode], '');
            $this->enmcrypt = @mcrypt_module_open($this->cipher_name_mcrypt, '', $mcrypt_modes[$this->mode], '');

            // we need the $ecb mcrypt resource (only) in MODE_CFB with enableContinuousBuffer()
            // to workaround mcrypt's broken ncfb implementation in buffered mode
            // see: {@link http://phpseclib.sourceforge.net/cfb-demo.phps}
            if ($this->mode == CRYPT_MODE_CFB) {
                $this->ecb = @mcrypt_module_open($this->cipher_name_mcrypt, '', MCRYPT_MODE_ECB, '');
            }
        } // else should mcrypt_generic_deinit be called?

        if ($this->mode == CRYPT_MODE_CFB) {
            @mcrypt_generic_init($this->ecb, $this->key, str_repeat("\0", $this->block_size));
        }
    }

    /**
     * Pads a string
     *
     * Pads a string using the RSA PKCS padding standards so that its length is a multiple of the blocksize.
     * $this->block_size - (strlen($text) % $this->block_size) bytes are added, each of which is equal to
     * chr($this->block_size - (strlen($text) % $this->block_size)
     *
     * If padding is disabled and $text is not a multiple of the blocksize, the string will be padded regardless
     * and padding will, hence forth, be enabled.
     *
     * @see self::_unpad()
     * @param string $text
     * @access private
     * @return string
     */
    public function _pad($text)
    {
        $length = strlen($text);

        if (!$this->padding) {
            if ($length % $this->block_size == 0) {
                return $text;
            } else {
                user_error("The plaintext's length ($length) is not a multiple of the block size ({$this->block_size})");
                $this->padding = true;
            }
        }

        $pad = $this->block_size - ($length % $this->block_size);

        return str_pad($text, $length + $pad, chr($pad));
    }

    /**
     * Unpads a string.
     *
     * If padding is enabled and the reported padding length is invalid the encryption key will be assumed to be wrong
     * and false will be returned.
     *
     * @see self::_pad()
     * @param string $text
     * @access private
     * @return string
     */
    public function _unpad($text)
    {
        if (!$this->padding) {
            return $text;
        }

        $length = ord($text[strlen($text) - 1]);

        if (!$length || $length > $this->block_size) {
            return false;
        }

        return substr($text, 0, -$length);
    }

    /**
     * Clears internal buffers
     *
     * Clearing/resetting the internal buffers is done everytime
     * after disableContinuousBuffer() or on cipher $engine (re)init
     * ie after setKey() or setIV()
     *
     * @access public
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function _clearBuffers()
    {
        $this->enbuffer = $this->debuffer = array('ciphertext' => '', 'xor' => '', 'pos' => 0, 'enmcrypt_init' => true);

        // mcrypt's handling of invalid's $iv:
        // $this->encryptIV = $this->decryptIV = strlen($this->iv) == $this->block_size ? $this->iv : str_repeat("\0", $this->block_size);
        $this->encryptIV = $this->decryptIV = str_pad(substr($this->iv, 0, $this->block_size), $this->block_size, "\0");

        if (!$this->skip_key_adjustment) {
            $this->key = str_pad(substr($this->key, 0, $this->key_length), $this->key_length, "\0");
        }
    }

    /**
     * String Shift
     *
     * Inspired by array_shift
     *
     * @param string $string
     * @param int $index
     * @access private
     * @return string
     */
    public function _string_shift(&$string, $index = 1)
    {
        $substr = substr($string, 0, $index);
        $string = substr($string, $index);
        return $substr;
    }

    /**
     * String Pop
     *
     * Inspired by array_pop
     *
     * @param string $string
     * @param int $index
     * @access private
     * @return string
     */
    public function _string_pop(&$string, $index = 1)
    {
        $substr = substr($string, -$index);
        $string = substr($string, 0, -$index);
        return $substr;
    }

    /**
     * Increment the current string
     *
     * @see self::decrypt()
     * @see self::encrypt()
     * @param string $var
     * @access private
     */
    public function _increment_str(&$var)
    {
        for ($i = 4; $i <= strlen($var); $i+= 4) {
            $temp = substr($var, -$i, 4);
            switch ($temp) {
                case "\xFF\xFF\xFF\xFF":
                    $var = substr_replace($var, "\x00\x00\x00\x00", -$i, 4);
                    break;
                case "\x7F\xFF\xFF\xFF":
                    $var = substr_replace($var, "\x80\x00\x00\x00", -$i, 4);
                    return;
                default:
                    $temp = unpack('Nnum', $temp);
                    $var = substr_replace($var, pack('N', $temp['num'] + 1), -$i, 4);
                    return;
            }
        }

        $remainder = strlen($var) % 4;

        if ($remainder == 0) {
            return;
        }

        $temp = unpack('Nnum', str_pad(substr($var, 0, $remainder), 4, "\0", STR_PAD_LEFT));
        $temp = substr(pack('N', $temp['num'] + 1), -$remainder);
        $var = substr_replace($var, $temp, 0, $remainder);
    }

    /**
     * Setup the performance-optimized public function for de/encrypt()
     *
     * Stores the created (or existing) callback function-name
     * in $this->inline_crypt
     *
     * Internally for phpseclib developers:
     *
     *     _setupInlineCrypt() would be called only if:
     *
     *     - $engine == CRYPT_ENGINE_INTERNAL and
     *
     *     - $use_inline_crypt === true
     *
     *     - each time on _setup(), after(!) _setupKey()
     *
     *
     *     This ensures that _setupInlineCrypt() has always a
     *     full ready2go initializated internal cipher $engine state
     *     where, for example, the keys allready expanded,
     *     keys/block_size calculated and such.
     *
     *     It is, each time if called, the responsibility of _setupInlineCrypt():
     *
     *     - to set $this->inline_crypt to a valid and fully working callback function
     *       as a (faster) replacement for encrypt() / decrypt()
     *
     *     - NOT to create unlimited callback functions (for memory reasons!)
     *       no matter how often _setupInlineCrypt() would be called. At some
     *       point of amount they must be generic re-useable.
     *
     *     - the code of _setupInlineCrypt() it self,
     *       and the generated callback code,
     *       must be, in following order:
     *       - 100% safe
     *       - 100% compatible to encrypt()/decrypt()
     *       - using only php5+ features/lang-constructs/php-extensions if
     *         compatibility (down to php4) or fallback is provided
     *       - readable/maintainable/understandable/commented and... not-cryptic-styled-code :-)
     *       - >= 10% faster than encrypt()/decrypt() [which is, by the way,
     *         the reason for the existence of _setupInlineCrypt() :-)]
     *       - memory-nice
     *       - short (as good as possible)
     *
     * Note: - _setupInlineCrypt() is using _createInlineCryptFunction() to create the full callback public function code.
     *       - In case of using inline crypting, _setupInlineCrypt() must extend by the child Crypt_* class.
     *       - The following variable names are reserved:
     *         - $_*  (all variable names prefixed with an underscore)
     *         - $self (object reference to it self. Do not use $this, but $self instead)
     *         - $in (the content of $in has to en/decrypt by the generated code)
     *       - The callback public function should not use the 'return' statement, but en/decrypt'ing the content of $in only
     *
     *
     * @see self::_setup()
     * @see self::_createInlineCryptFunction()
     * @see self::encrypt()
     * @see self::decrypt()
     * @access private
     * @internal If a Crypt_* class providing inline crypting it must extend _setupInlineCrypt()
     */
    public function _setupInlineCrypt()
    {
        // If, for any reason, an extending Crypt_Base() Crypt_* class
        // not using inline crypting then it must be ensured that: $this->use_inline_crypt = false
        // ie in the class var declaration of $use_inline_crypt in general for the Crypt_* class,
        // in the constructor at object instance-time
        // or, if it's runtime-specific, at runtime

        $this->use_inline_crypt = false;
    }

    /**
     * Creates the performance-optimized public function for en/decrypt()
     *
     * Internally for phpseclib developers:
     *
     *    _createInlineCryptFunction():
     *
     *    - merge the $cipher_code [setup'ed by _setupInlineCrypt()]
     *      with the current [$this->]mode of operation code
     *
     *    - create the $inline function, which called by encrypt() / decrypt()
     *      as its replacement to speed up the en/decryption operations.
     *
     *    - return the name of the created $inline callback function
     *
     *    - used to speed up en/decryption
     *
     *
     *
     *    The main reason why can speed up things [up to 50%] this way are:
     *
     *    - using variables more effective then regular.
     *      (ie no use of expensive arrays but integers $k_0, $k_1 ...
     *      or even, for example, the pure $key[] values hardcoded)
     *
     *    - avoiding 1000's of public function calls of ie _encryptBlock()
     *      but inlining the crypt operations.
     *      in the mode of operation for() loop.
     *
     *    - full loop unroll the (sometimes key-dependent) rounds
     *      avoiding this way ++$i counters and runtime-if's etc...
     *
     *    The basic code architectur of the generated $inline en/decrypt()
     *    lambda function, in pseudo php, is:
     *
     *    <code>
     *    +----------------------------------------------------------------------------------------------+
     *    | callback $inline = create_function:                                                          |
     *    | lambda_function_0001_crypt_ECB($action, $text)                                               |
     *    | {                                                                                            |
     *    |     INSERT PHP CODE OF:                                                                      |
     *    |     $cipher_code['init_crypt'];                  // general init code.                       |
     *    |                                                  // ie: $sbox'es declarations used for       |
     *    |                                                  //     encrypt and decrypt'ing.             |
     *    |                                                                                              |
     *    |     switch ($action) {                                                                       |
     *    |         case 'encrypt':                                                                      |
     *    |             INSERT PHP CODE OF:                                                              |
     *    |             $cipher_code['init_encrypt'];       // encrypt sepcific init code.               |
     *    |                                                    ie: specified $key or $box                |
     *    |                                                        declarations for encrypt'ing.         |
     *    |                                                                                              |
     *    |             foreach ($ciphertext) {                                                          |
     *    |                 $in = $block_size of $ciphertext;                                            |
     *    |                                                                                              |
     *    |                 INSERT PHP CODE OF:                                                          |
     *    |                 $cipher_code['encrypt_block'];  // encrypt's (string) $in, which is always:  |
     *    |                                                 // strlen($in) == $this->block_size          |
     *    |                                                 // here comes the cipher algorithm in action |
     *    |                                                 // for encryption.                           |
     *    |                                                 // $cipher_code['encrypt_block'] has to      |
     *    |                                                 // encrypt the content of the $in variable   |
     *    |                                                                                              |
     *    |                 $plaintext .= $in;                                                           |
     *    |             }                                                                                |
     *    |             return $plaintext;                                                               |
     *    |                                                                                              |
     *    |         case 'decrypt':                                                                      |
     *    |             INSERT PHP CODE OF:                                                              |
     *    |             $cipher_code['init_decrypt'];       // decrypt sepcific init code                |
     *    |                                                    ie: specified $key or $box                |
     *    |                                                        declarations for decrypt'ing.         |
     *    |             foreach ($plaintext) {                                                           |
     *    |                 $in = $block_size of $plaintext;                                             |
     *    |                                                                                              |
     *    |                 INSERT PHP CODE OF:                                                          |
     *    |                 $cipher_code['decrypt_block'];  // decrypt's (string) $in, which is always   |
     *    |                                                 // strlen($in) == $this->block_size          |
     *    |                                                 // here comes the cipher algorithm in action |
     *    |                                                 // for decryption.                           |
     *    |                                                 // $cipher_code['decrypt_block'] has to      |
     *    |                                                 // decrypt the content of the $in variable   |
     *    |                 $ciphertext .= $in;                                                          |
     *    |             }                                                                                |
     *    |             return $ciphertext;                                                              |
     *    |     }                                                                                        |
     *    | }                                                                                            |
     *    +----------------------------------------------------------------------------------------------+
     *    </code>
     *
     *    See also the Crypt_*::_setupInlineCrypt()'s for
     *    productive inline $cipher_code's how they works.
     *
     *    Structure of:
     *    <code>
     *    $cipher_code = array(
     *        'init_crypt'    => (string) '', // optional
     *        'init_encrypt'  => (string) '', // optional
     *        'init_decrypt'  => (string) '', // optional
     *        'encrypt_block' => (string) '', // required
     *        'decrypt_block' => (string) ''  // required
     *    );
     *    </code>
     *
     * @see self::_setupInlineCrypt()
     * @see self::encrypt()
     * @see self::decrypt()
     * @param array $cipher_code
     * @access private
     * @return string (the name of the created callback function)
     */
    public function _createInlineCryptFunction($cipher_code)
    {
        $block_size = $this->block_size;

        // optional
        $init_crypt    = isset($cipher_code['init_crypt'])    ? $cipher_code['init_crypt']    : '';
        $init_encrypt  = isset($cipher_code['init_encrypt'])  ? $cipher_code['init_encrypt']  : '';
        $init_decrypt  = isset($cipher_code['init_decrypt'])  ? $cipher_code['init_decrypt']  : '';
        // required
        $encrypt_block = $cipher_code['encrypt_block'];
        $decrypt_block = $cipher_code['decrypt_block'];

        // Generating mode of operation inline code,
        // merged with the $cipher_code algorithm
        // for encrypt- and decryption.
        switch ($this->mode) {
            case CRYPT_MODE_ECB:
                $encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);

                    for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                        $in = substr($_text, $_i, '.$block_size.');
                        '.$encrypt_block.'
                        $_ciphertext.= $in;
                    }

                    return $_ciphertext;
                    ';

                $decrypt = $init_decrypt . '
                    $_plaintext = "";
                    $_text = str_pad($_text, strlen($_text) + ('.$block_size.' - strlen($_text) % '.$block_size.') % '.$block_size.', chr(0));
                    $_ciphertext_len = strlen($_text);

                    for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                        $in = substr($_text, $_i, '.$block_size.');
                        '.$decrypt_block.'
                        $_plaintext.= $in;
                    }

                    return $self->_unpad($_plaintext);
                    ';
                break;
            case CRYPT_MODE_CTR:
                $encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);
                    $_xor = $self->encryptIV;
                    $_buffer = &$self->enbuffer;
                    if (strlen($_buffer["ciphertext"])) {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["ciphertext"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                $self->_increment_str($_xor);
                                $_buffer["ciphertext"].= $in;
                            }
                            $_key = $self->_string_shift($_buffer["ciphertext"], '.$block_size.');
                            $_ciphertext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            $in = $_xor;
                            '.$encrypt_block.'
                            $self->_increment_str($_xor);
                            $_key = $in;
                            $_ciphertext.= $_block ^ $_key;
                        }
                    }
                    if ($self->continuousBuffer) {
                        $self->encryptIV = $_xor;
                        if ($_start = $_plaintext_len % '.$block_size.') {
                            $_buffer["ciphertext"] = substr($_key, $_start) . $_buffer["ciphertext"];
                        }
                    }

                    return $_ciphertext;
                ';

                $decrypt = $init_encrypt . '
                    $_plaintext = "";
                    $_ciphertext_len = strlen($_text);
                    $_xor = $self->decryptIV;
                    $_buffer = &$self->debuffer;

                    if (strlen($_buffer["ciphertext"])) {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["ciphertext"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                $self->_increment_str($_xor);
                                $_buffer["ciphertext"].= $in;
                            }
                            $_key = $self->_string_shift($_buffer["ciphertext"], '.$block_size.');
                            $_plaintext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            $in = $_xor;
                            '.$encrypt_block.'
                            $self->_increment_str($_xor);
                            $_key = $in;
                            $_plaintext.= $_block ^ $_key;
                        }
                    }
                    if ($self->continuousBuffer) {
                        $self->decryptIV = $_xor;
                        if ($_start = $_ciphertext_len % '.$block_size.') {
                            $_buffer["ciphertext"] = substr($_key, $_start) . $_buffer["ciphertext"];
                        }
                    }

                    return $_plaintext;
                    ';
                break;
            case CRYPT_MODE_CFB:
                $encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_buffer = &$self->enbuffer;

                    if ($self->continuousBuffer) {
                        $_iv = &$self->encryptIV;
                        $_pos = &$_buffer["pos"];
                    } else {
                        $_iv = $self->encryptIV;
                        $_pos = 0;
                    }
                    $_len = strlen($_text);
                    $_i = 0;
                    if ($_pos) {
                        $_orig_pos = $_pos;
                        $_max = '.$block_size.' - $_pos;
                        if ($_len >= $_max) {
                            $_i = $_max;
                            $_len-= $_max;
                            $_pos = 0;
                        } else {
                            $_i = $_len;
                            $_pos+= $_len;
                            $_len = 0;
                        }
                        $_ciphertext = substr($_iv, $_orig_pos) ^ $_text;
                        $_iv = substr_replace($_iv, $_ciphertext, $_orig_pos, $_i);
                    }
                    while ($_len >= '.$block_size.') {
                        $in = $_iv;
                        '.$encrypt_block.';
                        $_iv = $in ^ substr($_text, $_i, '.$block_size.');
                        $_ciphertext.= $_iv;
                        $_len-= '.$block_size.';
                        $_i+= '.$block_size.';
                    }
                    if ($_len) {
                        $in = $_iv;
                        '.$encrypt_block.'
                        $_iv = $in;
                        $_block = $_iv ^ substr($_text, $_i);
                        $_iv = substr_replace($_iv, $_block, 0, $_len);
                        $_ciphertext.= $_block;
                        $_pos = $_len;
                    }
                    return $_ciphertext;
                ';

                $decrypt = $init_encrypt . '
                    $_plaintext = "";
                    $_buffer = &$self->debuffer;

                    if ($self->continuousBuffer) {
                        $_iv = &$self->decryptIV;
                        $_pos = &$_buffer["pos"];
                    } else {
                        $_iv = $self->decryptIV;
                        $_pos = 0;
                    }
                    $_len = strlen($_text);
                    $_i = 0;
                    if ($_pos) {
                        $_orig_pos = $_pos;
                        $_max = '.$block_size.' - $_pos;
                        if ($_len >= $_max) {
                            $_i = $_max;
                            $_len-= $_max;
                            $_pos = 0;
                        } else {
                            $_i = $_len;
                            $_pos+= $_len;
                            $_len = 0;
                        }
                        $_plaintext = substr($_iv, $_orig_pos) ^ $_text;
                        $_iv = substr_replace($_iv, substr($_text, 0, $_i), $_orig_pos, $_i);
                    }
                    while ($_len >= '.$block_size.') {
                        $in = $_iv;
                        '.$encrypt_block.'
                        $_iv = $in;
                        $cb = substr($_text, $_i, '.$block_size.');
                        $_plaintext.= $_iv ^ $cb;
                        $_iv = $cb;
                        $_len-= '.$block_size.';
                        $_i+= '.$block_size.';
                    }
                    if ($_len) {
                        $in = $_iv;
                        '.$encrypt_block.'
                        $_iv = $in;
                        $_plaintext.= $_iv ^ substr($_text, $_i);
                        $_iv = substr_replace($_iv, substr($_text, $_i), 0, $_len);
                        $_pos = $_len;
                    }

                    return $_plaintext;
                    ';
                break;
            case CRYPT_MODE_OFB:
                $encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);
                    $_xor = $self->encryptIV;
                    $_buffer = &$self->enbuffer;

                    if (strlen($_buffer["xor"])) {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["xor"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                $_xor = $in;
                                $_buffer["xor"].= $_xor;
                            }
                            $_key = $self->_string_shift($_buffer["xor"], '.$block_size.');
                            $_ciphertext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $in = $_xor;
                            '.$encrypt_block.'
                            $_xor = $in;
                            $_ciphertext.= substr($_text, $_i, '.$block_size.') ^ $_xor;
                        }
                        $_key = $_xor;
                    }
                    if ($self->continuousBuffer) {
                        $self->encryptIV = $_xor;
                        if ($_start = $_plaintext_len % '.$block_size.') {
                             $_buffer["xor"] = substr($_key, $_start) . $_buffer["xor"];
                        }
                    }
                    return $_ciphertext;
                    ';

                $decrypt = $init_encrypt . '
                    $_plaintext = "";
                    $_ciphertext_len = strlen($_text);
                    $_xor = $self->decryptIV;
                    $_buffer = &$self->debuffer;

                    if (strlen($_buffer["xor"])) {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["xor"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                $_xor = $in;
                                $_buffer["xor"].= $_xor;
                            }
                            $_key = $self->_string_shift($_buffer["xor"], '.$block_size.');
                            $_plaintext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $in = $_xor;
                            '.$encrypt_block.'
                            $_xor = $in;
                            $_plaintext.= substr($_text, $_i, '.$block_size.') ^ $_xor;
                        }
                        $_key = $_xor;
                    }
                    if ($self->continuousBuffer) {
                        $self->decryptIV = $_xor;
                        if ($_start = $_ciphertext_len % '.$block_size.') {
                             $_buffer["xor"] = substr($_key, $_start) . $_buffer["xor"];
                        }
                    }
                    return $_plaintext;
                    ';
                break;
            case CRYPT_MODE_STREAM:
                $encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    '.$encrypt_block.'
                    return $_ciphertext;
                    ';
                $decrypt = $init_decrypt . '
                    $_plaintext = "";
                    '.$decrypt_block.'
                    return $_plaintext;
                    ';
                break;
            // case CRYPT_MODE_CBC:
            default:
                $encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);

                    $in = $self->encryptIV;

                    for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                        $in = substr($_text, $_i, '.$block_size.') ^ $in;
                        '.$encrypt_block.'
                        $_ciphertext.= $in;
                    }

                    if ($self->continuousBuffer) {
                        $self->encryptIV = $in;
                    }

                    return $_ciphertext;
                    ';

                $decrypt = $init_decrypt . '
                    $_plaintext = "";
                    $_text = str_pad($_text, strlen($_text) + ('.$block_size.' - strlen($_text) % '.$block_size.') % '.$block_size.', chr(0));
                    $_ciphertext_len = strlen($_text);

                    $_iv = $self->decryptIV;

                    for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                        $in = $_block = substr($_text, $_i, '.$block_size.');
                        '.$decrypt_block.'
                        $_plaintext.= $in ^ $_iv;
                        $_iv = $_block;
                    }

                    if ($self->continuousBuffer) {
                        $self->decryptIV = $_iv;
                    }

                    return $self->_unpad($_plaintext);
                    ';
                break;
        }

        // Create the $inline function and return its name as string. Ready to run!
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            eval('$func = function ($_action, &$self, $_text) { ' . $init_crypt . 'if ($_action == "encrypt") { ' . $encrypt . ' } else { ' . $decrypt . ' } };');
            return $func;
        }

        return create_function('$_action, &$self, $_text', $init_crypt . 'if ($_action == "encrypt") { ' . $encrypt . ' } else { ' . $decrypt . ' }');
    }

    /**
     * Holds the lambda_functions table (classwide)
     *
     * Each name of the lambda function, created from
     * _setupInlineCrypt() && _createInlineCryptFunction()
     * is stored, classwide (!), here for reusing.
     *
     * The string-based index of $public function is a classwide
     * unique value representing, at least, the $mode of
     * operation (or more... depends of the optimizing level)
     * for which $mode the lambda public function was created.
     *
     * @access private
     * @return array &$functions
     */
    public function &_getLambdaFunctions()
    {
        static $functions = array();
        return $functions;
    }

    /**
     * Generates a digest from $bytes
     *
     * @see self::_setupInlineCrypt()
     * @access private
     * @param $bytes
     * @return string
     */
    public function _hashInlineCryptFunction($bytes)
    {
        if (!defined('CRYPT_BASE_WHIRLPOOL_AVAILABLE')) {
            define('CRYPT_BASE_WHIRLPOOL_AVAILABLE', (bool)(extension_loaded('hash') && in_array('whirlpool', hash_algos())));
        }

        $result = '';
        $hash = $bytes;

        switch (true) {
            case CRYPT_BASE_WHIRLPOOL_AVAILABLE:
                foreach (str_split($bytes, 64) as $t) {
                    $hash = hash('whirlpool', $hash, true);
                    $result .= $t ^ $hash;
                }
                return $result . hash('whirlpool', $hash, true);
            default:
                $len = strlen($bytes);
                for ($i = 0; $i < $len; $i+=20) {
                    $t = substr($bytes, $i, 20);
                    $hash = pack('H*', sha1($hash));
                    $result .= $t ^ $hash;
                }
                return $result . pack('H*', sha1($hash));
        }
    }

    /**
     * Convert float to int
     *
     * On 32-bit Linux installs running PHP < 5.3 converting floats to ints doesn't always work
     *
     * @access private
     * @param string $x
     * @return int
     */
    public function safe_intval($x)
    {
        switch (true) {
            case is_int($x):
                // PHP 5.3, per http://php.net/releases/5_3_0.php, introduced "more consistent float rounding"
            case version_compare(PHP_VERSION, '5.3.0') >= 0 && (php_uname('m') & "\xDF\xDF\xDF") != 'ARM':
                // PHP_OS & "\xDF\xDF\xDF" == strtoupper(substr(PHP_OS, 0, 3)), but a lot faster
            case (PHP_OS & "\xDF\xDF\xDF") === 'WIN':
                return $x;
        }
        return (fmod($x, 0x80000000) & 0x7FFFFFFF) |
            ((fmod(floor($x / 0x80000000), 2) & 1) << 31);
    }

    /**
     * eval()'able string for in-line float to int
     *
     * @access private
     * @return string
     */
    public function safe_intval_inline()
    {
        // on 32-bit linux systems with PHP < 5.3 float to integer conversion is bad
        switch (true) {
            case defined('PHP_INT_SIZE') && PHP_INT_SIZE == 8:
            case version_compare(PHP_VERSION, '5.3.0') >= 0 && (php_uname('m') & "\xDF\xDF\xDF") != 'ARM':
            case (PHP_OS & "\xDF\xDF\xDF") === 'WIN':
                return '%s';
                break;
            default:
                $safeint = '(is_int($temp = %s) ? $temp : (fmod($temp, 0x80000000) & 0x7FFFFFFF) | ';
                return $safeint . '((fmod(floor($temp / 0x80000000), 2) & 1) << 31))';
        }
    }
}

/**
 * Include Crypt_DES
 */
class Crypt_DES extends Crypt_Base
{
    /**
     * Block Length of the cipher
     *
     * @see Crypt_Base::block_size
     * @var int
     * @access private
     */
    public $block_size = 8;

    /**
     * Key Length (in bytes)
     *
     * @see Crypt_Base::setKeyLength()
     * @var int
     * @access private
     */
    public $key_length = 8;

    /**
     * The namespace used by the cipher for its constants.
     *
     * @see Crypt_Base::const_namespace
     * @var string
     * @access private
     */
    public $const_namespace = 'DES';

    /**
     * The mcrypt specific name of the cipher
     *
     * @see Crypt_Base::cipher_name_mcrypt
     * @var string
     * @access private
     */
    public $cipher_name_mcrypt = 'des';

    /**
     * The OpenSSL names of the cipher / modes
     *
     * @see Crypt_Base::openssl_mode_names
     * @var array
     * @access private
     */
    public $openssl_mode_names = array(
        CRYPT_MODE_ECB => 'des-ecb',
        CRYPT_MODE_CBC => 'des-cbc',
        CRYPT_MODE_CFB => 'des-cfb',
        CRYPT_MODE_OFB => 'des-ofb'
        // CRYPT_MODE_CTR is undefined for DES
    );

    /**
     * Optimizing value while CFB-encrypting
     *
     * @see Crypt_Base::cfb_init_len
     * @var int
     * @access private
     */
    public $cfb_init_len = 500;

    /**
     * Switch for DES/3DES encryption
     *
     * Used only if $engine == CRYPT_DES_MODE_INTERNAL
     *
     * @see self::_setupKey()
     * @see self::_processBlock()
     * @var int
     * @access private
     */
    public $des_rounds = 1;

    /**
     * max possible size of $key
     *
     * @see self::setKey()
     * @var string
     * @access private
     */
    public $key_length_max = 8;

    /**
     * The Key Schedule
     *
     * @see self::_setupKey()
     * @var array
     * @access private
     */
    public $keys;

    /**
     * Shuffle table.
     *
     * For each byte value index, the entry holds an 8-byte string
     * with each byte containing all bits in the same state as the
     * corresponding bit in the index value.
     *
     * @see self::_processBlock()
     * @see self::_setupKey()
     * @var array
     * @access private
     */
    public $shuffle = array(
        "\x00\x00\x00\x00\x00\x00\x00\x00", "\x00\x00\x00\x00\x00\x00\x00\xFF",
        "\x00\x00\x00\x00\x00\x00\xFF\x00", "\x00\x00\x00\x00\x00\x00\xFF\xFF",
        "\x00\x00\x00\x00\x00\xFF\x00\x00", "\x00\x00\x00\x00\x00\xFF\x00\xFF",
        "\x00\x00\x00\x00\x00\xFF\xFF\x00", "\x00\x00\x00\x00\x00\xFF\xFF\xFF",
        "\x00\x00\x00\x00\xFF\x00\x00\x00", "\x00\x00\x00\x00\xFF\x00\x00\xFF",
        "\x00\x00\x00\x00\xFF\x00\xFF\x00", "\x00\x00\x00\x00\xFF\x00\xFF\xFF",
        "\x00\x00\x00\x00\xFF\xFF\x00\x00", "\x00\x00\x00\x00\xFF\xFF\x00\xFF",
        "\x00\x00\x00\x00\xFF\xFF\xFF\x00", "\x00\x00\x00\x00\xFF\xFF\xFF\xFF",
        "\x00\x00\x00\xFF\x00\x00\x00\x00", "\x00\x00\x00\xFF\x00\x00\x00\xFF",
        "\x00\x00\x00\xFF\x00\x00\xFF\x00", "\x00\x00\x00\xFF\x00\x00\xFF\xFF",
        "\x00\x00\x00\xFF\x00\xFF\x00\x00", "\x00\x00\x00\xFF\x00\xFF\x00\xFF",
        "\x00\x00\x00\xFF\x00\xFF\xFF\x00", "\x00\x00\x00\xFF\x00\xFF\xFF\xFF",
        "\x00\x00\x00\xFF\xFF\x00\x00\x00", "\x00\x00\x00\xFF\xFF\x00\x00\xFF",
        "\x00\x00\x00\xFF\xFF\x00\xFF\x00", "\x00\x00\x00\xFF\xFF\x00\xFF\xFF",
        "\x00\x00\x00\xFF\xFF\xFF\x00\x00", "\x00\x00\x00\xFF\xFF\xFF\x00\xFF",
        "\x00\x00\x00\xFF\xFF\xFF\xFF\x00", "\x00\x00\x00\xFF\xFF\xFF\xFF\xFF",
        "\x00\x00\xFF\x00\x00\x00\x00\x00", "\x00\x00\xFF\x00\x00\x00\x00\xFF",
        "\x00\x00\xFF\x00\x00\x00\xFF\x00", "\x00\x00\xFF\x00\x00\x00\xFF\xFF",
        "\x00\x00\xFF\x00\x00\xFF\x00\x00", "\x00\x00\xFF\x00\x00\xFF\x00\xFF",
        "\x00\x00\xFF\x00\x00\xFF\xFF\x00", "\x00\x00\xFF\x00\x00\xFF\xFF\xFF",
        "\x00\x00\xFF\x00\xFF\x00\x00\x00", "\x00\x00\xFF\x00\xFF\x00\x00\xFF",
        "\x00\x00\xFF\x00\xFF\x00\xFF\x00", "\x00\x00\xFF\x00\xFF\x00\xFF\xFF",
        "\x00\x00\xFF\x00\xFF\xFF\x00\x00", "\x00\x00\xFF\x00\xFF\xFF\x00\xFF",
        "\x00\x00\xFF\x00\xFF\xFF\xFF\x00", "\x00\x00\xFF\x00\xFF\xFF\xFF\xFF",
        "\x00\x00\xFF\xFF\x00\x00\x00\x00", "\x00\x00\xFF\xFF\x00\x00\x00\xFF",
        "\x00\x00\xFF\xFF\x00\x00\xFF\x00", "\x00\x00\xFF\xFF\x00\x00\xFF\xFF",
        "\x00\x00\xFF\xFF\x00\xFF\x00\x00", "\x00\x00\xFF\xFF\x00\xFF\x00\xFF",
        "\x00\x00\xFF\xFF\x00\xFF\xFF\x00", "\x00\x00\xFF\xFF\x00\xFF\xFF\xFF",
        "\x00\x00\xFF\xFF\xFF\x00\x00\x00", "\x00\x00\xFF\xFF\xFF\x00\x00\xFF",
        "\x00\x00\xFF\xFF\xFF\x00\xFF\x00", "\x00\x00\xFF\xFF\xFF\x00\xFF\xFF",
        "\x00\x00\xFF\xFF\xFF\xFF\x00\x00", "\x00\x00\xFF\xFF\xFF\xFF\x00\xFF",
        "\x00\x00\xFF\xFF\xFF\xFF\xFF\x00", "\x00\x00\xFF\xFF\xFF\xFF\xFF\xFF",
        "\x00\xFF\x00\x00\x00\x00\x00\x00", "\x00\xFF\x00\x00\x00\x00\x00\xFF",
        "\x00\xFF\x00\x00\x00\x00\xFF\x00", "\x00\xFF\x00\x00\x00\x00\xFF\xFF",
        "\x00\xFF\x00\x00\x00\xFF\x00\x00", "\x00\xFF\x00\x00\x00\xFF\x00\xFF",
        "\x00\xFF\x00\x00\x00\xFF\xFF\x00", "\x00\xFF\x00\x00\x00\xFF\xFF\xFF",
        "\x00\xFF\x00\x00\xFF\x00\x00\x00", "\x00\xFF\x00\x00\xFF\x00\x00\xFF",
        "\x00\xFF\x00\x00\xFF\x00\xFF\x00", "\x00\xFF\x00\x00\xFF\x00\xFF\xFF",
        "\x00\xFF\x00\x00\xFF\xFF\x00\x00", "\x00\xFF\x00\x00\xFF\xFF\x00\xFF",
        "\x00\xFF\x00\x00\xFF\xFF\xFF\x00", "\x00\xFF\x00\x00\xFF\xFF\xFF\xFF",
        "\x00\xFF\x00\xFF\x00\x00\x00\x00", "\x00\xFF\x00\xFF\x00\x00\x00\xFF",
        "\x00\xFF\x00\xFF\x00\x00\xFF\x00", "\x00\xFF\x00\xFF\x00\x00\xFF\xFF",
        "\x00\xFF\x00\xFF\x00\xFF\x00\x00", "\x00\xFF\x00\xFF\x00\xFF\x00\xFF",
        "\x00\xFF\x00\xFF\x00\xFF\xFF\x00", "\x00\xFF\x00\xFF\x00\xFF\xFF\xFF",
        "\x00\xFF\x00\xFF\xFF\x00\x00\x00", "\x00\xFF\x00\xFF\xFF\x00\x00\xFF",
        "\x00\xFF\x00\xFF\xFF\x00\xFF\x00", "\x00\xFF\x00\xFF\xFF\x00\xFF\xFF",
        "\x00\xFF\x00\xFF\xFF\xFF\x00\x00", "\x00\xFF\x00\xFF\xFF\xFF\x00\xFF",
        "\x00\xFF\x00\xFF\xFF\xFF\xFF\x00", "\x00\xFF\x00\xFF\xFF\xFF\xFF\xFF",
        "\x00\xFF\xFF\x00\x00\x00\x00\x00", "\x00\xFF\xFF\x00\x00\x00\x00\xFF",
        "\x00\xFF\xFF\x00\x00\x00\xFF\x00", "\x00\xFF\xFF\x00\x00\x00\xFF\xFF",
        "\x00\xFF\xFF\x00\x00\xFF\x00\x00", "\x00\xFF\xFF\x00\x00\xFF\x00\xFF",
        "\x00\xFF\xFF\x00\x00\xFF\xFF\x00", "\x00\xFF\xFF\x00\x00\xFF\xFF\xFF",
        "\x00\xFF\xFF\x00\xFF\x00\x00\x00", "\x00\xFF\xFF\x00\xFF\x00\x00\xFF",
        "\x00\xFF\xFF\x00\xFF\x00\xFF\x00", "\x00\xFF\xFF\x00\xFF\x00\xFF\xFF",
        "\x00\xFF\xFF\x00\xFF\xFF\x00\x00", "\x00\xFF\xFF\x00\xFF\xFF\x00\xFF",
        "\x00\xFF\xFF\x00\xFF\xFF\xFF\x00", "\x00\xFF\xFF\x00\xFF\xFF\xFF\xFF",
        "\x00\xFF\xFF\xFF\x00\x00\x00\x00", "\x00\xFF\xFF\xFF\x00\x00\x00\xFF",
        "\x00\xFF\xFF\xFF\x00\x00\xFF\x00", "\x00\xFF\xFF\xFF\x00\x00\xFF\xFF",
        "\x00\xFF\xFF\xFF\x00\xFF\x00\x00", "\x00\xFF\xFF\xFF\x00\xFF\x00\xFF",
        "\x00\xFF\xFF\xFF\x00\xFF\xFF\x00", "\x00\xFF\xFF\xFF\x00\xFF\xFF\xFF",
        "\x00\xFF\xFF\xFF\xFF\x00\x00\x00", "\x00\xFF\xFF\xFF\xFF\x00\x00\xFF",
        "\x00\xFF\xFF\xFF\xFF\x00\xFF\x00", "\x00\xFF\xFF\xFF\xFF\x00\xFF\xFF",
        "\x00\xFF\xFF\xFF\xFF\xFF\x00\x00", "\x00\xFF\xFF\xFF\xFF\xFF\x00\xFF",
        "\x00\xFF\xFF\xFF\xFF\xFF\xFF\x00", "\x00\xFF\xFF\xFF\xFF\xFF\xFF\xFF",
        "\xFF\x00\x00\x00\x00\x00\x00\x00", "\xFF\x00\x00\x00\x00\x00\x00\xFF",
        "\xFF\x00\x00\x00\x00\x00\xFF\x00", "\xFF\x00\x00\x00\x00\x00\xFF\xFF",
        "\xFF\x00\x00\x00\x00\xFF\x00\x00", "\xFF\x00\x00\x00\x00\xFF\x00\xFF",
        "\xFF\x00\x00\x00\x00\xFF\xFF\x00", "\xFF\x00\x00\x00\x00\xFF\xFF\xFF",
        "\xFF\x00\x00\x00\xFF\x00\x00\x00", "\xFF\x00\x00\x00\xFF\x00\x00\xFF",
        "\xFF\x00\x00\x00\xFF\x00\xFF\x00", "\xFF\x00\x00\x00\xFF\x00\xFF\xFF",
        "\xFF\x00\x00\x00\xFF\xFF\x00\x00", "\xFF\x00\x00\x00\xFF\xFF\x00\xFF",
        "\xFF\x00\x00\x00\xFF\xFF\xFF\x00", "\xFF\x00\x00\x00\xFF\xFF\xFF\xFF",
        "\xFF\x00\x00\xFF\x00\x00\x00\x00", "\xFF\x00\x00\xFF\x00\x00\x00\xFF",
        "\xFF\x00\x00\xFF\x00\x00\xFF\x00", "\xFF\x00\x00\xFF\x00\x00\xFF\xFF",
        "\xFF\x00\x00\xFF\x00\xFF\x00\x00", "\xFF\x00\x00\xFF\x00\xFF\x00\xFF",
        "\xFF\x00\x00\xFF\x00\xFF\xFF\x00", "\xFF\x00\x00\xFF\x00\xFF\xFF\xFF",
        "\xFF\x00\x00\xFF\xFF\x00\x00\x00", "\xFF\x00\x00\xFF\xFF\x00\x00\xFF",
        "\xFF\x00\x00\xFF\xFF\x00\xFF\x00", "\xFF\x00\x00\xFF\xFF\x00\xFF\xFF",
        "\xFF\x00\x00\xFF\xFF\xFF\x00\x00", "\xFF\x00\x00\xFF\xFF\xFF\x00\xFF",
        "\xFF\x00\x00\xFF\xFF\xFF\xFF\x00", "\xFF\x00\x00\xFF\xFF\xFF\xFF\xFF",
        "\xFF\x00\xFF\x00\x00\x00\x00\x00", "\xFF\x00\xFF\x00\x00\x00\x00\xFF",
        "\xFF\x00\xFF\x00\x00\x00\xFF\x00", "\xFF\x00\xFF\x00\x00\x00\xFF\xFF",
        "\xFF\x00\xFF\x00\x00\xFF\x00\x00", "\xFF\x00\xFF\x00\x00\xFF\x00\xFF",
        "\xFF\x00\xFF\x00\x00\xFF\xFF\x00", "\xFF\x00\xFF\x00\x00\xFF\xFF\xFF",
        "\xFF\x00\xFF\x00\xFF\x00\x00\x00", "\xFF\x00\xFF\x00\xFF\x00\x00\xFF",
        "\xFF\x00\xFF\x00\xFF\x00\xFF\x00", "\xFF\x00\xFF\x00\xFF\x00\xFF\xFF",
        "\xFF\x00\xFF\x00\xFF\xFF\x00\x00", "\xFF\x00\xFF\x00\xFF\xFF\x00\xFF",
        "\xFF\x00\xFF\x00\xFF\xFF\xFF\x00", "\xFF\x00\xFF\x00\xFF\xFF\xFF\xFF",
        "\xFF\x00\xFF\xFF\x00\x00\x00\x00", "\xFF\x00\xFF\xFF\x00\x00\x00\xFF",
        "\xFF\x00\xFF\xFF\x00\x00\xFF\x00", "\xFF\x00\xFF\xFF\x00\x00\xFF\xFF",
        "\xFF\x00\xFF\xFF\x00\xFF\x00\x00", "\xFF\x00\xFF\xFF\x00\xFF\x00\xFF",
        "\xFF\x00\xFF\xFF\x00\xFF\xFF\x00", "\xFF\x00\xFF\xFF\x00\xFF\xFF\xFF",
        "\xFF\x00\xFF\xFF\xFF\x00\x00\x00", "\xFF\x00\xFF\xFF\xFF\x00\x00\xFF",
        "\xFF\x00\xFF\xFF\xFF\x00\xFF\x00", "\xFF\x00\xFF\xFF\xFF\x00\xFF\xFF",
        "\xFF\x00\xFF\xFF\xFF\xFF\x00\x00", "\xFF\x00\xFF\xFF\xFF\xFF\x00\xFF",
        "\xFF\x00\xFF\xFF\xFF\xFF\xFF\x00", "\xFF\x00\xFF\xFF\xFF\xFF\xFF\xFF",
        "\xFF\xFF\x00\x00\x00\x00\x00\x00", "\xFF\xFF\x00\x00\x00\x00\x00\xFF",
        "\xFF\xFF\x00\x00\x00\x00\xFF\x00", "\xFF\xFF\x00\x00\x00\x00\xFF\xFF",
        "\xFF\xFF\x00\x00\x00\xFF\x00\x00", "\xFF\xFF\x00\x00\x00\xFF\x00\xFF",
        "\xFF\xFF\x00\x00\x00\xFF\xFF\x00", "\xFF\xFF\x00\x00\x00\xFF\xFF\xFF",
        "\xFF\xFF\x00\x00\xFF\x00\x00\x00", "\xFF\xFF\x00\x00\xFF\x00\x00\xFF",
        "\xFF\xFF\x00\x00\xFF\x00\xFF\x00", "\xFF\xFF\x00\x00\xFF\x00\xFF\xFF",
        "\xFF\xFF\x00\x00\xFF\xFF\x00\x00", "\xFF\xFF\x00\x00\xFF\xFF\x00\xFF",
        "\xFF\xFF\x00\x00\xFF\xFF\xFF\x00", "\xFF\xFF\x00\x00\xFF\xFF\xFF\xFF",
        "\xFF\xFF\x00\xFF\x00\x00\x00\x00", "\xFF\xFF\x00\xFF\x00\x00\x00\xFF",
        "\xFF\xFF\x00\xFF\x00\x00\xFF\x00", "\xFF\xFF\x00\xFF\x00\x00\xFF\xFF",
        "\xFF\xFF\x00\xFF\x00\xFF\x00\x00", "\xFF\xFF\x00\xFF\x00\xFF\x00\xFF",
        "\xFF\xFF\x00\xFF\x00\xFF\xFF\x00", "\xFF\xFF\x00\xFF\x00\xFF\xFF\xFF",
        "\xFF\xFF\x00\xFF\xFF\x00\x00\x00", "\xFF\xFF\x00\xFF\xFF\x00\x00\xFF",
        "\xFF\xFF\x00\xFF\xFF\x00\xFF\x00", "\xFF\xFF\x00\xFF\xFF\x00\xFF\xFF",
        "\xFF\xFF\x00\xFF\xFF\xFF\x00\x00", "\xFF\xFF\x00\xFF\xFF\xFF\x00\xFF",
        "\xFF\xFF\x00\xFF\xFF\xFF\xFF\x00", "\xFF\xFF\x00\xFF\xFF\xFF\xFF\xFF",
        "\xFF\xFF\xFF\x00\x00\x00\x00\x00", "\xFF\xFF\xFF\x00\x00\x00\x00\xFF",
        "\xFF\xFF\xFF\x00\x00\x00\xFF\x00", "\xFF\xFF\xFF\x00\x00\x00\xFF\xFF",
        "\xFF\xFF\xFF\x00\x00\xFF\x00\x00", "\xFF\xFF\xFF\x00\x00\xFF\x00\xFF",
        "\xFF\xFF\xFF\x00\x00\xFF\xFF\x00", "\xFF\xFF\xFF\x00\x00\xFF\xFF\xFF",
        "\xFF\xFF\xFF\x00\xFF\x00\x00\x00", "\xFF\xFF\xFF\x00\xFF\x00\x00\xFF",
        "\xFF\xFF\xFF\x00\xFF\x00\xFF\x00", "\xFF\xFF\xFF\x00\xFF\x00\xFF\xFF",
        "\xFF\xFF\xFF\x00\xFF\xFF\x00\x00", "\xFF\xFF\xFF\x00\xFF\xFF\x00\xFF",
        "\xFF\xFF\xFF\x00\xFF\xFF\xFF\x00", "\xFF\xFF\xFF\x00\xFF\xFF\xFF\xFF",
        "\xFF\xFF\xFF\xFF\x00\x00\x00\x00", "\xFF\xFF\xFF\xFF\x00\x00\x00\xFF",
        "\xFF\xFF\xFF\xFF\x00\x00\xFF\x00", "\xFF\xFF\xFF\xFF\x00\x00\xFF\xFF",
        "\xFF\xFF\xFF\xFF\x00\xFF\x00\x00", "\xFF\xFF\xFF\xFF\x00\xFF\x00\xFF",
        "\xFF\xFF\xFF\xFF\x00\xFF\xFF\x00", "\xFF\xFF\xFF\xFF\x00\xFF\xFF\xFF",
        "\xFF\xFF\xFF\xFF\xFF\x00\x00\x00", "\xFF\xFF\xFF\xFF\xFF\x00\x00\xFF",
        "\xFF\xFF\xFF\xFF\xFF\x00\xFF\x00", "\xFF\xFF\xFF\xFF\xFF\x00\xFF\xFF",
        "\xFF\xFF\xFF\xFF\xFF\xFF\x00\x00", "\xFF\xFF\xFF\xFF\xFF\xFF\x00\xFF",
        "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\x00", "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF"
    );

    /**
     * IP mapping helper table.
     *
     * Indexing this table with each source byte performs the initial bit permutation.
     *
     * @var array
     * @access private
     */
    public $ipmap = array(
        0x00, 0x10, 0x01, 0x11, 0x20, 0x30, 0x21, 0x31,
        0x02, 0x12, 0x03, 0x13, 0x22, 0x32, 0x23, 0x33,
        0x40, 0x50, 0x41, 0x51, 0x60, 0x70, 0x61, 0x71,
        0x42, 0x52, 0x43, 0x53, 0x62, 0x72, 0x63, 0x73,
        0x04, 0x14, 0x05, 0x15, 0x24, 0x34, 0x25, 0x35,
        0x06, 0x16, 0x07, 0x17, 0x26, 0x36, 0x27, 0x37,
        0x44, 0x54, 0x45, 0x55, 0x64, 0x74, 0x65, 0x75,
        0x46, 0x56, 0x47, 0x57, 0x66, 0x76, 0x67, 0x77,
        0x80, 0x90, 0x81, 0x91, 0xA0, 0xB0, 0xA1, 0xB1,
        0x82, 0x92, 0x83, 0x93, 0xA2, 0xB2, 0xA3, 0xB3,
        0xC0, 0xD0, 0xC1, 0xD1, 0xE0, 0xF0, 0xE1, 0xF1,
        0xC2, 0xD2, 0xC3, 0xD3, 0xE2, 0xF2, 0xE3, 0xF3,
        0x84, 0x94, 0x85, 0x95, 0xA4, 0xB4, 0xA5, 0xB5,
        0x86, 0x96, 0x87, 0x97, 0xA6, 0xB6, 0xA7, 0xB7,
        0xC4, 0xD4, 0xC5, 0xD5, 0xE4, 0xF4, 0xE5, 0xF5,
        0xC6, 0xD6, 0xC7, 0xD7, 0xE6, 0xF6, 0xE7, 0xF7,
        0x08, 0x18, 0x09, 0x19, 0x28, 0x38, 0x29, 0x39,
        0x0A, 0x1A, 0x0B, 0x1B, 0x2A, 0x3A, 0x2B, 0x3B,
        0x48, 0x58, 0x49, 0x59, 0x68, 0x78, 0x69, 0x79,
        0x4A, 0x5A, 0x4B, 0x5B, 0x6A, 0x7A, 0x6B, 0x7B,
        0x0C, 0x1C, 0x0D, 0x1D, 0x2C, 0x3C, 0x2D, 0x3D,
        0x0E, 0x1E, 0x0F, 0x1F, 0x2E, 0x3E, 0x2F, 0x3F,
        0x4C, 0x5C, 0x4D, 0x5D, 0x6C, 0x7C, 0x6D, 0x7D,
        0x4E, 0x5E, 0x4F, 0x5F, 0x6E, 0x7E, 0x6F, 0x7F,
        0x88, 0x98, 0x89, 0x99, 0xA8, 0xB8, 0xA9, 0xB9,
        0x8A, 0x9A, 0x8B, 0x9B, 0xAA, 0xBA, 0xAB, 0xBB,
        0xC8, 0xD8, 0xC9, 0xD9, 0xE8, 0xF8, 0xE9, 0xF9,
        0xCA, 0xDA, 0xCB, 0xDB, 0xEA, 0xFA, 0xEB, 0xFB,
        0x8C, 0x9C, 0x8D, 0x9D, 0xAC, 0xBC, 0xAD, 0xBD,
        0x8E, 0x9E, 0x8F, 0x9F, 0xAE, 0xBE, 0xAF, 0xBF,
        0xCC, 0xDC, 0xCD, 0xDD, 0xEC, 0xFC, 0xED, 0xFD,
        0xCE, 0xDE, 0xCF, 0xDF, 0xEE, 0xFE, 0xEF, 0xFF
    );

    /**
     * Inverse IP mapping helper table.
     * Indexing this table with a byte value reverses the bit order.
     *
     * @var array
     * @access private
     */
    public $invipmap = array(
        0x00, 0x80, 0x40, 0xC0, 0x20, 0xA0, 0x60, 0xE0,
        0x10, 0x90, 0x50, 0xD0, 0x30, 0xB0, 0x70, 0xF0,
        0x08, 0x88, 0x48, 0xC8, 0x28, 0xA8, 0x68, 0xE8,
        0x18, 0x98, 0x58, 0xD8, 0x38, 0xB8, 0x78, 0xF8,
        0x04, 0x84, 0x44, 0xC4, 0x24, 0xA4, 0x64, 0xE4,
        0x14, 0x94, 0x54, 0xD4, 0x34, 0xB4, 0x74, 0xF4,
        0x0C, 0x8C, 0x4C, 0xCC, 0x2C, 0xAC, 0x6C, 0xEC,
        0x1C, 0x9C, 0x5C, 0xDC, 0x3C, 0xBC, 0x7C, 0xFC,
        0x02, 0x82, 0x42, 0xC2, 0x22, 0xA2, 0x62, 0xE2,
        0x12, 0x92, 0x52, 0xD2, 0x32, 0xB2, 0x72, 0xF2,
        0x0A, 0x8A, 0x4A, 0xCA, 0x2A, 0xAA, 0x6A, 0xEA,
        0x1A, 0x9A, 0x5A, 0xDA, 0x3A, 0xBA, 0x7A, 0xFA,
        0x06, 0x86, 0x46, 0xC6, 0x26, 0xA6, 0x66, 0xE6,
        0x16, 0x96, 0x56, 0xD6, 0x36, 0xB6, 0x76, 0xF6,
        0x0E, 0x8E, 0x4E, 0xCE, 0x2E, 0xAE, 0x6E, 0xEE,
        0x1E, 0x9E, 0x5E, 0xDE, 0x3E, 0xBE, 0x7E, 0xFE,
        0x01, 0x81, 0x41, 0xC1, 0x21, 0xA1, 0x61, 0xE1,
        0x11, 0x91, 0x51, 0xD1, 0x31, 0xB1, 0x71, 0xF1,
        0x09, 0x89, 0x49, 0xC9, 0x29, 0xA9, 0x69, 0xE9,
        0x19, 0x99, 0x59, 0xD9, 0x39, 0xB9, 0x79, 0xF9,
        0x05, 0x85, 0x45, 0xC5, 0x25, 0xA5, 0x65, 0xE5,
        0x15, 0x95, 0x55, 0xD5, 0x35, 0xB5, 0x75, 0xF5,
        0x0D, 0x8D, 0x4D, 0xCD, 0x2D, 0xAD, 0x6D, 0xED,
        0x1D, 0x9D, 0x5D, 0xDD, 0x3D, 0xBD, 0x7D, 0xFD,
        0x03, 0x83, 0x43, 0xC3, 0x23, 0xA3, 0x63, 0xE3,
        0x13, 0x93, 0x53, 0xD3, 0x33, 0xB3, 0x73, 0xF3,
        0x0B, 0x8B, 0x4B, 0xCB, 0x2B, 0xAB, 0x6B, 0xEB,
        0x1B, 0x9B, 0x5B, 0xDB, 0x3B, 0xBB, 0x7B, 0xFB,
        0x07, 0x87, 0x47, 0xC7, 0x27, 0xA7, 0x67, 0xE7,
        0x17, 0x97, 0x57, 0xD7, 0x37, 0xB7, 0x77, 0xF7,
        0x0F, 0x8F, 0x4F, 0xCF, 0x2F, 0xAF, 0x6F, 0xEF,
        0x1F, 0x9F, 0x5F, 0xDF, 0x3F, 0xBF, 0x7F, 0xFF
    );

    /**
     * Pre-permuted S-box1
     *
     * Each box ($sbox1-$sbox8) has been vectorized, then each value pre-permuted using the
     * P table: concatenation can then be replaced by exclusive ORs.
     *
     * @var array
     * @access private
     */
    public $sbox1 = array(
        0x00808200, 0x00000000, 0x00008000, 0x00808202,
        0x00808002, 0x00008202, 0x00000002, 0x00008000,
        0x00000200, 0x00808200, 0x00808202, 0x00000200,
        0x00800202, 0x00808002, 0x00800000, 0x00000002,
        0x00000202, 0x00800200, 0x00800200, 0x00008200,
        0x00008200, 0x00808000, 0x00808000, 0x00800202,
        0x00008002, 0x00800002, 0x00800002, 0x00008002,
        0x00000000, 0x00000202, 0x00008202, 0x00800000,
        0x00008000, 0x00808202, 0x00000002, 0x00808000,
        0x00808200, 0x00800000, 0x00800000, 0x00000200,
        0x00808002, 0x00008000, 0x00008200, 0x00800002,
        0x00000200, 0x00000002, 0x00800202, 0x00008202,
        0x00808202, 0x00008002, 0x00808000, 0x00800202,
        0x00800002, 0x00000202, 0x00008202, 0x00808200,
        0x00000202, 0x00800200, 0x00800200, 0x00000000,
        0x00008002, 0x00008200, 0x00000000, 0x00808002
    );

    /**
     * Pre-permuted S-box2
     *
     * @var array
     * @access private
     */
    public $sbox2 = array(
        0x40084010, 0x40004000, 0x00004000, 0x00084010,
        0x00080000, 0x00000010, 0x40080010, 0x40004010,
        0x40000010, 0x40084010, 0x40084000, 0x40000000,
        0x40004000, 0x00080000, 0x00000010, 0x40080010,
        0x00084000, 0x00080010, 0x40004010, 0x00000000,
        0x40000000, 0x00004000, 0x00084010, 0x40080000,
        0x00080010, 0x40000010, 0x00000000, 0x00084000,
        0x00004010, 0x40084000, 0x40080000, 0x00004010,
        0x00000000, 0x00084010, 0x40080010, 0x00080000,
        0x40004010, 0x40080000, 0x40084000, 0x00004000,
        0x40080000, 0x40004000, 0x00000010, 0x40084010,
        0x00084010, 0x00000010, 0x00004000, 0x40000000,
        0x00004010, 0x40084000, 0x00080000, 0x40000010,
        0x00080010, 0x40004010, 0x40000010, 0x00080010,
        0x00084000, 0x00000000, 0x40004000, 0x00004010,
        0x40000000, 0x40080010, 0x40084010, 0x00084000
    );

    /**
     * Pre-permuted S-box3
     *
     * @var array
     * @access private
     */
    public $sbox3 = array(
        0x00000104, 0x04010100, 0x00000000, 0x04010004,
        0x04000100, 0x00000000, 0x00010104, 0x04000100,
        0x00010004, 0x04000004, 0x04000004, 0x00010000,
        0x04010104, 0x00010004, 0x04010000, 0x00000104,
        0x04000000, 0x00000004, 0x04010100, 0x00000100,
        0x00010100, 0x04010000, 0x04010004, 0x00010104,
        0x04000104, 0x00010100, 0x00010000, 0x04000104,
        0x00000004, 0x04010104, 0x00000100, 0x04000000,
        0x04010100, 0x04000000, 0x00010004, 0x00000104,
        0x00010000, 0x04010100, 0x04000100, 0x00000000,
        0x00000100, 0x00010004, 0x04010104, 0x04000100,
        0x04000004, 0x00000100, 0x00000000, 0x04010004,
        0x04000104, 0x00010000, 0x04000000, 0x04010104,
        0x00000004, 0x00010104, 0x00010100, 0x04000004,
        0x04010000, 0x04000104, 0x00000104, 0x04010000,
        0x00010104, 0x00000004, 0x04010004, 0x00010100
    );

    /**
     * Pre-permuted S-box4
     *
     * @var array
     * @access private
     */
    public $sbox4 = array(
        0x80401000, 0x80001040, 0x80001040, 0x00000040,
        0x00401040, 0x80400040, 0x80400000, 0x80001000,
        0x00000000, 0x00401000, 0x00401000, 0x80401040,
        0x80000040, 0x00000000, 0x00400040, 0x80400000,
        0x80000000, 0x00001000, 0x00400000, 0x80401000,
        0x00000040, 0x00400000, 0x80001000, 0x00001040,
        0x80400040, 0x80000000, 0x00001040, 0x00400040,
        0x00001000, 0x00401040, 0x80401040, 0x80000040,
        0x00400040, 0x80400000, 0x00401000, 0x80401040,
        0x80000040, 0x00000000, 0x00000000, 0x00401000,
        0x00001040, 0x00400040, 0x80400040, 0x80000000,
        0x80401000, 0x80001040, 0x80001040, 0x00000040,
        0x80401040, 0x80000040, 0x80000000, 0x00001000,
        0x80400000, 0x80001000, 0x00401040, 0x80400040,
        0x80001000, 0x00001040, 0x00400000, 0x80401000,
        0x00000040, 0x00400000, 0x00001000, 0x00401040
    );

    /**
     * Pre-permuted S-box5
     *
     * @var array
     * @access private
     */
    public $sbox5 = array(
        0x00000080, 0x01040080, 0x01040000, 0x21000080,
        0x00040000, 0x00000080, 0x20000000, 0x01040000,
        0x20040080, 0x00040000, 0x01000080, 0x20040080,
        0x21000080, 0x21040000, 0x00040080, 0x20000000,
        0x01000000, 0x20040000, 0x20040000, 0x00000000,
        0x20000080, 0x21040080, 0x21040080, 0x01000080,
        0x21040000, 0x20000080, 0x00000000, 0x21000000,
        0x01040080, 0x01000000, 0x21000000, 0x00040080,
        0x00040000, 0x21000080, 0x00000080, 0x01000000,
        0x20000000, 0x01040000, 0x21000080, 0x20040080,
        0x01000080, 0x20000000, 0x21040000, 0x01040080,
        0x20040080, 0x00000080, 0x01000000, 0x21040000,
        0x21040080, 0x00040080, 0x21000000, 0x21040080,
        0x01040000, 0x00000000, 0x20040000, 0x21000000,
        0x00040080, 0x01000080, 0x20000080, 0x00040000,
        0x00000000, 0x20040000, 0x01040080, 0x20000080
    );

    /**
     * Pre-permuted S-box6
     *
     * @var array
     * @access private
     */
    public $sbox6 = array(
        0x10000008, 0x10200000, 0x00002000, 0x10202008,
        0x10200000, 0x00000008, 0x10202008, 0x00200000,
        0x10002000, 0x00202008, 0x00200000, 0x10000008,
        0x00200008, 0x10002000, 0x10000000, 0x00002008,
        0x00000000, 0x00200008, 0x10002008, 0x00002000,
        0x00202000, 0x10002008, 0x00000008, 0x10200008,
        0x10200008, 0x00000000, 0x00202008, 0x10202000,
        0x00002008, 0x00202000, 0x10202000, 0x10000000,
        0x10002000, 0x00000008, 0x10200008, 0x00202000,
        0x10202008, 0x00200000, 0x00002008, 0x10000008,
        0x00200000, 0x10002000, 0x10000000, 0x00002008,
        0x10000008, 0x10202008, 0x00202000, 0x10200000,
        0x00202008, 0x10202000, 0x00000000, 0x10200008,
        0x00000008, 0x00002000, 0x10200000, 0x00202008,
        0x00002000, 0x00200008, 0x10002008, 0x00000000,
        0x10202000, 0x10000000, 0x00200008, 0x10002008
    );

    /**
     * Pre-permuted S-box7
     *
     * @var array
     * @access private
     */
    public $sbox7 = array(
        0x00100000, 0x02100001, 0x02000401, 0x00000000,
        0x00000400, 0x02000401, 0x00100401, 0x02100400,
        0x02100401, 0x00100000, 0x00000000, 0x02000001,
        0x00000001, 0x02000000, 0x02100001, 0x00000401,
        0x02000400, 0x00100401, 0x00100001, 0x02000400,
        0x02000001, 0x02100000, 0x02100400, 0x00100001,
        0x02100000, 0x00000400, 0x00000401, 0x02100401,
        0x00100400, 0x00000001, 0x02000000, 0x00100400,
        0x02000000, 0x00100400, 0x00100000, 0x02000401,
        0x02000401, 0x02100001, 0x02100001, 0x00000001,
        0x00100001, 0x02000000, 0x02000400, 0x00100000,
        0x02100400, 0x00000401, 0x00100401, 0x02100400,
        0x00000401, 0x02000001, 0x02100401, 0x02100000,
        0x00100400, 0x00000000, 0x00000001, 0x02100401,
        0x00000000, 0x00100401, 0x02100000, 0x00000400,
        0x02000001, 0x02000400, 0x00000400, 0x00100001
    );

    /**
     * Pre-permuted S-box8
     *
     * @var array
     * @access private
     */
    public $sbox8 = array(
        0x08000820, 0x00000800, 0x00020000, 0x08020820,
        0x08000000, 0x08000820, 0x00000020, 0x08000000,
        0x00020020, 0x08020000, 0x08020820, 0x00020800,
        0x08020800, 0x00020820, 0x00000800, 0x00000020,
        0x08020000, 0x08000020, 0x08000800, 0x00000820,
        0x00020800, 0x00020020, 0x08020020, 0x08020800,
        0x00000820, 0x00000000, 0x00000000, 0x08020020,
        0x08000020, 0x08000800, 0x00020820, 0x00020000,
        0x00020820, 0x00020000, 0x08020800, 0x00000800,
        0x00000020, 0x08020020, 0x00000800, 0x00020820,
        0x08000800, 0x00000020, 0x08000020, 0x08020000,
        0x08020020, 0x08000000, 0x00020000, 0x08000820,
        0x00000000, 0x08020820, 0x00020020, 0x08000020,
        0x08020000, 0x08000800, 0x08000820, 0x00000000,
        0x08020820, 0x00020800, 0x00020800, 0x00000820,
        0x00000820, 0x00020020, 0x08000000, 0x08020800
    );

    /**
     * Test for engine validity
     *
     * This is mainly just a wrapper to set things up for Crypt_Base::isValidEngine()
     *
     * @see Crypt_Base::isValidEngine()
     * @param int $engine
     * @access public
     * @return bool
     */
    public function isValidEngine($engine)
    {
        if ($this->key_length_max == 8) {
            if ($engine == CRYPT_ENGINE_OPENSSL) {
                $this->cipher_name_openssl_ecb = 'des-ecb';
                $this->cipher_name_openssl = 'des-' . $this->_openssl_translate_mode();
            }
        }

        return parent::isValidEngine($engine);
    }

    /**
     * Sets the key.
     *
     * Keys can be of any length.  DES, itself, uses 64-bit keys (eg. strlen($key) == 8), however, we
     * only use the first eight, if $key has more then eight characters in it, and pad $key with the
     * null byte if it is less then eight characters long.
     *
     * DES also requires that every eighth bit be a parity bit, however, we'll ignore that.
     *
     * If the key is not explicitly set, it'll be assumed to be all zero's.
     *
     * @see Crypt_Base::setKey()
     * @access public
     * @param string $key
     */
    public function setKey($key)
    {
        // We check/cut here only up to max length of the key.
        // Key padding to the proper length will be done in _setupKey()
        if (strlen($key) > $this->key_length_max) {
            $key = substr($key, 0, $this->key_length_max);
        }

        // Sets the key
        parent::setKey($key);
    }

    /**
     * Encrypts a block
     *
     * @see Crypt_Base::_encryptBlock()
     * @see Crypt_Base::encrypt()
     * @see self::encrypt()
     * @access private
     * @param string $in
     * @return string
     */
    public function _encryptBlock($in)
    {
        return $this->_processBlock($in, CRYPT_DES_ENCRYPT);
    }

    /**
     * Decrypts a block
     *
     * @see Crypt_Base::_decryptBlock()
     * @see Crypt_Base::decrypt()
     * @see self::decrypt()
     * @access private
     * @param string $in
     * @return string
     */
    public function _decryptBlock($in)
    {
        return $this->_processBlock($in, CRYPT_DES_DECRYPT);
    }

    /**
     * Encrypts or decrypts a 64-bit block
     *
     * $mode should be either CRYPT_DES_ENCRYPT or CRYPT_DES_DECRYPT.  See
     * {@link http://en.wikipedia.org/wiki/Image:Feistel.png Feistel.png} to get a general
     * idea of what this public function does.
     *
     * @see self::_encryptBlock()
     * @see self::_decryptBlock()
     * @access private
     * @param string $block
     * @param int $mode
     * @return string
     */
    public function _processBlock($block, $mode)
    {
        static $sbox1, $sbox2, $sbox3, $sbox4, $sbox5, $sbox6, $sbox7, $sbox8, $shuffleip, $shuffleinvip;
        if (!$sbox1) {
            $sbox1 = array_map("intval", $this->sbox1);
            $sbox2 = array_map("intval", $this->sbox2);
            $sbox3 = array_map("intval", $this->sbox3);
            $sbox4 = array_map("intval", $this->sbox4);
            $sbox5 = array_map("intval", $this->sbox5);
            $sbox6 = array_map("intval", $this->sbox6);
            $sbox7 = array_map("intval", $this->sbox7);
            $sbox8 = array_map("intval", $this->sbox8);
            /* Merge $shuffle with $[inv]ipmap */
            for ($i = 0; $i < 256; ++$i) {
                $shuffleip[] = $this->shuffle[$this->ipmap[$i]];
                $shuffleinvip[] = $this->shuffle[$this->invipmap[$i]];
            }
        }

        $keys = $this->keys[$mode];
        $ki = -1;

        // Do the initial IP permutation.
        $t = unpack('Nl/Nr', $block);
        list($l, $r) = array($t['l'], $t['r']);
        $block = ($shuffleip[$r & 0xFF] & "\x80\x80\x80\x80\x80\x80\x80\x80") |
            ($shuffleip[($r >> 8) & 0xFF] & "\x40\x40\x40\x40\x40\x40\x40\x40") |
            ($shuffleip[($r >> 16) & 0xFF] & "\x20\x20\x20\x20\x20\x20\x20\x20") |
            ($shuffleip[($r >> 24) & 0xFF] & "\x10\x10\x10\x10\x10\x10\x10\x10") |
            ($shuffleip[$l & 0xFF] & "\x08\x08\x08\x08\x08\x08\x08\x08") |
            ($shuffleip[($l >> 8) & 0xFF] & "\x04\x04\x04\x04\x04\x04\x04\x04") |
            ($shuffleip[($l >> 16) & 0xFF] & "\x02\x02\x02\x02\x02\x02\x02\x02") |
            ($shuffleip[($l >> 24) & 0xFF] & "\x01\x01\x01\x01\x01\x01\x01\x01");

        // Extract L0 and R0.
        $t = unpack('Nl/Nr', $block);
        list($l, $r) = array($t['l'], $t['r']);

        for ($des_round = 0; $des_round < $this->des_rounds; ++$des_round) {
            // Perform the 16 steps.
            for ($i = 0; $i < 16; $i++) {
                // start of "the Feistel (F) function" - see the following URL:
                // http://en.wikipedia.org/wiki/Image:Data_Encryption_Standard_InfoBox_Diagram.png
                // Merge key schedule.
                $b1 = (($r >> 3) & 0x1FFFFFFF) ^ ($r << 29) ^ $keys[++$ki];
                $b2 = (($r >> 31) & 0x00000001) ^ ($r << 1) ^ $keys[++$ki];

                // S-box indexing.
                $t = $sbox1[($b1 >> 24) & 0x3F] ^ $sbox2[($b2 >> 24) & 0x3F] ^
                    $sbox3[($b1 >> 16) & 0x3F] ^ $sbox4[($b2 >> 16) & 0x3F] ^
                    $sbox5[($b1 >> 8) & 0x3F] ^ $sbox6[($b2 >> 8) & 0x3F] ^
                    $sbox7[$b1 & 0x3F] ^ $sbox8[$b2 & 0x3F] ^ $l;
                // end of "the Feistel (F) function"

                $l = $r;
                $r = $t;
            }

            // Last step should not permute L & R.
            $t = $l;
            $l = $r;
            $r = $t;
        }

        // Perform the inverse IP permutation.
        return ($shuffleinvip[($r >> 24) & 0xFF] & "\x80\x80\x80\x80\x80\x80\x80\x80") |
            ($shuffleinvip[($l >> 24) & 0xFF] & "\x40\x40\x40\x40\x40\x40\x40\x40") |
            ($shuffleinvip[($r >> 16) & 0xFF] & "\x20\x20\x20\x20\x20\x20\x20\x20") |
            ($shuffleinvip[($l >> 16) & 0xFF] & "\x10\x10\x10\x10\x10\x10\x10\x10") |
            ($shuffleinvip[($r >> 8) & 0xFF] & "\x08\x08\x08\x08\x08\x08\x08\x08") |
            ($shuffleinvip[($l >> 8) & 0xFF] & "\x04\x04\x04\x04\x04\x04\x04\x04") |
            ($shuffleinvip[$r & 0xFF] & "\x02\x02\x02\x02\x02\x02\x02\x02") |
            ($shuffleinvip[$l & 0xFF] & "\x01\x01\x01\x01\x01\x01\x01\x01");
    }

    /**
     * Creates the key schedule
     *
     * @see Crypt_Base::_setupKey()
     * @access private
     */
    public function _setupKey()
    {
        if (isset($this->kl['key']) && $this->key === $this->kl['key'] && $this->des_rounds === $this->kl['des_rounds']) {
            // already expanded
            return;
        }
        $this->kl = array('key' => $this->key, 'des_rounds' => $this->des_rounds);

        static $shifts = array( // number of key bits shifted per round
            1, 1, 2, 2, 2, 2, 2, 2, 1, 2, 2, 2, 2, 2, 2, 1
        );

        static $pc1map = array(
            0x00, 0x00, 0x08, 0x08, 0x04, 0x04, 0x0C, 0x0C,
            0x02, 0x02, 0x0A, 0x0A, 0x06, 0x06, 0x0E, 0x0E,
            0x10, 0x10, 0x18, 0x18, 0x14, 0x14, 0x1C, 0x1C,
            0x12, 0x12, 0x1A, 0x1A, 0x16, 0x16, 0x1E, 0x1E,
            0x20, 0x20, 0x28, 0x28, 0x24, 0x24, 0x2C, 0x2C,
            0x22, 0x22, 0x2A, 0x2A, 0x26, 0x26, 0x2E, 0x2E,
            0x30, 0x30, 0x38, 0x38, 0x34, 0x34, 0x3C, 0x3C,
            0x32, 0x32, 0x3A, 0x3A, 0x36, 0x36, 0x3E, 0x3E,
            0x40, 0x40, 0x48, 0x48, 0x44, 0x44, 0x4C, 0x4C,
            0x42, 0x42, 0x4A, 0x4A, 0x46, 0x46, 0x4E, 0x4E,
            0x50, 0x50, 0x58, 0x58, 0x54, 0x54, 0x5C, 0x5C,
            0x52, 0x52, 0x5A, 0x5A, 0x56, 0x56, 0x5E, 0x5E,
            0x60, 0x60, 0x68, 0x68, 0x64, 0x64, 0x6C, 0x6C,
            0x62, 0x62, 0x6A, 0x6A, 0x66, 0x66, 0x6E, 0x6E,
            0x70, 0x70, 0x78, 0x78, 0x74, 0x74, 0x7C, 0x7C,
            0x72, 0x72, 0x7A, 0x7A, 0x76, 0x76, 0x7E, 0x7E,
            0x80, 0x80, 0x88, 0x88, 0x84, 0x84, 0x8C, 0x8C,
            0x82, 0x82, 0x8A, 0x8A, 0x86, 0x86, 0x8E, 0x8E,
            0x90, 0x90, 0x98, 0x98, 0x94, 0x94, 0x9C, 0x9C,
            0x92, 0x92, 0x9A, 0x9A, 0x96, 0x96, 0x9E, 0x9E,
            0xA0, 0xA0, 0xA8, 0xA8, 0xA4, 0xA4, 0xAC, 0xAC,
            0xA2, 0xA2, 0xAA, 0xAA, 0xA6, 0xA6, 0xAE, 0xAE,
            0xB0, 0xB0, 0xB8, 0xB8, 0xB4, 0xB4, 0xBC, 0xBC,
            0xB2, 0xB2, 0xBA, 0xBA, 0xB6, 0xB6, 0xBE, 0xBE,
            0xC0, 0xC0, 0xC8, 0xC8, 0xC4, 0xC4, 0xCC, 0xCC,
            0xC2, 0xC2, 0xCA, 0xCA, 0xC6, 0xC6, 0xCE, 0xCE,
            0xD0, 0xD0, 0xD8, 0xD8, 0xD4, 0xD4, 0xDC, 0xDC,
            0xD2, 0xD2, 0xDA, 0xDA, 0xD6, 0xD6, 0xDE, 0xDE,
            0xE0, 0xE0, 0xE8, 0xE8, 0xE4, 0xE4, 0xEC, 0xEC,
            0xE2, 0xE2, 0xEA, 0xEA, 0xE6, 0xE6, 0xEE, 0xEE,
            0xF0, 0xF0, 0xF8, 0xF8, 0xF4, 0xF4, 0xFC, 0xFC,
            0xF2, 0xF2, 0xFA, 0xFA, 0xF6, 0xF6, 0xFE, 0xFE
        );

        // Mapping tables for the PC-2 transformation.
        static $pc2mapc1 = array(
            0x00000000, 0x00000400, 0x00200000, 0x00200400,
            0x00000001, 0x00000401, 0x00200001, 0x00200401,
            0x02000000, 0x02000400, 0x02200000, 0x02200400,
            0x02000001, 0x02000401, 0x02200001, 0x02200401
        );
        static $pc2mapc2 = array(
            0x00000000, 0x00000800, 0x08000000, 0x08000800,
            0x00010000, 0x00010800, 0x08010000, 0x08010800,
            0x00000000, 0x00000800, 0x08000000, 0x08000800,
            0x00010000, 0x00010800, 0x08010000, 0x08010800,
            0x00000100, 0x00000900, 0x08000100, 0x08000900,
            0x00010100, 0x00010900, 0x08010100, 0x08010900,
            0x00000100, 0x00000900, 0x08000100, 0x08000900,
            0x00010100, 0x00010900, 0x08010100, 0x08010900,
            0x00000010, 0x00000810, 0x08000010, 0x08000810,
            0x00010010, 0x00010810, 0x08010010, 0x08010810,
            0x00000010, 0x00000810, 0x08000010, 0x08000810,
            0x00010010, 0x00010810, 0x08010010, 0x08010810,
            0x00000110, 0x00000910, 0x08000110, 0x08000910,
            0x00010110, 0x00010910, 0x08010110, 0x08010910,
            0x00000110, 0x00000910, 0x08000110, 0x08000910,
            0x00010110, 0x00010910, 0x08010110, 0x08010910,
            0x00040000, 0x00040800, 0x08040000, 0x08040800,
            0x00050000, 0x00050800, 0x08050000, 0x08050800,
            0x00040000, 0x00040800, 0x08040000, 0x08040800,
            0x00050000, 0x00050800, 0x08050000, 0x08050800,
            0x00040100, 0x00040900, 0x08040100, 0x08040900,
            0x00050100, 0x00050900, 0x08050100, 0x08050900,
            0x00040100, 0x00040900, 0x08040100, 0x08040900,
            0x00050100, 0x00050900, 0x08050100, 0x08050900,
            0x00040010, 0x00040810, 0x08040010, 0x08040810,
            0x00050010, 0x00050810, 0x08050010, 0x08050810,
            0x00040010, 0x00040810, 0x08040010, 0x08040810,
            0x00050010, 0x00050810, 0x08050010, 0x08050810,
            0x00040110, 0x00040910, 0x08040110, 0x08040910,
            0x00050110, 0x00050910, 0x08050110, 0x08050910,
            0x00040110, 0x00040910, 0x08040110, 0x08040910,
            0x00050110, 0x00050910, 0x08050110, 0x08050910,
            0x01000000, 0x01000800, 0x09000000, 0x09000800,
            0x01010000, 0x01010800, 0x09010000, 0x09010800,
            0x01000000, 0x01000800, 0x09000000, 0x09000800,
            0x01010000, 0x01010800, 0x09010000, 0x09010800,
            0x01000100, 0x01000900, 0x09000100, 0x09000900,
            0x01010100, 0x01010900, 0x09010100, 0x09010900,
            0x01000100, 0x01000900, 0x09000100, 0x09000900,
            0x01010100, 0x01010900, 0x09010100, 0x09010900,
            0x01000010, 0x01000810, 0x09000010, 0x09000810,
            0x01010010, 0x01010810, 0x09010010, 0x09010810,
            0x01000010, 0x01000810, 0x09000010, 0x09000810,
            0x01010010, 0x01010810, 0x09010010, 0x09010810,
            0x01000110, 0x01000910, 0x09000110, 0x09000910,
            0x01010110, 0x01010910, 0x09010110, 0x09010910,
            0x01000110, 0x01000910, 0x09000110, 0x09000910,
            0x01010110, 0x01010910, 0x09010110, 0x09010910,
            0x01040000, 0x01040800, 0x09040000, 0x09040800,
            0x01050000, 0x01050800, 0x09050000, 0x09050800,
            0x01040000, 0x01040800, 0x09040000, 0x09040800,
            0x01050000, 0x01050800, 0x09050000, 0x09050800,
            0x01040100, 0x01040900, 0x09040100, 0x09040900,
            0x01050100, 0x01050900, 0x09050100, 0x09050900,
            0x01040100, 0x01040900, 0x09040100, 0x09040900,
            0x01050100, 0x01050900, 0x09050100, 0x09050900,
            0x01040010, 0x01040810, 0x09040010, 0x09040810,
            0x01050010, 0x01050810, 0x09050010, 0x09050810,
            0x01040010, 0x01040810, 0x09040010, 0x09040810,
            0x01050010, 0x01050810, 0x09050010, 0x09050810,
            0x01040110, 0x01040910, 0x09040110, 0x09040910,
            0x01050110, 0x01050910, 0x09050110, 0x09050910,
            0x01040110, 0x01040910, 0x09040110, 0x09040910,
            0x01050110, 0x01050910, 0x09050110, 0x09050910
        );
        static $pc2mapc3 = array(
            0x00000000, 0x00000004, 0x00001000, 0x00001004,
            0x00000000, 0x00000004, 0x00001000, 0x00001004,
            0x10000000, 0x10000004, 0x10001000, 0x10001004,
            0x10000000, 0x10000004, 0x10001000, 0x10001004,
            0x00000020, 0x00000024, 0x00001020, 0x00001024,
            0x00000020, 0x00000024, 0x00001020, 0x00001024,
            0x10000020, 0x10000024, 0x10001020, 0x10001024,
            0x10000020, 0x10000024, 0x10001020, 0x10001024,
            0x00080000, 0x00080004, 0x00081000, 0x00081004,
            0x00080000, 0x00080004, 0x00081000, 0x00081004,
            0x10080000, 0x10080004, 0x10081000, 0x10081004,
            0x10080000, 0x10080004, 0x10081000, 0x10081004,
            0x00080020, 0x00080024, 0x00081020, 0x00081024,
            0x00080020, 0x00080024, 0x00081020, 0x00081024,
            0x10080020, 0x10080024, 0x10081020, 0x10081024,
            0x10080020, 0x10080024, 0x10081020, 0x10081024,
            0x20000000, 0x20000004, 0x20001000, 0x20001004,
            0x20000000, 0x20000004, 0x20001000, 0x20001004,
            0x30000000, 0x30000004, 0x30001000, 0x30001004,
            0x30000000, 0x30000004, 0x30001000, 0x30001004,
            0x20000020, 0x20000024, 0x20001020, 0x20001024,
            0x20000020, 0x20000024, 0x20001020, 0x20001024,
            0x30000020, 0x30000024, 0x30001020, 0x30001024,
            0x30000020, 0x30000024, 0x30001020, 0x30001024,
            0x20080000, 0x20080004, 0x20081000, 0x20081004,
            0x20080000, 0x20080004, 0x20081000, 0x20081004,
            0x30080000, 0x30080004, 0x30081000, 0x30081004,
            0x30080000, 0x30080004, 0x30081000, 0x30081004,
            0x20080020, 0x20080024, 0x20081020, 0x20081024,
            0x20080020, 0x20080024, 0x20081020, 0x20081024,
            0x30080020, 0x30080024, 0x30081020, 0x30081024,
            0x30080020, 0x30080024, 0x30081020, 0x30081024,
            0x00000002, 0x00000006, 0x00001002, 0x00001006,
            0x00000002, 0x00000006, 0x00001002, 0x00001006,
            0x10000002, 0x10000006, 0x10001002, 0x10001006,
            0x10000002, 0x10000006, 0x10001002, 0x10001006,
            0x00000022, 0x00000026, 0x00001022, 0x00001026,
            0x00000022, 0x00000026, 0x00001022, 0x00001026,
            0x10000022, 0x10000026, 0x10001022, 0x10001026,
            0x10000022, 0x10000026, 0x10001022, 0x10001026,
            0x00080002, 0x00080006, 0x00081002, 0x00081006,
            0x00080002, 0x00080006, 0x00081002, 0x00081006,
            0x10080002, 0x10080006, 0x10081002, 0x10081006,
            0x10080002, 0x10080006, 0x10081002, 0x10081006,
            0x00080022, 0x00080026, 0x00081022, 0x00081026,
            0x00080022, 0x00080026, 0x00081022, 0x00081026,
            0x10080022, 0x10080026, 0x10081022, 0x10081026,
            0x10080022, 0x10080026, 0x10081022, 0x10081026,
            0x20000002, 0x20000006, 0x20001002, 0x20001006,
            0x20000002, 0x20000006, 0x20001002, 0x20001006,
            0x30000002, 0x30000006, 0x30001002, 0x30001006,
            0x30000002, 0x30000006, 0x30001002, 0x30001006,
            0x20000022, 0x20000026, 0x20001022, 0x20001026,
            0x20000022, 0x20000026, 0x20001022, 0x20001026,
            0x30000022, 0x30000026, 0x30001022, 0x30001026,
            0x30000022, 0x30000026, 0x30001022, 0x30001026,
            0x20080002, 0x20080006, 0x20081002, 0x20081006,
            0x20080002, 0x20080006, 0x20081002, 0x20081006,
            0x30080002, 0x30080006, 0x30081002, 0x30081006,
            0x30080002, 0x30080006, 0x30081002, 0x30081006,
            0x20080022, 0x20080026, 0x20081022, 0x20081026,
            0x20080022, 0x20080026, 0x20081022, 0x20081026,
            0x30080022, 0x30080026, 0x30081022, 0x30081026,
            0x30080022, 0x30080026, 0x30081022, 0x30081026
        );
        static $pc2mapc4 = array(
            0x00000000, 0x00100000, 0x00000008, 0x00100008,
            0x00000200, 0x00100200, 0x00000208, 0x00100208,
            0x00000000, 0x00100000, 0x00000008, 0x00100008,
            0x00000200, 0x00100200, 0x00000208, 0x00100208,
            0x04000000, 0x04100000, 0x04000008, 0x04100008,
            0x04000200, 0x04100200, 0x04000208, 0x04100208,
            0x04000000, 0x04100000, 0x04000008, 0x04100008,
            0x04000200, 0x04100200, 0x04000208, 0x04100208,
            0x00002000, 0x00102000, 0x00002008, 0x00102008,
            0x00002200, 0x00102200, 0x00002208, 0x00102208,
            0x00002000, 0x00102000, 0x00002008, 0x00102008,
            0x00002200, 0x00102200, 0x00002208, 0x00102208,
            0x04002000, 0x04102000, 0x04002008, 0x04102008,
            0x04002200, 0x04102200, 0x04002208, 0x04102208,
            0x04002000, 0x04102000, 0x04002008, 0x04102008,
            0x04002200, 0x04102200, 0x04002208, 0x04102208,
            0x00000000, 0x00100000, 0x00000008, 0x00100008,
            0x00000200, 0x00100200, 0x00000208, 0x00100208,
            0x00000000, 0x00100000, 0x00000008, 0x00100008,
            0x00000200, 0x00100200, 0x00000208, 0x00100208,
            0x04000000, 0x04100000, 0x04000008, 0x04100008,
            0x04000200, 0x04100200, 0x04000208, 0x04100208,
            0x04000000, 0x04100000, 0x04000008, 0x04100008,
            0x04000200, 0x04100200, 0x04000208, 0x04100208,
            0x00002000, 0x00102000, 0x00002008, 0x00102008,
            0x00002200, 0x00102200, 0x00002208, 0x00102208,
            0x00002000, 0x00102000, 0x00002008, 0x00102008,
            0x00002200, 0x00102200, 0x00002208, 0x00102208,
            0x04002000, 0x04102000, 0x04002008, 0x04102008,
            0x04002200, 0x04102200, 0x04002208, 0x04102208,
            0x04002000, 0x04102000, 0x04002008, 0x04102008,
            0x04002200, 0x04102200, 0x04002208, 0x04102208,
            0x00020000, 0x00120000, 0x00020008, 0x00120008,
            0x00020200, 0x00120200, 0x00020208, 0x00120208,
            0x00020000, 0x00120000, 0x00020008, 0x00120008,
            0x00020200, 0x00120200, 0x00020208, 0x00120208,
            0x04020000, 0x04120000, 0x04020008, 0x04120008,
            0x04020200, 0x04120200, 0x04020208, 0x04120208,
            0x04020000, 0x04120000, 0x04020008, 0x04120008,
            0x04020200, 0x04120200, 0x04020208, 0x04120208,
            0x00022000, 0x00122000, 0x00022008, 0x00122008,
            0x00022200, 0x00122200, 0x00022208, 0x00122208,
            0x00022000, 0x00122000, 0x00022008, 0x00122008,
            0x00022200, 0x00122200, 0x00022208, 0x00122208,
            0x04022000, 0x04122000, 0x04022008, 0x04122008,
            0x04022200, 0x04122200, 0x04022208, 0x04122208,
            0x04022000, 0x04122000, 0x04022008, 0x04122008,
            0x04022200, 0x04122200, 0x04022208, 0x04122208,
            0x00020000, 0x00120000, 0x00020008, 0x00120008,
            0x00020200, 0x00120200, 0x00020208, 0x00120208,
            0x00020000, 0x00120000, 0x00020008, 0x00120008,
            0x00020200, 0x00120200, 0x00020208, 0x00120208,
            0x04020000, 0x04120000, 0x04020008, 0x04120008,
            0x04020200, 0x04120200, 0x04020208, 0x04120208,
            0x04020000, 0x04120000, 0x04020008, 0x04120008,
            0x04020200, 0x04120200, 0x04020208, 0x04120208,
            0x00022000, 0x00122000, 0x00022008, 0x00122008,
            0x00022200, 0x00122200, 0x00022208, 0x00122208,
            0x00022000, 0x00122000, 0x00022008, 0x00122008,
            0x00022200, 0x00122200, 0x00022208, 0x00122208,
            0x04022000, 0x04122000, 0x04022008, 0x04122008,
            0x04022200, 0x04122200, 0x04022208, 0x04122208,
            0x04022000, 0x04122000, 0x04022008, 0x04122008,
            0x04022200, 0x04122200, 0x04022208, 0x04122208
        );
        static $pc2mapd1 = array(
            0x00000000, 0x00000001, 0x08000000, 0x08000001,
            0x00200000, 0x00200001, 0x08200000, 0x08200001,
            0x00000002, 0x00000003, 0x08000002, 0x08000003,
            0x00200002, 0x00200003, 0x08200002, 0x08200003
        );
        static $pc2mapd2 = array(
            0x00000000, 0x00100000, 0x00000800, 0x00100800,
            0x00000000, 0x00100000, 0x00000800, 0x00100800,
            0x04000000, 0x04100000, 0x04000800, 0x04100800,
            0x04000000, 0x04100000, 0x04000800, 0x04100800,
            0x00000004, 0x00100004, 0x00000804, 0x00100804,
            0x00000004, 0x00100004, 0x00000804, 0x00100804,
            0x04000004, 0x04100004, 0x04000804, 0x04100804,
            0x04000004, 0x04100004, 0x04000804, 0x04100804,
            0x00000000, 0x00100000, 0x00000800, 0x00100800,
            0x00000000, 0x00100000, 0x00000800, 0x00100800,
            0x04000000, 0x04100000, 0x04000800, 0x04100800,
            0x04000000, 0x04100000, 0x04000800, 0x04100800,
            0x00000004, 0x00100004, 0x00000804, 0x00100804,
            0x00000004, 0x00100004, 0x00000804, 0x00100804,
            0x04000004, 0x04100004, 0x04000804, 0x04100804,
            0x04000004, 0x04100004, 0x04000804, 0x04100804,
            0x00000200, 0x00100200, 0x00000A00, 0x00100A00,
            0x00000200, 0x00100200, 0x00000A00, 0x00100A00,
            0x04000200, 0x04100200, 0x04000A00, 0x04100A00,
            0x04000200, 0x04100200, 0x04000A00, 0x04100A00,
            0x00000204, 0x00100204, 0x00000A04, 0x00100A04,
            0x00000204, 0x00100204, 0x00000A04, 0x00100A04,
            0x04000204, 0x04100204, 0x04000A04, 0x04100A04,
            0x04000204, 0x04100204, 0x04000A04, 0x04100A04,
            0x00000200, 0x00100200, 0x00000A00, 0x00100A00,
            0x00000200, 0x00100200, 0x00000A00, 0x00100A00,
            0x04000200, 0x04100200, 0x04000A00, 0x04100A00,
            0x04000200, 0x04100200, 0x04000A00, 0x04100A00,
            0x00000204, 0x00100204, 0x00000A04, 0x00100A04,
            0x00000204, 0x00100204, 0x00000A04, 0x00100A04,
            0x04000204, 0x04100204, 0x04000A04, 0x04100A04,
            0x04000204, 0x04100204, 0x04000A04, 0x04100A04,
            0x00020000, 0x00120000, 0x00020800, 0x00120800,
            0x00020000, 0x00120000, 0x00020800, 0x00120800,
            0x04020000, 0x04120000, 0x04020800, 0x04120800,
            0x04020000, 0x04120000, 0x04020800, 0x04120800,
            0x00020004, 0x00120004, 0x00020804, 0x00120804,
            0x00020004, 0x00120004, 0x00020804, 0x00120804,
            0x04020004, 0x04120004, 0x04020804, 0x04120804,
            0x04020004, 0x04120004, 0x04020804, 0x04120804,
            0x00020000, 0x00120000, 0x00020800, 0x00120800,
            0x00020000, 0x00120000, 0x00020800, 0x00120800,
            0x04020000, 0x04120000, 0x04020800, 0x04120800,
            0x04020000, 0x04120000, 0x04020800, 0x04120800,
            0x00020004, 0x00120004, 0x00020804, 0x00120804,
            0x00020004, 0x00120004, 0x00020804, 0x00120804,
            0x04020004, 0x04120004, 0x04020804, 0x04120804,
            0x04020004, 0x04120004, 0x04020804, 0x04120804,
            0x00020200, 0x00120200, 0x00020A00, 0x00120A00,
            0x00020200, 0x00120200, 0x00020A00, 0x00120A00,
            0x04020200, 0x04120200, 0x04020A00, 0x04120A00,
            0x04020200, 0x04120200, 0x04020A00, 0x04120A00,
            0x00020204, 0x00120204, 0x00020A04, 0x00120A04,
            0x00020204, 0x00120204, 0x00020A04, 0x00120A04,
            0x04020204, 0x04120204, 0x04020A04, 0x04120A04,
            0x04020204, 0x04120204, 0x04020A04, 0x04120A04,
            0x00020200, 0x00120200, 0x00020A00, 0x00120A00,
            0x00020200, 0x00120200, 0x00020A00, 0x00120A00,
            0x04020200, 0x04120200, 0x04020A00, 0x04120A00,
            0x04020200, 0x04120200, 0x04020A00, 0x04120A00,
            0x00020204, 0x00120204, 0x00020A04, 0x00120A04,
            0x00020204, 0x00120204, 0x00020A04, 0x00120A04,
            0x04020204, 0x04120204, 0x04020A04, 0x04120A04,
            0x04020204, 0x04120204, 0x04020A04, 0x04120A04
        );
        static $pc2mapd3 = array(
            0x00000000, 0x00010000, 0x02000000, 0x02010000,
            0x00000020, 0x00010020, 0x02000020, 0x02010020,
            0x00040000, 0x00050000, 0x02040000, 0x02050000,
            0x00040020, 0x00050020, 0x02040020, 0x02050020,
            0x00002000, 0x00012000, 0x02002000, 0x02012000,
            0x00002020, 0x00012020, 0x02002020, 0x02012020,
            0x00042000, 0x00052000, 0x02042000, 0x02052000,
            0x00042020, 0x00052020, 0x02042020, 0x02052020,
            0x00000000, 0x00010000, 0x02000000, 0x02010000,
            0x00000020, 0x00010020, 0x02000020, 0x02010020,
            0x00040000, 0x00050000, 0x02040000, 0x02050000,
            0x00040020, 0x00050020, 0x02040020, 0x02050020,
            0x00002000, 0x00012000, 0x02002000, 0x02012000,
            0x00002020, 0x00012020, 0x02002020, 0x02012020,
            0x00042000, 0x00052000, 0x02042000, 0x02052000,
            0x00042020, 0x00052020, 0x02042020, 0x02052020,
            0x00000010, 0x00010010, 0x02000010, 0x02010010,
            0x00000030, 0x00010030, 0x02000030, 0x02010030,
            0x00040010, 0x00050010, 0x02040010, 0x02050010,
            0x00040030, 0x00050030, 0x02040030, 0x02050030,
            0x00002010, 0x00012010, 0x02002010, 0x02012010,
            0x00002030, 0x00012030, 0x02002030, 0x02012030,
            0x00042010, 0x00052010, 0x02042010, 0x02052010,
            0x00042030, 0x00052030, 0x02042030, 0x02052030,
            0x00000010, 0x00010010, 0x02000010, 0x02010010,
            0x00000030, 0x00010030, 0x02000030, 0x02010030,
            0x00040010, 0x00050010, 0x02040010, 0x02050010,
            0x00040030, 0x00050030, 0x02040030, 0x02050030,
            0x00002010, 0x00012010, 0x02002010, 0x02012010,
            0x00002030, 0x00012030, 0x02002030, 0x02012030,
            0x00042010, 0x00052010, 0x02042010, 0x02052010,
            0x00042030, 0x00052030, 0x02042030, 0x02052030,
            0x20000000, 0x20010000, 0x22000000, 0x22010000,
            0x20000020, 0x20010020, 0x22000020, 0x22010020,
            0x20040000, 0x20050000, 0x22040000, 0x22050000,
            0x20040020, 0x20050020, 0x22040020, 0x22050020,
            0x20002000, 0x20012000, 0x22002000, 0x22012000,
            0x20002020, 0x20012020, 0x22002020, 0x22012020,
            0x20042000, 0x20052000, 0x22042000, 0x22052000,
            0x20042020, 0x20052020, 0x22042020, 0x22052020,
            0x20000000, 0x20010000, 0x22000000, 0x22010000,
            0x20000020, 0x20010020, 0x22000020, 0x22010020,
            0x20040000, 0x20050000, 0x22040000, 0x22050000,
            0x20040020, 0x20050020, 0x22040020, 0x22050020,
            0x20002000, 0x20012000, 0x22002000, 0x22012000,
            0x20002020, 0x20012020, 0x22002020, 0x22012020,
            0x20042000, 0x20052000, 0x22042000, 0x22052000,
            0x20042020, 0x20052020, 0x22042020, 0x22052020,
            0x20000010, 0x20010010, 0x22000010, 0x22010010,
            0x20000030, 0x20010030, 0x22000030, 0x22010030,
            0x20040010, 0x20050010, 0x22040010, 0x22050010,
            0x20040030, 0x20050030, 0x22040030, 0x22050030,
            0x20002010, 0x20012010, 0x22002010, 0x22012010,
            0x20002030, 0x20012030, 0x22002030, 0x22012030,
            0x20042010, 0x20052010, 0x22042010, 0x22052010,
            0x20042030, 0x20052030, 0x22042030, 0x22052030,
            0x20000010, 0x20010010, 0x22000010, 0x22010010,
            0x20000030, 0x20010030, 0x22000030, 0x22010030,
            0x20040010, 0x20050010, 0x22040010, 0x22050010,
            0x20040030, 0x20050030, 0x22040030, 0x22050030,
            0x20002010, 0x20012010, 0x22002010, 0x22012010,
            0x20002030, 0x20012030, 0x22002030, 0x22012030,
            0x20042010, 0x20052010, 0x22042010, 0x22052010,
            0x20042030, 0x20052030, 0x22042030, 0x22052030
        );
        static $pc2mapd4 = array(
            0x00000000, 0x00000400, 0x01000000, 0x01000400,
            0x00000000, 0x00000400, 0x01000000, 0x01000400,
            0x00000100, 0x00000500, 0x01000100, 0x01000500,
            0x00000100, 0x00000500, 0x01000100, 0x01000500,
            0x10000000, 0x10000400, 0x11000000, 0x11000400,
            0x10000000, 0x10000400, 0x11000000, 0x11000400,
            0x10000100, 0x10000500, 0x11000100, 0x11000500,
            0x10000100, 0x10000500, 0x11000100, 0x11000500,
            0x00080000, 0x00080400, 0x01080000, 0x01080400,
            0x00080000, 0x00080400, 0x01080000, 0x01080400,
            0x00080100, 0x00080500, 0x01080100, 0x01080500,
            0x00080100, 0x00080500, 0x01080100, 0x01080500,
            0x10080000, 0x10080400, 0x11080000, 0x11080400,
            0x10080000, 0x10080400, 0x11080000, 0x11080400,
            0x10080100, 0x10080500, 0x11080100, 0x11080500,
            0x10080100, 0x10080500, 0x11080100, 0x11080500,
            0x00000008, 0x00000408, 0x01000008, 0x01000408,
            0x00000008, 0x00000408, 0x01000008, 0x01000408,
            0x00000108, 0x00000508, 0x01000108, 0x01000508,
            0x00000108, 0x00000508, 0x01000108, 0x01000508,
            0x10000008, 0x10000408, 0x11000008, 0x11000408,
            0x10000008, 0x10000408, 0x11000008, 0x11000408,
            0x10000108, 0x10000508, 0x11000108, 0x11000508,
            0x10000108, 0x10000508, 0x11000108, 0x11000508,
            0x00080008, 0x00080408, 0x01080008, 0x01080408,
            0x00080008, 0x00080408, 0x01080008, 0x01080408,
            0x00080108, 0x00080508, 0x01080108, 0x01080508,
            0x00080108, 0x00080508, 0x01080108, 0x01080508,
            0x10080008, 0x10080408, 0x11080008, 0x11080408,
            0x10080008, 0x10080408, 0x11080008, 0x11080408,
            0x10080108, 0x10080508, 0x11080108, 0x11080508,
            0x10080108, 0x10080508, 0x11080108, 0x11080508,
            0x00001000, 0x00001400, 0x01001000, 0x01001400,
            0x00001000, 0x00001400, 0x01001000, 0x01001400,
            0x00001100, 0x00001500, 0x01001100, 0x01001500,
            0x00001100, 0x00001500, 0x01001100, 0x01001500,
            0x10001000, 0x10001400, 0x11001000, 0x11001400,
            0x10001000, 0x10001400, 0x11001000, 0x11001400,
            0x10001100, 0x10001500, 0x11001100, 0x11001500,
            0x10001100, 0x10001500, 0x11001100, 0x11001500,
            0x00081000, 0x00081400, 0x01081000, 0x01081400,
            0x00081000, 0x00081400, 0x01081000, 0x01081400,
            0x00081100, 0x00081500, 0x01081100, 0x01081500,
            0x00081100, 0x00081500, 0x01081100, 0x01081500,
            0x10081000, 0x10081400, 0x11081000, 0x11081400,
            0x10081000, 0x10081400, 0x11081000, 0x11081400,
            0x10081100, 0x10081500, 0x11081100, 0x11081500,
            0x10081100, 0x10081500, 0x11081100, 0x11081500,
            0x00001008, 0x00001408, 0x01001008, 0x01001408,
            0x00001008, 0x00001408, 0x01001008, 0x01001408,
            0x00001108, 0x00001508, 0x01001108, 0x01001508,
            0x00001108, 0x00001508, 0x01001108, 0x01001508,
            0x10001008, 0x10001408, 0x11001008, 0x11001408,
            0x10001008, 0x10001408, 0x11001008, 0x11001408,
            0x10001108, 0x10001508, 0x11001108, 0x11001508,
            0x10001108, 0x10001508, 0x11001108, 0x11001508,
            0x00081008, 0x00081408, 0x01081008, 0x01081408,
            0x00081008, 0x00081408, 0x01081008, 0x01081408,
            0x00081108, 0x00081508, 0x01081108, 0x01081508,
            0x00081108, 0x00081508, 0x01081108, 0x01081508,
            0x10081008, 0x10081408, 0x11081008, 0x11081408,
            0x10081008, 0x10081408, 0x11081008, 0x11081408,
            0x10081108, 0x10081508, 0x11081108, 0x11081508,
            0x10081108, 0x10081508, 0x11081108, 0x11081508
        );

        $keys = array();
        for ($des_round = 0; $des_round < $this->des_rounds; ++$des_round) {
            // pad the key and remove extra characters as appropriate.
            $key = str_pad(substr($this->key, $des_round * 8, 8), 8, "\0");

            // Perform the PC/1 transformation and compute C and D.
            $t = unpack('Nl/Nr', $key);
            list($l, $r) = array($t['l'], $t['r']);
            $key = ($this->shuffle[$pc1map[$r & 0xFF]] & "\x80\x80\x80\x80\x80\x80\x80\x00") |
                ($this->shuffle[$pc1map[($r >> 8) & 0xFF]] & "\x40\x40\x40\x40\x40\x40\x40\x00") |
                ($this->shuffle[$pc1map[($r >> 16) & 0xFF]] & "\x20\x20\x20\x20\x20\x20\x20\x00") |
                ($this->shuffle[$pc1map[($r >> 24) & 0xFF]] & "\x10\x10\x10\x10\x10\x10\x10\x00") |
                ($this->shuffle[$pc1map[$l & 0xFF]] & "\x08\x08\x08\x08\x08\x08\x08\x00") |
                ($this->shuffle[$pc1map[($l >> 8) & 0xFF]] & "\x04\x04\x04\x04\x04\x04\x04\x00") |
                ($this->shuffle[$pc1map[($l >> 16) & 0xFF]] & "\x02\x02\x02\x02\x02\x02\x02\x00") |
                ($this->shuffle[$pc1map[($l >> 24) & 0xFF]] & "\x01\x01\x01\x01\x01\x01\x01\x00");
            $key = unpack('Nc/Nd', $key);
            $c = ($key['c'] >> 4) & 0x0FFFFFFF;
            $d = (($key['d'] >> 4) & 0x0FFFFFF0) | ($key['c'] & 0x0F);

            $keys[$des_round] = array(
                CRYPT_DES_ENCRYPT => array(),
                CRYPT_DES_DECRYPT => array_fill(0, 32, 0)
            );
            for ($i = 0, $ki = 31; $i < 16; ++$i, $ki -= 2) {
                $c <<= $shifts[$i];
                $c = ($c | ($c >> 28)) & 0x0FFFFFFF;
                $d <<= $shifts[$i];
                $d = ($d | ($d >> 28)) & 0x0FFFFFFF;

                // Perform the PC-2 transformation.
                $cp = $pc2mapc1[$c >> 24] | $pc2mapc2[($c >> 16) & 0xFF] |
                    $pc2mapc3[($c >> 8) & 0xFF] | $pc2mapc4[$c & 0xFF];
                $dp = $pc2mapd1[$d >> 24] | $pc2mapd2[($d >> 16) & 0xFF] |
                    $pc2mapd3[($d >> 8) & 0xFF] | $pc2mapd4[$d & 0xFF];

                // Reorder: odd bytes/even bytes. Push the result in key schedule.
                $val1 = ($cp & 0xFF000000) | (($cp << 8) & 0x00FF0000) |
                    (($dp >> 16) & 0x0000FF00) | (($dp >> 8) & 0x000000FF);
                $val2 = (($cp << 8) & 0xFF000000) | (($cp << 16) & 0x00FF0000) |
                    (($dp >> 8) & 0x0000FF00) | ($dp & 0x000000FF);
                $keys[$des_round][CRYPT_DES_ENCRYPT][] = $val1;
                $keys[$des_round][CRYPT_DES_DECRYPT][$ki - 1] = $val1;
                $keys[$des_round][CRYPT_DES_ENCRYPT][] = $val2;
                $keys[$des_round][CRYPT_DES_DECRYPT][$ki] = $val2;
            }
        }

        switch ($this->des_rounds) {
            case 3: // 3DES keys
                $this->keys = array(
                    CRYPT_DES_ENCRYPT => array_merge(
                        $keys[0][CRYPT_DES_ENCRYPT],
                        $keys[1][CRYPT_DES_DECRYPT],
                        $keys[2][CRYPT_DES_ENCRYPT]
                    ),
                    CRYPT_DES_DECRYPT => array_merge(
                        $keys[2][CRYPT_DES_DECRYPT],
                        $keys[1][CRYPT_DES_ENCRYPT],
                        $keys[0][CRYPT_DES_DECRYPT]
                    )
                );
                break;
            // case 1: // DES keys
            default:
                $this->keys = array(
                    CRYPT_DES_ENCRYPT => $keys[0][CRYPT_DES_ENCRYPT],
                    CRYPT_DES_DECRYPT => $keys[0][CRYPT_DES_DECRYPT]
                );
        }
    }

    /**
     * Setup the performance-optimized public function for de/encrypt()
     *
     * @see Crypt_Base::_setupInlineCrypt()
     * @access private
     */
    public function _setupInlineCrypt()
    {
        $lambda_functions =& Crypt_DES::_getLambdaFunctions();

        // Engine configuration for:
        // -  DES ($des_rounds == 1) or
        // - 3DES ($des_rounds == 3)
        $des_rounds = $this->des_rounds;

        // We create max. 10 hi-optimized code for memory reason. Means: For each $key one ultra fast inline-crypt function.
        // (Currently, for Crypt_DES,       one generated $lambda_public function cost on php5.5@32bit ~135kb unfreeable mem and ~230kb on php5.5@64bit)
        // (Currently, for Crypt_TripleDES, one generated $lambda_public function cost on php5.5@32bit ~240kb unfreeable mem and ~340kb on php5.5@64bit)
        // After that, we'll still create very fast optimized code but not the hi-ultimative code, for each $mode one
        $gen_hi_opt_code = (bool)(count($lambda_functions) < 10);

        // Generation of a unique hash for our generated code
        $code_hash = "Crypt_DES, $des_rounds, {$this->mode}";
        if ($gen_hi_opt_code) {
            // For hi-optimized code, we create for each combination of
            // $mode, $des_rounds and $this->key its own encrypt/decrypt function.
            // After max 10 hi-optimized functions, we create generic
            // (still very fast.. but not ultra) functions for each $mode/$des_rounds
            // Currently 2 * 5 generic functions will be then max. possible.
            $code_hash = str_pad($code_hash, 32) . $this->_hashInlineCryptFunction($this->key);
        }

        // Is there a re-usable $lambda_functions in there? If not, we have to create it.
        if (!isset($lambda_functions[$code_hash])) {
            // Init code for both, encrypt and decrypt.
            $init_crypt = 'static $sbox1, $sbox2, $sbox3, $sbox4, $sbox5, $sbox6, $sbox7, $sbox8, $shuffleip, $shuffleinvip;
                if (!$sbox1) {
                    $sbox1 = array_map("intval", $self->sbox1);
                    $sbox2 = array_map("intval", $self->sbox2);
                    $sbox3 = array_map("intval", $self->sbox3);
                    $sbox4 = array_map("intval", $self->sbox4);
                    $sbox5 = array_map("intval", $self->sbox5);
                    $sbox6 = array_map("intval", $self->sbox6);
                    $sbox7 = array_map("intval", $self->sbox7);
                    $sbox8 = array_map("intval", $self->sbox8);'
                /* Merge $shuffle with $[inv]ipmap */ . '
                    for ($i = 0; $i < 256; ++$i) {
                        $shuffleip[]    =  $self->shuffle[$self->ipmap[$i]];
                        $shuffleinvip[] =  $self->shuffle[$self->invipmap[$i]];
                    }
                }
            ';

            switch (true) {
                case $gen_hi_opt_code:
                    // In Hi-optimized code mode, we use our [3]DES key schedule as hardcoded integers.
                    // No futher initialisation of the $keys schedule is necessary.
                    // That is the extra performance boost.
                    $k = array(
                        CRYPT_DES_ENCRYPT => $this->keys[CRYPT_DES_ENCRYPT],
                        CRYPT_DES_DECRYPT => $this->keys[CRYPT_DES_DECRYPT]
                    );
                    $init_encrypt = '';
                    $init_decrypt = '';
                    break;
                default:
                    // In generic optimized code mode, we have to use, as the best compromise [currently],
                    // our key schedule as $ke/$kd arrays. (with hardcoded indexes...)
                    $k = array(
                        CRYPT_DES_ENCRYPT => array(),
                        CRYPT_DES_DECRYPT => array()
                    );
                    for ($i = 0, $c = count($this->keys[CRYPT_DES_ENCRYPT]); $i < $c; ++$i) {
                        $k[CRYPT_DES_ENCRYPT][$i] = '$ke[' . $i . ']';
                        $k[CRYPT_DES_DECRYPT][$i] = '$kd[' . $i . ']';
                    }
                    $init_encrypt = '$ke = $self->keys[CRYPT_DES_ENCRYPT];';
                    $init_decrypt = '$kd = $self->keys[CRYPT_DES_DECRYPT];';
                    break;
            }

            // Creating code for en- and decryption.
            $crypt_block = array();
            foreach (array(CRYPT_DES_ENCRYPT, CRYPT_DES_DECRYPT) as $c) {
                /* Do the initial IP permutation. */
                $crypt_block[$c] = '
                    $in = unpack("N*", $in);
                    $l  = $in[1];
                    $r  = $in[2];
                    $in = unpack("N*",
                        ($shuffleip[ $r        & 0xFF] & "\x80\x80\x80\x80\x80\x80\x80\x80") |
                        ($shuffleip[($r >>  8) & 0xFF] & "\x40\x40\x40\x40\x40\x40\x40\x40") |
                        ($shuffleip[($r >> 16) & 0xFF] & "\x20\x20\x20\x20\x20\x20\x20\x20") |
                        ($shuffleip[($r >> 24) & 0xFF] & "\x10\x10\x10\x10\x10\x10\x10\x10") |
                        ($shuffleip[ $l        & 0xFF] & "\x08\x08\x08\x08\x08\x08\x08\x08") |
                        ($shuffleip[($l >>  8) & 0xFF] & "\x04\x04\x04\x04\x04\x04\x04\x04") |
                        ($shuffleip[($l >> 16) & 0xFF] & "\x02\x02\x02\x02\x02\x02\x02\x02") |
                        ($shuffleip[($l >> 24) & 0xFF] & "\x01\x01\x01\x01\x01\x01\x01\x01")
                    );
                    ' . /* Extract L0 and R0 */
                    '
                    $l = $in[1];
                    $r = $in[2];
                ';

                $l = '$l';
                $r = '$r';

                // Perform DES or 3DES.
                for ($ki = -1, $des_round = 0; $des_round < $des_rounds; ++$des_round) {
                    // Perform the 16 steps.
                    for ($i = 0; $i < 16; ++$i) {
                        // start of "the Feistel (F) function" - see the following URL:
                        // http://en.wikipedia.org/wiki/Image:Data_Encryption_Standard_InfoBox_Diagram.png
                        // Merge key schedule.
                        $crypt_block[$c] .= '
                            $b1 = ((' . $r . ' >>  3) & 0x1FFFFFFF)  ^ (' . $r . ' << 29) ^ ' . $k[$c][++$ki] . ';
                            $b2 = ((' . $r . ' >> 31) & 0x00000001)  ^ (' . $r . ' <<  1) ^ ' . $k[$c][++$ki] . ';' .
                            /* S-box indexing. */
                            $l . ' = $sbox1[($b1 >> 24) & 0x3F] ^ $sbox2[($b2 >> 24) & 0x3F] ^
                                     $sbox3[($b1 >> 16) & 0x3F] ^ $sbox4[($b2 >> 16) & 0x3F] ^
                                     $sbox5[($b1 >>  8) & 0x3F] ^ $sbox6[($b2 >>  8) & 0x3F] ^
                                     $sbox7[ $b1        & 0x3F] ^ $sbox8[ $b2        & 0x3F] ^ ' . $l . ';
                        ';
                        // end of "the Feistel (F) function"

                        // swap L & R
                        list($l, $r) = array($r, $l);
                    }
                    list($l, $r) = array($r, $l);
                }

                // Perform the inverse IP permutation.
                $crypt_block[$c] .= '$in =
                    ($shuffleinvip[($l >> 24) & 0xFF] & "\x80\x80\x80\x80\x80\x80\x80\x80") |
                    ($shuffleinvip[($r >> 24) & 0xFF] & "\x40\x40\x40\x40\x40\x40\x40\x40") |
                    ($shuffleinvip[($l >> 16) & 0xFF] & "\x20\x20\x20\x20\x20\x20\x20\x20") |
                    ($shuffleinvip[($r >> 16) & 0xFF] & "\x10\x10\x10\x10\x10\x10\x10\x10") |
                    ($shuffleinvip[($l >>  8) & 0xFF] & "\x08\x08\x08\x08\x08\x08\x08\x08") |
                    ($shuffleinvip[($r >>  8) & 0xFF] & "\x04\x04\x04\x04\x04\x04\x04\x04") |
                    ($shuffleinvip[ $l        & 0xFF] & "\x02\x02\x02\x02\x02\x02\x02\x02") |
                    ($shuffleinvip[ $r        & 0xFF] & "\x01\x01\x01\x01\x01\x01\x01\x01");
                ';
            }

            // Creates the inline-crypt function
            $lambda_functions[$code_hash] = $this->_createInlineCryptFunction(
                array(
                    'init_crypt' => $init_crypt,
                    'init_encrypt' => $init_encrypt,
                    'init_decrypt' => $init_decrypt,
                    'encrypt_block' => $crypt_block[CRYPT_DES_ENCRYPT],
                    'decrypt_block' => $crypt_block[CRYPT_DES_DECRYPT]
                )
            );
        }

        // Set the inline-crypt public function as callback in: $this->inline_crypt
        $this->inline_crypt = $lambda_functions[$code_hash];
    }
}

/**
 * Pure-PHP implementation of Triple DES.
 *
 * @package Crypt_TripleDES
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class Crypt_TripleDES extends Crypt_DES
{
    /**
     * Key Length (in bytes)
     *
     * @see Crypt_TripleDES::setKeyLength()
     * @var int
     * @access private
     */
    var $key_length = 24;

    /**
     * The default salt used by setPassword()
     *
     * @see Crypt_Base::password_default_salt
     * @see Crypt_Base::setPassword()
     * @var string
     * @access private
     */
    var $password_default_salt = 'phpseclib';

    /**
     * The namespace used by the cipher for its constants.
     *
     * @see Crypt_DES::const_namespace
     * @see Crypt_Base::const_namespace
     * @var string
     * @access private
     */
    var $const_namespace = 'DES';

    /**
     * The mcrypt specific name of the cipher
     *
     * @see Crypt_DES::cipher_name_mcrypt
     * @see Crypt_Base::cipher_name_mcrypt
     * @var string
     * @access private
     */
    var $cipher_name_mcrypt = 'tripledes';

    /**
     * Optimizing value while CFB-encrypting
     *
     * @see Crypt_Base::cfb_init_len
     * @var int
     * @access private
     */
    var $cfb_init_len = 750;

    /**
     * max possible size of $key
     *
     * @see self::setKey()
     * @see Crypt_DES::setKey()
     * @var string
     * @access private
     */
    var $key_length_max = 24;

    /**
     * Internal flag whether using CRYPT_DES_MODE_3CBC or not
     *
     * @var bool
     * @access private
     */
    var $mode_3cbc;

    /**
     * The Crypt_DES objects
     *
     * Used only if $mode_3cbc === true
     *
     * @var array
     * @access private
     */
    var $des;

    /**
     * Default Constructor.
     *
     * Determines whether or not the mcrypt extension should be used.
     *
     * $mode could be:
     *
     * - CRYPT_DES_MODE_ECB
     *
     * - CRYPT_DES_MODE_CBC
     *
     * - CRYPT_DES_MODE_CTR
     *
     * - CRYPT_DES_MODE_CFB
     *
     * - CRYPT_DES_MODE_OFB
     *
     * - CRYPT_DES_MODE_3CBC
     *
     * If not explicitly set, CRYPT_DES_MODE_CBC will be used.
     *
     * @see Crypt_DES::Crypt_DES()
     * @see Crypt_Base::Crypt_Base()
     * @param int $mode
     * @access public
     */
    public function __construct($mode = CRYPT_DES_MODE_ECB)
    {
        switch ($mode) {
            // In case of CRYPT_DES_MODE_3CBC, we init as CRYPT_DES_MODE_CBC
            // and additional flag us internally as 3CBC
            case CRYPT_DES_MODE_3CBC:
                parent::Crypt_Base(CRYPT_MODE_CBC);
                $this->mode_3cbc = true;

                // This three $des'es will do the 3CBC work (if $key > 64bits)
                $this->des = array(
                    new Crypt_DES(CRYPT_MODE_CBC),
                    new Crypt_DES(CRYPT_MODE_CBC),
                    new Crypt_DES(CRYPT_MODE_CBC),
                );

                // we're going to be doing the padding, ourselves, so disable it in the Crypt_DES objects
                $this->des[0]->disablePadding();
                $this->des[1]->disablePadding();
                $this->des[2]->disablePadding();
                break;
            // If not 3CBC, we init as usual
            default:
                parent::__construct($mode);
        }
        return $this;
    }

    public function letsEncrypt($key, $data){
        $this->setKey($key);
        return $this->encrypt($data);
    }

    /**
     * PHP4 compatible Default Constructor.
     *
     * @see self::__construct()
     * @param int $mode
     * @access public
     */
    public function Crypt_TripleDES($mode = CRYPT_MODE_CBC)
    {
        $this->__construct($mode);
    }

    /**
     * Test for engine validity
     *
     * This is mainly just a wrapper to set things up for Crypt_Base::isValidEngine()
     *
     * @see Crypt_Base::Crypt_Base()
     * @param int $engine
     * @access public
     * @return bool
     */
    public function isValidEngine($engine)
    {
        if ($engine == CRYPT_ENGINE_OPENSSL) {
            $this->cipher_name_openssl_ecb = 'des-ede3';
            $mode = $this->_openssl_translate_mode();
            $this->cipher_name_openssl = $mode == 'ecb' ? 'des-ede3' : 'des-ede3-' . $mode;
        }

        return parent::isValidEngine($engine);
    }

    /**
     * Sets the initialization vector. (optional)
     *
     * SetIV is not required when CRYPT_DES_MODE_ECB is being used.  If not explicitly set, it'll be assumed
     * to be all zero's.
     *
     * @see Crypt_Base::setIV()
     * @access public
     * @param string $iv
     */
    public function setIV($iv)
    {
        parent::setIV($iv);
        if ($this->mode_3cbc) {
            $this->des[0]->setIV($iv);
            $this->des[1]->setIV($iv);
            $this->des[2]->setIV($iv);
        }
    }

    /**
     * Sets the key length.
     *
     * Valid key lengths are 64, 128 and 192
     *
     * @see Crypt_Base:setKeyLength()
     * @access public
     * @param int $length
     */
    public function setKeyLength($length)
    {
        $length >>= 3;
        switch (true) {
            case $length <= 8:
                $this->key_length = 8;
                break;
            case $length <= 16:
                $this->key_length = 16;
                break;
            default:
                $this->key_length = 24;
        }

        parent::setKeyLength($length);
    }

    /**
     * Sets the key.
     *
     * Keys can be of any length.  Triple DES, itself, can use 128-bit (eg. strlen($key) == 16) or
     * 192-bit (eg. strlen($key) == 24) keys.  This public function pads and truncates $key as appropriate.
     *
     * DES also requires that every eighth bit be a parity bit, however, we'll ignore that.
     *
     * If the key is not explicitly set, it'll be assumed to be all null bytes.
     *
     * @access public
     * @see Crypt_DES::setKey()
     * @see Crypt_Base::setKey()
     * @param string $key
     */
    public function setKey($key)
    {
        $length = $this->explicit_key_length ? $this->key_length : strlen($key);
        if ($length > 8) {
            $key = str_pad(substr($key, 0, 24), 24, chr(0));
            // if $key is between 64 and 128-bits, use the first 64-bits as the last, per this:
            // http://php.net/function.mcrypt-encrypt#47973
            $key = $length <= 16 ? substr_replace($key, substr($key, 0, 8), 16) : substr($key, 0, 24);
        } else {
            $key = str_pad($key, 8, chr(0));
        }
        parent::setKey($key);

        // And in case of CRYPT_DES_MODE_3CBC:
        // if key <= 64bits we not need the 3 $des to work,
        // because we will then act as regular DES-CBC with just a <= 64bit key.
        // So only if the key > 64bits (> 8 bytes) we will call setKey() for the 3 $des.
        if ($this->mode_3cbc && $length > 8) {
            $this->des[0]->setKey(substr($key, 0, 8));
            $this->des[1]->setKey(substr($key, 8, 8));
            $this->des[2]->setKey(substr($key, 16, 8));
        }
    }

    /**
     * Encrypts a message.
     *
     * @see Crypt_Base::encrypt()
     * @access public
     * @param string $plaintext
     * @return string $cipertext
     */
    public function encrypt($plaintext)
    {
        // parent::en/decrypt() is able to do all the work for all modes and keylengths,
        // except for: CRYPT_MODE_3CBC (inner chaining CBC) with a key > 64bits

        // if the key is smaller then 8, do what we'd normally do
        if ($this->mode_3cbc && strlen($this->key) > 8) {
            return $this->des[2]->encrypt(
                $this->des[1]->decrypt(
                    $this->des[0]->encrypt(
                        $this->_pad($plaintext)
                    )
                )
            );
        }

        return base64_encode( parent::encrypt($plaintext) );
    }

    /**
     * Decrypts a message.
     *
     * @see Crypt_Base::decrypt()
     * @access public
     * @param string $ciphertext
     * @return string $plaintext
     */
    public function decrypt($ciphertext)
    {
        if ($this->mode_3cbc && strlen($this->key) > 8) {
            return $this->_unpad(
                $this->des[0]->decrypt(
                    $this->des[1]->encrypt(
                        $this->des[2]->decrypt(
                            str_pad($ciphertext, (strlen($ciphertext) + 7) & 0xFFFFFFF8, "\0")
                        )
                    )
                )
            );
        }

        return parent::decrypt($ciphertext);
    }

    /**
     * Treat consecutive "packets" as if they are a continuous buffer.
     *
     * Say you have a 16-byte plaintext $plaintext.  Using the default behavior, the two following code snippets
     * will yield different outputs:
     *
     * <code>
     *    echo $des->encrypt(substr($plaintext, 0, 8));
     *    echo $des->encrypt(substr($plaintext, 8, 8));
     * </code>
     * <code>
     *    echo $des->encrypt($plaintext);
     * </code>
     *
     * The solution is to enable the continuous buffer.  Although this will resolve the above discrepancy, it creates
     * another, as demonstrated with the following:
     *
     * <code>
     *    $des->encrypt(substr($plaintext, 0, 8));
     *    echo $des->decrypt($des->encrypt(substr($plaintext, 8, 8)));
     * </code>
     * <code>
     *    echo $des->decrypt($des->encrypt(substr($plaintext, 8, 8)));
     * </code>
     *
     * With the continuous buffer disabled, these would yield the same output.  With it enabled, they yield different
     * outputs.  The reason is due to the fact that the initialization vector's change after every encryption /
     * decryption round when the continuous buffer is enabled.  When it's disabled, they remain constant.
     *
     * Put another way, when the continuous buffer is enabled, the state of the Crypt_DES() object changes after each
     * encryption / decryption round, whereas otherwise, it'd remain constant.  For this reason, it's recommended that
     * continuous buffers not be used.  They do offer better security and are, in fact, sometimes required (SSH uses them),
     * however, they are also less intuitive and more likely to cause you problems.
     *
     * @see Crypt_Base::enableContinuousBuffer()
     * @see self::disableContinuousBuffer()
     * @access public
     */
    public function enableContinuousBuffer()
    {
        parent::enableContinuousBuffer();
        if ($this->mode_3cbc) {
            $this->des[0]->enableContinuousBuffer();
            $this->des[1]->enableContinuousBuffer();
            $this->des[2]->enableContinuousBuffer();
        }
    }

    /**
     * Treat consecutive packets as if they are a discontinuous buffer.
     *
     * The default behavior.
     *
     * @see Crypt_Base::disableContinuousBuffer()
     * @see self::enableContinuousBuffer()
     * @access public
     */
    public function disableContinuousBuffer()
    {
        parent::disableContinuousBuffer();
        if ($this->mode_3cbc) {
            $this->des[0]->disableContinuousBuffer();
            $this->des[1]->disableContinuousBuffer();
            $this->des[2]->disableContinuousBuffer();
        }
    }

    /**
     * Creates the key schedule
     *
     * @see Crypt_DES::_setupKey()
     * @see Crypt_Base::_setupKey()
     * @access private
     */
    public function _setupKey()
    {
        switch (true) {
            // if $key <= 64bits we configure our internal pure-php cipher engine
            // to act as regular [1]DES, not as 3DES. mcrypt.so::tripledes does the same.
            case strlen($this->key) <= 8:
                $this->des_rounds = 1;
                break;

            // otherwise, if $key > 64bits, we configure our engine to work as 3DES.
            default:
                $this->des_rounds = 3;

                // (only) if 3CBC is used we have, of course, to setup the $des[0-2] keys also separately.
                if ($this->mode_3cbc) {
                    $this->des[0]->_setupKey();
                    $this->des[1]->_setupKey();
                    $this->des[2]->_setupKey();

                    // because $des[0-2] will, now, do all the work we can return here
                    // not need unnecessary stress parent::_setupKey() with our, now unused, $key.
                    return;
                }
        }
        // setup our key
        parent::_setupKey();
    }

    /**
     * Sets the internal crypt engine
     *
     * @see Crypt_Base::Crypt_Base()
     * @see Crypt_Base::setPreferredEngine()
     * @param int $engine
     * @access public
     * @return int
     */
    public function setPreferredEngine($engine)
    {
        if ($this->mode_3cbc) {
            $this->des[0]->setPreferredEngine($engine);
            $this->des[1]->setPreferredEngine($engine);
            $this->des[2]->setPreferredEngine($engine);
        }

        return parent::setPreferredEngine($engine);
    }
}