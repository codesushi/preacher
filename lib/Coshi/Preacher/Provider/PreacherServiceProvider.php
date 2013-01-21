<?php

namespace Coshi\Preacher\Provider;

use Doctrine\DBAL\Types\Type;
use Silex\Application;
use Silex\ServiceProviderInterface;

use Coshi\Model;
use Coshi\Preacher\Model\Base as BaseModel;

/**
 * PreacherServiceProvider
 *
 * Preacher on doctrine !
 * Very simple active-record like pseudo ORM
 *
 * @author Krzysztof Ozog, <coder@kopalniapikseli.pl>
 */
class PreacherServiceProvider implements ServiceProviderInterface
{


    public function boot(Application $app)
    {

        if (isset($app['db'])) {   
            BaseModel::initialize($app['db']);
        }
    }

    public function register(Application $app)
    {

        if (isset($app['db'])) {
            $this->setupDBAL($app['db']);
            BaseModel::initialize($app['db']);
        }
        $app['preacher'] = $app->share(function() use($app) {


        });


    }

    public function setupDBAL($conn)
    {

        Type::overrideType('datetime', 'Doctrine\DBAL\Types\VarDateTimeType');
        Type::overrideType('datetimetz', 'Doctrine\DBAL\Types\VarDateTimeType');
        Type::overrideType('time', 'Doctrine\DBAL\Types\VarDateTimeType');
        Type::addType(
            'textarray',
            'Coshi\Preacher\Types\PgStringArray'
        );
        $conn
            ->getDatabasePlatform()
            ->registerDoctrineTypeMapping('_text', 'textarray');

    }

}
