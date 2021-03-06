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

class Result implements \Iterator
{
		protected $_couchbaseResult;
		protected $_bucket;
		protected $_bucketName;
		protected $_isRestApi;

    public function __construct($response, $bucket,$bucketName, $isRestApi = false)
    {
    	$this->_bucket = $bucket;
    	$this->_bucketName = $bucketName;
    	$this->_couchbaseResult = $response;
    	$this->_isRestApi = $isRestApi;

    	return $this;
    }

    public function count()
		{
			return isset($this->_couchbaseResult->rows) ? count($this->_couchbaseResult->rows) : 0;

		}
    public function isEmpty(){}


		public function fetchAll()
		{
			$docs = new Docs([]);
			$docs->loaded();
			$docs->bucket($this->_bucket);

			if(@$this->_couchbaseResult->rows)
			{
				foreach ($this->_couchbaseResult->rows as $row)
				{
					if($this->_isRestApi)
					{
						$doc = new Doc($row->id);
						$doc->bucket($this->_bucket)
								->body(@$row->doc->json)
								->loaded();
					}
					else
					{
						$id = @$row->_id;
						unset($row->_id);
						$doc = new Doc($id);
						$doc->bucket($this->_bucket)
								->body(isset($row->{$this->_bucketName})? $row->{$this->_bucketName}:$row);

						if($id !== null)
							$doc->loaded();

					}

					$docs->addDoc($doc);
				}
			}

			return $docs;
		}

		public function fetch()
		{
			$doc = null;
			if(@$this->_couchbaseResult->rows)
			{
				if($row = array_shift($this->_couchbaseResult->rows))
				{
					if($this->_isRestApi)
					{
						$doc = new Doc($row->id);
						$doc->bucket($this->_bucket)
								->body(@$row->doc->json)
								->loaded();
					}
					else
					{
						$id = @$row->_id;
						unset($row->_id);
						$doc = new Doc($id);
						$doc->bucket($this->_bucket)
								->body(isset($row->{$this->_bucketName})? $row->{$this->_bucketName}:$row);

						if($id !== null)
							$doc->loaded();
					}
				}
			}

			return $doc;
		}

		/**
		 * @return array - Fields:  lapsedTime, executionTime, resultCount, resultSize, mutationCount (eg. Updated rows)
		 */
		public function getMetrics()
		{
			return isset($this->_couchbaseResult->metrics) ? $this->_couchbaseResult->metrics : [];
		}

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