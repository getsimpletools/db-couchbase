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

	class Model extends Bucket
	{
        protected static $____selfModel;
        protected $___connectionName;
        protected $___currentBucket;

		public function __construct($settings=false,$connectionName='default')
		{
			$this->___connectionName 	= $connectionName;
			$this->___currentBucket 	= defined('static::CURRENT_BUCKET') ? static::CURRENT_BUCKET : '';
			
			if($settings)
			{
				parent::__construct($settings,$connectionName);
			}

			$this->___bucketName = @self::$_gSettings[$this->___connectionName]['bucket'];
		}

        public static function self($settings=false,$connectionName='default')
        {
            if(isset(static::$____selfModel[static::class]))
                return static::$____selfModel[static::class];

            $obj = new static($settings,$connectionName);
           // $obj->injectDependency(); //todo check whether is needs

            if(method_exists($obj, 'init') && is_callable(array($obj,'init')))
            {
                call_user_func_array(array($obj,'init'),func_get_args());
            }

            return static::$____selfModel[static::class]   = $obj;
        }

		public function getConnectionName()
		{
			return defined('static::CONNECTION_NAME') ? static::CONNECTION_NAME : 'default';
		}

		public function getClient()
		{
			return Bucket::getInstance($this->getConnectionName());
		}

		public function injectDependency()
		{
			$this->setSettings($this->getClient()->getSettings());
		}

		public function doc($id =null,$connectionName=null, $ns =null)
		{
			$connectionName = $connectionName ? $connectionName : $this->___connectionName;
			$ns = $ns ?: $this->___ns;
			return new Doc($id,$connectionName, $ns);
		}

		public function docs(array $id,$connectionName=null,$ns =null)
		{
			$connectionName = $connectionName ? $connectionName : $this->___connectionName;
			$ns = $ns ?: $this->___ns;
			return new Docs($id,$connectionName,$ns);
		}

		public function getIterator($query,$params=array())
		{
			if(!$query) return false;

			$query = trim($query);

			preg_match('/(\w+)\s*('.implode('|',array(
							'\=', '\>', '\<', '\>\=', '\<\=', '\<\>', '\!\='
					)).')\s*\:\:(\w+)/i',$query,$matches);

			if(!isset($matches[1]) OR !isset($matches[3]) OR !$matches[1]) return false;

			$settings['query'] 				= trim(str_replace('::'.$matches[3],'::',$query));
			$settings['start'] 				= is_numeric($matches[3])? $matches[3]:"'".$matches[3]."'";
			$settings['iteratorField'] 		= $matches[1];
			$settings['iteratorDirection'] 	= $matches[2];
			$settings['params']				= $params;

			return new \Simpletools\Db\Couchbase\Iterator($this,$settings);
		}

		public function getPrefixIterator($startKey = null, $endKey=null, $limit=null, $includeDocs=true, $connectionName=null)
		{
			$connectionName = $connectionName ? $connectionName : $this->___connectionName;
			return new \Simpletools\Db\Couchbase\PrefixIterator($startKey, $endKey, $limit, $includeDocs, $connectionName);
		}
	}