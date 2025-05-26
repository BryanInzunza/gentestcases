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

# Filtrado previo eficiente y detección de lenguaje
archivos_filtrados_tmp=$(mktemp)
lenguaje_detectado="Desconocido"
./filter_testable_files.sh "$carpeta_proyecto" > "$archivos_filtrados_tmp"

# Extraer lenguaje detectado del output
if grep -q "#LENGUAJE_DETECTADO=" "$archivos_filtrados_tmp"; then
    lenguaje_detectado=$(grep "#LENGUAJE_DETECTADO=" "$archivos_filtrados_tmp" | tail -1 | cut -d'=' -f2)
    # Quitar línea del output para dejar sólo paths
    grep -v "#LENGUAJE_DETECTADO=" "$archivos_filtrados_tmp" > "${archivos_filtrados_tmp}_clean"
    mv "${archivos_filtrados_tmp}_clean" "$archivos_filtrados_tmp"
fi

num_archivos=$(wc -l < "$archivos_filtrados_tmp")
if [ "$num_archivos" -eq 0 ]; then
    echo "No se encontraron archivos testeables."
    exit 0
fi

# Selección de frameworks top
declare -A frameworks_top1
declare -A frameworks_top2
declare -A frameworks_top3

frameworks_top1["PHP"]="PHPUnit"
frameworks_top2["PHP"]="Pest"
frameworks_top3["PHP"]="Codeception"

frameworks_top1["Python"]="pytest"
frameworks_top2["Python"]="unittest"
frameworks_top3["Python"]="nose2"

frameworks_top1["JavaScript"]="Jest"
frameworks_top2["JavaScript"]="Mocha"
frameworks_top3["JavaScript"]="AVA"

frameworks_top1["Java"]="JUnit"
frameworks_top2["Java"]="TestNG"
frameworks_top3["Java"]="Spock"

frameworks_top1["C#"]="NUnit"
frameworks_top2["C#"]="xUnit"
frameworks_top3["C#"]="MSTest"

frameworks_top1["Go"]="testing"
frameworks_top2["Go"]="Ginkgo"
frameworks_top3["Go"]="Testify"

frameworks_top1["Ruby"]="RSpec"
frameworks_top2["Ruby"]="Minitest"
frameworks_top3["Ruby"]="Test::Unit"

frameworks_top1["Swift"]="XCTest"
frameworks_top2["Swift"]="Quick"
frameworks_top3["Swift"]="Specta"

frameworks_top1["Scala"]="ScalaTest"
frameworks_top2["Scala"]="Specs2"
frameworks_top3["Scala"]="MUnit"

frameworks_top1["Kotlin"]="JUnit"
frameworks_top2["Kotlin"]="Spek"
frameworks_top3["Kotlin"]="KotlinTest"

frameworks_top1["Dart"]="test"
frameworks_top2["Dart"]="flutter_test"
frameworks_top3["Dart"]="mockito"

frameworks_top1["Rust"]="cargo test"
frameworks_top2["Rust"]="rstest"
frameworks_top3["Rust"]="speculate"

frameworks_top1["C/C++"]="GoogleTest"
frameworks_top2["C/C++"]="Boost.Test"
frameworks_top3["C/C++"]="CppUnit"

framework_default="Desconocido"

fw1="${frameworks_top1[$lenguaje_detectado]:-$framework_default}"
fw2="${frameworks_top2[$lenguaje_detectado]:-$framework_default}"
fw3="${frameworks_top3[$lenguaje_detectado]:-$framework_default}"

echo "Lenguaje detectado: $lenguaje_detectado"
echo "Elige el framework de pruebas a utilizar:"
echo "1) $fw1"
echo "2) $fw2"
echo "3) $fw3"
read -rp "Ingresa el número de tu elección [1-3]: " fw_choice

case "$fw_choice" in
    1) framework_utilizado="$fw1";;
    2) framework_utilizado="$fw2";;
    3) framework_utilizado="$fw3";;
    *) framework_utilizado="$fw1"; echo "Selección inválida, usando $fw1 por defecto.";;
esac

echo "Usando framework: $framework_utilizado"

# Paralelización dinámica según CPU
HILOS_PARALELOS=$(nproc)
echo "Procesando $num_archivos archivos en paralelo con $HILOS_PARALELOS hilos..."

trabajos_actuales=0

while IFS= read -r ruta_completa || [ -n "$ruta_completa" ]; do
    (
        # Pasa el framework como argumento extra
        bash "$script_a_ejecutar" "$ruta_completa" "$directorio_salida" "$framework_utilizado"
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

# ----- Característica: Generar reporte global unificado en JSON (en español) con métricas adicionales -----
reporte_global="$directorio_salida/reporte_global.json"

if [ -f "$directorio_salida/tiempo_ejecucion.log" ]; then
    tiempo_total=$(cat "$directorio_salida/tiempo_ejecucion.log")
else
    tiempo_total=""
fi

# Cambiado para incluir ambos formatos de archivos de log
archivos_json=$(find "$directorio_salida" -type f \( -name "*_test.json" -o -name "*.test.json" \) -exec cat {} + | jq -s '.')

total_archivos=$(echo "$archivos_json" | jq 'length')
nombres_archivos=$(echo "$archivos_json" | jq -r '.[].archivo' | jq -R . | jq -s .)

jq -n \
    --arg ruta_proyecto "$carpeta_proyecto" \
    --arg tiempo_ejecucion_global "$tiempo_total" \
    --arg fecha_ejecucion "$inicio_formato" \
    --argjson archivos "$archivos_json" \
    --argjson total_archivos "$total_archivos" \
    --argjson lista_archivos "$nombres_archivos" \
    --arg framework "$framework_utilizado" \
    --arg lenguaje "$lenguaje_detectado" \
    '{ruta_proyecto: $ruta_proyecto, tiempo_ejecucion_global: $tiempo_ejecucion_global, fecha_ejecucion: $fecha_ejecucion, lenguaje: $lenguaje, framework_utilizado: $framework, total_archivos: $total_archivos, lista_archivos: $lista_archivos, archivos: $archivos}' \
    > "$reporte_global"

echo "Procesamiento completado. Casos de prueba guardados en '$directorio_salida'."
echo "Reporte global generado en: $reporte_global"