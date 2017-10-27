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

class Docs  implements \Iterator
{
    protected $_ids     = array();
    protected $_docs    = array();

    protected $_bucket;
    protected $_connectionName = 'default';
    protected $_loaded = false;

    public function __construct(array $ids,$connectionName='default')
    {
        $this->_ids = $ids;
        $this->_connectionName = $connectionName;
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

		public function bucket(\Couchbase\Bucket $bucket)
		{
			$this->_bucket = $bucket;
			return $this;
		}

		public function loaded()
		{
			$this->_loaded = true;
			return $this;
		}


    public function load()
    {
        $this->_loaded = true;

        $docs = $this->
        connect()->connector()
            ->get($this->_ids);

        foreach($docs as $key=>$doc)
        {
            if($doc->error) {
                //$this->_docs[$key] = null;
                continue;
            }
            else {
                $this->_docs[$key] = (new Doc($key))->body($doc->value);
            }
        }

        return $this;
    }

    public function addDoc(\Simpletools\Db\Couchbase\Doc $doc)
		{
			$this->_ids[] = $doc->id();
			$this->_docs[$doc->id()] = $doc;
		}


    public function getDocs()
		{
			return $this->_docs;
		}

		public function count()
		{
			return count($this->_docs);
		}


    /*
     * ITERATOR
     */
    public function current()
    {
        return current($this->_docs);
    }

    public function next()
    {
        return next($this->_docs);
    }

    public function key()
    {
        return key($this->_docs);
    }

    public function valid()
    {
        return current($this->_docs)!==false;
    }

    public function rewind()
    {
        return reset($this->_docs);
    }
}