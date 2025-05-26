# gentestcase

Sistema automatizado para la generación de casos de prueba unitarios utilizando IA (Bito) sobre proyectos de código fuente.  
Optimizado para eficiencia, extensibilidad e integración sencilla en flujos de trabajo de desarrollo profesional.

---

## Tabla de Contenidos

- [gentestcase](#gentestcase)
  - [Tabla de Contenidos](#tabla-de-contenidos)
  - [Descripción General](#descripción-general)
  - [Arquitectura del Sistema](#arquitectura-del-sistema)
  - [Requisitos](#requisitos)
  - [Estructura del Proyecto](#estructura-del-proyecto)
  - [Funcionamiento](#funcionamiento)
    - [1. Filtros de Archivos y Personalización](#1-filtros-de-archivos-y-personalización)
    - [2. Elección de Framework de Pruebas](#2-elección-de-framework-de-pruebas)
    - [3. Procesamiento en Paralelo y Generación de Casos de Prueba](#3-procesamiento-en-paralelo-y-generación-de-casos-de-prueba)
    - [4. Almacenamiento y Métricas](#4-almacenamiento-y-métricas)
  - [Uso](#uso)
  - [Resultados](#resultados)
  - [Personalización y Extensión](#personalización-y-extensión)
  - [Recomendaciones de Uso](#recomendaciones-de-uso)
  - [Limitaciones y Consideraciones](#limitaciones-y-consideraciones)

---

## Descripción General

**gentestcase** es un sistema automatizado en Bash que te permite:

-   Analizar un proyecto de software completo.
-   Identificar y filtrar inteligentemente archivos relevantes para pruebas unitarias, con filtros altamente configurables.
-   Elegir de manera interactiva el framework de pruebas unitarias a utilizar según el lenguaje detectado en tu proyecto.
-   Generar casos de prueba mediante la IA de Bito, con prompts personalizables y adaptados a cada tecnología.
-   Almacenar los resultados en un formato organizado y listo para integración y visualización profesional.

Ideal para pipelines CI/CD, auditorías de código o acelerar la cobertura de tests en bases de código legacy o activas.

---

## Arquitectura del Sistema

La solución se compone de scripts Bash y prompts de IA, orquestados del siguiente modo:

1. **Filtrado Inteligente y Personalizable de Archivos:**  
   Se descartan archivos irrelevantes (assets, configs, dependencias, binarios, etc.) y se priorizan los archivos fuente realmente testeables mediante filtros agresivos o relajados, según el tipo de proyecto y tus preferencias.

2. **Selección Interactiva de Framework de Pruebas:**  
   El sistema detecta el lenguaje predominante del proyecto y te ofrece elegir entre los frameworks más populares y adecuados para ese lenguaje antes de generar los casos de prueba.

3. **Procesamiento Paralelo y Generación de Tests:**  
   Los archivos identificados se procesan en paralelo, maximizando el uso de recursos del sistema y generando para cada uno sus casos de prueba con la IA.

4. **Carpeta de Salida con Métricas y Reporte Global:**  
   Todos los resultados (test cases, logs, tiempos de ejecución, métricas globales) se guardan en una carpeta independiente y organizada, junto con un reporte global en JSON.

---

## Requisitos

-   **SO:** Debian GNU/Linux (compatible con otros sistemas tipo Unix)
-   **Dependencias:**
    -   `bash` (>= 4.0)
    -   `find`, `grep`, `awk`, `sed`, `stat`, `head`, `jq`
    -   [Bito CLI](https://github.com/gitbito/AI-Automation) (debe estar instalado y autenticado)
    -   Permisos de ejecución sobre los scripts (`chmod +x *.sh`)

---

## Estructura del Proyecto

```
gentestcase/
├── context.txt
├── filter_testable_files.sh
├── process_in_parallel.sh
├── process_single_file.sh
└── prompts/
    ├── gen_test_case_1.pmt
    └── gen_test_case_2.pmt
```

-   **context.txt**: Archivo base para contexto extra (puedes personalizarlo).
-   **filter_testable_files.sh**: Filtra archivos del proyecto para elegir solo los testeables, con configuraciones seleccionables y comentadas para distintos stacks.
-   **process_in_parallel.sh**: Orquestador principal, procesa todo el proyecto en paralelo y genera reporte global.
-   **process_single_file.sh**: Encargado de procesar cada archivo individualmente con la IA y manejar extensiones según el lenguaje.
-   **prompts/**: Carpeta con los prompts personalizables para Bito.

---

## Funcionamiento

### 1. Filtros de Archivos y Personalización

-   Se ejecuta `filter_testable_files.sh` para explorar recursivamente la carpeta del proyecto y descartar archivos irrelevantes mediante listas negras/blancas de extensiones, nombres y directorios.
-   **¡Nuevo!** El script incluye varios bloques de configuración por tipo de proyecto (agresivo, relajado, Python, Java, etc.).  
    Puedes activar el filtro que mejor se adapte a tu stack descomentando el bloque correspondiente.
-   **Presencia de código:** Solo se procesan archivos con código fuente real (se busca la presencia de funciones, clases, módulos, etc.).
-   **Extensiones soportadas:** El sistema soporta de manera flexible archivos `.js`, `.ts`, `.php`, `.py`, `.java`, `.cs`, `.go`, `.rb`, `.swift`, `.scala`, `.kt`, `.rs`, `.dart`, `.c`, `.cpp`, y más.  
    Puedes personalizar fácilmente la variable `exts_testeables` en el filtro para soportar otros lenguajes.

### 2. Elección de Framework de Pruebas

-   **¡Nuevo!**  
    Tras filtrar los archivos, el sistema **detecta automáticamente el lenguaje predominante en el proyecto** y te muestra los frameworks de testing más populares para que elijas con un simple prompt:
    ```
    Lenguaje detectado: JavaScript
    Elige el framework de pruebas a utilizar:
    1) Jest
    2) Mocha
    3) AVA
    Ingresa el número de tu elección [1-3]:
    ```
-   La selección se emplea para adaptar los prompts de la IA y la generación de los archivos de test.

### 3. Procesamiento en Paralelo y Generación de Casos de Prueba

-   `process_in_parallel.sh` recibe la ruta del proyecto y lanza el procesamiento sobre los archivos relevantes usando todos los núcleos disponibles.
-   Cada archivo se envía a `process_single_file.sh`, que:
    -   Determina la extensión correcta para el archivo de test generado (`.test.js`, `.test.ts`, `_test.php`, etc.).
    -   Llama a Bito dos veces por archivo: primero para verificar la relevancia y luego para generar los casos de prueba unitarios.
    -   Maneja reintentos automáticos y logs detallados por archivo.

### 4. Almacenamiento y Métricas

-   Todo el output (casos de prueba y logs en formato JSON) se almacena en una carpeta `<nombre_proyecto>_testcases`.
-   **¡Nuevo!** El reporte global (`reporte_global.json`) ahora agrega automáticamente todos los logs generados, usando ambos formatos de nombre (`*_test.json` y `*.test.json`).
-   El reporte incluye: total de archivos procesados, fecha/hora de ejecución, lista de archivos y tiempos globales.

---

## Uso

1. **Ubícate en la carpeta donde están los scripts:**

    ```bash
    cd /ruta/a/gentestcase
    ```

2. **Dale permisos de ejecución a los scripts (si no lo hiciste antes):**

    ```bash
    chmod +x *.sh
    ```

3. **Ejecuta el proceso sobre tu proyecto:**
    ```bash
    ./process_in_parallel.sh /ruta/a/mi_proyecto
    ```
    > El sistema generará una carpeta `/ruta/a/mi_proyecto_testcases` con los resultados y métricas.

---

## Resultados

En la carpeta `<nombre_proyecto>_testcases` encontrarás para cada archivo procesado:

-   Un archivo de casos de prueba (`.test.js`, `.test.ts`, `_test.php`, etc.) generados según el lenguaje original.
-   Un archivo de log (`.test.json` o `_test.json`) con el resultado del proceso.
-   Un archivo `tiempo_ejecucion.log` con el tiempo total de ejecución.
-   Un archivo `reporte_global.json` con métricas y resumen del procesamiento.

Ejemplo:

```
mi_proyecto_testcases/
├── tiempo_ejecucion.log
├── reporte_global.json
├── Archivo1.test.ts
├── Archivo1.test.json
├── Archivo2.test.js
├── Archivo2.test.json
...
```

---

## Personalización y Extensión

-   **Filtros y extensiones:**  
    Edita `filter_testable_files.sh` para activar el filtro que mejor se adapte a tu stack (agresivo, relajado, Python, Java, etc.) y ajusta las variables para incluir/excluir carpetas, extensiones o patrones.
-   **Frameworks de testing:**  
    Puedes agregar o modificar frameworks sugeridos editando el bloque de arrays en `process_in_parallel.sh`.
-   **Prompts de IA:**  
    Personaliza los archivos en la carpeta `prompts/` para adaptar el tono, cobertura o tipo de tests.
-   **Paralelismo:**  
    El script usa por defecto todos los núcleos disponibles, puedes limitarlo modificando la variable que determina los hilos (`nproc`).

---

## Recomendaciones de Uso

-   **Proyectos Grandes:**  
    Para proyectos con miles de archivos, ejecuta primero el filtro para revisar qué archivos serán procesados.
-   **Debug:**  
    Los logs JSON contienen detalles de cada intento y errores para fácil auditoría.
-   **Ejecución repetida:**  
    El sistema es idempotente: puedes volver a correrlo sin riesgo de sobrescribir archivos de entrada.

---

## Limitaciones y Consideraciones

-   **Dependencia de Bito:**  
    La calidad y velocidad dependen de la respuesta de la IA y tu conexión.
-   **Cobertura de Tests:**  
    Los casos de prueba generados dependen del prompt y el contexto proporcionado.
-   **Filtrado:**  
    Aunque el filtrado es robusto, revisa la configuración si aparecen archivos irrelevantes.
-   **Frameworks sugeridos:**  
    Si tu stack o framework no aparece en la lista, puedes agregarlo fácilmente en el bloque de selección interactiva.

---

¿Dudas, sugerencias o quieres contribuir? ¡Abre un issue o PR!
