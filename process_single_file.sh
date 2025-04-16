#!/bin/bash

# Verificar que bito esté instalado
if ! command -v bito > /dev/null 2>&1; then
    echo '{"error": "No se encontró bito. Por favor instálalo y vuelve a intentarlo."}'
    exit 1
fi

# Verificar que se proporcionen los argumentos necesarios
if [ "$#" -lt 2 ]; then
    echo '{"error": "Uso: ./script.sh <archivo_a_procesar> <directorio_de_salida>"}'
    exit 1
fi

# Archivo a procesar y directorio de salida
inputfile_for_ut_gen="$1"
output_dir="$2"

# Crear el directorio de salida si no existe
mkdir -p "$output_dir"

# Lista de archivos y directorios a ignorar
ignore_list=(
    ".git"
    ".gitignore"
    ".gitattributes"
    "package.json"
    "yarn.lock"
    "package-lock.json"
    ".env"
    "Dockerfile"
    "Containerfile"
    "docker-compose.yml"
    "node_modules"
    "Debug"
    "Release"
    ".vs"
    "init"
    ".ignore"
    "Pack"
    "x64"
    "x86"
)

# Lista de extensiones de archivo a ignorar
ignore_extensions=(
    ".md"
    ".json"
    ".txt"
    ".csv"
    ".yaml"
    ".yml"
    ".ini"
    ".xml"
    ".properties"
    ".htaccess"
    ".sln"
    ".suo"
    ".sdf"
    "modernzr.js"
)

# Verificar si el archivo o directorio está en la lista de ignorados
for ignore_item in "${ignore_list[@]}"; do
    if [[ "$inputfile_for_ut_gen" == *"$ignore_item"* ]]; then
        echo "{\"status\": \"omitido\", \"reason\": \"Archivo o directorio ignorado: $inputfile_for_ut_gen\"}"
        exit 0
    fi
done

# Verificar si el archivo tiene una extensión ignorada
for ext in "${ignore_extensions[@]}"; do
    if [[ "$inputfile_for_ut_gen" == *"$ext" ]]; then
        echo "{\"status\": \"omitido\", \"reason\": \"Archivo con extensión ignorada ($ext): $inputfile_for_ut_gen\"}"
        exit 0
    fi
done

# Verificar que el archivo exista
if [ ! -f "$inputfile_for_ut_gen" ]; then
    echo "{\"error\": \"El archivo no existe: $inputfile_for_ut_gen\"}"
    exit 1
fi

# Framework a utilizar para las pruebas
framework="PHPUnit"

# Leer las plantillas de pregunta en variables
prompt=$(<prompts/gen_test_case_1.pmt)
prompt2=$(<prompts/gen_test_case_2.pmt)

# Extraer el nombre del archivo sin extensión y su extensión original
filename=$(basename -- "$inputfile_for_ut_gen")
extension="${filename##*.}"
filename="${filename%.*}"

# Reemplazar los placeholders en la plantilla con el framework y nombre del archivo
prompt_instance=${prompt/\$framework/${framework}}
prompt_instance=${prompt_instance/\$filename/${filename}.${extension}}

# Crear un archivo temporal para la plantilla modificada
temp_prompt=$(mktemp --suffix=".pmt")
trap "rm -f $temp_prompt" EXIT
echo "$prompt_instance" > "$temp_prompt"

# Preparar los archivos de salida: el archivo de prueba y el log JSON
output_file="$output_dir/${filename}_test.php"
log_file="$output_dir/${filename}_test.json"

# Iniciar registro
start_time_fmt=$(date +"%d/%m/%Y %H:%M:%S")
start_epoch=$(date +%s)
log_data="{\"file\": \"$inputfile_for_ut_gen\", \"start_time\": \"$start_time_fmt\", \"retries\": [], \"success\": false}"

# Número máximo de reintentos para el procesamiento
MAX_RETRIES=3
retries=0
success=false

while [ $retries -lt $MAX_RETRIES ]; do
    # Ejecutar el comando bito con la primera plantilla
    if ! bito --agent gentestcase -p "$temp_prompt" -f "$inputfile_for_ut_gen" -c "context.txt" > /dev/null; then
        log_data=$(echo "$log_data" | jq --arg retry "$retries" --arg error "El comando bito falló. Reintentando..." '.retries += [{"attempt": $retry, "error": $error}]')
        retries=$((retries+1))
        sleep 2
        continue
    fi

    echo "$prompt2" > "$temp_prompt"

    # Ejecutar el comando bito con la segunda plantilla y guardar la salida
    if ! bito --agent gentestcase -p "$temp_prompt" -f "$inputfile_for_ut_gen" -c "context.txt" > "$output_file"; then
        log_data=$(echo "$log_data" | jq --arg retry "$retries" --arg error "El comando bito falló. Reintentando..." '.retries += [{"attempt": $retry, "error": $error}]')
        retries=$((retries+1))
        sleep 2
        continue
    fi

    # Verificar que el archivo de salida exista y no esté vacío
    if [ -s "$output_file" ]; then
        log_data=$(echo "$log_data" | jq '.success = true')
        success=true
        break
    else
        log_data=$(echo "$log_data" | jq --arg retry "$retries" --arg error "El archivo de prueba no se generó. Reintentando..." '.retries += [{"attempt": $retry, "error": $error}]')
        retries=$((retries+1))
        sleep 2
    fi
done

if [ "$success" = false ]; then
    log_data=$(echo "$log_data" | jq '.error = "No se pudo generar el archivo de prueba después del número máximo de reintentos."')
    echo "$log_data" > "$log_file"
    echo "$log_data"
    exit 1
fi

# Calcular tiempo de ejecución usando epochs
end_epoch=$(date +%s)
end_time_fmt=$(date +"%d/%m/%Y %H:%M:%S")
execution_time=$(( end_epoch - start_epoch ))
log_data=$(echo "$log_data" | jq --arg end_time "$end_time_fmt" --argjson execution_time "$execution_time" '. + {"end_time": $end_time, "execution_time": $execution_time}')

# Contar caracteres en el archivo original
char_count=$(wc -m < "$inputfile_for_ut_gen")
log_data=$(echo "$log_data" | jq --argjson char_count "$char_count" '. + {"char_count": $char_count}')

# Ejecutar extract_code.sh sobre el archivo de salida
extract_output=$(./extract_code.sh "$output_file" 2>&1)
log_data=$(echo "$log_data" | jq --arg extract_output "$extract_output" '. + {"extract_code_output": $extract_output}')

# Guardar el registro (log) en el archivo JSON y mostrarlo en STDOUT
echo "$log_data" > "$log_file"
echo "$log_data"