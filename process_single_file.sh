#!/bin/bash

set -e

if ! command -v bito > /dev/null 2>&1; then
    echo '{"error": "No se encontró bito. Por favor instálalo y vuelve a intentarlo."}'
    exit 1
fi

if [ "$#" -lt 2 ]; then
    echo '{"error": "Uso: ./process_single_file.sh <archivo_a_procesar> <directorio_de_salida>"}'
    exit 1
fi

archivo_entrada="$1"
directorio_salida="$2"

mkdir -p "$directorio_salida"

nombre_archivo_con_ruta="${archivo_entrada##*/}"
extension="${nombre_archivo_con_ruta##*.}"
nombre_archivo="${nombre_archivo_con_ruta%.*}"

framework="PHPUnit"

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

archivo_salida="$directorio_salida/${nombre_archivo}_test.php"
log_salida="$directorio_salida/${nombre_archivo}_test.json"

inicio_formato=$(date +"%d/%m/%Y %H:%M:%S")
inicio_epoch=$(date +%s)
datos_log="{\"archivo\": \"$archivo_entrada\", \"inicio\": \"$inicio_formato\", \"reintentos\": [], \"exito\": false}"

MAX_REINTENTOS=3
reintentos=0
exito=false

# Redirige salida de bito a archivo temporal para limpiarla
bito_tmp=$(mktemp)

while [ $reintentos -lt $MAX_REINTENTOS ]; do
    # Primer prompt: solo para filtrar y decidir si se debe generar test
    if ! bito --agent gentestcase -p "$prompt_temporal" -f "$archivo_entrada" -c "$archivo_contexto" > "$bito_tmp" 2>&1; then
        datos_log=$(echo "$datos_log" | jq --arg reintento "$reintentos" --arg error "El comando bito falló. Reintentando..." '.reintentos += [{"intento": $reintento, "error": $error}]')
        reintentos=$((reintentos+1))
        sleep 2
        continue
    fi

    # Limpia mensajes de bito, toma solo el bloque de código o mensaje principal
    salida_principal=$(awk '/```/ { flag=!flag; next } flag { print }' "$bito_tmp" | sed '/^\s*$/d')
    # Si no hay bloque de código, tal vez fue omitido por IA: busca si hay mensaje
    if [ -z "$salida_principal" ]; then
        salida_principal=$(grep -v -e "^Model in use:" "$bito_tmp" | grep -v '^\s*$')
        if echo "$salida_principal" | grep -qi "omit"; then
            # Mensaje de omisión, escribe log y termina
            datos_log=$(echo "$datos_log" | jq --arg estatus "$salida_principal" '. + {"estatus": $estatus}')
            echo "$datos_log" > "$log_salida"
            echo "$datos_log"
            rm "$bito_tmp"
            exit 0
        fi
    fi

    echo "$prompt2" > "$prompt_temporal"

    # Segundo prompt: ahora sí, genera el caso de prueba completo
    if ! bito --agent gentestcase -p "$prompt_temporal" -f "$archivo_entrada" -c "$archivo_contexto" > "$bito_tmp" 2>&1; then
        datos_log=$(echo "$datos_log" | jq --arg reintento "$reintentos" --arg error "El comando bito falló. Reintentando..." '.reintentos += [{"intento": $reintento, "error": $error}]')
        reintentos=$((reintentos+1))
        sleep 2
        continue
    fi

    # Limpia y guarda solo el bloque de código
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