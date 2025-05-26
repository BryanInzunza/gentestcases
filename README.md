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
    - [1. Filtrado de Archivos Testeables](#1-filtrado-de-archivos-testeables)
    - [2. Procesamiento en Paralelo](#2-procesamiento-en-paralelo)
    - [3. Generación de Casos de Prueba](#3-generación-de-casos-de-prueba)
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
-   Identificar y filtrar inteligentemente archivos relevantes para pruebas unitarias.
-   Generar casos de prueba mediante la IA de Bito, con prompts personalizables.
-   Almacenar los resultados en un formato organizado, preparado para integración y visualización.

Ideal para pipelines CI/CD, auditorías de código o acelerar la cobertura de tests en bases de código legacy o activas.

---

## Arquitectura del Sistema

La solución se compone de scripts Bash y prompts de IA, orquestados del siguiente modo:

1. **Filtrado Inteligente de Archivos:**  
   Se descartan archivos irrelevantes (assets, configs, dependencias, binarios, etc.) y se priorizan los archivos fuente realmente testeables.

2. **Procesamiento Paralelo:**  
   Los archivos identificados se procesan en paralelo, maximizando el uso de recursos del sistema.

3. **Generación y Almacenamiento de Tests:**  
   Para cada archivo, se invoca la IA con prompts robustos para obtener casos de prueba, almacenando logs en formato JSON.

4. **Carpeta de Salida con Métricas:**  
   Todos los resultados (test cases, logs, tiempos de ejecución, métricas globales) se guardan en una carpeta independiente y organizada.

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
-   **filter_testable_files.sh**: Filtra archivos del proyecto para elegir solo los testeables.
-   **process_in_parallel.sh**: Orquestador principal, procesa todo el proyecto en paralelo y genera reporte global.
-   **process_single_file.sh**: Encargado de procesar cada archivo individualmente con la IA.
-   **prompts/**: Carpeta con los prompts personalizables para Bito.

---

## Funcionamiento

### 1. Filtrado de Archivos Testeables

-   Se ejecuta `filter_testable_files.sh` para explorar recursivamente la carpeta del proyecto, descartando archivos irrelevantes mediante listas negras/blancas de extensiones, nombres y directorios.
-   Solo se procesan archivos con código fuente real (se busca la presencia de funciones, clases, etc.).

### 2. Procesamiento en Paralelo

-   `process_in_parallel.sh` recibe la ruta del proyecto y lanza el procesamiento sobre los archivos relevantes usando todos los núcleos disponibles.
-   Cada archivo se envía a `process_single_file.sh` para su procesamiento individual.

### 3. Generación de Casos de Prueba

-   `process_single_file.sh` llama a Bito dos veces por archivo: primero para verificar la relevancia y luego para generar los casos de prueba unitarios.
-   Maneja reintentos automáticos y logs detallados por archivo.

### 4. Almacenamiento y Métricas

-   Todo el output (casos de prueba y logs en formato JSON) se almacena en una carpeta `<nombre_proyecto>_testcases`.
-   Se genera un archivo `reporte_global.json` con métricas agregadas: total de archivos procesados, fecha/hora de ejecución, lista de archivos y tiempos globales.

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

-   Un archivo `_test.php` (o del lenguaje correspondiente) con los casos de prueba generados.
-   Un archivo `_test.json` con el log detallado del proceso.
-   Un archivo `tiempo_ejecucion.log` con el tiempo total de ejecución.
-   Un archivo `reporte_global.json` con métricas y resumen del procesamiento.

Ejemplo:

```
mi_proyecto_testcases/
├── tiempo_ejecucion.log
├── reporte_global.json
├── Archivo1_test.php
├── Archivo1_test.json
├── Archivo2_test.php
├── Archivo2_test.json
...
```

---

## Personalización y Extensión

-   **Lenguajes:**  
    Puedes agregar extensiones soportadas en `filter_testable_files.sh` y personalizar los prompts según el framework o lenguaje.
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

---

¿Dudas, sugerencias o quieres contribuir? ¡Abre un issue o PR!
