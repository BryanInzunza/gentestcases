<?php

namespace Coppel\RAC\Modules;

use PDO;
use Phalcon\DI\DI;
use Phalcon\Mvc\Micro\Collection;

class Module implements IModule
{
    public function registerLoader($loader)
    {
        $loader->setNamespaces([
            'Coppel\LegacyTmsEntregasropadistribucion\Controllers' => __DIR__ . '/controllers/',
            'Coppel\LegacyTmsEntregasropadistribucion\Models' => __DIR__ . '/models/'
        ], true);
    }

    public function getCollections()
    {
        $collection = new Collection();

        $collection->setPrefix('/api')
            ->setHandler('\Coppel\LegacyTmsEntregasropadistribucion\Controllers\RopaDistribucionController')
            ->setLazy(true);

        $collection->post('/cedis/{cedis}/distribucion/paquetes/entregar', 'registrarEnvioClientesRopa');

        $collection->post('/cedis/{cedis}/distribucion/paquetes/confirmacion', 'confirmarPaqueteRopa');
        $collection->post('/cedis/{cedis}/distribucion/paquetes/reimpresion', 'reimpresionDeGuias');

        return [
            $collection
        ];
    }

    public function registerServices()
    {
        $di = DI::getDefault();
        $config = $di->get('config');

        $di->set('bodegaMuebles', function() use ($di, $config) {
            $host = $di->host;
            $dbname = $di->dbname;
          return new \PDO("pgsql:host=$host;dbname=$dbname",
             $config->bodegaMuebles->username,
             $config->bodegaMuebles->password,
             array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
            );
        });

        $di->set('apartadoEcommerce', function() use ($di, $config) {
            $host = $config->apartadoEcommerce->host;
            $dbname = $config->apartadoEcommerce->dbname;
          return new \PDO("pgsql:host=$host;dbname=$dbname",
             $config->apartadoEcommerce->username,
             $config->apartadoEcommerce->password,
             array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
            );
        });

        $di->set('logger', function () {
            return new \Katzgrau\KLogger\Logger('logs');
        });
    }
}
