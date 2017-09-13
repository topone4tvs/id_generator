<?php
namespace Jiuyan\IdGenerator\Generator;

/**
 * ID生成器，通过APC实现
 * @author laughing
 */

use Exception;
use PDO;
use Psr\Log\LoggerInterface;

class ApcIdGenerator
{
    const TABLE_NAME = 'common_generator';
    const IDGEN_MAXID_CACHEKEY_PREFIX = 'IDGEN_TABLE_MAXID';
    const IDGEN_CURRENTID_CACHEKEY_PREFIX = 'IDGEN_TABLE_CURRENTID';
    const IDGEN_LOCK_CACHEKEY_PREFIX = 'IDGEN_LOCK';
    const MAX_FETCHKEY_TRYTIMES = 6;
    const NO_ID = -1;
    const APP_NAME = 'apc-idgenerator';

    /**
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }

    private $_connection = null;
    private $_table_name = null;

    public $_start_id = 10000;//在没有初始id的时候，赋初始值

    public function __construct($tableName = null)
    {
        $this->_table_name = $tableName ?: self::TABLE_NAME;
    }

    /**
     * 取ID
     *
     * @param string $name
     * @param string $cachedStep
     * @return int ID
     */
    public function getNextId($name, $step = 1, $cachedStep = 100, $origin = 10000)
    {
        $tryTimes = 0;
        $this->_start_id = (int)$origin;

        while (true) {

            if ($this->getLock($name) === false) {
                if ($tryTimes < self::MAX_FETCHKEY_TRYTIMES) {
                    $tryTimes++;
                } else {
                    $this->getLogger()->error('APC_GET_ID get id times:' . $tryTimes . ',cant get!' . 'name:' . $name);
                    throw new Exception("APC 异常!", 1);
                }
                usleep(100000);
                continue;
            }
            $currId = $this->getNextIdFromApc($name, $step);

            if ($currId == self::NO_ID) {
                $currId = $this->getNextIdFromDb($name, $step, $cachedStep);
            }
            $this->releaseLock($name);
            break;
        }

        return $currId;
    }

    private function getLock($name)
    {
        return $this->cacheAdd($this->getLockCacheKey($name), 1, 1);
    }

    private function releaseLock($name)
    {
        return $this->cacheDelete($this->getLockCacheKey($name));
    }

    private function getNextIdFromApc($name, $step)
    {
        $currentIdCacheKey = $this->getCurrentIdCacheKey($name);
        $maxId = $this->cacheFetch($this->getMaxIdCacheKey($name));
        $currId = $this->cacheFetch($currentIdCacheKey);

        if (($currId === false) || ($maxId === false)) {
            return self::NO_ID;
        }

        $currId += (int)$step;
        $this->cacheStore($currentIdCacheKey, $currId);

        if ($currId <= $maxId) {
            return $currId;
        }
        return self::NO_ID;
    }

    private function setSerialIdsIntoCache($name, $currentId, $maxId)
    {

        if ($this->cacheStore($this->getMaxIdCacheKey($name), $maxId) == true) {
            $this->getLogger()->notice("set `{$name}` max id: $maxId");
        } else {
            $this->getLogger()->error("when set `{$name}` max id:$maxId, some error happened.");
        }

        if ($this->cacheStore($this->getCurrentIdCacheKey($name), $currentId) == true) {

            $this->getLogger()->notice("set `{$name}` current id: $currentId");
        } else {
            $this->getLogger()->error("when set `{$name}` current id:$currentId, some error happened.");
        }
    }

    private function getNextIdFromDb($name, $step, $length)
    {
        $startId = $endId = -1;
        try {
            $this->getConnection()->beginTransaction();

            $sql = 'SELECT currentId from ' . $this->getTablename() . ' where keyName=:keyname FOR UPDATE';
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue(':keyname', $name);
            $stmt->execute();
            $currentId = $stmt->fetch(PDO::FETCH_COLUMN);
            if ($currentId === false) {
                $startId = $this->_start_id;//在没有初始id的时候，赋初始值
                $endId = $startId + ($step * $length);
                $this->insertIDRow($name, $endId, $step, $length);
            } else {
                $startId = $currentId + $step;
                $endId = $currentId + ($step * $length);
                $this->updateCurrentID($name, $endId);
            }

            $this->setSerialIdsIntoCache($name, $startId, $endId);

            $this->getConnection()->commit();
            $this->getLogger()->notice("fetch `{$name}` keyname's id from {$startId} to {$endId};");

        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
        return $startId;
    }

    private function getCurrentIdCacheKey($name)
    {
        return self::IDGEN_CURRENTID_CACHEKEY_PREFIX . ':' . $name;
    }

    private function getMaxIdCacheKey($name)
    {
        return self::IDGEN_MAXID_CACHEKEY_PREFIX . ':' . $name;
    }

    private function getLockCacheKey($name)
    {
        return self::IDGEN_LOCK_CACHEKEY_PREFIX . ':' . $name;
    }

    private function updateCurrentID($name, $id)
    {
        $sql = "UPDATE {$this->getTableName()} SET currentId = :currentId where keyName=:keyName";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindValue(':currentId', $id);
        $stmt->bindValue(':keyName', $name);
        $stmt->execute();
    }

    private function insertIDRow($name, $currentId, $step, $cachedStep)
    {
        $sql = "INSERT {$this->getTableName()} (keyName,currentId,step,cacheStep) values(:keyName,:currentId,:step,:cacheStep)";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindValue(':keyName', $name);
        $stmt->bindValue(':currentId', $currentId);
        $stmt->bindValue(':step', $step);
        $stmt->bindValue(':cacheStep', $cachedStep);
        $stmt->execute();
    }

    private function getTableName()
    {
        return $this->_table_name;
    }

    public function getConnection()
    {
        return $this->_connection;
    }

    public function setConnection(PDO $conn)
    {
        $this->_connection = $conn;
    }

    /**
     * @return
     */
    private function getLogger()
    {
        if (self::$logger) {
            return self::$logger;
        }
        return $this;
    }

    private function notice($msg)
    {

    }

    private function info($msg)
    {

    }

    private function error($msg)
    {

    }

    private function debug($msg)
    {

    }

    /**
     * @param $key
     * @param $var
     * @param int $ttl
     * @return bool
     * @throws Exception
     */
    private function cacheAdd($key, $var, $ttl = 0)
    {
        $function_name = 'apc_add';
        if (function_exists($function_name)) {
            return apc_add($key, $var, $ttl);
        }
        $function_name = 'apcu_add';
        if (function_exists($function_name)) {
            return apcu_add($key, $var, $ttl);
        }
        throw new Exception("ext-apc or ext-apcu must install!");
    }

    private function cacheDelete($key)
    {
        $function_name = 'apc_delete';
        if (function_exists($function_name)) {
            return apc_delete($key);
        }
        $function_name = 'apcu_delete';
        if (function_exists($function_name)) {
            return apcu_delete($key);
        }
        throw new Exception("ext-apc or ext-apcu must install!");
    }

    private function cacheFetch($key, &$success = null)
    {
        $function_name = 'apc_fetch';
        if (function_exists($function_name)) {
            return apc_fetch($key, $success);
        }
        $function_name = 'apcu_fetch';
        if (function_exists($function_name)) {
            return apcu_fetch($key, $success);
        }
        throw new Exception("ext-apc or ext-apcu must install!");
    }

    private function cacheStore($key, $var, $ttl = 0)
    {
        $function_name = 'apc_store';
        if (function_exists($function_name)) {
            return apc_store($key, $var, $ttl);
        }
        $function_name = 'apcu_store';
        if (function_exists($function_name)) {
            return apcu_store($key, $var, $ttl);
        }
        throw new Exception("ext-apc or ext-apcu must install!");
    }
}
