<?php

namespace Simpletools\Db\Couchbase;

class Iterator implements \Iterator
{
	private $_query 			= '';
	private $_currentId 		= 0;
	private $_startId			= 0;
	private $_model 			= '';
	private $_params			= array();
	private $_cursor 			= '';
	private $_iteratorField		= '';
	private $_currentRow		= false;

	public function __construct($model,$settings)
	{
		$this->_query 			= $settings['query'];
		$this->_params 			= isset($settings['params']) ? $settings['params'] : array();
		$this->_currentId 		= $settings['start'];
		$this->_startId 		= $settings['start'];
		$this->_model 			= $model;
		$this->_iteratorField 	= $settings['iteratorField'];
	}

	private function _runQuery($rewind=false)
	{
		if($rewind)
		{
			$this->_currentId = $this->_startId;
		}

		if(!count($this->_params))
			$this->_cursor = $this->_model->query(str_replace('::',''.$this->_currentId.'',$this->_query));
		else
			$this->_cursor = $this->_model->prepare(str_replace('::',''.$this->_currentId.'',$this->_query))->execute($this->_params);
		
	}

	private function _setRow()
	{
		if(!$this->_cursor OR !($row = $this->_cursor->fetch()))
		{
			$this->_runQuery();

			if(!($row = $this->_cursor->fetch()))
			{
				$this->_currentRow = false;
			}
		}

		if($row)
		{
			$this->_currentId 	= $row->body->{$this->_iteratorField};
			$this->_currentRow = $row;
		}
	}

	function rewind()
	{
		$this->_runQuery(true);
	}

	function current()
	{
		return $this->_currentRow;
	}

	function key()
	{
		return $this->_currentId;
	}

	function next()
	{
		$this->_setRow();
		return $this->_currentRow;
	}

	function valid()
	{
		if($this->_currentId === $this->_startId)
		{
			$this->next();
		}

		return ($this->_currentRow===false) ? false : true;
	}
}

?>