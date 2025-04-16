<?php

namespace Coppel\LegacyTmsEntregasropadistribucion\Models;

use Phalcon\DI\DI;
use Phalcon\Mvc\Model;
use Coppel\RAC\Exceptions\HTTPException;

class RopaDistribucionModel extends Model
{
    private $logger;

	public function onConstruct()
	{
		$this->logger = DI::getDefault()->get('logger');
	}

    const CEDIS = "bodegamuebles.";

    private function respuestaException($mensaje = '', $httpcode= 500, $metodo = __METHOD__)
    {
        $this->logger->error(
            '[CLIENTE] '.$_SERVER['REMOTE_ADDR'].' '.
            '['.$metodo."] Se lanzó la excepción > $mensaje"
        );
        throw new HTTPException(
            $mensaje,
            $httpcode,
            [
                'dev' => 'No fue posible completar su solicitud, intente de nuevo por favor.',
                'internalCode' => '500'
                
            ]
        );
    }

    public function confirmarPaqueteRopa($cedis, $paquete)
	{		
        $response = null;
		try {
            $di = DI::getDefault();
            $di->host = $this->consultarIpCedis($cedis);
            $di->dbname = self::CEDIS . $cedis;
            $db = $di->get('bodegaMuebles');
            $statement = $db->prepare("SELECT fun_bmactualizarestadoguiasropa(:numguia, :numpedido, :estado);");
            $statement->bindValue('numguia', $paquete->numeroguia, \PDO::PARAM_STR);
            $statement->bindValue('numpedido', $paquete->numeropedido, \PDO::PARAM_INT);
            $statement->bindValue('estado', $paquete->opc_estado, \PDO::PARAM_INT);
            $statement->execute();
            while($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $response = $entry["fun_bmactualizarestadoguiasropa"] == 1;
            }
            $statement->closeCursor();
		} catch (\Exception $ex) {
			$this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
		}

        return $response;
	}

    public function registrarEnvioClientesRopa($cedis, $cedisDistribuye, $paquete)
	{		
        $guia = new \stdClass();
		try {
            $di = DI::getDefault();
            $di->host = $this->consultarIpCedis($cedisDistribuye);
            $di->dbname = self::CEDIS . $cedisDistribuye;
            $db = $di->get('bodegaMuebles');

            $encoding = $this->obtenerEncondingBd($cedisDistribuye, $di->host);

            $statement = $db->prepare("SELECT fechaservidor, descripcionruta, nombrebodega, codigobarras, fechasurtir, ciudadcliente
            FROM fun_registrarenvionclientesropa(:numtienda, :numfactura, :fecventa, :numpedido, :numcliente, :nomcliente,
            :nomapellidopaterno, :nomapellidomaterno, :numciudad, :numzona, :nomzona, :nomcalle, :numcasa, :numcasainterior,
            :numtelefono, :desentrecalles, :desobservaciones, :nompersonarecibe, :numcodigopostal, :numcodigo, :fecpromesa, :numCedis,
            :consecutivoGuia, :numruta, :numciudadpertenece);");

            $nombrecliente = $this->convertAscii($encoding, $paquete->nombrecliente, true);
            $nombreapellidopaterno = $this->convertAscii($encoding, $paquete->nombreapellidopaterno, true);
            $nombreapellidomaterno = $this->convertAscii($encoding, $paquete->nombreapellidomaterno, true);
            $nombrezona = $this->convertAscii($encoding, $paquete->nombrezona, true);
            $nombrecalle = $this->convertAscii($encoding, $paquete->nombrecalle, true);
            $entrecalles = $this->convertAscii($encoding, $paquete->entrecalles, true);
            $observaciones = $this->convertAscii($encoding, $paquete->observaciones, true);
            $nombrepersonarecibe = $this->convertAscii($encoding, $paquete->nombrepersonarecibe, true);
            $consecutivo = $this->convertAscii($encoding, $paquete->consecutivoGuia, true);

            $statement->bindValue('numtienda', $paquete->tienda, \PDO::PARAM_INT);
            $statement->bindValue('numfactura', $paquete->factura, \PDO::PARAM_INT);
            $statement->bindValue('fecventa', $paquete->fechaventa, \PDO::PARAM_STR);
            $statement->bindValue('numpedido', $paquete->numeropedido, \PDO::PARAM_INT);
            $statement->bindValue('numcliente', $paquete->numerocliente, \PDO::PARAM_INT);
            $statement->bindValue('nomcliente', $nombrecliente, \PDO::PARAM_STR);
            $statement->bindValue('nomapellidopaterno', $nombreapellidopaterno, \PDO::PARAM_STR);
            $statement->bindValue('nomapellidomaterno', $nombreapellidomaterno, \PDO::PARAM_STR);
            $statement->bindValue('numciudad', $paquete->numerociudad, \PDO::PARAM_INT);
            $statement->bindValue('numzona', $paquete->numerozona, \PDO::PARAM_INT);
            $statement->bindValue('nomzona', $nombrezona, \PDO::PARAM_STR);
            $statement->bindValue('nomcalle', $nombrecalle, \PDO::PARAM_STR);
            $statement->bindValue('numcasa', $paquete->numerocasa, \PDO::PARAM_INT);
            $statement->bindValue('numcasainterior', $paquete->numerocasainterior, \PDO::PARAM_STR);
            $statement->bindValue('numtelefono', $paquete->numerotelefono, \PDO::PARAM_STR);
            $statement->bindValue('desentrecalles', $entrecalles, \PDO::PARAM_STR);
            $statement->bindValue('desobservaciones', $observaciones, \PDO::PARAM_STR);
            $statement->bindValue('nompersonarecibe', $nombrepersonarecibe, \PDO::PARAM_STR);
            $statement->bindValue('numcodigopostal', $paquete->numcodigopostal, \PDO::PARAM_STR);
            $statement->bindValue('numcodigo', $paquete->numerocodigo, \PDO::PARAM_INT);
            $statement->bindValue('fecpromesa', $paquete->fecha_promesa, \PDO::PARAM_STR);
            $statement->bindValue('numCedis', $cedis, \PDO::PARAM_INT);
            $statement->bindValue('consecutivoGuia', $consecutivo, \PDO::PARAM_STR);
            $statement->bindValue('numruta', $paquete->numruta, \PDO::PARAM_INT);
            $statement->bindValue('numciudadpertenece', $paquete->numciudadpertenece, \PDO::PARAM_INT);
            $statement->execute();

            while($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $guia->fechaservidor = $entry["fechaservidor"];
                $guia->descripcionruta = $this->convertAscii($encoding, $entry["descripcionruta"], false);
                $guia->nombrebodega = $this->convertAscii($encoding, $entry["nombrebodega"], false);
                $guia->codigobarras = $entry["codigobarras"];
                $guia->fechasurtir = $entry["fechasurtir"];
                $guia->ciudadcliente = $entry["ciudadcliente"];
            }
            $statement->closeCursor();
		} catch (\Exception $ex) {
			$this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
		}

        return $guia;
	}

    public function consultarBodegaDistribuyeRegistro($cedis, $paquete)
    {
        // Se consulta que bodega es la responsable de la distribución a esa ciudad
        $bodegaDistribuye = $this->consultarBodegaDistribuyePorCiudad($cedis, $paquete->numerociudad);

        // Se buscan datos de la ruta en la bodega encontrada
        $datosRuta = $this->obtenerRutayCiudadPertenece($bodegaDistribuye->numBodega, $paquete->numerociudad,
            $paquete->numerozona);

        if($datosRuta->numRuta != 0 && $datosRuta->numCiudadPertenece != 0) {
            $datosRuta->numBodega = $bodegaDistribuye->numBodega;
            $datosRuta->nomBodegaCorto = $bodegaDistribuye->nomBodegaCorto;

            // Graba la bodega responsable de la distribucion y se obtiene el cosecutivo de la
            // bodega donde se cierra el paquete
            $datosRuta->consecutivo = $this->grabaBodegaDistribuye($cedis, $bodegaDistribuye->numBodega,
                $paquete->tienda, $paquete->factura, $paquete->numerocodigo);

            return $datosRuta;
        }

        // Si no existe ruta para la bodega encontrada basandose en la ciudad, se busca que
        // bodega tiene ruta para esa ciudad-zona
        $bodegasCercanas = $this->obtenerBodegasCercanas($bodegaDistribuye->numBodega);
        foreach($bodegasCercanas as $bodega) {
            $datosRuta = $this->obtenerRutayCiudadPertenece($bodega->numBodega, $paquete->numerociudad,
            $paquete->numerozona);

            if($datosRuta->numRuta != 0 && $datosRuta->numCiudadPertenece != 0) {
                $datosRuta->numBodega = $bodega->numBodega;
                $datosRuta->nomBodegaCorto = $bodega->nomBodegaCorto;

                // Graba la bodega responsable de la distribucion y se obtiene el cosecutivo de la
                // bodega donde se cierra el paquete
                $datosRuta->consecutivo = $this->grabaBodegaDistribuye($cedis, $bodega->numBodega,
                    $paquete->tienda, $paquete->factura, $paquete->numerocodigo);
    
                return $datosRuta;
            }
        }
    }

    public function consultarBodegaDistribuyePorCiudad($cedis, $numciudad)
    {
        $bodega = new \stdClass();

        try {
            $di = DI::getDefault();
            $di->host = $this->consultarIpCedis($cedis);
            $di->dbname = self::CEDIS . $cedis;
            $db = $di->get('bodegaMuebles');

            $encoding = $this->obtenerEncondingBd($cedis, $di->host);
            $statement = $db->prepare("SELECT bodega, nombodega4 FROM
             fun_bmconsultabodegadistribuyeporciudad(:numciudad::int)");

            $statement->bindValue('numciudad', $numciudad, \PDO::PARAM_INT);

            $statement->execute();

            $data = $statement->fetch(\PDO::FETCH_ASSOC);
            if ($data) {
                $bodega->numBodega = $this->validateData($data['bodega']);
                $bodega->nomBodegaCorto = $this->convertAscii($encoding, $data["nombodega4"], false);
            }

            $statement->closeCursor();
        } catch (\Exception $ex) {
			$this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
		}

        return $bodega;
    }

    private function obtenerBodegasCercanas($cedis)
    {
        $bodegas = array();
        $latitudCedis = 0;
        $longitudCedis = 0;

        try {
            $di = DI::getDefault();
            $di->host = $this->consultarIpCedis($cedis);
            $di->dbname = self::CEDIS . $cedis;
            $db = $di->get('bodegaMuebles');

            $encoding = $this->obtenerEncondingBd($cedis, $di->host);
            $statement = $db->prepare("SELECT numbodega, nomcortobodega,
             latitud, longitud FROM fun_bmconsultageolocalizacionesdebodegas()");

            $statement->execute();

            while($data = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $bodega = new \stdClass();

                $bodega->numBodega = $this->validateData($data['numbodega']);
                $bodega->nomBodegaCorto = $this->convertAscii($encoding, $data["nomcortobodega"], false);
                $bodega->latitud = $this->convertAscii($encoding, $data["latitud"], false);
                $bodega->longitud = $this->convertAscii($encoding, $data["longitud"], false);

                if($cedis == $bodega->numBodega) {
                    // Se graban los datos del cedis principal
                    $latitudCedis = $bodega->latitud;
                    $longitudCedis = $bodega->longitud;
                } else {
                    // El cedis principal no se agrega al arreglo
                    array_push($bodegas, $bodega);
                }
            }

            $statement->closeCursor();
        } catch (\Exception $ex) {
			$this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
		}

        // No se encontraron datos de la bodega donde se busca
        if($latitudCedis == 0 || $longitudCedis == 0) {
            // Se omite ordenamiento por cercania
            return $bodegas;
        }

        // Se obtiene la distancia que se tiene a cada bodega encontrada
        $bodegasDistancias = array();
        foreach($bodegas as $bod) {
            $bd = new \stdClass();

            $bd->numBodega = $bod->numBodega;
            $bd->nomBodegaCorto = $bod->nomBodegaCorto;

            if($bod->latitud != '0' && $bod->longitud != '0') {
                $bd->distance = $this->obtenerDistancia($latitudCedis, $longitudCedis, $bod->latitud, $bod->longitud);
            } else {
                // Si no se tiene latitud y longitud se pone un número grande para ser los últimos
                $bd->distance = 100000000;
            }
            
            array_push($bodegasDistancias, $bd);
        }

        // Se regresa un arreglo ordenado por la distancia
        return $this->ordenarArregloPorCercania($bodegasDistancias);
    }

    public function consultarBodegaDistribuyeConfirmacion($cedis, $guia)
    {
        $bodega = new \stdClass();

        try {
            $di = DI::getDefault();
            $di->host = $this->consultarIpCedis($cedis);
            $di->dbname = self::CEDIS . $cedis;
            $db = $di->get('bodegaMuebles');

            $encoding = $this->obtenerEncondingBd($cedis, $di->host);
            $statement = $db->prepare("SELECT
             bodega, nombodega4 FROM fun_bmconsultabodegadistribuyeporguia(:guia::character varying)");

            $statement->bindValue('guia', $this->convertAscii($encoding, $guia, true), \PDO::PARAM_STR);

            $statement->execute();

            $data = $statement->fetch(\PDO::FETCH_ASSOC);
            if ($data) {
                $bodega->numBodega = $this->validateData($data['bodega']);
                $bodega->nomBodegaCorto = $this->convertAscii($encoding, $data["nombodega4"], false);
            }

            $statement->closeCursor();
        } catch (\Exception $ex) {
			$this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
		}

        return $bodega;
    }

    private function obtenerRutayCiudadPertenece($cedis, $ciudad, $zona) {
        $datosRuta = new \stdClass();

        try {
            $di = DI::getDefault();
            $di->host = $this->consultarIpCedis($cedis);
            $di->dbname = self::CEDIS . $cedis;
            $db = $di->get('bodegaMuebles');

            $statement = $db->prepare("SELECT numruta, numciudadpertenece FROM
             fun_bmconsultarutaporciudadyzona(:numciudad::int, :numzona::int)");

            $statement->bindValue('numciudad', $ciudad, \PDO::PARAM_INT);
            $statement->bindValue('numzona', $zona, \PDO::PARAM_INT);

            $statement->execute();

            $data = $statement->fetch(\PDO::FETCH_ASSOC);
            if ($data) {
                $datosRuta->numRuta = $data['numruta'];
                $datosRuta->numCiudadPertenece = $data['numciudadpertenece'];
            }

            $statement->closeCursor();
        } catch (\Exception $ex) {
			$this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
		}

        return $datosRuta;
    }

    private function grabaBodegaDistribuye($cedis, $bodegaDistribuye, $tienda, $factura, $codigo) {
        $consecutivo = '';

        try {
            $di = DI::getDefault();
            $di->host = $this->consultarIpCedis($cedis);
            $di->dbname = self::CEDIS . $cedis;
            $db = $di->get('bodegaMuebles');

            $encoding = $this->obtenerEncondingBd($cedis, $di->host);

            $statement = $db->prepare("SELECT cadenaconsecutivo FROM
             fun_bmguardabodegadistribuyeguia(:numbodega::int, :numtienda::int, :numfactura::int,
             :numcodigo::int)");

            $statement->bindValue('numbodega', $bodegaDistribuye, \PDO::PARAM_INT);
            $statement->bindValue('numtienda', $tienda, \PDO::PARAM_INT);
            $statement->bindValue('numfactura', $factura, \PDO::PARAM_INT);
            $statement->bindValue('numcodigo', $codigo, \PDO::PARAM_INT);

            $statement->execute();

            $data = $statement->fetch(\PDO::FETCH_ASSOC);
            if ($data) {
                $consecutivo = $this->convertAscii($encoding, $data["cadenaconsecutivo"], false);
            }

            $statement->closeCursor();
        } catch (\Exception $ex) {
			$this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
		}

        return $consecutivo;
    }

    public function consultarIpCedis($cedis)
    {
        $ip = "";
        try {
            $di = DI::getDefault();
            $db = $di->get('apartadoEcommerce');
            $statement = $db->prepare("SELECT fun_consultaipcedisenvioclientes(:cedis);");
            $statement->bindValue('cedis', $cedis, \PDO::PARAM_INT);
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC))
            {
                $ip = $entry["fun_consultaipcedisenvioclientes"] ;
            }
            $statement->closeCursor();
        } catch (\Exception $ex) {
			$this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
		}

        return $ip;
    }

    public function obtenerEncondingBd($bodega, $ipBodega)
    {
        $encoding = false;
        $di = DI::getDefault();
        $di->host = $ipBodega;
        $di->dbname = self::CEDIS . $bodega;
        $db = $di->get('bodegaMuebles');

        try {
            $statement = $db->prepare("SHOW server_encoding");
            $statement->execute();
            while ($entry = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $encoding = trim($entry["server_encoding"]) == "SQL_ASCII";
            }
            $statement->closeCursor();
            return $encoding;
        } catch (\Exception $ex) {
			$this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
		}
    }

    private function validateData($bodega)
    {
        $data = 0;

        if (!isset($bodega) && !is_numeric($bodega)) {
            return $data;
        }
        if ($bodega > 30000 && $bodega < 100000) {
            $sanitiziedBodega = strval($bodega);
            str_replace("'", "", $sanitiziedBodega);
            return intval($sanitiziedBodega);
        }
        return $data;
    }

    private function convertAscii($encoding, $cadena, $insert)
    {
        if ($insert) {
            return $encoding ? utf8_decode($cadena) : $cadena;
        }
        return $encoding ? utf8_encode($cadena) : $cadena;
    }

    private function ordenarArregloPorCercania($array)
    {
        $leftAuxArray = $rightAuxArray = array();
        if(count($array) < 2)
        {
            return $array;
        }
        $pivotKey = key($array);
        $pivot = array_shift($array);
        foreach($array as $val)
        {
            if($val->distance <= $pivot->distance)
            {
                $leftAuxArray[] = $val;
            }elseif ($val->distance > $pivot->distance)
            {
                $rightAuxArray[] = $val;
            }
        }
        return array_merge($this->ordenarArregloPorCercania($leftAuxArray),
            array($pivotKey=>$pivot), $this->ordenarArregloPorCercania($rightAuxArray));
    }

    private function obtenerDistancia(
        $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);
    
        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
        pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);
    
        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }
}
