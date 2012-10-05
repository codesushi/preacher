<?php

namespace coshi\Preacher\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;

/**
 * Base
 *
 * A base class for Preacher schema managment
 *
 * @author Krzysztof Ozog <krzysztof.ozog@codesushi.co>
 */
abstract class Base
{

    protected $sm;

    protected $tablename;

    protected $table;

    protected $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
        $this->sm = $conn->getSchemaManager();
    }

    public function create()
    {
        if($this->sm->tablesExist($this->tablename)) {
            throw new \Exception('Table already exists! Maybe you mean migrate?');
            return false;

        }

        $table = $this->getTable();
        $this->addPrimaryKey($table);
        $this->addColumns($table);
        $this->addConstrains($table);
        $this->addIndicies($table);
        $this->sm->createTable($table);
        return true;

    }

    public function exists()
    {
        return $this->sm->tablesExist($this->tablename);
    }

    public function drop()
    {

        if($this->exists()) {
                $this->getTable();
                $this->sm->dropTable($this->table);
        } else {
            throw new \Exception(sprintf('Table: %s does not exists, create it first'));
        }
    }

    public function getTable()
    {
        if(!$this->table instanceof Table) {

            if($this->sm->tablesExist($this->tablename)) {
                $this->table = $this->sm->listTableDetails($this->tablename);
            } else {
                $table = new Table($this->tablename);
                $this->table = $table;
            }
        }
        return $this->table;
    }

    public function getTablename()
    {
        return $this->tablename;
    }

    public function addPrimaryKey(Table $table)
    {
        $table->addColumn('id', 'integer', array('autoincrement'=>true));
        $table->setPrimaryKey(array('id'));
    }

    abstract public function addColumns(Table $table);

    abstract public function addConstrains(Table $table);

    abstract public function addIndicies(Table $table);

    public function migrateTo($version, $dir = 'up')
    {
        $method = 'migration'.$version.ucfirst($dir);

        if(method_exists($this, $method))
        {
            try {
                $status = call_user_func_array(array($this, $method));
                if($status) {
                    $this->setVerision($version);
                }
            } catch(\Exception $e) {
                throw $e;
            }
        } else {
            throw new \Exception('No such migration, make sure that you defined proper method: '.$method);
        }
    }


    public function isVersioned()
    {

        if($this->sm->tablesExist('preacher_migrations')) {
            $result = $this->conn->executeQuery(
                'SELECT version FROM preacher_migrations where table_name = ?',
                array($this->tablename),
                array('string')
            )
            ->fetch(PDO::FETCH_ASSOC);

            return isset($result['version']);

        } else {
            return false;

        }
    }

    public function startVersioning()
    {
        if($this->sm->tablesExist('preacher_migrations')) {
            $result = $this->conn->executeQuery(
                'SELECT version FROM preacher_migrations where table_name = ?',
                array($this->tablename),
                array('string')
            )
            ->fetch(PDO::FETCH_ASSOC);

            if(!isset($result['version'])) {

                $this->conn->executeUpdate(
                    'INSERT INTO preacher_migrations (table_name, version) VALUES(?,?)',
                    array($this->tablename, 1),
                    array('string','integer')
                );
            }

        } else {
            $migrations = new Table('preacher_migrations');
            $migrations->addColumn('table_name', 'string', array('length'=>64,));
            $migrations->setPrimaryKey('table_name');
            //$migrations->addUniqueIndex(array('table_name'));
            $migrations->addColumn('version','integer',array('default'=>0));

            $this->sm->createTable($migrations);

            $this->conn->executeUpdate('INSERT INTO preacher_migrations (table_name, version) VALUES(?,?)',array($this->tablename, 1), array('string','integer'));

        }
    }




}
