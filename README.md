Preacher
========

Preacher on doctrine! Preacher is Active Record library build on top of Doctrine\DBAL 
It is aimed for small projects, where you would like to have some data abstraction and
Doctrine\ORM is just too much. For example, simple CMS or Silex based webapp.

Usage
-----

We assume that you know how to get Doctrine\DBAL to work. In your project bootstrap file, put:

    use coshi\Preacher\Model\Base as BaseModel;

    BaseModel::initialize($conn);

where $conn is Doctrine\DBAL\Connection instance.

Next create your db structure, and yes Preacher can help you with this (Documentation for this is being written)
Create model classes that extends coshi\\Preacher\\Model\\Base class.


    class User extends BaseModel 
    {
        static $tableName = 'users';
        static $alias = 'u';

        public static $primaryKey = 'id';

    }

And that's all,
Now you can do some basic CRUD.

    $u1 = new User();
    $u1->username = 'Preacher';
    $u1->password = 'bible';
    $u1->save();


