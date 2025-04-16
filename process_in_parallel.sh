#!/bin/bash

# Medir el tiempo de inicio
start_time=$(date +%s)

# Verificar que se haya proporcionado el número correcto de argumentos
if [ "$#" -lt 1 ]; then
    echo "Uso: $0 <carpeta_del_proyecto>"
    exit 1
fi

# Verificar que el primer argumento sea un directorio válido
project_folder="$1"
if [ ! -d "$project_folder" ]; then
    echo "Error: $project_folder no es un directorio válido."
    exit 1
fi

# Verificar que exista el script para procesar archivos individuales
script_to_run="./process_single_file.sh"
if [ ! -x "$script_to_run" ]; then
    echo "Error: $script_to_run no se encontró o no es ejecutable."
    exit 1
fi

# Crear un directorio para los casos de prueba
output_dir="${project_folder}_testcases"
mkdir -p "$output_dir"

# Crear un directorio temporal para los logs
temp_dir=$(mktemp -d)
trap "rm -rf $temp_dir" EXIT

# Ejecutar el script de procesamiento en paralelo para cada archivo en la carpeta
find "$project_folder" -type f | xargs -P 4 -I {} bash -c "
    base_name=\$(basename \"{}\")
    log_file=\"$temp_dir/\${base_name}.log\"
    bash \"$script_to_run\" \"{}\" \"$output_dir\" > \"\$log_file\" 2>&1
"

echo "Procesamiento completado. Casos de prueba guardados en '$output_dir'."

# Medir el tiempo de finalización
end_time=$(date +%s)
execution_time=$((end_time - start_time))

# Guardar el tiempo de ejecución en un archivo dentro del directorio de salida
time_log_file="$output_dir/execution_time.log"
echo "Tiempo de ejecución: ${execution_time} segundos" > "$time_log_file"

# Notificar al usuario
echo "Tiempo de ejecución guardado en '$time_log_file'."
