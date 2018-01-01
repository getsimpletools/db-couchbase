<?php

namespace Simpletools\Db\Couchbase\Id;

use Simpletools\Db\Couchbase\Bucket;
use Simpletools\Db\Couchbase;


class AutoInc extends Couchbase\Id
{
    protected $_id;
    protected $_bucket;
    protected $_containerKey;

    public function __construct($ns='',$connectionName='default')
    {
        $this->_ns = $ns;

        if(!isset($this->_bucket))
            $this->_bucket  = (new Bucket($connectionName))->getApiConnector();

        $this->_containerKey = 'autoincrement';
        if($this->_ns)
            $this->_containerKey .= self::$_separator.$this->_ns;
    }

    public function getContainerKey()
    {
        return $this->_containerKey;
    }

    protected function _generate()
    {
        if(!$this->_id)
        {
            try {
                $count = $this->_bucket->counter($this->_containerKey, 1, [
                    'initial' => 1
                ]);
            }
            catch(\Exception $e)
            {
                throw $e;
            }

            $this->_id = $this->_ns.self::$_separator.$count->value;
        }

        return $this->_id;
    }

    public function __toString()
    {
        return (string) $this->_generate();
    }
}
