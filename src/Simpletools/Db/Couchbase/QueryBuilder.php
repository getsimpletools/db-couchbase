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

class QueryBuilder implements \Iterator
{
    protected $_ns;
    protected $_bucketName;
    protected $_query = array();
    protected $_bucket;
    protected $_docId;

    public function __construct($bucketName,$bucket,$ns=null,$columns=array())
    {
        $this->_bucketName  = $bucketName;
        $this->_ns          = $ns;

        if($columnsCount = count($columns))
        {
            if($columnsCount == 1)
            {
                $columns = $columns[0];
            }

            $this->_query['columns'] = $columns;
        }

        $this->ns($ns);
        $this->_bucket = $bucket;
    }

    public function doc($id)
    {
        $this->_docId = $id;
        if(isset($this->_query[$id])) unset($this->_query[$id]);
        $this->_query[$id] = array();
        return $this;
    }

    public function ns($ns)
    {
        $this->_ns = $ns;
        return $this;
    }

    public function insert(array $data)
    {
        $this->_addQueryMethod('insert',$data);

        return $this;
    }

    public function set(array $data)
    {
        $this->_addQueryMethod('upsert',$data);

        return $this;
    }

    public function unset($data)
    {
        if(!is_array($data)) $data = array($data);
        $this->_addQueryMethod('remove',$data);

        return $this;
    }

    public function push($data)
    {
        if(!is_array($data)) $data = array($data);

        $this->_addQueryMethod('arrayAppendAll',$data);
        return $this;
    }

    public function pushUnique($data)
    {
        if(!is_array($data)) $data = array($data);

        $this->_addQueryMethod('arrayAddUnique',$data);
        return $this;
    }

    public function increment(array $data)
    {
        $this->_addQueryMethod('increment',$data);
        return $this;
    }

    public function decrement(array $data)
    {
        $this->_addQueryMethod('decrement',$data);
        return $this;
    }

    public function unshift($data)
    {
        if(!is_array($data)) $data = array($data);

        $this->_addQueryMethod('arrayPrependAll',$data);
        return $this;
    }

    protected function _addQueryMethod($method,$data)
    {
        if(!isset($this->_query[$this->_docId][$method])) $this->_query[$this->_docId][$method] = array();
        $this->_query[$this->_docId][$method] = $data + $this->_query[$this->_docId][$method];
    }

    public function run()
    {
        print_r($this->_query);
    }

    public function limit($limit)
    {
        $this->_query['limit'] 	= $limit;
        return $this;
    }

    public function find()
    {
        $args = func_get_args();
        if(count($args)==1) $args = $args[0];

        $this->_query['where'][] 	= $args;

        return $this;
    }

    public function where()
    {
        return $this->find(func_get_args());
    }

    public function columns()
    {
        $args = func_get_args();

        if(count($args) == 1)
        {
            $args = $args[0];
        }

        $this->_query['columns'] = $args;

        return $this;
    }

    /*
     * ITERATOR
     */
    public function current(){

    }

    public function next(){

    }

    public function key(){

    }

    public function valid(){

    }

    public function rewind(){

    }
}