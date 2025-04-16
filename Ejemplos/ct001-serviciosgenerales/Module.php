<?php

use Coppel\RAC\Modules\IModule;
use Phalcon\Mvc\Micro\Collection;
use Katzgrau\KLogger\Logger;

class Module implements IModule
{
    public function __construct()
    {
    }

    public function registerLoader($loader)
    {
        $loader->registerNamespaces([
            'Coppel\ApiBtGenerales\Controllers' => __DIR__ . '/controllers/',
            'Coppel\ApiBtGenerales\Models' => __DIR__ . '/models/'
        ], true);
    }

    public function getCollections()
    {
        $collection = new Collection();

        $collection->setPrefix('/api')
            ->setHandler('\Coppel\ApiBtGenerales\Controllers\BtGeneralController')
            ->setLazy(true);

        $collection->get('/sesion', 'validarIngreso');
        $collection->get('/notificaciones', 'consultarNotificaciones');
        $collection->get('/datos/refaccionaria', 'datosRefaccionaria');
        $collection->get('/centro/{centro}', 'consultaCentros');
        $collection->get('/centro/{centro}/detalle', 'consultarCentroDetalles');
        $collection->get('/centro/busqueda/nombre/{nombre}', 'consultarCentroPorNombre');
        $collection->get('/fecha/corte/{bodega}', 'consultaFechaCorte');
        $collection->get('/empleados', 'consultarEmpleado');
        $collection->get('/empleados/puestos', 'validarPuestoEmpleado');
        $collection->post('/huellas', 'validarEmpleadoTemplate');
        $collection->get('/articulo/{descripcion}', 'buscarArticulo');
        $collection->get('/centro', 'buscarCentro');
        $collection->get('/consulta/servicios', 'buscarServicios');
        $collection->get('/centro/salidas/{centro}', 'buscarCentroSalida');
        $collection->get('/articulo/salida', 'buscarArticuloSalida');
        $collection->get('/conexiones', 'pruebaConexion');

        return [
            $collection
        ];
    }

    public function registerServices()
    {
        $di = Phalcon\DI::getDefault();
        $di->set('conexion', function () use ($di) {
            $config = $di->get('config');
            $host = $config->db->host;
            $dbname = $config->db->dbname;
            return new \PDO(
                "mysql:host=$host;dbname=$dbname",
                $config->db->username,
                $config->db->password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]
            );
        });

        $di->set('logger', function () {
            return new Logger('logs');
        });
    }
}
