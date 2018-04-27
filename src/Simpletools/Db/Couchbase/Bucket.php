<?php

namespace Simpletools\Db\Couchbase;


class Bucket
{
    protected static $_gSettings    = array();

    protected $___connectionName	= 'default';
    protected $___connectionKey     = '';

    protected $___bucketName;
    protected $___bucket;
    protected $___ns;

    public function __construct($connectionName='default',array $settings = null)
    {
        $this->___connectionName = $connectionName;

        if($settings) {
            self::settings($settings, $connectionName);
        }
    }

    public function bucket($bucket = null)
    {
    	if(empty($bucket)){
    		return $this->___bucketName;
		}else{
			$this->___bucketName = $bucket;
		}
    }

    public function connect()
    {
        if(!isset(self::$_gSettings[$this->___connectionName]))
        {
            throw new \Exception("Please specify your connection settings first");
        }

        $settings       = self::$_gSettings[$this->___connectionName];

				$clientSettings = '';
				if(@$settings['clientSettings'] && is_array($settings['clientSettings']))
				{
					$clientSettings = '?'.http_build_query($settings['clientSettings']);
				}

				if(!$this->___bucketName)
					$this->___bucketName = $settings['bucket'];

        $this->___connectionKey = $this->___connectionName.'/'.$this->___bucketName;
        $connection = Connection::getOne($this->___connectionKey);

        if($connection)
        {
            $this->___bucket = $connection;
						$this->___bucketName = $settings['bucket'];
            return $this;
        }

        if(isset($settings['user']))
        {
						$uri = is_array($settings['host']) ? implode(',',$settings['host']) : (isset($settings['proto']) ? $settings['proto'].'://'.$settings['host'] : $settings['host']) . (isset($settings['port']) ? ':'.$settings['port'] : '');
            $cluster = new \Couchbase\Cluster( $uri.$clientSettings);
            $cluster->authenticateAs($settings['user'], $settings['pass']);
        }
        else
        {
            $authenticator  = new \Couchbase\ClassicAuthenticator();
            $authenticator->bucket($this->___bucketName, $settings['pass']);

            $uri = is_array($settings['host']) ? implode(',',$settings['host']) : (isset($settings['proto']) ? $settings['proto'].'://'.$settings['host'] : $settings['host']) . (isset($settings['port']) ? ':'.$settings['port'] : '');
            $cluster        = new \CouchbaseCluster($uri.$clientSettings);
            $cluster->authenticate($authenticator);
        }


        $this->___bucket = $cluster->openBucket($this->___bucketName);
        Connection::setOne($this->___connectionKey,$this->___bucket);

        return $this;
    }

    public static function settings(array $settings,$connectionName='default')
    {
        $connectionName = (isset($settings['connectionName']) ? $settings['connectionName'] : $connectionName);

        $settings['host'] = isset($settings['host']) ? $settings['host'] : @$settings['hosts'];

        if(!isset($settings['host']))
        {
            throw new \Exception('Please specify host');
        }

        self::$_gSettings[$connectionName] = $settings;
    }

		public static function getSettings($connectionName='default')
		{
			return self::$_gSettings[$connectionName];
		}

    protected $_query;

    public function prepare($query)
    {
        $this->_query = $query;
        return $this;
    }

    public function execute()
    {
        $args = func_get_args();
        if(is_array($args[0])) $args = $args[0];

        return $this->_query($this->_prepareQuery($this->_query,$args));
    }

		public function query($query)
		{
			$this->_query = $query;
			return $this->_query($this->_prepareQuery($this->_query,[]));
		}

    protected function _query($query)
    {
        $this->connect();
        return new Result($this->___bucket->query($query), $this->___bucket, $this->___bucketName);
    }

    public function fetchAll(\Simpletools\Db\Couchbase\Result $result)
		{
			return $result->fetchAll();
		}

		public function fetch(\Simpletools\Db\Couchbase\Result $result)
		{
			return $result->fetch();
		}

		public function getQuery($args = [])
		{
			return $this->_prepareQuery($this->_query,$args);
		}

    private function _prepareQuery($query, array $args)
    {
			$paramCounter =1;

			preg_match('/select\s(.+?)\sfrom\s/is', $query,$match);
			if(@$match[1])
			{
				$originalSelect = $match[1];
				$newSelect=[];

				if(strpos(strtolower($originalSelect),'meta().id as _id')=== false)
				{
					$newSelect[] = 'meta().id as _id';
				}
				if(strpos(strtolower($originalSelect),'_ns')=== false)
				{
					$newSelect[] = '_ns';
				}

				foreach(explode(',',trim($match[1])) as $index => $field)
				{
					if(strpos($field,'meta().id') !== false) continue;

					$field = trim(str_replace('`','',$field));
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
					elseif($field =='*'){}
					else
					{
						$field = '`'.$field.'`';
					}

					$newSelect[] = $field;
				}

				$query = str_replace($originalSelect, implode(', ',$newSelect), $query);
			}

			foreach($args as $arg)
			{
					$query = $this->replace_first('?', '$'.$paramCounter++, $query);
			}

			$query = \CouchbaseN1qlQuery::fromString($query);
			$query->positionalParams($args);

			return $query;
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

    public function get($id)
    {
        $this->connect();
        return $this->___bucket->get($id);
    }

	public function remove($id)
	{
		$this->connect();
		return $this->___bucket->remove($id);
	}

	public function upsert($id, $value, $options = [])
	{
		$this->connect();
		return $this->___bucket->upsert($id, $value, $options);
	}

    public function getApiConnector()
    {
        $this->connect();
        return $this->___bucket;
    }

    public function __get($ns)
    {
        $this->connect();
        if(!$this->___bucketName)
        {
            throw new \Exception('Please specify your default bucket first');
        }

				$this->connect();
				if($ns)
					$this->___ns = $ns;

				return new QueryBuilder($this->___bucketName,$this->___bucket,$this->___ns, array(), $this->___connectionName);
    }

    public function __call($method, $arguments)
    {
        $this->connect();
        if(!$this->___bucketName)
        {
            throw new \Exception('Please specify your default bucket first');
        }

        return call_user_func_array(array(
            (new QueryBuilder($this->___bucketName,$this->___bucket,$ns=null)),$method),
            $arguments
        );
    }

    public function ns($ns=null)
    {
        $this->connect();
        if($ns)
        	$this->___ns = $ns;

        return new QueryBuilder($this->___bucketName,$this->___bucket,$this->___ns);
    }
}