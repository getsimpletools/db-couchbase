<?php

namespace Simpletools\Db\Couchbase;

class Bucket
{
    protected static $_gSettings    = array();

    protected $___connectionName	= 'default';
    protected $___connectionKey     = '';

    protected $___bucketName;
    protected $___bucket;

    public function __construct($connectionName='default',array $settings = null)
    {
        $this->___connectionName = $connectionName;

        if($settings) {
            self::settings($settings, $connectionName);
        }
    }

    public function bucket($bucket)
    {
        $this->___bucketName = $bucket;
    }

    public function connect()
    {
        if(!isset(self::$_gSettings[$this->___connectionName]))
        {
            throw new \Exception("Please specify your connection settings first");
        }

        $settings       = self::$_gSettings[$this->___connectionName];

        $this->___connectionKey = $this->___connectionName.'/'.$this->___bucketName;
        $connection = Connection::getOne($this->___connectionKey);
        if($connection)
        {
            $this->___bucket = $connection;
            return $this;
        }

        if(!$this->___bucketName)
            $this->___bucketName = $settings['bucket'];

        $authenticator  = new \Couchbase\ClassicAuthenticator();
        $authenticator->bucket($this->___bucketName, $settings['pass']);

        $cluster        = new \CouchbaseCluster(isset($settings['proto']) ? $settings['proto'].'://'.$settings['host'] : $settings['host']);
        $cluster->authenticate($authenticator);

        $this->___bucket = $cluster->openBucket($this->___bucketName);
        Connection::setOne($this->___connectionKey,$this->___bucket);

        return $this;
    }

    public static function settings(array $settings,$connectionName='default')
    {
        $connectionName = (isset($settings['connectionName']) ? $settings['connectionName'] : $connectionName);

        if(!isset($settings['host']))
        {
            throw new \Exception('Please specify host');
        }

        self::$_gSettings[$connectionName] = $settings;
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

        return $this->query($this->_prepareQuery($this->_query,$args));
    }

    public function query($query)
    {
        $this->connect();

        $query      = \CouchbaseN1qlQuery::fromString($query);
        $response   = $this->___bucket->query($query);

        return new Result($response);
    }

    private function _prepareQuery($query, array $args)
    {
        foreach($args as $arg)
        {
            if(is_string($arg))
            {
                if(strpos($arg,'?') !== false)
                {
                    $arg = str_replace('?','<--SimpleCouchbase-QuestionMark-->',$arg);
                }

                $arg = "'".addslashes($arg)."'";
            }

            if($arg === null)
            {
                $arg = 'NULL';
            }

            $query = $this->replace_first('?', $arg, $query);
        }

        if(strpos($query,'<--SimpleCouchbase-QuestionMark-->') !== false)
        {
            $query = str_replace('<--SimpleCouchbase-QuestionMark-->','?',$query);
        }

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

        return new QueryBuilder($this->___bucketName,$this->___bucket,$ns);
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

    public function ns($ns)
    {
        $this->connect();
        return new QueryBuilder($this->___bucketName,$this->___bucket,$ns);
    }
}