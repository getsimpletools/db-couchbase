<?php

namespace Simpletools\Db\Couchbase;

class N1ql
{
	protected $_statement = '';

	public function __construct($statement)
	{
		$this->_statement = $statement;
	}

	public function __toString()
	{
		return $this->_statement;
	}
}