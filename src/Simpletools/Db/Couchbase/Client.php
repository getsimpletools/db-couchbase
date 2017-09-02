<?php

namespace Simpletools\Db\Couchbase;

class Client
{
    protected static $_gSettings    = array();

    protected $___connectionName	= 'default';
    protected $___connectionKey     = '';

    protected $___bucketName;
    protected $___bucket;

    public function __construct(array $settings = null,$connectionName='default')
    {
        if($settings) {
            $this->___connectionName = $connectionName;
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

        $cluster        = new CouchbaseCluster($settings['proto'] ? $settings['proto'].'://'.$settings['host'] : $settings['host']);
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

    public function query($query)
    {
        $query      = CouchbaseN1qlQuery::fromString($query);
        $response   = $this->___bucket->query($query);

        return Result($response);
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