#!/bin/bash

# Recibe una carpeta, imprime paths de archivos testables (uno por línea)
if [ $# -ne 1 ]; then
    echo "Uso: $0 <directorio_proyecto>" >&2
    exit 1
fi

project_dir="$1"

# --- CONFIGURACIÓN ---

# Directorios a ignorar (regex)
ignore_dirs="node_modules|.git|.vs|Debug|Release|init|Pack|x64|x86|vendor|dist|build|out|logs|coverage|.idea|.vscode|cache"

# Archivos a ignorar por nombre exacto (case-insensitive)
ignore_files="package.json|package-lock.json|yarn.lock|Dockerfile|Containerfile|docker-compose.yml|.env|.gitignore|.gitattributes|.ignore|modernzr.js|composer.lock|composer.json|Makefile|README|readme"

# Extensiones a ignorar (case-insensitive, con punto)
ignore_exts="md|json|txt|csv|yaml|yml|ini|xml|properties|htaccess|sln|suo|sdf|map|lock|log|conf|cfg|bak|tmp|db|db3|sqlite|jpg|jpeg|png|gif|ico|svg|pdf|mp3|mp4|zip|tar|gz|rar|7z|exe|dll|bin|obj"

# Extensiones válidas y prioritarias para código fuente (agrega aquí tus lenguajes)
testable_exts="php|js|ts|py|java|c|cpp|cs|go|rb|swift|scala|kt|m|rs|dart"

# Patrones adicionales para excluir (archivos generados)
ignore_patterns="\.min\.js$|\.bundle\.js$|\.d\.ts$"

# Patrones para excluir archivos de test ya existentes
test_file_patterns="(_test|Test)\.(php|js|ts|py|java|c|cpp|cs|go|rb|swift|scala|kt|m|rs|dart)$"

# Tamaño mínimo de archivo para considerar testable (en bytes, ej: 50B)
min_size=50

# Palabras clave para detectar código (agrega según tus necesidades)
code_keywords="function|def |class |public |private |protected |static |module |interface |trait |enum |extends |implements |namespace "

# --- BÚSQUEDA Y FILTRO ---

find "$project_dir" -type f \
    ! \( $(echo "$ignore_dirs" | sed 's/|/ -o -path */g' | sed 's/^/-path */;s/$/*/') \) \
    | grep -Ev "/($(echo "$ignore_dirs"))/" \
    | grep -E "\.($(echo "$testable_exts"))$" \
    | grep -Ev "\.($(echo "$ignore_exts"))$" \
    | grep -Ev "/($(echo "$ignore_files"))$" \
    | grep -Ev "$ignore_patterns" \
    | grep -Ev "$test_file_patterns" \
    | while read -r file; do
        # Verifica tamaño mínimo (descarta archivos vacíos o triviales)
        if [ "$(stat -c %s "$file")" -lt "$min_size" ]; then
            continue
        fi
        # Busca palabras clave de código
        if grep -Eq "$code_keywords" "$file"; then
            echo "$file"
        fi
    done