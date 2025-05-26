#!/bin/bash

# ==========================
# FILTROS SUGERIDOS POR TIPO DE PROYECTO (Descomenta el bloque que necesites)
# ==========================

# --------------------------
# Filtro AGRESIVO (por defecto, recomendado para máxima precisión)
# --------------------------
# Este filtro solo selecciona archivos fuente que realmente contienen código relevante para testear.
# Usa palabras clave de código y excluye archivos de configuración, tests existentes, y recursos comunes.
dirs_ignorar="node_modules|.git|.vs|Debug|Release|init|Pack|x64|x86|vendor|dist|build|out|logs|coverage|.idea|.vscode|cache"
archivos_ignorar="package.json|package-lock.json|yarn.lock|Dockerfile|Containerfile|docker-compose.yml|.env|.gitignore|.gitattributes|.ignore|modernzr.js|composer.lock|composer.json|Makefile|README|readme"
exts_ignorar="md|json|txt|csv|yaml|yml|ini|xml|properties|htaccess|sln|suo|sdf|map|lock|log|conf|cfg|bak|tmp|db|db3|sqlite|jpg|jpeg|png|gif|ico|svg|pdf|mp3|mp4|zip|tar|gz|rar|7z|exe|dll|bin|obj"
exts_testeables="php|js|ts|py|java|c|cpp|cs|go|rb|swift|scala|kt|m|rs|dart"
patrones_ignorar="\.min\.js$|\.bundle\.js$|\.d\.ts$"
patrones_test="(_test|Test)\.(php|js|ts|py|java|c|cpp|cs|go|rb|swift|scala|kt|m|rs|dart)$"
tam_min=50
palabras_codigo="function|def |class |public |private |protected |static |module |interface |trait |enum |extends |implements |namespace "

# --------------------------
# Filtro RELAJADO para proyectos JS/TS modernos (React, Node, etc.)
# --------------------------
#dirs_ignorar="node_modules|.git|dist|build|coverage|out|logs|public/img|db"
#archivos_ignorar="package.json|package-lock.json|tsconfig.json|tsconfig.app.json|tsconfig.node.json|vite.config.ts|eslint.config.js|README.md|index.html"
#exts_ignorar="md|json|txt|csv|yaml|yml|ini|xml|properties|htaccess|map|lock|log|conf|cfg|bak|tmp|jpg|jpeg|png|gif|ico|svg|pdf|mp3|mp4|zip|tar|gz|rar|7z|exe|dll|bin|obj|sql|css"
#exts_testeables="js|jsx|ts|tsx"
#patrones_ignorar="\.min\.js$|\.bundle\.js$|\.d\.ts$|_test\.(js|ts|jsx|tsx)$|\.spec\.(js|ts|jsx|tsx)$"
#patrones_test=""
#tam_min=50
#palabras_codigo="function|class|export|const|let|=>|import|require|interface|extends"

# --------------------------
# Filtro para proyectos Python (Django, Flask)
# --------------------------
#dirs_ignorar="venv|__pycache__|.git|build|dist|tests|.mypy_cache|.pytest_cache"
#archivos_ignorar="requirements.txt|Pipfile|Pipfile.lock|README.md"
#exts_ignorar="md|json|txt|csv|yaml|yml|ini|xml|properties|log|conf|cfg|bak|tmp|jpg|jpeg|png|gif|ico|svg|pdf|mp3|mp4|zip|tar|gz|rar|7z|exe|dll|bin|obj"
#exts_testeables="py"
#patrones_ignorar=""
#patrones_test="(_test|Test)\.py$"
#tam_min=50
#palabras_codigo="def |class |import |from |lambda "

# --------------------------
# Filtro para proyectos Java
# --------------------------
#dirs_ignorar="target|build|out|.git|.idea|logs"
#archivos_ignorar="pom.xml|build.gradle|README.md"
#exts_ignorar="md|json|txt|csv|yaml|yml|ini|xml|properties|log|conf|cfg|bak|tmp|jpg|jpeg|png|gif|ico|svg|pdf|mp3|mp4|zip|tar|gz|rar|7z|exe|dll|bin|obj"
#exts_testeables="java"
#patrones_ignorar=""
#patrones_test="(_test|Test)\.java$"
#tam_min=50
#palabras_codigo="public class|private class|interface|enum|abstract|package|import|@"

# ==========================
# SCRIPT PRINCIPAL
# ==========================

if [ $# -ne 1 ]; then
    echo "Uso: $0 <directorio_proyecto>" >&2
    exit 1
fi

directorio_proyecto="$1"

# Archivo temporal para guardar paths
tmp_paths=$(mktemp)

find "$directorio_proyecto" -type f \
    ! \( $(echo "$dirs_ignorar" | sed 's/|/ -o -path */g' | sed 's/^/-path */;s/$/*/') \) \
| grep -Ev "/($dirs_ignorar)/" \
| grep -E "\.($exts_testeables)$" \
| grep -Ev "\.($exts_ignorar)$" \
| grep -Ev "/($archivos_ignorar)$" \
| grep -Ev "$patrones_ignorar" \
| grep -Ev "$patrones_test" \
> "$tmp_paths"

# Filtrar por tamaño y presencia de código
declare -A extensiones_detectadas

while read -r archivo; do
    if [ ! -f "$archivo" ]; then continue; fi
    if [ "$(stat -c %s "$archivo")" -lt "$tam_min" ]; then continue; fi
    extension="${archivo##*.}"
    if grep -Eq "$palabras_codigo" "$archivo"; then
        echo "$archivo"
        extensiones_detectadas["$extension"]=$(( ${extensiones_detectadas["$extension"]} + 1 ))
    fi
done < "$tmp_paths"

if [ ${#extensiones_detectadas[@]} -gt 0 ]; then
    max=0
    lenguaje=""
    for ext in "${!extensiones_detectadas[@]}"; do
        if [ ${extensiones_detectadas[$ext]} -gt $max ]; then
            max=${extensiones_detectadas[$ext]}
            lenguaje="$ext"
        fi
    done

    case "$lenguaje" in
        php)    lenguaje_name="PHP";;
        py)     lenguaje_name="Python";;
        js|jsx|ts|tsx)  lenguaje_name="JavaScript";;
        java)   lenguaje_name="Java";;
        cs)     lenguaje_name="C#";;
        go)     lenguaje_name="Go";;
        rb)     lenguaje_name="Ruby";;
        swift)  lenguaje_name="Swift";;
        scala)  lenguaje_name="Scala";;
        kt)     lenguaje_name="Kotlin";;
        m)      lenguaje_name="Objective-C";;
        rs)     lenguaje_name="Rust";;
        dart)   lenguaje_name="Dart";;
        c|cpp)  lenguaje_name="C/C++";;
        *)      lenguaje_name="Desconocido";;
    esac
    echo "#LENGUAJE_DETECTADO=$lenguaje_name"
else
    echo "#LENGUAJE_DETECTADO=Desconocido"
fi

rm "$tmp_paths"