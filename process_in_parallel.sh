#!/bin/bash

set -e

start_time=$(date +%s)

if [ "$#" -lt 1 ]; then
    echo "Uso: $0 <carpeta_del_proyecto>"
    exit 1
fi

project_folder="$1"
if [ ! -d "$project_folder" ]; then
    echo "Error: $project_folder no es un directorio válido."
    exit 1
fi

script_to_run="./process_single_file.sh"
if [ ! -x "$script_to_run" ]; then
    echo "Error: $script_to_run no se encontró o no es ejecutable."
    exit 1
fi

output_dir="${project_folder}_testcases"
mkdir -p "$output_dir"

# Filtrado previo eficiente
filtered_files_tmp=$(mktemp)
./filter_testable_files.sh "$project_folder" > "$filtered_files_tmp"
file_count=$(wc -l < "$filtered_files_tmp")
if [ "$file_count" -eq 0 ]; then
    echo "No se encontraron archivos testeables."
    exit 0
fi

# Paralelización dinámica según CPU
PARALLEL_JOBS=$(nproc)
echo "Procesando $file_count archivos en paralelo con $PARALLEL_JOBS hilos..."

current_jobs=0

while IFS= read -r full_path || [ -n "$full_path" ]; do
    (
        # Ejecuta el script hijo normalmente (este se encarga de guardar su propio log JSON)
        bash "$script_to_run" "$full_path" "$output_dir"
    ) &
    current_jobs=$((current_jobs+1))
    if [ "$current_jobs" -ge "$PARALLEL_JOBS" ]; then
        wait -n
        current_jobs=$((current_jobs-1))
    fi
done < "$filtered_files_tmp"
wait

rm "$filtered_files_tmp"

end_time=$(date +%s)
execution_time=$((end_time - start_time))
echo "Tiempo de ejecución: ${execution_time} segundos" > "$output_dir/execution_time.log"
echo "Procesamiento completado. Casos de prueba guardados en '$output_dir'."