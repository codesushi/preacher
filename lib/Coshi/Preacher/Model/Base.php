<?php

namespace Coshi\Preacher\Model;

use Doctrine\DBAL\Connection;

use Coshi\Preacher\Exception\Model\RecordNotFoundException;
use Coshi\Preacher\Exception\Model\UnknownColumnException;

/**
 * Base
 *
 * Base class for all models
 *
 * @author Krzysztof Ozog, <krzysztof.ozog@codesushi.co>
 */
abstract class Base
{


    /**
     * Name of the table in database
     * @var string
     */
    public static $tableName;

    /**
     * table alias used in query builder, must be unique
     * @var string
     */
    public static $alias;

    /**
     * List of columns names
     * @var array
     */
    public static $fields;

    /**
     * Name of Primary Key column
     * @var mixed
     *
     * @todo Support for composite PK
     */
    public static $primaryKey = 'id';



    /**
     * @var mixed
     * @access protected
     */
    protected static $conn;


    protected static $table;

    /**
     * values of hydrated record
     * @var array
     */
    protected $fieldsValues = array();


    /**
     * __construct
     * Record constructor
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        self::inspectTable();
    }

    public final function get($field)
    {
        if ($this->has($field)) {
            return $this->fieldsValues[$field];
        }

        throw new UnknownColumnException(
            sprintf(
                'Table %s has no %s column',
                static::getTablename(),
                $field
            )
        );

    }

    public final function set($field, $value)
    {
        if ($this->has($field)) {
            $this->fieldsValues[$field] = $value;
            return $this;
        }
        throw new UnknownColumnException(
            sprintf(
                'Table %s has no %s column',
                static::getTablename(),
                $field
            )
        );

    }

    public final function has($field)
    {
        return array_key_exists($field, static::$fields);
    }


    /**
     * __get
     *
     * magick method for handling fields
     * when method getField is defined it is called instedad of
     * returning value form array
     *
     * @param mixed $key
     * @access public
     * @returns void
     */
    public function __get($key)
    {
        $getterName = 'get'.static::camelize($key);
        if (method_exists($this, $getterName)) {
            return call_user_func_array(
                array($this, $getterName),
                array()
            );

        } elseif ($this->has($key)) {
            return $this->get($key);
        }

        return null;

    }

    public function __isset($key)
    {
        return $this->has($key);
    }

    /**
     * __set
     *
     * magick method for setting record values.
     *
     * if setX is defined calls it when you are trying to set x property value
     * <code>
     *  $model->x = value
     * </code>
     *
     * above code is equivalent to
     *
     * <code>
     * $model->setX(value);
     * </code>
     *
     * @param mixed $key
     * @param mixed $value
     * @access public
     * @return void
     */
    public function __set($key, $value)
    {

        static::inspectTable();

        $setterName = 'set'.static::camelize($key);

        if ($key == static::$primaryKey) {

            if (static::$fields[static::$primaryKey] != 'integer') {
                $this->fieldsValues[$key] = (string) $value;
            } else {

                $this->fieldsValues[$key] = (int) $value;
            }

        } elseif(method_exists($this, $setterName) ) {
            call_user_func_array(
                array($this, $setterName),
                array($value)
            );

        }
        return $this->set($key, $value);
    }


    public function toArray($raw = false)
    {
        if ($raw) {
            return $this->fieldsValues;
        }

        $row = array();

        foreach ($this->fields as $f => $t) {
            $row[$f] = $this->{$f};

        }
        return $row;
    }

    public function save()
    {

        $values = $this->fieldsValues;
        $types = static::$fields;

        unset($values[static::$primaryKey]);
        unset($types[static::$primaryKey]);
        $types = array_values($types);


        if (
            isset($this->fieldsValues[static::$primaryKey])
            and $this->fieldsValues[static::$primaryKey]
        ) {
            $status = static::$conn->update(
                static::$tableName,
                $values,
                array(
                    static::$primaryKey => $this->fieldsValues[static::$primaryKey]
                ),
                $types
            );
        } else {
            $status = static::$conn->insert(
                static::$tableName,
                $values,
                $types
            );
            $this->fieldsValues[static::$primaryKey] = static::$conn->lastInsertId();
        }

        return $this;

    }

    public static function getClass()
    {
        return (string) get_called_class();
    }

    /**
     * getConnection
     *
     * @access public
     * @return Doctrine\DBAL\Connection;
     */
    public function getConnection()
    {
        return self::$conn;
    }

    /**
     * find loads and hydrates record identified by id
     *
     * @param mixed $id - records primary Key
     * @static
     * @access public
     * @returns Coshi\Preacher\Model\Base
     */
    public static function find($id)
    {

        static::inspectTable();

        $qb = self::$conn->createQueryBuilder();
        $qb->select(static::getPrefixedFields())
            ->from(static::$tableName, static::$alias)
            ->where(static::prefixField(static::$primaryKey) ." = :id");
        $qb->setMaxResults(1);
        $qb->setParameter('id', $id, 'integer');
        $stmt = $qb->execute();

        if ($result = $stmt->fetch()) {
            $class = static::getClass();
            $record = new $class;
            $record->hydrate($result);
            return $record;

        } else {
            //TODO: Exception;
            throw new RecordNotFoundException();
            return null;
        }
    }

    /**
     * hydrates current object with values from array
     *
     * @param bool $values
     * @access public
     * @return Model\Base
     */
    public function hyrate($values = array())
    {
        foreach ($values as $k => $v) {
            //$this->{$k} = $v;
            $this->fieldsValues[$k] = static::$table->getColumn($k)
                ->getType()->convertToPHPValue($v, static::$conn->getDatabasePlatform());
        }
        return $this;

    }

    public static function initialize(
       \Doctrine\DBAL\Connection $conn
    )
    {
        static::$conn = $conn;

    }
    public static function findOneBy($conditions = array())
    {
        static::inspectTable();

        $fieldNames = array_keys(static::$fields);

        $qb = static::getSelect();

        foreach ($conditions as $field => $value) {
            if (in_array($field, $fieldNames)) {
                $qb->andWhere(static::prefixField($field).' = :'.$field)
                    ->setParameter(
                        $field,
                        $value,
                        static::$fields[$field]
                    );
            }

        }

        $qb->setMaxResults(1);
        $row = $qb->execute()->fetch();
        if ($row) {
            $obj = static::getClass();
            $obj = new $obj();
            $obj->hydrate($row);
            return $obj;
        } else {
            return null;
        }
    }

    public static function findAllBy($conditions = array(), $options =array())
    {
        static::inspectTable();

        $fieldNames = array_keys(static::$fields);

        $qb = static::getSelect();

        foreach ($conditions as $field => $value) {
            if (in_array($field, $fieldNames)) {
                $qb->andWhere(static::prefixField($field).' = :'.$field)
                    ->bindParameter(
                        ':'.$field,
                        $value,
                        static::$fields[$field]
                    );
            }

        }

        if (isset($options['order'])) {
            foreach ($options['order'] as $field => $dir) {
                $qb->addOrder($field, $dir);
            }
        }

        if (isset($options['limit'])) {
            $qb->setMaxResults($options['limit']);
        }

        if (isset($options['offset'])) {
            $qb->setFirstResult($options['offset']);
        }


        $results = array();

        $resultset = $qb->execute();

        while ($row = $resultset->fetch()) {
            $results[] = static::create($row);
        }
        return $results;
    }

    /**
     * getSelect
     *
     * Returns DBAL QueryBuilder prestet for current Table
     *
     * @access public
     * @return void
     */
    public static function getSelect()
    {
        self::inspectTable();
        $table = self::getTablename();
        $alias = self::getAlias();

        $qb = self::$conn->createQueryBuilder();
        $qb->select(static::getPrefixedFields())
            ->from(static::$tableName, static::$alias);

        return $qb;
    }

    public static function create($values = array(), $flush = false)
    {
        $class = static::getClass();
        $object = new $class();
        $object->hydrate($values);
        if ($flush) {
            $object->save();
        }
        return $object;

    }

    public static function execute($qb)
    {
        $results = array();
        $class = static::getClass();
        $resultset = $qb->execute();
        while ($row = $resultset->fetch()) {
            $obj = new $class();
            $obj->hydrate($row);
            $results[] = $obj;
        }

        return $results;

    }

    /**
     * getPrefixedFields
     * Returns string that contains prefixed fields for select
     * useful for building joins and relations
     *
     * @static
     * @access public
     * @return void
     */
    public static function getPrefixedFields()
    {
        static::inspectTable();
        $fields = '';
        foreach (static::$fields as $k => $v) {
            $fields .= static::prefixField($k).', ';
        }
        return trim($fields, ', ');
    }

    public static function inspectTable()
    {
        if (!empty(static::$fields)) {
            return static::$fields;
        }



        $sm = self::$conn->getSchemaManager();
        static::$table = $sm->listTableDetails(static::getTablename());

        foreach (static::$table->getColumns() as $column) {

            static::$fields[$column->getName()] = $column->getType()->getName();

        }
        return static::$fields;

    }

    public static function camelize($word)
    {
        if (preg_match_all('/\/(.?)/', $word, $got)) {
            foreach ($got[1] as $k => $v) {
                $got[1][$k] = '::'.strtoupper($v);
            }
            $word = str_replace($got[0], $got[1], $word);
        }
        return str_replace(
            ' ',
            '',
            ucwords(preg_replace('/[^A-Z^a-z^0-9^:]+/', ' ', $word))
        );
    }

    public static function getAlias()
    {
        if (!static::$alias) {
            static ::$alias = substr(self::getTablename(), 0, 2);
        }
        return static::$alias;

    }

    protected static function getTablename()
    {
        if (!static::$tableName) {
            static::$tableName = strtolower(__CLASS__);
        }
        return static::$tableName;
    }

    protected static function prefixField($field)
    {
        return sprintf('%s.%s', static::$alias, $field);
    }


}
