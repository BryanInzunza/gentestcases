#!/bin/bash

set -e

inicio_segundos=$(date +%s)
inicio_formato=$(date +"%d/%m/%Y %H:%M:%S")

if [ "$#" -lt 1 ]; then
    echo "Uso: $0 <carpeta_del_proyecto>"
    exit 1
fi

carpeta_proyecto="$1"
if [ ! -d "$carpeta_proyecto" ]; then
    echo "Error: $carpeta_proyecto no es un directorio válido."
    exit 1
fi

script_a_ejecutar="./process_single_file.sh"
if [ ! -x "$script_a_ejecutar" ]; then
    echo "Error: $script_a_ejecutar no se encontró o no es ejecutable."
    exit 1
fi

directorio_salida="${carpeta_proyecto}_testcases"
mkdir -p "$directorio_salida"

# Filtrado previo eficiente
archivos_filtrados_tmp=$(mktemp)
./filter_testable_files.sh "$carpeta_proyecto" > "$archivos_filtrados_tmp"
num_archivos=$(wc -l < "$archivos_filtrados_tmp")
if [ "$num_archivos" -eq 0 ]; then
    echo "No se encontraron archivos testeables."
    exit 0
fi

# Paralelización dinámica según CPU
HILOS_PARALELOS=$(nproc)
echo "Procesando $num_archivos archivos en paralelo con $HILOS_PARALELOS hilos..."

trabajos_actuales=0

while IFS= read -r ruta_completa || [ -n "$ruta_completa" ]; do
    (
        # Ejecuta el script hijo normalmente (este se encarga de guardar su propio log JSON)
        bash "$script_a_ejecutar" "$ruta_completa" "$directorio_salida"
    ) &
    trabajos_actuales=$((trabajos_actuales+1))
    if [ "$trabajos_actuales" -ge "$HILOS_PARALELOS" ]; then
        wait -n
        trabajos_actuales=$((trabajos_actuales-1))
    fi
done < "$archivos_filtrados_tmp"
wait

rm "$archivos_filtrados_tmp"

fin_segundos=$(date +%s)
tiempo_ejecucion=$((fin_segundos - inicio_segundos))
echo "Tiempo de ejecución: ${tiempo_ejecucion} segundos" > "$directorio_salida/tiempo_ejecucion.log"

# ----- Característica: Generar reporte global unificado en JSON con métricas adicionales -----
reporte_global="$directorio_salida/reporte_global.json"

# Leer tiempo de ejecución global
if [ -f "$directorio_salida/tiempo_ejecucion.log" ]; then
    tiempo_total=$(cat "$directorio_salida/tiempo_ejecucion.log")
else
    tiempo_total=""
fi

# Leer todos los archivos *_test.json y unirlos en un array usando jq
archivos_json=$(find "$directorio_salida" -type f -name "*_test.json" -exec cat {} \; | jq -s '.')

# Métricas adicionales 
total_archivos=$(echo "$archivos_json" | jq 'length')
nombres_archivos=$(echo "$archivos_json" | jq -r '.[].archivo' | jq -R . | jq -s .)

# Crear el reporte_global.json 
jq -n \
    --arg ruta_proyecto "$carpeta_proyecto" \
    --arg tiempo_ejecucion_global "$tiempo_total" \
    --arg fecha_ejecucion "$inicio_formato" \
    --argjson archivos "$archivos_json" \
    --argjson total_archivos "$total_archivos" \
    --argjson lista_archivos "$nombres_archivos" \
    '{ruta_proyecto: $ruta_proyecto, tiempo_ejecucion_global: $tiempo_ejecucion_global, fecha_ejecucion: $fecha_ejecucion, total_archivos: $total_archivos, lista_archivos: $lista_archivos, archivos: $archivos}' \
    > "$reporte_global"

echo "Procesamiento completado. Casos de prueba guardados en '$directorio_salida'."
echo "Reporte global generado en: $reporte_global"