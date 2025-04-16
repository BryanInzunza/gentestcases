<?php

namespace Coppel\LegacyTmsEntregasropadistribucion\Controllers;

use Exception;
use Phalcon\DI\DI;
use Coppel\RAC\Controllers\RESTController;
use Coppel\RAC\Exceptions\HTTPException;
use Coppel\LegacyTmsEntregasropadistribucion\Models as Modelos;

header("Strict-Transport-Security: max-age=31536000; includeSubDomains");


class RopaDistribucionController extends RESTController
{
    private $logger;
    private $modelo;

    const EX1 = "No fue posible completar su solicitud, intente de nuevo por favor.";
    const EX2 = "Verificar conexión con la base de datos.";

    public function onConstruct()
    {
        $this->logger = DI::getDefault()->get('logger');
        $this->modelo = new Modelos\RopaDistribucionModel();
    }

    private function respuestaException($mensaje = '', $httpcode= 500, $metodo = __METHOD__)
    {
       

        
        $this->logger->error(
            '[CLIENTE] '.$_SERVER['REMOTE_ADDR'].' '.
            '['.$metodo."] Se lanzó la excepción > $mensaje"
        );
        throw new HTTPException(
            self::EX1,
            $httpcode,
            [
                'dev' => $mensaje,
                'internalCode' => 'SIE1000',
                'more' => self::EX2
            ]
        );
    }
    
    public function confirmarPaqueteRopa($cedis)
    {
        $response = null;

        try {
            $paquete = $this->request->getJsonRawBody();
            $this->logger->info('[' . __METHOD__ . '] Request: ' . json_encode($paquete) . ' Timestamp ' . date("h:i:sa"));
            // Traspaso de paquetes de ropa
            $bodegaDistribuye = $this->modelo->consultarBodegaDistribuyeConfirmacion($cedis, $paquete->numeroguia);

            $response = $this->modelo->confirmarPaqueteRopa($bodegaDistribuye->numBodega, $paquete);
            $this->logger->info('Response: ' . $response);
            
        } catch (\Exception $ex) {
            $this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
        }


        return $this->respond(['response' => $response]);
    }

    public function registrarEnvioClientesRopa($cedis)
    {
        $response = null;

        try {
            if (!defined('K_TCPDF_CALLS_IN_HTML')) {
                define('K_TCPDF_CALLS_IN_HTML', true);
            }
            error_reporting(0);
            $paquete = $this->request->getJsonRawBody();

            // Traspaso de paquetes de ropa
            $bodegaDistribuye = $this->modelo->consultarBodegaDistribuyeRegistro($cedis, $paquete);
            
            $paquete->consecutivoGuia = $bodegaDistribuye->consecutivo;
            $paquete->numbodegadistribuye = $bodegaDistribuye->numBodega;
            $paquete->numbodegagenera = $cedis;
            $paquete->numruta = $bodegaDistribuye->numRuta;
            $paquete->numciudadpertenece = $bodegaDistribuye->numCiudadPertenece;
            $paquete->nomcortobodegadistribuye = $bodegaDistribuye->nomBodegaCorto;

            $response = $this->modelo->registrarEnvioClientesRopa($cedis, $bodegaDistribuye->numBodega, $paquete);

            if ($paquete->numerocasainterior != '') {
                $paquete->numerocasa = $paquete->numerocasa . " int. #" . $paquete->numerocasainterior;
            }
            $paquete->observaciones = trim(substr($paquete->observaciones, 0, 100));
            $paquete->guia = $response->codigobarras;
            $paquete->fechaservidor = $response->fechaservidor;
            $paquete->fechasurtir = $response->fechasurtir;
            $paquete->descripcionruta = $response->descripcionruta;
            $paquete->nombrebodega = $response->nombrebodega;
            $paquete->ciudadcliente = $response->ciudadcliente;
            
            $pdf = $this->generarPDFBase64($paquete);

            $guia = new \stdClass();
            $guia->numeroguia = $response->codigobarras;
            $guia->pdf = $pdf->pdf;

        } catch (\Exception $ex) {
            $this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
        }


        return $this->respond(['response' => $guia]);
    }

    public function reimpresionDeGuias($cedis)
    {
        $response = null;

        try {
            if (!defined('K_TCPDF_CALLS_IN_HTML')) {
                define('K_TCPDF_CALLS_IN_HTML', true);
            }
            error_reporting(0);
            $json = $this->request->getJsonRawBody();

            $json->fechaservidor = date("d/m/Y", strtotime($json->fec_emision));
            $json->descripcionruta = $json->nom_ruta;
            $json->numeropedido = $json->num_pedido;
            $json->factura = $json->num_nota;

            $json->fechasurtir = date("d/m/Y", strtotime($json->fec_surtir));

            if ($json->num_casainterior != '' && $json->num_casainterior != '0') {
                $json->num_casa = $json->num_casa . " int. #" . $json->num_casainterior;
            }
            $json->des_observaciones = trim(substr($json->des_observaciones, 0, 100));
            $json->nombrebodega = $json->des_ciudad;

            // Si se cambia forma de consumir servicio se puede evitar esto
            $json->nombrecliente = $json->nom_cliente;
            $json->nombreapellidopaterno = $json->nom_apellidopaterno;
            $json->nombreapellidomaterno = $json->nom_apellidomaterno;
            $json->nombrepersonarecibe = $json->nom_personarecibe;
            $json->numerotelefono = $json->num_telefono;
            $json->nombrecalle = $json->nom_calle;
            $json->numerocasa = $json->num_casa;
            $json->nombrezona= $json->nom_zona;
            $json->numcodigopostal = $json->num_codigopostal;
            $json->nombreestado = $json->des_estado;
            $json->observaciones = $json->des_observaciones;
            $json->ciudadcliente = $json->nombreciudad;

            $pdf = $this->generarPDFBase64($json);

            $guia = new \stdClass();
            $guia->nombre = $pdf->nombreArchivo;
            $guia->base64Doc = $pdf->pdf;
            
        } catch (\Exception $ex) {
            $this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
        }


        return $this->respond(['response' => $guia]);
    }

    public function generarPDFBase64($datos)
    {
        $response = null;

        try {
            $archivo = "etiquetaenvioclientes" . $datos->guia . ".pdf";
            $logo = __DIR__ . "/../images/coppel_logo.png";
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $style = array(
                'position' => 'C',
                'stretch' => false,
                'fitwidth' => false,
                'cellfitalign' => '',
                'border' => false,
                'hpadding' => '115px',
                'fgcolor' => array(0,0,0),
                'bgcolor' => false, //array(255,255,255),
                'text' => false,
                'font' => 'helvetica',
                'fontsize' => 8,
                'stretchtext' => 4
            );
            if ($datos->numbodegadistribuye == $datos->numbodegagenera) {
                $encabezadoGuia = "<b>Fecha a surtir:</b>  {$datos->fechasurtir}";
            } else {
                $encabezadoGuia = "<b>TRASPASO A {$datos->numbodegadistribuye} {$datos->nomcortobodegadistribuye}</b>";
            }

            $texto = <<<EOD
            <table style="font-size:18px;">
				<tr align="center">
                    <td width="40%"><img src="{$logo}" width="300" height="80"></td>
                    <td style="line-height:20px;" width="60%"><b>Coppel S.A de C.V </b><br />
                    Calle República 2855 pte. Col Recursos Hidráulicos</td>
                </tr>
                <br />
                <tr>
                    <td align="left" width="40%"><b>Fecha de emisión:</b> {$datos->fechaservidor}<br /><b>Ruta: </b>{$datos->descripcionruta}</td>  
                    <td align="rigth" width="60%"><b>Pedido: </b>{$datos->numeropedido}<br /><b>Nota: </b>{$datos->factura}</td>
                </tr>
                <br />
                <tr align="center">
                <td width="100%" style="font-size:30px;"><p>{$encabezadoGuia}</p></td>
                </tr>
            </table>
            <table border="1" style="border-collapse: collapse" bordercolor="#111111" style="font-size:17px;">
                <tr>
                    <td style="line-height:30px;" align="left" width="100%"><b>Origen: </b>{$datos->nombrebodega}</td>
                </tr>
                <tr>
                    <td align="left" rowspan="2" width="80%"><b>Destino</b><br />
                        <b>{$datos->nombrecliente} {$datos->nombreapellidopaterno} {$datos->nombreapellidomaterno}</b><br /><br />
                        <b>Recibe:</b> {$datos->nombrepersonarecibe}  <br /><br />
                        <b>Teléfono:</b> {$datos->numerotelefono} <br /><br />
                        <b>Domicilio de entrega:</b> {$datos->nombrecalle} #{$datos->numerocasa} col. {$datos->nombrezona},
                        CP. {$datos->numcodigopostal}, {$datos->nombreciudad}, {$datos->nombreestado}. <br /><br />
                        <b>Referencias:</b> {$datos->observaciones}
                    </td>
                    <td align="center" style="line-height:85px;" height="100" width="20%"><h2>{$datos->ciudadcliente}</h2></td>
                </tr>
                <tr>
                    <td align="center" style="line-height:50px;" width="20%"><h2>C.P: <br /> {$datos->numcodigopostal}</h2></td>
                </tr>
            </table>
            <br />
EOD;

            $textoNumGuia = <<<EOD
            <table cellpadding="0" border="0" align="center" cellspacing="0">
                <tr style="font-size:25px;">
                    <td align="center">{$datos->guia}</td>
                </tr>
            </table>
            EOD;



            $pdf->writeHTML($texto, true, false, false, false, '');
            $pdf->write1DBarcode($datos->guia, 'C128', '', '', 120, 35, 0.4, $style, 'N');
            $pdf-> writeHTML($textoNumGuia, true, false, false, false, '');
            ob_start();
            $pdf->Output($archivo, 'I'); //F, I, D, S
            $pdfData = ob_get_contents();
            ob_end_clean();
    
            $datosGuia = new \stdClass();
            $datosGuia->nombreArchivo = $archivo;
            $datosGuia->pdf = base64_encode($pdfData);
    
            return $datosGuia;
            
        } catch (\Exception $ex) {
            $this->respuestaException($ex->getMessage(), $ex->getCode(), __METHOD__);
        }


    }
}
