<?php

namespace Coshi\Preacher\Provider;

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

        \Doctrine\DBAL\Types\Type::addType(
            '_text',
            'Coshi\Preacher\Types\PgStringArray'
        );
        $app['db']
            ->getDatabasePlatform()
            ->registerDoctrineTypeMapping('_text', '_text');
        if (isset($app['db'])) {
            BaseModel::initialize($app['db']);
        }
    }

    public function register(Application $app)
    {

        Doctrine\DBAL\Types\Type::addType(
            '_text',
            'Coshi\Preacher\Types\PgStringArray'
        );
        $app['db']
            ->getDatabasePlatform()
            ->registerDoctrineTypeMapping('_text', '_text');
        if (isset($app['db'])) {
            BaseModel::initialize($app['db']);
        }


        $app['preacher'] = $app->share(function() use($app) {


        });


    }

}
