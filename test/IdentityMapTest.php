<?php

if (!isset($service))
{
    $service = require __DIR__."/init/bootstrap.php";
}

require_once "PommBench.php";

use Pomm\Service;
use Pomm\Connection\Database;
use Pomm\Identity\IdentityMapperNone;
use Pomm\Identity\IdentityMapperStrict;
use Pomm\Identity\IdentityMapperSmart;
use Pomm\Identity\IdentityMapperInterface;

class IdentityMapTest extends \lime_test
{
    protected $service;
    protected $map;

    public function initialize($service)
    {
        $this->service = $service;
        $this->map = $this->service->getDatabase()->createConnection(new Pomm\Identity\IdentityMapperNone())->getMapFor('Bench\PommBench');
        $this->map->createTable();
        $this->map->feedTable(10);

        return $this;
    }

    public function __destruct()
    {
        $this->map->dropTable();
        parent::__destruct();
    }

    public function setMapper(IdentityMapperInterface $mapper, $nopk = false)
    {
        $this->map = $this->service->getDatabase()->createConnection($mapper)->getMapFor('Bench\PommBench');

        if ($nopk === true)
        {
            $this->map->removePkDefinition();
            $this->info(sprintf("Testing IM '%s' on table without PK.", get_class($mapper)));
        }
        else
        {
            $this->info(sprintf("Testing IM '%s'.", get_class($mapper)));
        }


        return $this;
    }

    public function testCreateObject($same = true)
    {
        $object = $this->map->createObject();
        $object->setDataInt(4);
        $object->setDataChar('plop');
        $object->setDataBool(true);

        $this->map->saveOne($object);
        $object['pika'] = 'chu';
        $result = $this->map->findWhere("id = ?", array($object->id));
        $other_object = count($result) == 1 ? $result->current() : null;

        if ($same === true)
        {
            $this->ok($other_object->isModified(), 'same instance');
            $this->is($other_object, $object, 'same instance.');
            $this->is($other_object->getPika(), 'chu', 'same instance.');
        }
        else
        {
            $this->is_deeply($other_object->get('id'), $object->get('id'), "They have the same 'id'.");
            $this->ok(!($other_object->isModified()), "New object is not modified.");
            $this->cmp_ok($other_object->_getStatus(), '<>', $object->_getStatus(), sprintf("Status are different."));
        }

        return $this;
    }

    public function testDelete($returned = false)
    {
        $collection = $this->map->query(sprintf("SELECT %s FROM %s LIMIT 1", join(', ', $this->map->getSelectFields()), $this->map->getTableName()));
        $object = $collection->current();
        $this->map->deleteOne($object);


        if ($returned === true) 
        {
            $other_object = $this->map->findByPk($object->get($this->map->getPrimaryKey()));
            $object->data_int++;

            $this->is($other_object->_getStatus(), \Pomm\Object\BaseObject::MODIFIED, sprintf("Retrieved object is modified."));
            $this->is($other_object->data_int, $object->data_int, sprintf("They share the same \"data_int\" value."));
        }
        else
        {
            $result = $this->map->findWhere("id = ?", array($object->id));
            $other_object = count($result) == 1 ? $result->current() : null;
            $this->ok(is_null($other_object), sprintf("Deleted objects are not returned by IM."));
        }

        return $this;
    }
}


$test = new IdentityMapTest();
$test
    ->initialize($service)
    ->testCreateObject(false)
    ->setMapper(new IdentityMapperStrict())
    ->testCreateObject()
    ->setMapper(new IdentityMapperSmart())
    ->testCreateObject()
    ->setMapper(new IdentityMapperNone())
    ->testDelete()
    ->setMapper(new IdentityMapperStrict())
    ->testDelete(true)
    ->setMapper(new IdentityMapperSmart())
    ->testDelete()
    ->setMapper(new IdentityMapperStrict(), true)
    ->testCreateObject(false)
    ->testDelete(false)
    ->setMapper(new IdentityMapperSmart(), true)
    ->testCreateObject(false)
    ->testDelete(false)
    ->setMapper(new IdentityMapperNone(), true)
    ->testCreateObject(false)
    ->testDelete(false)
    ;

$test->__destruct();
unset($test);
