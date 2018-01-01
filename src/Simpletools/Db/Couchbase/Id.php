<?php

namespace Simpletools\Db\Couchbase;

use Simpletools\Db\Couchbase\Id\RandomBytes;

class Id
{
    protected $_id;
    protected static $_separator = ':';

    public function __construct($ns='')
    {
        $this->_id = new RandomBytes($ns);
    }

    public static function separator($separator=null)
    {
        return !$separator ? self::$_separator : self::$_separator = $separator;
    }

    public function __toString()
    {
        return (string) $this->_id;
    }
}
