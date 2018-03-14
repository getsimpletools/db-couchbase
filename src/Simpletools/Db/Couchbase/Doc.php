<?php
/*
 * Simpletools Framework.
 * Copyright (c) 2009, Marcin Rosinski. (https://www.getsimpletools.com)
 * All rights reserved.
 *
 * LICENCE
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * - 	Redistributions of source code must retain the above copyright notice,
 * 		this list of conditions and the following disclaimer.
 *
 * -	Redistributions in binary form must reproduce the above copyright notice,
 * 		this list of conditions and the following disclaimer in the documentation and/or other
 * 		materials provided with the distribution.
 *
 * -	Neither the name of the Simpletools nor the names of its contributors may be used to
 * 		endorse or promote products derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER
 * IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF
 * THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @framework		Simpletools
 * @copyright  		Copyright (c) 2009 Marcin Rosinski. (http://www.getsimpletools.com)
 * @license    		http://www.opensource.org/licenses/bsd-license.php - BSD
 *
 */

namespace Simpletools\Db\Couchbase;

use Simpletools\Db\Couchbase\Doc\Body;
use Simpletools\Db\Couchbase\Doc\Meta;

class Doc
{
	/* @var $_bucket \Couchbase\Bucket */
    protected $_bucket;
    protected $_bucketName;
    protected $_meta;
    protected $_body;
    protected $_id;
    protected $_loaded = false;
    protected $_originBody;
    protected $_connectionName = 'default';
    protected $_ns;

    protected $_diff = [
            'upsert' => [],
            'delete' => [],
    ];

    public function __construct($id=null,$connectionName='default', $ns=null)
    {
        if(!$id) $id = new Id($ns);

        $this->_connectionName = $connectionName;
        $this->_ns = $ns;

        $this->_meta = new Meta((object) array(
            'id'    => $id,
            'expiration' => 0
        ));

        $this->_body = new Body((object) array());

        $this->_id = $id;
    }

    public function connect()
    {
        if(!isset($this->_bucket)){
        	$bucket = (new Bucket($this->_connectionName));
			$this->_bucket  = $bucket->getApiConnector();
        	$this->_bucketName = $bucket->bucket();
		}

        return $this;
    }

    public function connector()
    {
        return $this->_bucket;
    }

    public function bucket(\Couchbase\Bucket $bucket)
    {
        $this->_bucket = $bucket;
        return $this;
    }

    public function id($id = null)
    {
        if($id === null)
            return (string) $this->_id;

        $this->_id = $id;
    }

    public function expire($expire = null)
    {
        if(empty($expire)){
            return (int)@$this->_meta->expiration;
        }
        if(!is_int($expire)){
            throw new Exception("The parameter of expire must be a integer.");
        }
        if($expire < time() && $expire > 30*24*60*60)
        {
            throw new Exception("Expire needs to be less then 30 days or in future timestamp.");
        }

        $this->_meta->expiration = $expire;

        return $this;
    }

    public function ns($ns)
    {
        $this->_ns = $ns;
    }

    public function loaded()
    {
        $this->_loaded = true;
        $this->_originBody = new Body(unserialize(serialize($this->_body)));

        return $this;
    }

    public function load()
    {
        $this->_loaded = true;

        $doc = $this->
            connect()->connector()
            ->get((string) $this->_id);

        $this->body($doc->value)
            ->meta(array(
                'id'    => (string) $this->_id,
                'flags'  => $doc->flags,
                'cas'   => $doc->cas,
                'token' => $doc->token
            ));

        $this->_originBody = new Body(json_decode(json_encode($this->_body)));
        return $this;
    }

	public function loadMeta()
	{
		$this->connect();
		$query = \CouchbaseN1qlQuery::fromString('SELECT META() FROM `'.$this->_bucketName.'` USE KEYS [$1]');
		$query->positionalParams([(string)$this->id()]);
		$doc = $this->_bucket->query($query);
		if(!empty($doc->rows)){
			$this->meta((array)array_shift($doc->rows)->{'$1'});
		}
		return $this;
	}

    protected function  arrayDiff($arr1, $arr2)
    {
        return array_udiff($arr1, $arr2, function($v1, $v2){
            if(is_object($v1) && is_object($v2))
            {
                return json_encode($v1) === json_encode($v2) ? 0 : -1;
            }
            elseif(	is_object($v1) || is_object($v2))
            {
                return -1;
            }
            else
            {
                return $v1 === $v2 ? 0 : -1;
            }
        });
    }

    protected function getDifference($new, $origin, $currentPath = array())
    {
        foreach ($new as $k => $v)
        {
            $path = $currentPath;
            $path[] = $k;

            if(is_object($v))
            {
                if(isset($origin->{$k}))
                {
                    if(is_object($origin->{$k}))
                    {
                        $this->getDifference($new->{$k},$origin->{$k}, $path);
                    }
                    else
                    {
                        $this->_diff['upsert'][implode('.',$path)] =  $new->{$k};
                    }
                    unset($origin->{$k});
                }
                else
                {
                    $this->_diff['upsert'][implode('.',$path)] = $new->{$k};
                }
            }
            elseif(is_array($v))
            {
                if(isset($origin->{$k}))
                {
                    //echo"<pre>=================";var_dump($origin->{$k},$v);
                    //echo"<pre>++++++++++++++++";var_dump($this->arrayDiff($v,$origin->{$k}) , $this->arrayDiff($origin->{$k},$v));

                    if(!is_array($origin->{$k}) || $this->arrayDiff($v,$origin->{$k}) || $this->arrayDiff($origin->{$k},$v))
                    {
                        $this->_diff['upsert'][implode('.',$path)] =  $new->{$k};
                    }
                    unset($origin->{$k});
                }
                else
                {
                    $this->_diff['upsert'][implode('.',$path)] = $new->{$k};
                }
            }
            else
            {
                if(isset($origin->{$k}))//todo
                {
                    if(gettype ($v) != gettype($origin->{$k}) || $v != $origin->{$k})
                    {
                        $this->_diff['upsert'][implode('.',$path)] =  $new->{$k};
                    }
                    unset($origin->{$k});
                }
                else
                {
                    $this->_diff['upsert'][implode('.',$path)] = $new->{$k};
                }
            }
        }

        if($origin)
        {
            foreach ($origin as $k => $v)
            {
                $path = $currentPath;
                $path[] = $k;
                $this->_diff['delete'][implode('.',$path)] = 1;
            }
        }
    }

    public function insert()
    {
        $this->connect();

        if($this->_ns)
        {
            $this->_body->_ns = $this->_ns;
        }

        $raw = $this->_body->toObject();

        $res = $this->_bucket->insert((string) $this->_id,$raw, $this->_getOptionForSave());

        if($res->error)
        {
            throw new Exception($res->error);
        }

        return $this;
    }

    public function save()
    {
        if($this->_loaded)
        {
            $this->connect();
            $new = $this->_body->toObject();
            $origin = $this->_originBody->toObject();

            $this->getDifference($new,$origin);
        //	$this->_diff['upsert']['engine.variants[0].capacity'] = 2;
            //echo"<pre>";var_dump($this->_diff);die;//todo


            if($this->_diff['upsert'] || $this->_diff['delete'])
            {
                $mutateIn = $this->_bucket->mutateIn((string) $this->_id);

                foreach ($this->_diff['upsert'] as $k => $v)
                {
                    $mutateIn->upsert($k, $v, $this->_getOptionForSave());
                }

                foreach ($this->_diff['delete'] as $k => $v)
                {
                    $mutateIn->remove($k);
                }
                $res = $mutateIn->execute();

                if($res->error)
                {
                    throw new \Exception($res->error);
                }

                $this->_diff['upsert'] = [];
                $this->_diff['delete'] = [];
            }

            $this->_originBody = new Body(json_decode(json_encode($this->_body)));
        }
        else
        {
            $this->connect();

            if($this->_ns)
            {
                $this->_body->_ns = $this->_ns;
            }
            $raw = $this->_body->toObject();


            $res = $this->_bucket->upsert((string) $this->_id,$raw, $this->_getOptionForSave());

            if($res->error)
            {
                throw new \Exception($res->error);
            }
        }

        return $this;
    }

    protected function _getOptionForSave()
    {
        $option = [];
        if(@$this->_meta->expiration){
            $option['expiry'] = $this->_meta->expiration;
        }

        return $option;
    }

    public function remove()
    {
        $this->connect();

        try {
            $res = $this->_bucket->remove((string) $this->_id);
        }
        catch(\Exception $e)
        {
            if($e->getCode()==13)
            {
                /*
                 * Key already deleted
                 * LCB_KEY_ENOENT: The key does not exist on the server
                 */
                return $this;
            }
        }

        if($res->error)
        {
            throw new Exception($res->error);
        }

        return $this;
    }

    public function __set($name,$value)
    {
        if($name=="body")
        {
            return $this->body($value);
        }
        else
        {
            throw new Exception("Provided property `{$name}` doesn't exist");
        }
    }

    /*
     * @param array|object $meta List of meta properties
     *
     * @return $this|object
     */
    public function meta($meta=null)
    {
        if($meta===null)
            return $this->_meta;

        if($meta instanceof Meta)
        {
            $this->_meta = new Meta($meta);
            return $this;
        }
        elseif(!is_object($meta) && is_array($meta))
            $meta = (object) $meta;
        elseif(!is_array($meta)){
            throw new Exception("meta must be a type of either array or object");
        }

        $meta->id = $this->_id;
        $this->_meta = new Meta($meta);
        return $this;
    }

    /*
    * @param mixed $body Body of your doc
    *
    * @return $this|object
    */
    public function body($body=null)
    {
        if($body===null)
            return $this;

        if($body instanceof Body)
        {
            $this->_body = new Body($body);
            return $this;
        }

        if(is_array($body)) $body = (object)$body;

        if(is_object($body))
				{
					foreach ($body as $key => $val)
					{
						if(strpos($key,'.') !== false)
						{
							$this->convertToNestedBody($body, explode('.',$key), $val);
							unset($body->{$key});
						}
					}
				}

        $this->_body = new Body($body, !$this->_loaded);

        if(@$this->_body->_ns)
        {
            $this->_ns = $this->_body->_ns;
        }

        return $this;
    }

    protected function convertToNestedBody($body, $keys, $val)
    {
        $key = array_shift($keys);
        if(!isset($body->{$key}))
        {
            $body->{$key} = new \stdClass();
        }

        if($keys)
        {
            $this->convertToNestedBody($body->{$key}, $keys, $val);
        }
        else
        {
            $body->{$key} = $val;
        }
    }

    //todo
    protected function _unset($fields)
    {

    }

    public function __get($name)
    {
        if($name=='body')
        {
            return !isset($this->_body) ? ($this->_body = new Body($this->_body)) : $this->_body;
        }
        elseif($name=='meta')
        {
            return !isset($this->_meta) ? ($this->_meta = new Meta($this->_meta)) : $this->_meta;
        }
    }

    public function to2d(){

        $input = array(
            'meta'  => $this->_meta->toObject(),
            'body'  => $this->_body->toObject()
        );
        $ritit = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($input));
        $result = array();
        foreach ($ritit as $leafValue) {
            $keys = array();
            foreach (range(0, $ritit->getDepth()) as $depth) {
                $keys[] = $ritit->getSubIterator($depth)->key();
            }
            $result[ join('.', $keys) ] = $leafValue;
        }

        return $result;
    }
}