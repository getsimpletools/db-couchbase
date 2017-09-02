<?php

namespace Simpletools\Db\Couchbase\Id;

use Simpletools\Db\Couchbase\Connection;
use Simpletools\Db\Couchbase\Model;

class Increment extends Model
{
    public function __construct()
    {

    }

    public function getOne()
    {
        $this->{$this->_ns}->counter('key');

        //ns:key


//$res = $bucket->get('21st_amendment_brewery_cafe-bitter_american');
//print_r($res->value);
    }
}
