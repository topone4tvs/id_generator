<?php
namespace Jiuyan\IdGenerator\Generator;

use PDO;

class ApcIdGeneratorFactory
{
    private static $instance = null;
    private $_table_name = null;

    protected static $config = [];
    protected function __construct($tableName)
    {
        $this->_table_name = $tableName;
    }

    public static function setConfig($config)
    {
        self::$config = $config;
    }
    public function getConfig()
    {
        return self::$config;
    }

    /**
     * @param $tableName
     * @return ApcIdGeneratorFactory
     */
    public static function getInstance($tableName)
    {
        if (!isset(self::$instance[$tableName]) || !self::$instance[$tableName]) {
            self::$instance[$tableName] = new self($tableName);
        }
        return self::$instance[$tableName];
    }

    /**
     * @return ApcIdGenerator
     */
    public function getApcIdGenerator()
    {
        $config = $this->getConfig();
        $user = $config['user'];
        $password = $config['password'];
        $host = $config['dbhost'];
        $port = isset($config['dbport']) ? $config['dbport'] : '3306';
        $dbname = $config['dbname'];
        $genId = new ApcIdGenerator($this->_table_name);
        
        $genId->setConnection(new PDO('mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname, $user, $password));
        return $genId;
    }
}
