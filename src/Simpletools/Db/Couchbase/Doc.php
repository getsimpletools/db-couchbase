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
use Simpletools\ServiceQ\Exception;

class Doc
{
    protected $_bucket;
    protected $_meta;
    protected $_body;
    protected $_id;
    protected $_loaded = false;
    protected $_connectionName = 'default';

    public function __construct($id=null,$connectionName='default')
    {
        //todo
        if(!$id) $id = uniqid();

        $this->_connectionName = $connectionName;

        $this->_meta = new Meta((object) array(
            'id'    => $id
        ));

        $this->_body = new Body((object) array());

        $this->_id = $id;
    }

    public function connect()
    {
        if(!isset($this->_bucket))
            $this->_bucket  = (new Bucket($this->_connectionName))->getApiConnector();

        return $this;
    }

    public function connector()
    {
        return $this->_bucket;
    }

    public function id($id)
    {
        $this->_id = $id;
    }

    public function loaded()
    {
        $this->_loaded = true;
        return $this;
    }

    public function load()
    {
        $this->_loaded = true;

        $doc = $this->
            connect()->connector()
            ->get($this->_id);

        return $this
            ->body($doc->value)
            ->meta(array(
                'id'    => $this->_id,
                'flags'  => $doc->flags,
                'cas'   => $doc->cas,
                'token' => $doc->token
            ));
    }

    public function save()
    {
        $this->connect();

        $raw = $this->_body->toObject();
        $res = $this->_bucket->upsert($this->_id,$raw);

        if($res->error)
        {
            throw new \Exception($res->error);
        }

        return $this;
    }

    public function remove()
    {
        $this->connect();

        try {
            $res = $this->_bucket->remove($this->_id);
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
            throw new \Exception($res->error);
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
            throw new \Exception("Provided property `{$name}` doesn't exist");
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
            throw new \Exception("meta must be a type of either array or object");
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
            return $this->_body;

        if($body instanceof Body)
        {
            $this->_body = new Body($body);
            return $this;
        }

        if(is_array($body)) $body = (object)$body;

        $this->_body = new Body($body, !$this->_loaded);
        return $this;
    }

    public function unset($fields)
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