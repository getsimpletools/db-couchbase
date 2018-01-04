<?php

namespace Simpletools\Db\Couchbase;

class PrefixIterator implements \Iterator
{
	private $_CBResult 			= null;
	private $_currentRow		= null;
	private $_startKey = '';
	private $_endKey = '';
	private $_limit = 100;
	private $_skip = 0;
	private $_initSkip = 0;
	private $_bucket;
	private $_bucketName;
	private $_api = '';

	public function __construct($startKey, $endKey=null, $limit=null, $skip=null, $connectionName ='default')
	{
		$this->_api = new RestApi($connectionName);
		$this->_bucket  = new Bucket($connectionName);
		$this->_bucketName = $this->_bucket->getSettings()['bucket'];
		$this->_bucket = $this->_bucket->getApiConnector();

		$this->_startKey 	= $startKey;
		$this->_endKey 		= $endKey === null? $this->_startKey.'zzzzzzzzzzzz' : $endKey;
		$this->_limit 		= $limit === null? $this->_limit : $limit;
		$this->_skip 			= $skip === null? $this->_skip : $skip;
		$this->_initSkip 	= $this->_skip;
	}

	private function _runQuery()
	{
		$res = $this->_api->call('/pools/default/buckets/'.$this->_bucketName.'/docs',[
				'startkey' =>'"'.$this->_startKey.'"',
				'endkey' => '"'.$this->_endKey.'"',
				'skip' => $this->_skip,
				'limit' => $this->_limit,
				'include_docs' => 'true',
		]);

		if($res)
		{
			$this->_CBResult = new Result($res, $this->_bucket, $this->_bucketName);
		}
	}

	private function _setRow()
	{
		if(!$this->_CBResult || !$this->_CBResult->count())
		{
			if($this->_CBResult !== null)
			{
				$this->_skip += $this->_limit;
			}

			$this->_runQuery();
		}

		$this->_currentRow = $this->_CBResult->fetch();
	}

	function rewind()
	{
		$this->_skip = $this->_initSkip;
		$this->_CBResult = null;
	}

	function current()
	{
		return $this->_currentRow;
	}

	function key()
	{
		return $this->_currentRow ? $this->_currentRow->id() : null;
	}

	function next()
	{
		$this->_setRow();
		return $this->_currentRow;
	}

	function valid()
	{
		if($this->_CBResult == null && $this->_skip == $this->_initSkip)
		{
			$this->next();
		}

		return ($this->_currentRow===null) ? false : true;
	}
}

?>