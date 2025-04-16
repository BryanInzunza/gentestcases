<?php

namespace Coppel\ApiBtGenerales\Models;

use Phalcon\Mvc\Model as Modelo;
use Coppel\ApiBtGenerales\Models as Modelos;
use Coppel\RAC\Exceptions\HTTPException;

class BtGeneralModel extends Modelo
{
    private $respuestaModel;
    private $SERV_CENTRAL_MUE;
    private $CONEXION_TECNOLOGIA;
    private $CONEXION_CENTRAL_MUE;

    public function onConstruct() {
        $this->respuestaModel = new Modelos\RespuestaModel();
        $this->respuestaModel->iniciar();
        $this->SERV_CENTRAL_MUE = 1;
        $this->CONEXION_TECNOLOGIA = 1;
        $this->CONEXION_CENTRAL_MUE = 4;
    }

    private function responseException($mensaje='', $metodo=__METHOD__) {        
        throw new HTTPException('['.$metodo."] $mensaje", 500, []);
    }

    public function pruebaConexion($type, $host, $dbname, $username, $password) {
        try {
            $mRespuesta = new Modelos\RespuestaModel();
            $mRespuesta->iniciar();
            $port = 1433;
            
            if ($type === 'SQL') {
                $conn = new \PDO(
                    "dblib:host=$host:$port;dbname=$dbname",
                    $username, $password,
                    array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
                );
            } 
            if ($type === 'PGSQL') {
                $conn = new \PDO(
                    "pgsql:host=$host;dbname=$dbname", 
                    $username, $password, 
                    array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
                );
            }

            $statement = $conn->prepare("SELECT 'ok' AS conexion ");
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $bodega = 

                $registro = new \stdClass();
                $registro->conexion = $entry["conexion"];
                $registro->host = $_SERVER['HTTP_HOST'];
                $registro->client = $_SERVER['REMOTE_ADDR'];
                $this->respuestaModel->setEstatus(1);
                $this->respuestaModel->setMensaje("");
                $this->respuestaModel->setData($registro);
            }
            $statement->closeCursor();

        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $this->respuestaModel->getResponse();
    }


    public function consultarBodegaCorrespondiente($terminal) {
        try {
            $bodega = 0;
            $ipData = new \stdClass();
            $ipData = $this->consultaIpSql($this->SERV_CENTRAL_MUE);
            if ($ipData->STATUS > 0) {
                $ip = $ipData->IP;

                $db = $this->generarConexionCedis($this->CONEXION_CENTRAL_MUE, $ipData->IP, '');
				$statement = $db->prepare("EXEC PROC_REFCONSULTACENRANGOIPS :ip ");
                
				
				$statement->bindParam(":ip", $terminal, \PDO::PARAM_STR);
                $statement->execute();
                while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $bodega = intval($entry["Bodega"]);
                }
				
                $statement->closeCursor();

                if ($bodega >= 30000 ) {
                    $this->respuestaModel->setEstatus(1);
                    $this->respuestaModel->setMensaje("");
                    $this->respuestaModel->setData($bodega);
                } else {
                    $this->respuestaModel->setEstatus(-1);
                    $this->respuestaModel->setMensaje(
                        "El Rango de tu IP no se encuentra en la CenRangoIps.".
                        " Favor de Avisar a Mesa de Ayuda...");
                }
            } else {
                throw new HTTPException('Problemas de conexión.',500, []); 
            }            
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $this->respuestaModel->getResponse();
    }

    public function consultarAlmacen($params) {
        $dataAlmacen = new \stdClass();
        $dataAlmacen->almacen = 0;

        try {
            $db = $this->generarConexionCedis($this->CONEXION_TECNOLOGIA, 0, '');
            $statement = $db->prepare("
            SELECT
              num_refa, num_centro, numemp_encargado, numemp_geerente
            FROM
              fun_consultadatosrefaccionaria(:bodega, :terminal);"
            );
            $statement->bindParam(":bodega", $params->bodega,\PDO::PARAM_INT);
            $statement->bindParam(":terminal", $params->terminal,\PDO::PARAM_INT);
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
              $dataAlmacen->almacen = intval($entry["num_refa"]);
              $dataAlmacen->centro = intval($entry["num_centro"]);
              $dataAlmacen->encargado = intval($entry["numemp_encargado"]);
              $dataAlmacen->gerente = intval($entry["numemp_geerente"]);
            }
            $statement->closeCursor();

            $this->respuestaModel->iniciar();
            if ($dataAlmacen->almacen > 0) {        
                $this->respuestaModel->setEstatus(1);        
                $this->respuestaModel->setData($dataAlmacen);
            }
            else {
                $this->respuestaModel->setEstatus(-1);
                $this->respuestaModel->setMensaje("La ip de la computadora no tiene asignado un almacén,".
                    " Por favor registre un almacén");
            }            
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $this->respuestaModel->getResponse();
    }

    public function consultarMenu($params) {
        $menu = array();                 
        
        try {
            $db = $this->generarConexionCedis($this->CONEXION_TECNOLOGIA, 0, '');
            $statement = $db->prepare("
                SELECT
                    idu_nodopadre, idu_nodo, des_descripcion, nom_url, num_orden
                    num_contador
                FROM
                    fun_ct001consultarmenu(:bodega, :almacen);");
            $statement->bindParam(":bodega", $params->bodega,\PDO::PARAM_INT);
            $statement->bindParam(":almacen", $params->almacen,\PDO::PARAM_INT);
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {          

                $resultSet = new \stdClass();
                $resultSet->nodopadre = intval($entry["idu_nodopadre"]);
                $resultSet->nodo = intval($entry["idu_nodo"]);
                $resultSet->opcion = utf8_encode($entry["des_descripcion"]);
                $resultSet->url = utf8_encode($entry["nom_url"]);
                $menu[] = $resultSet;
                $resultSet = null;
            }
            $statement->closeCursor();            
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $menu;
    }

    public function consultarNotificaciones() {        
        try {
            $this->respuestaModel->iniciar();   
            $db = $this->generarConexionCedis($this->CONEXION_TECNOLOGIA, 0, '');

            $statement = $db->prepare("
                SELECT 
                    nom_titulo, nom_mensaje
                FROM
                    fun_ct001consultarnotificaciones();"
            );
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $this->respuestaModel->setEstatus(1);
                $this->respuestaModel->setMensaje('');
                $this->respuestaModel->addData(array(
                    'titulo' => utf8_encode(trim($entry["nom_titulo"])),
                    'mensaje' => utf8_encode(trim($entry["nom_mensaje"]))
                ));
            }
            $statement->closeCursor();

        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $this->respuestaModel->getResponse();
    }

    public function datosRefaccionaria($ipaddress)
    {

        $response = [];
        $status = 0;
        $mensaje = '';
        $data = [];
        $aData = [];

        $opcion = 1;
        $ip = 0;
        $namedb = '';

        try {
            $db = $this->generarConexionCedis($this->CONEXION_TECNOLOGIA, $ip, $namedb);

            if($db == null) {
                $status = -2;
                $mensaje = "No se logro abrir conexión, favor de consultar a mesa de ayuda";
                $logger = \Phalcon\DI::getDefault()->get('logger');
                $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
            } else {

                $statement = $db->prepare("SELECT bodega, refaccionaria, centro, gerente, encargado, ".
                    " aux1, aux2, ubicacion, tel, ext FROM refobtenerdatosrefaccionaria(:maquina)");

                $statement->bindParam(':maquina', $ipaddress);

                try {
                    $statement->execute();
                    if ($entry = $statement->fetch()) {
                        $status = 1;

                        $data = new \stdClass();
                        $data->bodega = $entry["bodega"];
                        $data->refaccionaria = $entry["refaccionaria"];
                        $data->centro = $entry["centro"];
                        $data->gerente = $entry["gerente"];
                        $data->encargado = $entry["encargado"];
                        $data->aux1 = $entry["aux1"];
                        $data->aux2 = $entry["aux2"];
                        $data->ubicacion = $entry["ubicacion"];
                        $data->tel = $entry["tel"];
                        $data->ext = $entry["ext"];

                        $aData[] = $data;
                        $data = null;
                    }

                    $statement->closeCursor();

                } catch (\Exception $ex) {
                    $status = -1;
                    $mensaje = "No se logro realizar la consulta de datos de la refaccionaria";
                    $m = utf8_encode($ex->getMessage());
                    $logger = \Phalcon\DI::getDefault()->get('logger');
                    $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
                }
            }

            $object = new \stdClass();
            $object->STATUS = intval($status);
            $object->MENSAJE = utf8_encode($mensaje);
            $object->DATA = $aData;
            
            $response = $object;
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $response;
    }

    public function consultaFechaCorte($bodega)
    {
        $response = [];
        $status = 0;
        $mensaje = '';
        $data = [];
        $aData = [];

        $opcion = 5;
        $ip = 0;
        $namedb = '';

        try {
            $ipData = new \stdClass();
            $ipData = $this->consultaIpMuebles($bodega);

            $status = $ipData->STATUS;
            $mensaje = $ipData->MENSAJE;

            if ($status > 0) {

                $ip = $ipData->IP;
                $namedb = 'bodegamuebles.'.$bodega;

                //opcion 1 = dbTecnologia, opcion 2 = dbGlobal, opcion 3 = dbPersonal, opcion 4 = dbMueblesSql, opcion 5 = dbMuebles
                $di = \Phalcon\DI::getDefault();
                $db = $this->generarConexionCedis($opcion, $ip, $namedb);

                if($db == null) {
                    $status = -2;
                    $mensaje = "No se logro abrir conexión, favor de consultar a mesa de ayuda";
                    $logger = \Phalcon\DI::getDefault()->get('logger');
                    $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
                } else {

                    $statement = $db->prepare("SELECT fechacorte FROM bmconsultarfechacorte();");

                    try {
                        $statement->execute();
                        if ($entry = $statement->fetch()) {
                            $status = 1;
                            $data = new \stdClass();
                            $data->fechacorte = $entry["fechacorte"];

                            $aData[] = $data;
                            $data = null;
                        }

                        $statement->closeCursor();

                    } catch (\Exception $ex) {
                        $status = -1;
                        $mensaje = "No se logro realizar la consulta de los motivos";
                        $m = utf8_encode($ex->getMessage());
                        $logger = \Phalcon\DI::getDefault()->get('logger');
                        $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
                    }
                }
            }

            $object = new \stdClass();
            $object->STATUS = intval($status);
            $object->MENSAJE = utf8_encode($mensaje);
            $object->DATA = $aData;

            $response = $object;
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $response;
    }

    public function consultaCentros($centro)
    {
        $response = [];
        $status = 0;
        $mensaje = '';
        $data = [];
        $aData = [];

        $opcion = 3;
        $ip = 0;
        $namedb = '';

        $numero = 1;

        try {
            $ipData = new \stdClass();
            $ipData = $this->consultaIpPersonal($numero);

            $status = $ipData->STATUS;
            $mensaje = $ipData->MENSAJE;

            if ($status > 0) {

                $status = 0;

                $ip = $ipData->IP;
                //opcion 1 = dbTecnologia, opcion 2 = dbGlobal, opcion 3 = dbPersonal, opcion 4 = dbMueblesSql, opcion 5 = dbMuebles
                $di = \Phalcon\DI::getDefault();
                $db = $this->generarConexionCedis($opcion, $ip, $namedb);

                if($db == null) {
                    $status = -2;
                    $mensaje = "No se logro abrir conexión, favor de consultar a mesa de ayuda";
                    $logger = \Phalcon\DI::getDefault()->get('logger');
                    $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
                } else {

                    $statement = $db->prepare("SELECT nombrecentro FROM ".
                        " bmconsultacatalogocentros (:centro);");

                    $statement->bindParam(':centro',$centro, \PDO::PARAM_INT);

                    try {
                        $statement->execute();
                        if ($entry = $statement->fetch()) {
                            $status = 1;
                            $data = new \stdClass();
                            $data->nombrecentro = utf8_encode($entry["nombrecentro"]);

                            $aData[] = $data;
                            $data = null;
                        }

                        $statement->closeCursor();

                    } catch (\Exception $ex) {
                        $status = -1;
                        $mensaje = "No se logro realizar la consulta de los motivos";
                        $m = utf8_encode($ex->getMessage());
                        $logger = \Phalcon\DI::getDefault()->get('logger');
                        $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
                    }
                }
            }

            $object = new \stdClass();
            $object->STATUS = intval($status);
            $object->MENSAJE = utf8_encode($mensaje);
            $object->DATA = $aData;
            
            $response = $object;
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $response;
    }

    public function consultarCentroDetalles($centro)
    {
        try {
            $respuesta = new Modelos\RespuestaModel();
            $respuesta->iniciar();
            
            $conn_personal = $this->conexionPersonal();
            $statement = $conn_personal->prepare("            
                SELECT
                    numerocentro, nombrecentro, numerociudad
                FROM
                    heconsultanombrecentro(:centro)
            ");
            $statement->bindValue(':centro', $centro);
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $registro = new \stdClass(); 
                $registro->centro = intval($entry["numerocentro"]);
                $registro->nomCentro = trim($entry["nombrecentro"]);
                $registro->ciudad = intval($entry["numerociudad"]);          
                
                $respuesta->setEstatus(1);
                $respuesta->setMensaje('');
                $respuesta->setData($registro);
            }
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $respuesta->getResponse();
    }

    public function consultarCentroPorNombre($nombre)
    {
        try {
            $respuesta = new Modelos\RespuestaModel();
            $respuesta->iniciar();

            $conn_personal = $this->conexionPersonal();
            $statement = $conn_personal->prepare("            
                SELECT
                    numerocentro, nombrecentro
                FROM
                    fun_consultacentrosactivos(:nombre)
                ORDER BY
                    numerocentro
            ");
            $nombre = utf8_decode($nombre);
            $statement->bindValue(':nombre', $nombre, \PDO::PARAM_STR);
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $registro = new \stdClass(); 
                $registro->numerocentro = intval($entry["numerocentro"]);
                $registro->nombrecentro = utf8_encode(trim($entry["nombrecentro"]));
                
                $respuesta->setEstatus(1);
                $respuesta->setMensaje('');
                $respuesta->addData($registro);
            }
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $respuesta->getResponse();
    }

    public function consultaIpPersonal($numero)
    {
        $response = [];
        $status = 0;
        $mensaje = '';

        $opcion = 2;
        $ip = 0;
        $ipServ = '';
        $namedb = '';

        try {
            //opcion 1 = dbTecnologia, opcion 2 = dbGlobal, opcion 3 = dbPersonal, opcion 4 = dbMueblesSql, opcion 5 = dbMuebles
            $db = $this->generarConexionCedis($opcion, $ip, $namedb);

            if($db == null) {
                $status = -2;
                $mensaje = "No se logro abrir conexión, favor de consultar a mesa de ayuda";
                $logger = \Phalcon\DI::getDefault()->get('logger');
                $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
            } else {

                $statement = $db->prepare("SELECT comconsultaip FROM comconsultaip(:numero);");

                $statement->bindParam(':numero',$numero, \PDO::PARAM_INT);

                try {
                    $statement->execute();
                    if ($entry = $statement->fetch()) {
                        $status = 1;
                        $ipServ = $entry["comconsultaip"];
                    }

                } catch (\Exception $ex) {
                    $status = -1;
                    $mensaje = "No se logro obtener la ip del centro";
                    $m = utf8_encode($ex->getMessage());
                    $logger = \Phalcon\DI::getDefault()->get('logger');
                    $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
                }
            }

            $object = new \stdClass();
            $object->STATUS = intval($status);
            $object->MENSAJE = utf8_encode($mensaje);
            $object->IP = $ipServ;

            $response = $object;
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $response;
    }

    public function consultaIpSql($numero)
    {
        $response = [];
        $status = 0;
        $mensaje = '';

        $opcion = 1;
        $ip = 0;
        $ipServ = '';
        $namedb = '';

        try {
            //opcion 1 = dbTecnologia, opcion 2 = dbGlobal, opcion 3 = dbPersonal, opcion 4 = dbMueblesSql, opcion 5 = dbMuebles
            $db = $this->generarConexionCedis($opcion, $ip, $namedb);

            if($db == null) {
                $status = -2;
                $mensaje = "No se logró abrir conexión, favor de consultar a mesa de ayuda";
                $logger = \Phalcon\DI::getDefault()->get('logger');
                $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
            } else {

                $statement = $db->prepare("SELECT TRIM(refconsultaip) AS refconsultaip FROM refconsultaip(:numero);");

                $statement->bindParam(':numero',$numero, \PDO::PARAM_INT);

                try {
                    $statement->execute();
                    if ($entry = $statement->fetch()) {
                        $status = 1;
                        $ipServ = $entry["refconsultaip"];                     
                    }

                } catch (\Exception $ex) {
                    $status = -1;
                    $mensaje = "No se logro obtener la ip del centro";
                    $m = utf8_encode($ex->getMessage());
                    $logger = \Phalcon\DI::getDefault()->get('logger');
                    $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
                }
            }

            $object = new \stdClass();
            $object->STATUS = intval($status);
            $object->MENSAJE = utf8_encode($mensaje);
            $object->IP = $ipServ;

            $response = $object;
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $response;
    }

    public function consultaIpMuebles($bodega)
    {
        $response = [];
        $status = 0;
        $mensaje = '';
        $data = [];
        $aData = [];

        $opcion = 4;
        $ip = 0;
        $namedb = '';
        $ipbodega = '';

        $numero = 1;

        try {
            $ipData = new \stdClass();
            $ipData = $this->consultaIpSql($numero);

            $status = $ipData->STATUS;
            $mensaje = $ipData->MENSAJE;

            if ($status > 0) {

                $status = 0;

                $ip = $ipData->IP;
                //opcion 1 = dbTecnologia, opcion 2 = dbGlobal, opcion 3 = dbPersonal, opcion 4 = dbMueblesSql, opcion 5 = dbMuebles
                $di = \Phalcon\DI::getDefault();
                $db = $this->generarConexionCedis($opcion, $ip, $namedb);

                if($db == null) {
                    $status = -2;
                    $mensaje = "No se logró abrir conexión, favor de consultar a mesa de ayuda";
                    $logger = \Phalcon\DI::getDefault()->get('logger');
                    $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
                } else {

                    $statement = $db->prepare("EXEC PROC_REFSALIDASCONSULTAIPBODEGA :bodega;");

                    $statement->bindParam(':bodega', $bodega, \PDO::PARAM_INT);

                    try {
                        $statement->execute();
                        if ($entry = $statement->fetch()) {
                            $status = 1;
                            $ipbodega = $entry["ipbodega"];
                        }

                        $statement->closeCursor();

                    } catch (\Exception $ex) {
                        $status = -1;
                        $mensaje = "No se logro realizar la consulta de los motivos";
                        $m = utf8_encode($ex->getMessage());
                        $logger = \Phalcon\DI::getDefault()->get('logger');
                        $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
                    }
                }
            }

            $object = new \stdClass();
            $object->STATUS = intval($status);
            $object->MENSAJE = utf8_encode($mensaje);
            $object->IP = $ipbodega;
            
            $response = $object;
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }           

        return $response;
    }

    public function generarConexionCedis($opcion, $ip, $namedb)
    {

        $di = \Phalcon\DI::getDefault();
        $dbc = null;
        $dbname = '';

        switch ($opcion) {
            case 1:

                try{

                    $config = $di->get('config');
                    $host = $config->dbTecnologia->host;
                    $dbname = $config->dbTecnologia->dbTecnologia;

                    $dbc = new \PDO("pgsql:host=$host;dbname=$dbname", 
                        $config->dbTecnologia->username,
                        $config->dbTecnologia->password,
                        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
                    );

                } catch (\Exception $e) {
                    $dbc = null;
                }

                break;

            case 2:

                try{

                    $config = $di->get('config');
                    $host = $config->dbTecnologia->host;
                    $dbname = $config->dbTecnologia->dbGlobal;

                    $dbc = new \PDO("pgsql:host=$host;dbname=$dbname", 
                        $config->dbTecnologia->username,
                        $config->dbTecnologia->password,
                        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
                    );

                } catch (\Exception $e) {
                    $dbc = null;
                }

                break;

            case 3:

                try{

                    $config = $di->get('config');
                    $host = $ip;
                    $dbname = $config->dbPersonal->dbname;

                    $dbc = new \PDO(
                        "pgsql:host=$host;dbname=$dbname", 
                        $config->dbPersonal->username,
                        $config->dbPersonal->password,
                        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
                    );

                } catch (\Exception $e) {
                    $dbc = null;
                }

                break;

            case 4:

                try{
                  
                    $config = $di->get('config');
                    $host = $ip;
                    $port = 1433;
                    $dbname = $config->dbMueblesSql->dbname;
                    $dbc = new \PDO(
                        "dblib:host=$host:$port;dbname=$dbname",
                        //"sqlsrv:server=$host;Database=$dbname",
                        $config->dbMueblesSql->username,
                        $config->dbMueblesSql->password,
                        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
                    );

                } catch (\Exception $e) {
                    $dbc = null;
                }

                break;

            case 5:

                try{

                    $config = $di->get('config');
                    $host = $ip;
                    $dbname = $namedb;

                    $dbc = new \PDO(
                        "pgsql:host=$host;dbname=$dbname",
                        $config->dbMuebles->username,
                        $config->dbMuebles->password,
                        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
                    );

                } catch (\Exception $e) {
                    $dbc = null;
                }

                break;

            default:
                break;
        }
		
        return $dbc;
    }

    private function conexionPersonal()
    {
        try {
            $ipPersonal = '';
            $conn_global = null;
            $conn_personal = null;

            $di = \Phalcon\DI::getDefault();            
                             
            $cnfgTec = $di->get('config')->dbTecnologia;
            $conn_global = new \PDO(
                "pgsql:host={$cnfgTec->host};dbname={$cnfgTec->dbGlobal};",
                $cnfgTec->username,
                $cnfgTec->password,
                [
                  \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]
            );
            $statement = $conn_global->prepare("SELECT comconsultaip FROM comconsultaip('1')");
            $statement->execute();
            if ($entry = $statement->fetch()) {
                $ipPersonal = $entry["comconsultaip"];
            }

            $cnfgPer = $di->get('config')->dbPersonal;  
            $conn_personal = new \PDO("pgsql:host=$ipPersonal;dbname=$cnfgPer->dbname", 
                $cnfgPer->username,
                $cnfgPer->password,
                [
                  \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]
            );
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $conn_personal;
    }

    public function consultarEmpleado($params) 
    {
        $respuesta = new Modelos\RespuestaModel();
        $respuesta->iniciar();

        try {        
            $conn_personal = $this->conexionPersonal();
            $statement = $conn_personal->prepare("
                SELECT
                    nombre, puesto, cancelado, centro, nombrecentro
                FROM
                    refconsultadatosempleados(:empleado)
            ");
            $statement->bindValue(':empleado', $params->empleado);
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $registro = new \stdClass();           
                $registro->nombre = utf8_encode(trim($entry["nombre"]));
                $registro->puesto = intval($entry["puesto"]);
                $registro->cancelado = trim($entry["cancelado"]);
                $registro->centro = intval($entry["centro"]);
                $registro->nombrecentro = utf8_encode(trim($entry["nombrecentro"]));
                
                $respuesta->setEstatus(1);
                $respuesta->setMensaje('');
                $respuesta->setData($registro);
            }
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $respuesta->getResponse();
    }

    public function validarPuesto($empleado='', $idPermiso='')
    {
        $respuesta = new Modelos\RespuestaModel();
        $respuesta->iniciar();
        $respuesta->setEstatus(-1);
        $respuesta->setMensaje('Validación de permiso incorrecta.');
        try {        
            $db = $this->generarConexionCedis($this->CONEXION_TECNOLOGIA, 0, '');
            $statement = $db->prepare("
                SELECT 
                    num_estatus
                FROM
                    fun_bt_validarpermisosprocesos(:idPermiso, :puesto)");
            $statement->bindParam(":idPermiso", $idPermiso,\PDO::PARAM_STR);
            $statement->bindParam(":puesto", $empleado->puesto,\PDO::PARAM_INT);
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {                
                $mensaje = '';
                if ($entry["num_estatus"] === 1) {
                    $respuesta->setEstatus(1);
                } else {
                    switch ($idPermiso) {
                        case "BT_ACCESO":
                            $mensaje = "El Empleado no tiene ninguno de los siguientes puestos 
                              * Gerente de Sistemas.
                              * Gerente Titular.
                              * Gerente Suplente.
                              * Gerente de Entrenamiento.
                              * Oficina
                              * Jefe.
                              * Soporte Tecnológico.
                              * Infraestructura y Soporte.
                              * Almacenista.
                              * Analista.
                              * Especialista en Soporte.";
                            break;

                        case "BT07_EMP_RECIBE":
                            $mensaje = 'El empleado no cuenta con el Puesto: ALMACENISTA, GERENTE ó JEFE.';
                            break;

                        case "BT07_EMP_ENTREGA":
                            $mensaje = 'El empleado no cuenta con el Puesto: GERENTE SISTEMAS, GERENTE TITULAR, SOPORTE TECNOLÓGICO ó JEFE.';
                            break;

                        case "BT08_EMP_CANCELA":
                            $mensaje = 'El empleado no cuenta con el Puesto: ALMACENISTA.';
                            break;

                        case "BT08_EMP_AUTORIZACANCELACION":
                            $mensaje = 'El empleado no cuenta con el Puesto: JEFE, GERENTE TITULAR ó GERENTE SISTEMAS.';
                            break;

                        case "BT31_AUTORIZAR_ENTRADA":
                                $mensaje = 'El empleado no cuenta con ninguno de los siguientes Puestos: GERENTE TITULAR, JEFE ó ALMACENISTA.';
                                break;

                        case "BT09_EMP_ENTREGA":
                                $mensaje = 'El Empleado no tiene puesto valido para entregar.';
                                break;

                        case "BT09_EMP_RECIBE":
                                $mensaje = 'El Empleado no tiene ninguno un puesto valido para recibir.';
                                break;

                        case "BT10_EMP_AUTORIZA":
                            $mensaje = 'El empleado no cuenta con el Puesto: ALMACENISTA ó JEFE ó GERENTE TITULAR '.
                                'ó GERENTE SISTEMAS ó SOPORTE TECNOLÓGICO.';
                            break;

                        case "BT33_PERMISO_POLIZA_AUDITOR":
                            $mensaje = 'Usted no cuenta con ninguno de los siguientes puestos Auditor , Jefe o Gerente.';
                            break;

                        case "BT33_PERMISO_POLIZA_GERENTE":
                            $mensaje='Usted no cuenta con ninguno de los siguientes puestos Almacenista , Jefe o Gerente.';
                            break;

                        case "BT33_PERMISO_POLIZA_HUESARIO_AUDITOR":
                            $mensaje = 'Usted no cuenta con el puesto de Auditor.';
                            break;

                        case "BT33_PERMISO_POLIZA_HUESARIO_GERENTE":
                            $mensaje = 'Usted no cuenta con ninguno de los siguientes puestos Jefe o Gerente.';
                            break;

                        default:
                            $respuesta->setEstatus(-1);
                            $mensaje = 'Permiso no válido.';
                            break;
                    }
                }
                $respuesta->setMensaje($mensaje);
            }
            $statement->closeCursor();
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $respuesta->getResponse();
    }

    public function buscarArticulo($descripcion)
    {
        $respuesta = new Modelos\RespuestaModel();
        $respuesta->iniciar();
        $respuesta->setEstatus(0);
        $respuesta->setMensaje('');
        $data = [];
        $opcion = 1;
        $ip = 0;
        $namedb = '';
        try {
            $db = $this->generarConexionCedis($opcion, $ip, $namedb);
            if($db == null) {
                $respuesta->setEstatus(-2);
                $respuesta->setMensaje('No se logró abrir conexión, favor de consultar a mesa de ayuda');
                $logger = \Phalcon\DI::getDefault()->get('logger');
                $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
            } else {
                $statement = $db->prepare("
                    SELECT
                        numarticulo, articulo, marca, modelo, numparte 
                    FROM
                        fun_tecconsultacodigospordescripcion(:descripcion);");
                $descripcion = utf8_decode($descripcion);
                $statement->bindParam(':descripcion',$descripcion, \PDO::PARAM_STR);
                try {
                    $statement->execute();
                    while ($entry = $statement->fetch()) {
                        $respuesta->setEstatus(1);
                        $respuesta->setMensaje('');
                        $data = new \stdClass();
                        $data->numarticulo = $entry["numarticulo"];
                        $data->articulo = utf8_encode($entry["articulo"]);
                        $data->marca = utf8_encode(trim($entry["marca"]));
                        $data->modelo = utf8_encode(trim($entry["modelo"]));
                        $data->numparte = $entry["numparte"];
                        $respuesta->addData($data);
                        $data = null;
                    }
                    $statement->closeCursor();
                } catch (\Exception $ex) {
                    $respuesta->setEstatus(-1);
                    $respuesta->setMensaje('No se logro realizar la consulta de los articulos');
                    $m = utf8_encode($ex->getMessage());
                    $logger = \Phalcon\DI::getDefault()->get('logger');
                    $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
                }
            }
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }           
    
        return $respuesta->getResponse();
    }

    public function buscarCentro($datos)
    {
        $respuesta = new Modelos\RespuestaModel();
        $respuesta->iniciar();
        $respuesta->setEstatus(0);
        $respuesta->setMensaje('');
        $data = [];
        $opcion = 3;
        $ip = 0;
        $namedb = '';
        $numero = 1;
        try {
            $ipData = new \stdClass();
            $ipData = $this->consultaIpPersonal($numero);
            $respuesta->setEstatus($ipData->STATUS);
            $respuesta->setMensaje($ipData->MENSAJE);
            if ($respuesta->getEstatus() > 0) {
                $ip = $ipData->IP;
                $respuesta->setEstatus(0);
                $respuesta->setMensaje('');
                $di = \Phalcon\DI::getDefault();
                $db = $this->generarConexionCedis($opcion, $ip, $namedb);
                if($db == null) {
                    $respuesta->setEstatus(-2);
                    $respuesta->setMensaje('No se logró abrir conexión, favor de consultar a mesa de ayuda');
                    $logger = \Phalcon\DI::getDefault()->get('logger');
                    $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
                } else {
                    $statement = $db->prepare("
                        SELECT
                            numerocentro, TRIM(nombrecentro) AS nombrecentro 
                        FROM
                            hetecayudaconsultacentroa( :centro, :tamanio, :opcion, '1')");
                    $datos->centro = utf8_decode($datos->centro);
                    $statement->bindParam(':centro', $datos->centro, \PDO::PARAM_STR);
                    $statement->bindParam(':tamanio',$datos->tamanio, \PDO::PARAM_INT);
                    $statement->bindParam(':opcion',$datos->opcion, \PDO::PARAM_INT);
                    try {
                        $statement->execute();
                        while ($entry = $statement->fetch()) {
                            $respuesta->setEstatus(1);
                            $respuesta->setMensaje('');
                            $data = new \stdClass();
                            $data->numerocentro = $entry["numerocentro"];
                            $data->nombrecentro = utf8_encode(trim($entry["nombrecentro"]));
                            $respuesta->addData($data);
                            $data = null;
                        }
                        $statement->closeCursor();
                    } catch (\Exception $ex) {
                        $respuesta->setEstatus(-1);
                        $respuesta->setMensaje('No se logro realizar la consulta de los centros');
                        $m = utf8_encode($ex->getMessage());
                        $logger = \Phalcon\DI::getDefault()->get('logger');
                        $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
                    }
                }
            }
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }           
    
        return $respuesta->getResponse();
    }

    public function buscarCentroSalida($centro)
    {
        $respuesta = new Modelos\RespuestaModel();
        $respuesta->iniciar();
        $respuesta->setEstatus(0);
        $respuesta->setMensaje('');
        $data = [];

        $opcion = 3;
        $ip = 0;
        $namedb = '';

        $numero = 1;

        try {
            $ipData = new \stdClass();
            $ipData = $this->consultaIpPersonal($numero);

            $respuesta->setEstatus($ipData->STATUS);
            $respuesta->setMensaje($ipData->MENSAJE);

            if ($respuesta->getEstatus() > 0) {

                $ip = $ipData->IP;

                $respuesta->setEstatus(0);
                $respuesta->setMensaje('');

                $di = \Phalcon\DI::getDefault();
                //opcion 1 = dbTecnologia, opcion 2 = dbGlobal, opcion 3 = dbPersonal, opcion 4 = dbMueblesSql, opcion 5 = dbMuebles
                $db = $this->generarConexionCedis($opcion, $ip, $namedb);

                if($db == null) {
                    $respuesta->setEstatus(-2);
                    $respuesta->setMensaje('No se logró abrir conexión, favor de consultar a mesa de ayuda');
                    $logger = \Phalcon\DI::getDefault()->get('logger');
                    $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
                } else {

                    $statement = $db->prepare("SELECT numerocentro, nombrecentro FROM hetecsalidascentrosconsultacentro (:centro)");

                    $statement->bindParam(':centro',$centro, \PDO::PARAM_INT);

                    try {
                        $statement->execute();
                        if ($entry = $statement->fetch()) {
                            $respuesta->setEstatus(1);
                            $respuesta->setMensaje('');
                            $data = new \stdClass();
                            $data->numerocentro = $entry["numerocentro"];
                            $data->nombrecentro = utf8_encode($entry["nombrecentro"]);

                            $respuesta->setData($data);
                            $data = null;
                        }

                        $statement->closeCursor();

                    } catch (\Exception $ex) {
                        $respuesta->setEstatus(-1);
                        $respuesta->setMensaje('No se logro realizar la consulta de los centros');
                        $m = utf8_encode($ex->getMessage());
                        $logger = \Phalcon\DI::getDefault()->get('logger');
                        $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
                    }
                }
            }
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }           

    
        return $respuesta->getResponse();
    }

    public function buscarServicios($datos)
    {
        $respuesta = new Modelos\RespuestaModel();
        $respuesta->iniciar();
        $respuesta->setEstatus(0);
        $respuesta->setMensaje('');
        $data = [];
       
        try {
            $db = $this->generarConexionCedis($this->CONEXION_TECNOLOGIA, 0, '');

            $statement = $db->prepare("
                SELECT
                    idu_servicio, nom_servicio, url_ruta
                FROM
                    fun_ct001consultaservicios (:bodega, :almacen)");
            $statement->bindParam(':bodega',$datos->bodega, \PDO::PARAM_INT);
            $statement->bindParam(':almacen',$datos->almacen, \PDO::PARAM_INT);
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $data = new \stdClass();
                $data->id = $entry["idu_servicio"];
                $data->servicio = utf8_encode($entry["nom_servicio"]);
                $data->ruta = $entry["url_ruta"];

                $respuesta->setEstatus(1);
                $respuesta->setMensaje('');
                $respuesta->addData($data);
            }
            $statement->closeCursor();            
        } catch (\Exception $ex) {
            $m = utf8_encode($ex->getMessage());
            $this->responseException($m, __METHOD__);
        }

        return $respuesta->getResponse();
    }

    public function buscarArticuloSalida($datos)
    {
        $respuesta = new Modelos\RespuestaModel();
        $respuesta->iniciar();
        $respuesta->setEstatus(0);
        $respuesta->setMensaje('');
        $data = [];
        $opcion = 1;
        $ip = 0;
        $namedb = '';
        $db = $this->generarConexionCedis($opcion, $ip, $namedb);
        if($db == null) {
            $respuesta->setEstatus(-2);
            $respuesta->setMensaje('No se logró abrir conexión, favor de consultar a mesa de ayuda');
            $logger = \Phalcon\DI::getDefault()->get('logger');
            $logger->error( "[".__METHOD__."] No se logro establecer la conexion al servidor ");
        } else {
            $statement = $db->prepare("
                SELECT
                    numarticulo, descripcion, disponible
                FROM 
                    fun_tecayudaitconsultacodigosdescripcion_2( :aplicacion, :bodega, :almacen, :tipo, :descripcion, :temporal);");
            $datos->descripcion = utf8_decode($datos->descripcion);
            $statement->bindParam(':aplicacion', $datos->aplicacion);
            $statement->bindParam(':bodega', $datos->bodega, \PDO::PARAM_INT);
            $statement->bindParam(':almacen', $datos->almacen, \PDO::PARAM_INT);
            $statement->bindParam(':tipo', $datos->tipo);
            $statement->bindParam(':descripcion', $datos->descripcion, \PDO::PARAM_STR);
            $statement->bindParam(':temporal', $datos->temporal, \PDO::PARAM_INT);
            try {
                $statement->execute();
                while ($entry = $statement->fetch()) {
                    $respuesta->setEstatus(1);
                    $respuesta->setMensaje('');
                    $data = new \stdClass();
                    $data->numarticulo = $entry["numarticulo"];
                    $data->descripcion = utf8_encode(trim($entry["descripcion"]));
                    $data->disponible = $entry["disponible"];
                    $respuesta->addData($data);
                    $data = null;
                }
                $statement->closeCursor();
            } catch (\Exception $ex) {
                $respuesta->setEstatus(-1);
                $respuesta->setMensaje('No se logro realizar la consulta de los articulos');
                $m = utf8_encode($ex->getMessage());
                $logger = \Phalcon\DI::getDefault()->get('logger');
                $logger->error( "[". $_SERVER['REMOTE_ADDR'] ."][".__METHOD__."][".__FUNCTION__ ."][". __LINE__ ."] Exception: " . $m );
            }
        }
    
        return $respuesta->getResponse();
    }
}
