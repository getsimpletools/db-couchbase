<?php

namespace Simpletools\Db\Couchbase;

class PrefixIterator implements \Iterator
{
	private $_CBResult 			= null;
	private $_currentRow		= null;
	private $_startKey = null;
	private $_endKey = null;
	private $_limit = 100;
	private $_skip = 0;
	private $_initSkip = 0;
	private $_bucket;
	private $_bucketName;
	private $_api = '';

	public function __construct($startKey=null, $endKey=null, $limit=null, $skip=null, $connectionName ='default')
	{
		$this->_api = new RestApi($connectionName);
		$this->_bucket  = new Bucket($connectionName);
		$this->_bucketName = $this->_bucket->getSettings()['bucket'];
		$this->_bucket = $this->_bucket->getApiConnector();

		$this->_startKey 	= $startKey;
		$this->_endKey 		= $endKey === null && $this->_startKey !== null ? $this->_startKey.'zzzzzzzzzzzz' : $endKey;
		$this->_limit 		= $limit === null? $this->_limit : $limit;
		$this->_skip 			= $skip === null? $this->_skip : $skip;
		$this->_initSkip 	= $this->_skip;
	}

	private function _runQuery()
	{
		$data = [
				'skip' => $this->_skip,
				'limit' => $this->_limit,
				'include_docs' => 'true',
		];

		if($this->_startKey!==null) 	$data['startkey'] = '"'.$this->_startKey.'"';
		if($this->_endKey!==null) 		$data['endkey'] 	= '"'.$this->_endKey.'"';

		$res = $this->_api->call('/pools/default/buckets/'.$this->_bucketName.'/docs',$data);

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