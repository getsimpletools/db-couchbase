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

class Session implements \SessionHandlerInterface
{
	protected static $_self;
	private $_prefix = 'ses';
	private $_cb;
	public $maxLifeTime = 1800;// 30min

	public function __construct(array $settings = [], $connectionName='session')
	{
		if(isset($settings['maxLifeTime']))
		{
			$this->maxLifeTime = $settings['maxLifeTime'];
		}
		elseif (get_cfg_var("session.gc_maxlifetime"))
		{
			$this->maxLifeTime = get_cfg_var("session.gc_maxlifetime");
		}

		$this->_cb = Model::self(false,$connectionName);
	}

	public static function self(array $settings = [], $connectionName='session')
	{
		if (!self::$_self)
		{
			self::$_self = new static($settings, $connectionName);
		}

		return self::$_self;
	}

	public function open($savePath, $sessionName)
	{
		return true;
	}

	public function close()
	{
		return true;
	}

	public function read($id)
	{
		try
		{
			return (string)$this->_cb->get($this->_prefix."_".$id)->value;
		}
		catch (\Exception $e)
		{
			if ($e->getCode() == 13) return '';
			throw $e;
		}
	}

	public function write($id, $data)
	{
		$this->_cb->upsert($this->_prefix."_".$id,$data,[
				'expiry' => $this->maxLifeTime
		]);

		return true;
	}


	public function destroy($id)
	{
		try{
			$this->_cb->doc($this->_prefix."_".$id)->load()->remove();
		}catch (\Exception $e){}
		return true;
	}

	public function gc($maxlifetime)
	{
		return true;
	}
}