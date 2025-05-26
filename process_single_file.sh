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

inputfile_for_ut_gen="$1"
output_dir="$2"

mkdir -p "$output_dir"

filename_with_path="${inputfile_for_ut_gen##*/}"
extension="${filename_with_path##*.}"
filename="${filename_with_path%.*}"

framework="PHPUnit"

# Contexto dinámico (opcional, igual que antes)
context_file=$(mktemp)
imports=$(grep -Eo "(require|import|include)[^;']*['\"]([^'\"]+)['\"]" "$inputfile_for_ut_gen" | grep -Eo "['\"][^'\"]+['\"]" | tr -d '"' | uniq | head -n 3)
for dep in $imports; do
    dep_path="$(dirname "$inputfile_for_ut_gen")/$dep"
    [ -f "$dep_path" ] && echo -e "\n#### Context from $dep:\n$(head -n 100 "$dep_path")\n" >> "$context_file"
done

prompt=$(<prompts/gen_test_case_1.pmt)
prompt2=$(<prompts/gen_test_case_2.pmt)

prompt_instance="${prompt/\$framework/$framework}"
prompt_instance="${prompt_instance/\$filename/${filename_with_path}}"
prompt_instance="${prompt_instance/\$context/$(cat "$context_file" 2>/dev/null)}"

temp_prompt=$(mktemp --suffix=".pmt")
trap "rm -f $temp_prompt $context_file" EXIT
echo "$prompt_instance" > "$temp_prompt"

output_file="$output_dir/${filename}_test.php"
log_file="$output_dir/${filename}_test.json"

start_time_fmt=$(date +"%d/%m/%Y %H:%M:%S")
start_epoch=$(date +%s)
log_data="{\"file\": \"$inputfile_for_ut_gen\", \"start_time\": \"$start_time_fmt\", \"retries\": [], \"success\": false}"

MAX_RETRIES=3
retries=0
success=false

# Redirige salida de bito a archivo temporal para limpiarla
bito_tmp=$(mktemp)

while [ $retries -lt $MAX_RETRIES ]; do
    # Primer prompt: solo para filtrar y decidir si se debe generar test
    if ! bito --agent gentestcase -p "$temp_prompt" -f "$inputfile_for_ut_gen" -c "$context_file" > "$bito_tmp" 2>&1; then
        log_data=$(echo "$log_data" | jq --arg retry "$retries" --arg error "El comando bito falló. Reintentando..." '.retries += [{"attempt": $retry, "error": $error}]')
        retries=$((retries+1))
        sleep 2
        continue
    fi

    # Limpia mensajes de bito, toma solo el code block o mensaje principal
    main_output=$(awk '/```/ { flag=!flag; next } flag { print }' "$bito_tmp" | sed '/^\s*$/d')
    # Si no hay bloque de código, tal vez fue omitido por IA: busca si hay mensaje
    if [ -z "$main_output" ]; then
        main_output=$(grep -v -e "^Model in use:" "$bito_tmp" | grep -v '^\s*$')
        if echo "$main_output" | grep -qi "omit"; then
            # Mensaje de omisión, escribe log y termina
            log_data=$(echo "$log_data" | jq --arg status "$main_output" '. + {"status": $status}')
            echo "$log_data" > "$log_file"
            echo "$log_data"
            rm "$bito_tmp"
            exit 0
        fi
    fi

    echo "$prompt2" > "$temp_prompt"

    # Segundo prompt: ahora sí, genera el test case completo
    if ! bito --agent gentestcase -p "$temp_prompt" -f "$inputfile_for_ut_gen" -c "$context_file" > "$bito_tmp" 2>&1; then
        log_data=$(echo "$log_data" | jq --arg retry "$retries" --arg error "El comando bito falló. Reintentando..." '.retries += [{"attempt": $retry, "error": $error}]')
        retries=$((retries+1))
        sleep 2
        continue
    fi

    # Limpia y guarda solo el bloque de código
    test_case=$(awk '/```/ { flag=!flag; next } flag { print }' "$bito_tmp" | sed '/^\s*$/d')
    if [ -n "$test_case" ]; then
        echo "$test_case" > "$output_file"
        log_data=$(echo "$log_data" | jq '.success = true')
        success=true
        break
    else
        log_data=$(echo "$log_data" | jq --arg retry "$retries" --arg error "El archivo de prueba no se generó. Reintentando..." '.retries += [{"attempt": $retry, "error": $error}]')
        retries=$((retries+1))
        sleep 2
    fi
done

rm "$bito_tmp"

if [ "$success" = false ]; then
    log_data=$(echo "$log_data" | jq '.error = "No se pudo generar el archivo de prueba después del número máximo de reintentos."')
    echo "$log_data" > "$log_file"
    echo "$log_data"
    exit 1
fi

end_epoch=$(date +%s)
end_time_fmt=$(date +"%d/%m/%Y %H:%M:%S")
execution_time=$(( end_epoch - start_epoch ))
log_data=$(echo "$log_data" | jq --arg end_time "$end_time_fmt" --argjson execution_time "$execution_time" '. + {"end_time": $end_time, "execution_time": $execution_time}')

char_count=$(wc -m < "$inputfile_for_ut_gen")
log_data=$(echo "$log_data" | jq --argjson char_count "$char_count" '. + {"char_count": $char_count}')

extract_output=$(./extract_code.sh "$output_file" 2>&1 || true)
log_data=$(echo "$log_data" | jq --arg extract_output "$extract_output" '. + {"extract_code_output": $extract_output}')

echo "$log_data" > "$log_file"
echo "$log_data"