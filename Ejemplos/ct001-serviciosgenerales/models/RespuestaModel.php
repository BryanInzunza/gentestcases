<?php

namespace Coppel\ApiBtGenerales\Models;

use Phalcon\Mvc\Model as Modelo;


class RespuestaModel extends Modelo
{
    private $respuesta;

    private $_status ="ESTATUS";
    private $_mensaje ="MENSAJE";
    private $_data = "DATA";


    public function iniciar($arg=null) {  
        $this->respuesta = new \stdClass();
        if ($arg == null) {
            $this->respuesta->{$this->_status} = 0;
            $this->respuesta->{$this->_mensaje} = "No existe información.";
            $this->respuesta->{$this->_data} = array();
        } else {
            $this->respuesta->{$this->_status} = $arg->{$this->_status};
            $this->respuesta->{$this->_mensaje} = $arg->{$this->_mensaje};
            $this->respuesta->{$this->_data} =  $arg->{$this->_data};
        }
    }
    public function setEstatus($STATUS=0) {
        $this->respuesta->{$this->_status} = intval($STATUS);
    }
    public function setMensaje($MENSAJE='') {
        $this->respuesta->{$this->_mensaje} = $MENSAJE;
    }
    public function setData($DATA=array()) {
        $this->respuesta->{$this->_data} = $DATA;
    }
    public function addData($DATA=array()) {
        $this->respuesta->{$this->_data}[] = $DATA;
    }
   
    public function getEstatus() {
        return $this->respuesta->{$this->_status};
    }
    public function getMensaje() {
        return $this->respuesta->{$this->_mensaje};
    }
    public function getData() {
        return $this->respuesta->{$this->_data};
    }

    public function getResponse(){
        return $this->respuesta;
    } 
}
