#!/bin/bash

set -e

if ! command -v bito > /dev/null 2>&1; then
    echo '{"error": "No se encontró bito. Por favor instálalo y vuelve a intentarlo."}'
    exit 1
fi

if [ "$#" -lt 3 ]; then
    echo '{"error": "Uso: ./process_single_file.sh <archivo_a_procesar> <directorio_de_salida> <framework>"}'
    exit 1
fi

archivo_entrada="$1"
directorio_salida="$2"
framework="$3"

mkdir -p "$directorio_salida"

nombre_archivo_con_ruta="${archivo_entrada##*/}"
extension="${nombre_archivo_con_ruta##*.}"
nombre_archivo="${nombre_archivo_con_ruta%.*}"

# Contexto dinámico (opcional, igual que antes)
archivo_contexto=$(mktemp)
importaciones=$(grep -Eo "(require|import|include)[^;']*['\"]([^'\"]+)['\"]" "$archivo_entrada" | grep -Eo "['\"][^'\"]+['\"]" | tr -d '"' | uniq | head -n 3)
for dep in $importaciones; do
    ruta_dep="$(dirname "$archivo_entrada")/$dep"
    [ -f "$ruta_dep" ] && echo -e "\n#### Contexto de $dep:\n$(head -n 100 "$ruta_dep")\n" >> "$archivo_contexto"
done

prompt=$(<prompts/gen_test_case_1.pmt)
prompt2=$(<prompts/gen_test_case_2.pmt)

instancia_prompt="${prompt/\$framework/$framework}"
instancia_prompt="${instancia_prompt/\$filename/${nombre_archivo_con_ruta}}"
instancia_prompt="${instancia_prompt/\$context/$(cat "$archivo_contexto" 2>/dev/null)}"

prompt_temporal=$(mktemp --suffix=".pmt")
trap "rm -f $prompt_temporal $archivo_contexto" EXIT
echo "$instancia_prompt" > "$prompt_temporal"

# Determinar extensión y nombre adecuado para el archivo de test y el log
case "$extension" in
    js|jsx)
        archivo_salida="$directorio_salida/${nombre_archivo}.test.js"
        log_salida="$directorio_salida/${nombre_archivo}.test.json"
        ;;
    ts|tsx)
        archivo_salida="$directorio_salida/${nombre_archivo}.test.ts"
        log_salida="$directorio_salida/${nombre_archivo}.test.json"
        ;;
    php)
        archivo_salida="$directorio_salida/${nombre_archivo}_test.php"
        log_salida="$directorio_salida/${nombre_archivo}_test.json"
        ;;
    py)
        archivo_salida="$directorio_salida/${nombre_archivo}_test.py"
        log_salida="$directorio_salida/${nombre_archivo}_test.json"
        ;;
    java)
        archivo_salida="$directorio_salida/${nombre_archivo}Test.java"
        log_salida="$directorio_salida/${nombre_archivo}Test.json"
        ;;
    cs)
        archivo_salida="$directorio_salida/${nombre_archivo}Test.cs"
        log_salida="$directorio_salida/${nombre_archivo}Test.json"
        ;;
    go)
        archivo_salida="$directorio_salida/${nombre_archivo}_test.go"
        log_salida="$directorio_salida/${nombre_archivo}_test.json"
        ;;
    rb)
        archivo_salida="$directorio_salida/${nombre_archivo}_test.rb"
        log_salida="$directorio_salida/${nombre_archivo}_test.json"
        ;;
    swift)
        archivo_salida="$directorio_salida/${nombre_archivo}Tests.swift"
        log_salida="$directorio_salida/${nombre_archivo}Tests.json"
        ;;
    kt)
        archivo_salida="$directorio_salida/${nombre_archivo}Test.kt"
        log_salida="$directorio_salida/${nombre_archivo}Test.json"
        ;;
    rs)
        archivo_salida="$directorio_salida/${nombre_archivo}_test.rs"
        log_salida="$directorio_salida/${nombre_archivo}_test.json"
        ;;
    dart)
        archivo_salida="$directorio_salida/${nombre_archivo}_test.dart"
        log_salida="$directorio_salida/${nombre_archivo}_test.json"
        ;;
    c|cpp)
        archivo_salida="$directorio_salida/${nombre_archivo}_test.${extension}"
        log_salida="$directorio_salida/${nombre_archivo}_test.json"
        ;;
    *)
        archivo_salida="$directorio_salida/${nombre_archivo}_test.${extension}"
        log_salida="$directorio_salida/${nombre_archivo}_test.json"
        ;;
esac

inicio_formato=$(date +"%d/%m/%Y %H:%M:%S")
inicio_epoch=$(date +%s)
datos_log="{\"archivo\": \"$archivo_entrada\", \"framework_utilizado\": \"$framework\", \"inicio\": \"$inicio_formato\", \"reintentos\": [], \"exito\": false}"

MAX_REINTENTOS=3
reintentos=0
exito=false

bito_tmp=$(mktemp)

while [ $reintentos -lt $MAX_REINTENTOS ]; do
    # Primer prompt: solo para filtrar y decidir si se debe generar test
    if ! bito --agent gentestcase -p "$prompt_temporal" -f "$archivo_entrada" -c "$archivo_contexto" > "$bito_tmp" 2>&1; then
        datos_log=$(echo "$datos_log" | jq --arg reintento "$reintentos" --arg error "El comando bito falló. Reintentando..." '.reintentos += [{"intento": $reintento, "error": $error}]')
        reintentos=$((reintentos+1))
        sleep 2
        continue
    fi

    salida_principal=$(awk '/```/ { flag=!flag; next } flag { print }' "$bito_tmp" | sed '/^\s*$/d')
    if [ -z "$salida_principal" ]; then
        salida_principal=$(grep -v -e "^Model in use:" "$bito_tmp" | grep -v '^\s*$')
        if echo "$salida_principal" | grep -qi "omit"; then
            datos_log=$(echo "$datos_log" | jq --arg estatus "$salida_principal" '. + {"estatus": $estatus}')
            echo "$datos_log" > "$log_salida"
            echo "$datos_log"
            rm "$bito_tmp"
            exit 0
        fi
    fi

    echo "$prompt2" > "$prompt_temporal"

    if ! bito --agent gentestcase -p "$prompt_temporal" -f "$archivo_entrada" -c "$archivo_contexto" > "$bito_tmp" 2>&1; then
        datos_log=$(echo "$datos_log" | jq --arg reintento "$reintentos" --arg error "El comando bito falló. Reintentando..." '.reintentos += [{"intento": $reintento, "error": $error}]')
        reintentos=$((reintentos+1))
        sleep 2
        continue
    fi

    caso_prueba=$(awk '/```/ { flag=!flag; next } flag { print }' "$bito_tmp" | sed '/^\s*$/d')
    if [ -n "$caso_prueba" ]; then
        echo "$caso_prueba" > "$archivo_salida"
        datos_log=$(echo "$datos_log" | jq '.exito = true')
        exito=true
        break
    else
        datos_log=$(echo "$datos_log" | jq --arg reintento "$reintentos" --arg error "El archivo de prueba no se generó. Reintentando..." '.reintentos += [{"intento": $reintento, "error": $error}]')
        reintentos=$((reintentos+1))
        sleep 2
    fi
done

rm "$bito_tmp"

if [ "$exito" = false ]; then
    datos_log=$(echo "$datos_log" | jq '.error = "No se pudo generar el archivo de prueba después del número máximo de reintentos."')
    echo "$datos_log" > "$log_salida"
    echo "$datos_log"
    exit 1
fi

fin_epoch=$(date +%s)
fin_formato=$(date +"%d/%m/%Y %H:%M:%S")
tiempo_ejecucion=$(( fin_epoch - inicio_epoch ))
datos_log=$(echo "$datos_log" | jq --arg fin "$fin_formato" --argjson tiempo "$tiempo_ejecucion" '. + {"fin": $fin, "tiempo_ejecucion": $tiempo}')

conteo_caracteres=$(wc -m < "$archivo_entrada")
datos_log=$(echo "$datos_log" | jq --argjson caracteres "$conteo_caracteres" '. + {"conteo_caracteres": $caracteres}')

echo "$datos_log" > "$log_salida"
echo "$datos_log"