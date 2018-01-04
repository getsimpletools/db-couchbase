<?php

namespace Simpletools\Db\Couchbase;

class PrefixIterator implements \Iterator
{
	private $_CBResult 			= null;
	private $_currentRow		= null;
	private $_startKey = null;
	private $_initStartKey = null;
	private $_endKey = null;
	private $_limit = 100;
	private $_skip = 0;
	private $_includeDocs = true;
	private $_bucket;
	private $_bucketName;
	private $_api = '';
	private $_lastId = null;

	public function __construct($startKey=null, $endKey=null, $limit=null, $includeDocs=true,  $connectionName ='default')
	{
		$this->_api = new RestApi($connectionName);
		$this->_bucket  = new Bucket($connectionName);
		$this->_bucketName = $this->_bucket->getSettings()['bucket'];
		$this->_bucket = $this->_bucket->getApiConnector();

		$this->_startKey 	= $startKey;
		$this->_initStartKey = $this->_startKey;
		$this->_endKey 		=  $endKey;
		$this->_limit 		= $limit === null? $this->_limit : $limit;
		$this->_includeDocs = $includeDocs;
	}

	private function _runQuery()
	{
		$data = [
				'skip' => $this->_skip,
				'limit' => $this->_limit,
		];

		if($this->_lastId !== null)
		{
			$this->_startKey = $this->_lastId;
		}

		if($this->_startKey!==null) 				$data['startkey'] = '"'.$this->_startKey.'"';
		if($this->_endKey!==null) 					$data['endkey'] 	= '"'.$this->_endKey.'"';
		if($this->_includeDocs === true) 		$data['include_docs'] 	= 'true';

		$res = $this->_api->call('/pools/default/buckets/'.$this->_bucketName.'/docs',$data);

		if($res)
		{
			$this->_CBResult = new Result($res, $this->_bucket, $this->_bucketName, true);
		}
	}

	private function _setRow()
	{
		if (!$this->_CBResult || !$this->_CBResult->count())
		{
			$this->_runQuery();
		}

		$this->_currentRow = $this->_CBResult->fetch();
		if ($this->_currentRow !== null)
		{
			if($this->_lastId == $this->_currentRow->id())
			{
				$this->_currentRow = $this->_CBResult->fetch();
			}
			if ($this->_currentRow !== null)
			{
				$this->_lastId = $this->_currentRow->id();
			}
		}
	}

	function rewind()
	{
		$this->_lastId = null;
		$this->_CBResult = null;
		$this->_currentRow = null;
		$this->_startKey = $this->_initStartKey;
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
		if($this->_CBResult == null && $this->_lastId === null)
		{
			$this->next();
		}

		return ($this->_currentRow===null) ? false : true;
	}
}

?>