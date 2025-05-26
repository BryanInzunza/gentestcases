#!/bin/bash

# Recibe una carpeta, imprime paths de archivos testables (uno por línea)
if [ $# -ne 1 ]; then
    echo "Uso: $0 <directorio_proyecto>" >&2
    exit 1
fi

project_dir="$1"

# Lista negra de directorios y archivos
ignore_dirs_regex='/(node_modules|.git|.vs|Debug|Release|init|Pack|x64|x86)/'
ignore_files_regex='(^|/)(package.json|package-lock.json|yarn.lock|Dockerfile|Containerfile|docker-compose.yml|.env|.gitignore|.gitattributes|.ignore|modernzr.js)$'
ignore_exts_regex='\.(md|json|txt|csv|yaml|yml|ini|xml|properties|htaccess|sln|suo|sdf)$'

# Lista blanca de extensiones de código fuente testeables (puedes expandirla)
testable_exts_regex='\.(php|js|ts|py|java|c|cpp|cs|go|rb|swift)$'

# Encuentra archivos testeables
find "$project_dir" -type f ! -regex ".*$ignore_dirs_regex.*" ! -regex ".*$ignore_files_regex" ! -regex ".*$ignore_exts_regex" | \
    grep -Ei "$testable_exts_regex" | \
    while read -r file; do
        # Además de la extensión, asegúrate que el archivo tiene código real (al menos 1 función o clase)
        if grep -Eq "(function |def |class |public |private |protected |static )" "$file"; then
            echo "$file"
        fi
    done