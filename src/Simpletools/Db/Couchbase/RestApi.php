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

class RestApi
{
	protected static $_instance;

	private $_url='';
	private $_authorization='';
	private $_curlSettings ='';

	public static function getInstance($connectionName='default')
	{
		if (!self::$_instance)
		{
			self::$_instance = new static($connectionName);
		}

		return self::$_instance;
	}

	public function __construct($connectionName='default')
	{
		$settings = Bucket::getSettings($connectionName);
		$this->_url = is_array($settings['host'])
				? $settings['host'][array_rand($settings['host'])] . (isset($settings['port']) ? ':'.$settings['port'] : ':8091')
				: (isset($settings['proto']) ? $settings['proto'].'://'.$settings['host'] : $settings['host']) . (isset($settings['port']) ? ':'.$settings['port'] : ':8091');
		$this->_authorization = base64_encode($settings['user'].":".$settings['pass']);

		return $this;
	}

	public function curlSettings(array $settings)
	{
		$this->_curlSettings = $settings;
		return $this;
	}

	public function call($endPoint, $data = [], $method = "GET")
	{
		if(substr($this->_url,0,4) == 'http' )
		{
			$url = $this->_url.$endPoint;
		}
		else
		{
			$url = 'http://'.$this->_url.$endPoint;
		}

		$data = http_build_query($data);

		if($method == 'GET')
		{
			$url .= '?'.$data;
		}

		$ch = curl_init($url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

		if($method == 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}

		if($this->_authorization)
		{
			$headers = array(
					'Authorization: Basic '. $this->_authorization
			);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		if(is_array($this->_curlSettings))
		{
			foreach($this->_curlSettings as $curlOption => $curlValue)
			{
				curl_setopt($ch, $curlOption,$curlValue);
			}
		}

		$response = curl_exec($ch);

		if(!$res = json_decode($response))
		{
			throw new \Exception('Couchbase Api Error: '.$response,curl_getinfo($ch, CURLINFO_HTTP_CODE));
		}
		curl_close($ch);

		return $res;
	}
}