<?php

namespace Simpletools\Db\Couchbase\Id;

use Simpletools\Db\Couchbase;

class RandomBytes extends Couchbase\Id
{
    protected $_doc;
    protected $_id;
    protected $_ns = '';
    protected static $_length = 20;

    public function __construct($ns='')
    {
        if($ns)
            $this->_ns = $ns.static::$_separator;
    }

    public static function entropy($entropy=null)
    {
        if(!$entropy) return self::$_length;
        else self::$_length = (int) $entropy;

        return self::$_length;
    }

    protected function _generate()
    {
        $length = self::$_length;

        if(!$this->_id) {
            //$time = round(microtime(true) * 1000);

            if (function_exists('random_bytes')) {
                $this->_id = $this->_ns.bin2hex(random_bytes($length));
            } elseif (function_exists('openssl_random_pseudo_bytes')) {
                $this->_id = $this->_ns.bin2hex(openssl_random_pseudo_bytes($length));
            } else {
                throw new Exception("No random bytes generator found, please install random_bytes() or openssl_random_pseudo_bytes()", 404);
            }
        }

        return $this->_id;
    }

    public function __toString()
    {
        return $this->_generate();
    }
}
