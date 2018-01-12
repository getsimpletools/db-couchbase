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
		protected $_connectionName = 'default';
		protected $_result;
		protected $_fieldsMap  = array();

    public function __construct($bucketName,$bucket,$ns=null,$columns=array(),$connectionName='default')
    {
        $this->_bucketName  = $bucketName;
        $this->_ns          = $ns;
        $this->_connectionName = $connectionName;

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

    public function ns($ns)
    {
        $this->_ns = $ns;
        return $this;
    }

		public function doc($id =null,$connectionName=null, $ns =null)
		{
			$connectionName = $connectionName ? $connectionName : $this->_connectionName;
			$ns = $ns ?: $this->_ns;
			return new Doc($id,$connectionName, $ns);
		}

		public function docs(array $id,$connectionName=null,$ns =null)
		{
			$connectionName = $connectionName ? $connectionName : $this->_connectionName;
			$ns = $ns ?: $this->_ns;
			return new Docs($id,$connectionName,$ns);
		}

    public function run()
    {
			if($this->_result) return $this->_result;

			$this->_result = new Result($this->_bucket->query($this->getQuery()), $this->_bucket, $this->_bucketName);
			return $this;
    }

    public function limit($limit)
    {
        $this->_query['limit'] 	= (int) $limit;
        return $this;
    }

		public function offset($offset)
		{
			$this->_query['offset'] 	= (int) $offset;

			return $this;
		}

		public function sort()
		{
			$args = func_get_args();

			if(count($args) == 1)
			{
				$args = $args[0];
			}

			$this->_query['sort'] = $args;

			return $this;
		}

    public function find()
    {
        $args = func_get_args();
				$args = $args[0];
				$args[-1] 	= 'AND';

        $this->_query['where'][] 	= $args;

        return $this;
    }

    public function where()
    {
        return $this->find(func_get_args());
    }

		public function also()
		{
			return $this->find(func_get_args());
		}

    public function fields()
    {
        $args = func_get_args();

        if(count($args) == 1)
        {
            $args = $args[0];
        }

        $this->_query['fields'] = $args;

        return $this;
    }

	public function getQuery()
	{
		$paramCounter = 1;
		$params = array();
		
		if(!isset($this->_query['type']))
			$this->_query['type']		= "SELECT";

		if(!isset($this->_query['fields']))
		{
			$this->_query['fields']		= "*";
		}
		else
		{
			if(!is_array($this->_query['fields']) && !(
							$this->_query['fields'] instanceof N1ql
					))
			{
				$this->_query['fields'] = explode(',',$this->_query['fields']);
			}

			if(!($this->_query['fields'] instanceof N1ql))
			{
				$queryFields = [];


				foreach ($this->_query['fields'] as $index => $field)
				{
					if($field  instanceof N1ql)
					{
						$queryFields[] = $field;
						continue;
					}

					$field = trim(str_replace('`','',$field));

					if(strpos(strtolower($field),'meta().id') !== false) continue;
					if(strtolower($field) == '_ns') continue;

					if(strpos($field,'.') !== false && strpos(strtolower($field),'as') === false)
					{
						$field = '`'.implode('`.`',explode('.',$field)).'` as `'.$field.'`';
					}
					elseif(strpos(strtolower($field),'as') !== false)
					{
						$field = str_replace(' AS ', ' as ',$field);
						$as = explode(' as ',$field);
						$field = '`'.implode('`.`',explode('.',$as[0])).'` as `'.$as[1].'`';
					}
					else
					{
						$field = '`'.$field.'`';
					}

					$queryFields[] = $field;
				}
				$this->_query['fields'] = $queryFields;
			}
		}


		$query 		= array();
		$query[] 	= $this->_query['type'];

		if($this->_query['type']=='SELECT')
		{
			$query[] = 'meta().id as _id, _ns, ';
			$query[] = is_array($this->_query['fields']) ? implode(', ',$this->_query['fields']) : $this->_query['fields'];
			$query[] = 'FROM';
		}

		$query[] = $this->escapeKey($this->_bucketName);

		if(isset($this->_query['where']))
		{
			array_unshift($this->_query['where'],array('_ns', $this->_ns));
		}
		else
		{
			$this->_query['where'] =array(array('_ns', $this->_ns));
		}

		$query['WHERE'] = 'WHERE';

		if(is_array($this->_query['where']))
		{
			foreach($this->_query['where'] as $operands)
			{
				if(!isset($operands[2]))
				{
					if(@$operands[1]===null) {
						if($operands[0] instanceof N1ql)
						{
							$query[] = @$operands[-1] . ' ' . $operands[0];
						}
						else
						{
							$query[] = @$operands[-1] . ' ' . $this->escapeKey($operands[0]) . " IS NULL";
						}
					}
					else{

						if($operands[1] instanceof N1ql)
						{
							$query[] = @$operands[-1] . ' ' . $this->escapeKey($operands[0]) . " = " . (string) $operands[1];
						}
						else
						{
							$query[] = @$operands[-1] . ' ' . $this->escapeKey($operands[0]) . " = " . "$".$paramCounter++;
							$params[] = $operands[1];
						}
					}
				}
				else
				{
					$operands[1] = strtoupper($operands[1]);

					if($operands[1] == "IN" AND is_array($operands[2]))
					{
						$operands_ = array();

						foreach($operands[2] as $op)
						{
							if($op instanceof N1ql)
							{
								$operands_[] = $op;
							}
							else
							{
								$operands_[] = "$".$paramCounter++;
								$params[] = $op;
							}
						}

						$query[] = @$operands[-1].' '.$this->escapeKey($operands[0])." ".$operands[1]." (".implode(",",$operands_).')';
					}
					else
					{
						if($operands[2]===null) {
							$query[] = @$operands[-1] . ' ' . $this->escapeKey($operands[0]) . " " . $operands[1] . " NULL";
						}
						else
						{
							$query[] = @$operands[-1] . ' ' . $this->escapeKey($operands[0]) . " " . $operands[1] . " " . "$".$paramCounter++;
							$params[] = $operands[2];


							if($operands[2] instanceof N1ql)
							{
								$query[] = @$operands[-1] . ' ' . $this->escapeKey($operands[0]) . " " . $operands[1] . " " . $operands[2];
							}
							else
							{
								$query[] = @$operands[-1] . ' ' . $this->escapeKey($operands[0]) . " " . $operands[1] . " " . "$".$paramCounter++;
								$params[] = $operands[2];
							}
						}
					}
				}
			}
		}
		else
		{
			if($this->_query['where'] instanceof N1ql)
			{
				$query[] = 'id = '.$this->_query['where'];
			}
			else
			{
				$query[] = 'id = '."$".$paramCounter++;
				$params[] = $this->_query['where'];
			}
		}

		if(isset($this->_query['sort']))
		{
			$query[] = 'ORDER BY';

			if(!is_array($this->_query['sort']))
			{
				$this->_query['sort'] = explode(',', $this->_query['sort']);
			}

			$sort = array();
			foreach($this->_query['sort'] as $column)
			{
				$column = explode(' ', $column);
				foreach ($column as $k => $col)
				{
					if($col !='' && strtolower($col) != 'asc' && strtolower($col) != 'desc')
					{
						$column[$k] = $this->escapeKey($col);
					}
				}
				$sort[] = implode(' ',$column);
			}

			$query[] = implode(', ',$sort);
		}

		if(isset($this->_query['limit']))
		{
			$query[] = 'LIMIT '.$this->_query['limit'];
		}

		if(isset($this->_query['offset']))
		{
			$query[] = 'OFFSET '.$this->_query['offset'];
		}

		$this->_query = array();


		$query = \CouchbaseN1qlQuery::fromString(implode(' ',$query));
		if($params)
		{
			$query->positionalParams($params);
		}

		return $query;
	}

	public function _escape($value)
	{
		if($value instanceof N1ql)
		{
			return (string) $value;
		}
		elseif(is_float($value) || is_integer($value))
		{
			return $value;
		}
		elseif(is_bool($value))
		{
			return (int) $value;
		}
		elseif(is_null($value))
		{
			return null;
		}
		else
		{
			return "'".$value."'";
		}
	}

	public function escapeKey($key)
	{
		if($key instanceof N1ql)
		{
			return (string) $key;
		}
		elseif(trim($key)=='*')
		{
			return '*';
		}
		elseif(strpos($key,'.')===false)
		{
			$key = "`".trim(str_replace("`","",$key))."`";
			$key = str_replace([' as ', ' AS '], '` as `', $key);
			return $key;
		}
		else
		{
			$keys = explode('.',$key);
			foreach($keys as $index => $key)
			{
				$keys[$index] = "`".trim(str_replace("`","",$key))."`";
				$keys[$index] = str_replace([' as ', ' AS '], '` as `', $keys[$index]);
			}

			return implode('.',$keys);
		}
	}


	public function replace_first($needle , $replace , $haystack)
	{
		$pos = strpos($haystack, $needle);

		if ($pos === false)
		{
			// Nothing found
			return $haystack;
		}

		return substr_replace($haystack, $replace, $pos, strlen($needle));
	}


	public  function fetchRaw()
	{
		return $this->_result;
	}

	public function fetch()
	{
		return $this->_result->fetch();
	}

	public  function fetchAll()
	{
		return $this->_result->fetchAll();
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