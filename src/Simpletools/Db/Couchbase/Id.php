<?php

namespace Simpletools\Db\Couchbase;

use Simpletools\Db\Couchbase\Id\RandomBytes;

class Id
{
    protected $_id;
    protected $_publicId=null;

    protected static $_separator = ':';

    public function __construct($ns='')
    {
        $this->_id = new RandomBytes($ns);
    }

    public static function separator($separator=null)
    {
        return !$separator ? self::$_separator : self::$_separator = $separator;
    }

    public function id($id=null)
    {
        if(!$id) return $this->_id;

        $this->_id              = (string) $id;
        $this->_publicId        = null;

        return $this;
    }

    public function pid()
    {
        if($this->_publicId===null) {
            $id = explode(self::separator(), (string)$this->_id);
            $this->_publicId = end($id);
        }

        return $this->_publicId;
    }

    public function __toString()
    {
        return (string) $this->_id;
    }
}
