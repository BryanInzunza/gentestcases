# Test Case Generation

Este proyecto incluye tres scripts que trabajan en conjunto para generar casos de prueba a partir de archivos fuente, utilizando [bito] para la generación de pruebas y [jq] para el procesamiento de logs en formato JSON.

## Requisitos

-   Tener instalado [bito] https://github.com/gitbito/CLI
-   Tener instalado [jq]
-   Bash (Shell Script).

## Archivos Incluidos

-   **process_in_parallel.sh**: Script principal que recorre un directorio de un proyecto y ejecuta en paralelo el procesamiento de cada archivo (llama a process_single_file.sh).
-   **process_single_file.sh**: Procesa individualmente cada archivo, genera un archivo de prueba con la salida de bito y crea un log en formato JSON.
-   **extract_code.sh**: Extrae bloques de código del archivo de prueba generado y salva cada bloque en un archivo separado.

## Instalación y Configuración

1. Clona el repositorio o coloca los scripts en el directorio deseado.

2. Asegúrate de dar permisos de ejecución a los tres scripts. En una terminal, ejecuta:

    ```bash
    chmod +x process_in_parallel.sh process_single_file.sh extract_code.sh
    ```

3. Verifica que tanto `bito` como `jq` estén instalados. Puedes comprobarlo ejecutando:

    ```bash
    command -v bito
    command -v jq
    ```

    Si alguno no está instalado, consulta la documentación oficial para su instalación.

## Instalación de jq

### En Linux

1. Abre una terminal.
2. Ejecuta el siguiente comando para instalar `jq`:
    ```bash
    sudo apt-get install jq
    ```

## Cómo Ejecutar

1. Prepara tu proyecto o carpeta que contenga los archivos fuente que deseas procesar.

2. Ejecuta el script principal `process_in_parallel.sh` indicando como argumento la carpeta del proyecto. Por ejemplo:

    ```bash
    ./process_in_parallel.sh /ruta/a/tu/proyecto
    ```

    Esto realizará lo siguiente:

    - Verificará que la carpeta exista.
    - Procesará cada archivo (exceptuando aquellos que se encuentran en las listas de exclusión) en paralelo, utilizando `process_single_file.sh`.
    - Guardará los casos de prueba generados en un directorio con el sufijo `_testcases` (por ejemplo, `proyecto_testcases`).
    - Generará un log en JSON para cada archivo procesado en un directorio temporal.

3. Verifica el directorio de salida para revisar que se hayan generado correctamente los casos de prueba y los logs en formato JSON.

## Notas Adicionales

-   Los scripts utilizan plantillas de archivos (ubicados en `prompts/gen_test_case_1.pmt` y `prompts/gen_test_case_2.pmt`); asegúrate de que existan y estén configuradas correctamente.
-   El script `process_single_file.sh` guarda el registro de cada ejecución en un archivo JSON cuyo nombre se forma a partir del nombre del archivo fuente, y también imprime el log en STDOUT para que pueda ser capturado por el script que ejecuta en paralelo.

Con estos pasos, deberías poder ejecutar el proyecto sin inconvenientes. ¡Buena suerte!
