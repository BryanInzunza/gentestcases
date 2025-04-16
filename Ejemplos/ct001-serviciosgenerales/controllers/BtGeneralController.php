<?php

namespace Coppel\ApiBtGenerales\Controllers;

header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

use Coppel\RAC\Controllers\RESTController;
use Coppel\RAC\Exceptions\HTTPException;
use Coppel\ApiBtGenerales\Models as Modelos;
use Httpful\Request;

class BtGeneralController extends RESTController
{
    private $logger;
    private $modelo;

    public function onConstruct()
    {
        $this->logger = \Phalcon\DI::getDefault()->get('logger');
        $this->modelo = new Modelos\BtGeneralModel();
    }

    private function responseException($mensaje='', $metodo=__METHOD__) {        
        $this->logger->error('['. $metodo."] Se lanzó la excepción > $mensaje");

        throw new HTTPException(
            'No fue posible completar su solicitud, intente de nuevo por favor.',
            500, [
                'dev' => $mensaje,
                'internalCode' => 'SIE1000',
                'more' => 'Verificar conexión con la base de datos.'
            ]
        );
    }

    public function pruebaConexion() {
        try {
          $mRespuesta = new Modelos\RespuestaModel();
          $mRespuesta->iniciar();
          $data = new \stdClass();
          $data->type = $this->request->getQuery("tipo", null, '');
          $data->host = $this->request->getQuery("servidor", null, '');
          $data->db = $this->request->getQuery("basedatos", null, '');
          $data->usr = $this->request->getQuery("usuario", null, '');
          $data->psw = $this->request->getQuery("clave", null, '');
          
          if (
            $data->type === '' ||
            $data->host === '' ||
            $data->db === '' ||
            $data->usr === '' ||
            $data->psw === ''
          ) {
            $mRespuesta->setEstatus(-1);
            $mRespuesta->setMensaje("Parámetros incorrectos");
            return $this->respond(['response' => $mRespuesta->getResponse()]);    
          }
         
          $data = $this->modelo->pruebaConexion(
            $data->type,
            $data->host,
            $data->db,
            $data->usr,
            $data->psw
          );
          $mRespuesta->iniciar($data);
        } catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }

        return $this->respond(['response' => $mRespuesta->getResponse()]);    
    }

    public function validarIngreso() {
        try {
          $terminal = $this->request->getQuery("terminal", null, '');
          $terminal = ($terminal == '') ? $_SERVER['REMOTE_ADDR'] : $terminal; 
          $terminal = trim($terminal);
          $puesto = $this->request->getQuery("puesto", null, 0);
          $puesto = ($puesto == '') ? 0 : $puesto; 
          $puesto = intval($puesto);

          // DATOS BODEGA
          $dataBodega = new Modelos\RespuestaModel();
          $data = $this->modelo->consultarBodegaCorrespondiente($terminal);
		  
          $dataBodega->iniciar($data);
          if ($dataBodega->getEstatus() < 0) {         
            return $this->respond(['response' => $dataBodega->getResponse()]);
          }

          $dataEmpleado = new \stdClass();
          $dataEmpleado->puesto = $puesto;
          $data = $this->modelo->validarPuesto($dataEmpleado, 'BT_ACCESO');
          $dataPuesto = new Modelos\RespuestaModel();
          $dataPuesto->iniciar($data);
          if ($dataPuesto->getEstatus() <= 0) {
              $rResponse = new Modelos\RespuestaModel();
              $rResponse->iniciar();
              $rResponse->setEstatus(-1);
              $rResponse->setMensaje($dataPuesto->getMensaje());

              return $this->respond(['response' => $rResponse->getResponse()]);
          }

          // DATOS ALMACEN
          $params = new \stdClass();
          $params->bodega = $dataBodega->getData();
          $params->terminal = $terminal;
		  
		  
          $dataAlmacen = new Modelos\RespuestaModel();
          $data = $this->modelo->consultarAlmacen($params);
          $dataAlmacen->iniciar($data);
          if ($dataAlmacen->getEstatus() <= 0) {
            return $this->respond(['response' => $dataAlmacen->getResponse()]);
          }

          // MENU
          $dataMenu = new Modelos\RespuestaModel();
          $params->almacen = $dataAlmacen->getData()->almacen;
          $opciones = $this->modelo->consultarMenu($params);
          $menu = $this->formarMenu($opciones, 0);          
          $dataMenu->iniciar();
          $dataMenu->setData($this->formarMenu($opciones, 0));

          // RETORNAR RESPUESTA
          $dataRespuesta = new Modelos\RespuestaModel();
          $dataRespuesta->iniciar();
          $dataRespuesta->setEstatus(1);
          $dataRespuesta->setMensaje('');


          $respuesta['sesion'] = array(
            'terminal' => $terminal,
            'temporal' => str_replace(".","",$terminal),
            'bodega' => $dataBodega->getData(),
            'almacen' => $dataAlmacen->getData()->almacen,
            'centro' => $dataAlmacen->getData()->centro
          );
          $respuesta['menu'] = $menu;
          $dataRespuesta->setData($respuesta);

          return $this->respond(['response' => $dataRespuesta->getResponse()]);

        } catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }
    }

    public function consultarMenu() {
        $respuesta = new \stdClass();

        try {
            $params = new \stdClass();
            $params->bodega = $this->request->getQuery("bodega", null, '0');
            $params->almacen = $this->request->getQuery("almacen", null, '0');
            
            $opciones = array();
            $opciones = $this->modelo->consultarMenu($params);
            $menu = $this->formarMenu($opciones, 0);
        } catch (\Exception $ex) {
            $mensaje = $ex->getMessage();
            $this->logger->error('['. __METHOD__ ."] Se lanzó la excepción > $mensaje");

            throw new HTTPException(
                'No fue posible completar su solicitud, intente de nuevo por favor.',
                500, [
                    'dev' => $mensaje,
                    'internalCode' => 'SIE1000',
                    'more' => 'Verificar conexión con la base de datos.'
                ]
            );
        }

        $respuesta->menu = $menu;

        return $this->respond(['response' => $respuesta]);
    }

    public function formarMenu($opciones, $nodo) {
        $respuesta = array();
        $resultSet = new \stdClass();
        $hijos = [];

        try {
            for ($i=0; $i < count($opciones); $i++) { 
                if($nodo == $opciones[$i]->nodopadre){
                    $resultSet = new \stdClass();
                    $resultSet->name = $opciones[$i]->opcion;
                    $resultSet->url = $opciones[$i]->url;                    
                    $resultSet->icon = '';                    
                    if(intval($opciones[$i]->nodo) > 0){
                        $hijos = $this->formarMenu($opciones, $opciones[$i]->nodo);                         
                        foreach ($hijos as $hijo) {
                          $resultSet->icon = "icon-list";
                          $resultSet->children[] = $hijo;
                        }
                    }
                    
                    $respuesta[] = $resultSet;
                    $resultSet = null;
                }
            }
        } catch (\Exception $ex) {
            $mensaje = $ex->getMessage();
            $this->logger->error('['. __METHOD__ ."] Se lanzó la excepción > $mensaje");

            throw new HTTPException(
                'No fue posible completar su solicitud, intente de nuevo por favor.',
                500, [
                    'dev' => $mensaje,
                    'internalCode' => 'SIE1000',
                    'more' => 'Verificar conexión con la base de datos.'
                ]
            );
        }
      
        return $this->respond($respuesta);
    }

     public function consultarNotificaciones() {    
        try {
          $dataNotificaciones = new Modelos\RespuestaModel();
          $data = $this->modelo->consultarNotificaciones();
          $dataNotificaciones->iniciar($data);
        } catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }

        return $this->respond(['response' => $dataNotificaciones->getResponse()]);
    }

    public function datosRefaccionaria()
    {
        $datos = null;
        $response = null;
        $datos = new \stdClass();

        $ipaddress = '';

        try {

            $ipaddress = $this->request->getQuery("ipaddress", null, $_SERVER['REMOTE_ADDR']) ;
            $response = $this->modelo->datosRefaccionaria($ipaddress);

        } catch(\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->logger->error("[".__METHOD__ ."]"."Se lanzó la excepción ".$mensaje);
            throw new \Coppel\RAC\Exceptions\HTTPException(
                'No fue posible completar su solicitud, intente de nuevo por favor.',
                500,
                array(
                    'dev' => $mensaje,
                    'internalCode' => 'SIE2000',
                    'more' => 'Verificar conexión con la base de datos.'
                )
            );
        }
      
        return $this->respond([
            "STATUS" => $response->STATUS,
            "MENSAJE" => $response->MENSAJE,
            "DATA" => $response->DATA
            ]);
    }

    public function consultaFechaCorte($bodega)
    {
        $datos = null;
        $response = null;
        $datos = new \stdClass();
        try {

            $response = $this->modelo->consultaFechaCorte($bodega);

        } catch(\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->logger->error("[".__METHOD__ ."]"."Se lanzó la excepción ".$mensaje);
            throw new \Coppel\RAC\Exceptions\HTTPException(
                'No fue posible completar su solicitud, intente de nuevo por favor.',
                500,
                array(
                    'dev' => $mensaje,
                    'internalCode' => 'SIE2000',
                    'more' => 'Verificar conexión con la base de datos.'
                )
            );
        }
      
        return $this->respond([
            "STATUS" => $response->STATUS,
            "MENSAJE" => $response->MENSAJE,
            "DATA" => $response->DATA
            ]);
    }

    public function consultaCentros($centro)
    {
        $datos = null;
        $response = null;
        $datos = new \stdClass();
        try {

            $response = $this->modelo->consultaCentros($centro);

        } catch(\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->logger->error("[".__METHOD__ ."]"."Se lanzó la excepción ".$mensaje);
            throw new \Coppel\RAC\Exceptions\HTTPException(
                'No fue posible completar su solicitud, intente de nuevo por favor.',
                500,
                array(
                    'dev' => $mensaje,
                    'internalCode' => 'SIE2000',
                    'more' => 'Verificar conexión con la base de datos.'
                )
            );
        }
      
        return $this->respond([
            "STATUS" => $response->STATUS,
            "MENSAJE" => $response->MENSAJE,
            "DATA" => $response->DATA
            ]);
    }

    public function consultarCentroDetalles($centro) {
        try {            
            $data = $this->modelo->consultarCentroDetalles($centro);
            $rCentro = new Modelos\RespuestaModel();
            $rCentro->iniciar($data);
        }
        catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }        
        return $this->respond(['response' => $rCentro->getResponse()]);      
    }

    public function consultarCentroPorNombre($nombre) {
        try {            
            $data = $this->modelo->consultarCentroPorNombre($nombre);
            $rCentro = new Modelos\RespuestaModel();
            $rCentro->iniciar($data);
        }
        catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }        
        return $this->respond(['response' => $rCentro->getResponse()]);      
    }

    public function consultarEmpleado() {
        try { 
            $rResponse = new Modelos\RespuestaModel();

            $respuesta = array();
            $empleado = $this->request->getQuery("empleado", null);
            if (!isset($empleado)) {
                throw new HTTPException('Petición mal formada.',500, array());
            }
            if (!is_numeric($empleado)) {
                throw new HTTPException('Empleado no válido.',500, array());   
            }
        
            $params = new \stdClass();
            $params->empleado = intval($empleado);
            $data = $this->modelo->consultarEmpleado($params);
            $rResponse->iniciar($data);                       

            return $this->respond(['response' => $rResponse->getResponse()]);

        } catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }        
    }

    public function validarPuestoEmpleado() {
        try { 
            $rResponse = new Modelos\RespuestaModel();

            $respuesta = array();
            $empleado = $this->request->getQuery("empleado", null);
            $validarPuesto = $this->request->getQuery("validarPuesto", null);
            if (!isset($empleado) || !isset($validarPuesto)) {
                throw new HTTPException('Petición mal formada.',500, array());
            }
            if (!is_numeric($empleado)) {
                throw new HTTPException('Empleado no válido.',500, array());   
            }

            $params = new \stdClass();
            $params->empleado = $empleado;
            $data = $this->modelo->consultarEmpleado($params);
            $rResponse->iniciar($data);
            if ($rResponse->getEstatus() > 0) {
                $dataEmpleado = $rResponse->getData();
                $data = $this->modelo->validarPuesto($dataEmpleado, $validarPuesto);                    
                $rResponse->iniciar($data);
                if ($rResponse->getEstatus() > 0) {
                    $dataEmpleado->puestoValido = ($rResponse->getEstatus() > 0) ? 1 : 0;           
                    $rResponse->setData($dataEmpleado);            
                }
                
            }
                
            return $this->respond(['response' => $rResponse->getResponse()]);

        } catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }        
    }

    public function validarEmpleadoTemplate() {
        $rRespuesta = new Modelos\RespuestaModel();
        $rRespuesta->iniciar();
        $di = \Phalcon\DI::getDefault();

        try {
            $data = $this->request->GetJsonRawBody(); 
            $empleado = intval($data->empleado);
            $template = $data->template;
            if (!isset($empleado) || !isset($template)) {
                throw new HTTPException('Petición mal formada.',500, []);
            }    

            $cnfgHuellas = $di->get('config')->cnfgHuellas;
            $body['sistemaOrigen'] = $cnfgHuellas->sistemaOrigen;
            $body['tipoOperacion'] = $cnfgHuellas->tipoOperacion;
            $body['data']['Fingerprints'][0]['NumberEmp'] = $empleado;
            $body['data']['Fingerprints'][0]['TipoOperacion'] = $cnfgHuellas->idOperacion;
            $body['data']['Fingerprints'][0]['IpOrigen'] = $_SERVER['REMOTE_ADDR'];
            $body['data']['Fingerprints'][0]['Template'] = $template;
            $respuesta = Request::post($cnfgHuellas->url)
                ->addHeader('Content-Type', 'application/json')
                ->body(json_encode($body))
                ->send();

            $estatusHuellas = isset($respuesta->body->meta->status) ? $respuesta->body->meta->status : '';
            if ($estatusHuellas === '000') {
                $rRespuesta->setEstatus(1);
                $rRespuesta->setMEnsaje('');
            } else {
                $rRespuesta->setEstatus(-1);
                $rRespuesta->setMEnsaje('Empleado no válido.');
            }
        }
        catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }
        return $this->respond(["response" => $rRespuesta->getResponse()]);
    }

    public function buscarArticulo($descripcion) {
        try { 
            $respuesta = $this->modelo->buscarArticulo($descripcion);
        }
        catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }
        return $this->respond(['response' => $respuesta]);
    }

    public function buscarCentro() {
        $datos = null;
        $datos = new \stdClass();
        try {

            $datos->centro = $this->request->getQuery("centro", null, '0');
            $datos->tamanio = $this->request->getQuery("tamanio", null, '0');
            $datos->opcion = $this->request->getQuery("opcion", null, '0');

            $respuesta = $this->modelo->buscarCentro($datos);
        }
        catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }
        return $this->respond(['response' => $respuesta]);
    }

    public function buscarCentroSalida($centro) {
        $datos = null;
        $datos = new \stdClass();
        try {

            $respuesta = $this->modelo->buscarCentroSalida($centro);
        }
        catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }
        return $this->respond(['response' => $respuesta]);
    }

    public function buscarServicios() {
        $datos = null;
        $datos = new \stdClass();
        try {

            $datos->bodega = $this->request->getQuery("bodega", null, '0');
            $datos->almacen = $this->request->getQuery("almacen", null, '0');

            $respuesta = $this->modelo->buscarServicios($datos);
        }
        catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }
        return $this->respond(['response' => $respuesta]);
    }

    public function buscarArticuloSalida() {
        $datos = null;
        $datos = new \stdClass();
        try { 

            $datos->aplicacion = $this->request->getQuery("aplicacion", null, '');
            $datos->bodega = $this->request->getQuery("bodega", null, '0');
            $datos->almacen = $this->request->getQuery("almacen", null, '0');
            $datos->tipo = $this->request->getQuery("tipo", null, '');
            $datos->descripcion = $this->request->getQuery("descripcion", null, '');
            $datos->temporal = $this->request->getQuery("temporal", null, '0');

            $respuesta = $this->modelo->buscarArticuloSalida($datos);
        }
        catch (\Exception $ex) {
            $mensaje = utf8_encode($ex->getMessage());
            $this->responseException($mensaje, __METHOD__);
        }
        return $this->respond(['response' => $respuesta]);
    }
}
