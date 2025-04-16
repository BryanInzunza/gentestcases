#!/bin/bash

if [ "$#" -ne 1 ]; then
    echo "Uso: $0 <archivo_fuente>"
    exit 1
fi

if [ ! -f "$1" ]; then
    echo "Error: No se encontró el archivo $1."
    exit 1
fi

# Extraer el nombre del archivo sin la extensión
filename=$(basename -- "$1")
filename_no_ext="${filename%.*}"

# Definir el directorio de salida basándose en el directorio del archivo fuente
output_dir=$(dirname "$1")
mkdir -p "$output_dir"

in_code_block=false
block_content=""
block_lang=""

python_counter=0
javascript_counter=0
bash_counter=0
default_counter=0
typescript_counter=0
csharp_counter=0
php_counter=0
java_counter=0
sql_counter=0
go_counter=0

while IFS= read -r line; do
    if [[ $line == \`\`\`* ]]; then
        if $in_code_block; then
            # Fin de un bloque de código
            in_code_block=false

            # Determinar la extensión del archivo según el lenguaje e incrementar el contador
            case "$block_lang" in
                python)
                    ext=".py"
                    ((python_counter++))
                    counter=$python_counter
                    ;;
                javascript|js)
                    ext=".js"
                    ((javascript_counter++))
                    counter=$javascript_counter
                    ;;
                bash)
                    ext=".sh"
                    ((bash_counter++))
                    counter=$bash_counter
                    ;;
                typescript)
                    ext=".ts"
                    ((typescript_counter++))
                    counter=$typescript_counter
                    ;;
                csharp)
                    ext=".cs"
                    ((csharp_counter++))
                    counter=$csharp_counter
                    ;;
                php)
                    ext=".php"
                    ((php_counter++))
                    counter=$php_counter
                    ;;
                java)
                    ext=".java"
                    ((java_counter++))
                    counter=$java_counter
                    ;;
                sql)
                    ext=".sql"
                    ((sql_counter++))
                    counter=$sql_counter
                    ;;
                go)
                    ext=".go"
                    ((go_counter++))
                    counter=$go_counter
                    ;;
                *)
                    ext=".txt"
                    ((default_counter++))
                    counter=$default_counter
                    ;;
            esac

            # Construir el nombre del archivo usando el nombre original, el lenguaje y el contador
            output_file="$output_dir/test_case_${filename_no_ext}_${counter}${ext}"

            # Guardar el contenido en el archivo
            echo "$block_content" > "$output_file"
            block_content=""
            block_lang=""
            echo "Código guardado en: $output_file"
        else
            # Inicio de un bloque de código
            in_code_block=true
            block_lang="${line#\`\`\`}"
        fi
    elif $in_code_block; then
        if [ -z "$block_content" ]; then
            block_content="$line"
        else
            block_content="$block_content"$'\n'"$line"
        fi
    fi
done < "$1"
