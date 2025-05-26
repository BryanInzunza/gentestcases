#!/bin/bash

if [ "$#" -ne 1 ]; then
    echo "Uso: $0 <archivo_fuente>"
    exit 1
fi

if [ ! -f "$1" ]; then
    echo "Error: No se encontró el archivo $1."
    exit 1
fi

filename=$(basename -- "$1")
filename_no_ext="${filename%.*}"
output_dir=$(dirname "$1")
mkdir -p "$output_dir"

in_code_block=false
block_content=""
block_lang=""

declare -A counters

while IFS= read -r line; do
    if [[ $line == \`\`\`* ]]; then
        if $in_code_block; then
            # Fin de bloque
            in_code_block=false
            ext=".txt"
            case "$block_lang" in
                python) ext=".py";;
                javascript|js) ext=".js";;
                bash) ext=".sh";;
                typescript) ext=".ts";;
                csharp) ext=".cs";;
                php) ext=".php";;
                java) ext=".java";;
                sql) ext=".sql";;
                go) ext=".go";;
            esac
            counters["$ext"]=$((counters["$ext"]+1))
            output_file="$output_dir/test_case_${filename_no_ext}_${counters[$ext]}$ext"
            echo "$block_content" > "$output_file"
            echo "Código guardado en: $output_file"
            block_content=""
            block_lang=""
        else
            in_code_block=true
            block_lang="${line#\`\`\`}"
        fi
    elif $in_code_block; then
        block_content="$block_content"$'\n'"$line"
    fi
done < "$1"