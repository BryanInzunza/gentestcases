#!/bin/bash

# Recibe una carpeta, imprime paths de archivos testeables (uno por línea)
if [ $# -ne 1 ]; then
    echo "Uso: $0 <directorio_proyecto>" >&2
    exit 1
fi

directorio_proyecto="$1"

# --- CONFIGURACIÓN ---

# Directorios a ignorar (regex)
dirs_ignorar="node_modules|.git|.vs|Debug|Release|init|Pack|x64|x86|vendor|dist|build|out|logs|coverage|.idea|.vscode|cache"

# Archivos a ignorar por nombre exacto (case-insensitive)
archivos_ignorar="package.json|package-lock.json|yarn.lock|Dockerfile|Containerfile|docker-compose.yml|.env|.gitignore|.gitattributes|.ignore|modernzr.js|composer.lock|composer.json|Makefile|README|readme"

# Extensiones a ignorar (case-insensitive, con punto)
exts_ignorar="md|json|txt|csv|yaml|yml|ini|xml|properties|htaccess|sln|suo|sdf|map|lock|log|conf|cfg|bak|tmp|db|db3|sqlite|jpg|jpeg|png|gif|ico|svg|pdf|mp3|mp4|zip|tar|gz|rar|7z|exe|dll|bin|obj"

# Extensiones válidas y prioritarias para código fuente (agrega aquí tus lenguajes)
exts_testeables="php|js|ts|py|java|c|cpp|cs|go|rb|swift|scala|kt|m|rs|dart"

# Patrones adicionales para excluir (archivos generados)
patrones_ignorar="\.min\.js$|\.bundle\.js$|\.d\.ts$"

# Patrones para excluir archivos de test ya existentes
patrones_test="(_test|Test)\.(php|js|ts|py|java|c|cpp|cs|go|rb|swift|scala|kt|m|rs|dart)$"

# Tamaño mínimo de archivo para considerar testeable (en bytes, ej: 50B)
tam_min=50

# Palabras clave para detectar código (agrega según tus necesidades)
palabras_codigo="function|def |class |public |private |protected |static |module |interface |trait |enum |extends |implements |namespace "

# --- BÚSQUEDA Y FILTRO ---

find "$directorio_proyecto" -type f \
    ! \( $(echo "$dirs_ignorar" | sed 's/|/ -o -path */g' | sed 's/^/-path */;s/$/*/') \) \
    | grep -Ev "/($(echo "$dirs_ignorar"))/" \
    | grep -E "\.($(echo "$exts_testeables"))$" \
    | grep -Ev "\.($(echo "$exts_ignorar"))$" \
    | grep -Ev "/($(echo "$archivos_ignorar"))$" \
    | grep -Ev "$patrones_ignorar" \
    | grep -Ev "$patrones_test" \
    | while read -r archivo; do
        # Verifica tamaño mínimo (descarta archivos vacíos o triviales)
        if [ "$(stat -c %s "$archivo")" -lt "$tam_min" ]; then
            continue
        fi
        # Busca palabras clave de código
        if grep -Eq "$palabras_codigo" "$archivo"; then
            echo "$archivo"
        fi
    done