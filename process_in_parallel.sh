#!/bin/bash

# Start time measurement
start_time=$(date +%s)

# Check if the correct number of arguments is provided
if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <project_folder>"
    exit 1
fi

# Check if the first argument is a valid directory
project_folder="$1"
if [ ! -d "$project_folder" ]; then
    echo "Error: $project_folder is not a valid directory."
    exit 1
fi

# Check if the script for processing individual files exists
script_to_run="./process_single_file.sh"
if [ ! -x "$script_to_run" ]; then
    echo "Error: $script_to_run not found or not executable."
    exit 1
fi

# Create a directory for test cases
output_dir="${project_folder}_testcases"
mkdir -p "$output_dir"

# Create a temporary directory for logs
temp_dir=$(mktemp -d)
trap "rm -rf $temp_dir" EXIT

# Run the processing script in parallel for each file in the directory
find "$project_folder" -type f | xargs -P 4 -I {} bash -c "
    base_name=\$(basename \"{}\")
    log_file=\"$temp_dir/\${base_name}.log\"
    bash \"$script_to_run\" \"{}\" \"$output_dir\" > \"\$log_file\" 2>&1
"

echo "Processing completed. Test cases saved in '$output_dir'."

# End time measurement
end_time=$(date +%s)
execution_time=$((end_time - start_time))

# Save execution time to a file in the output directory
time_log_file="$output_dir/execution_time.log"
echo "Execution time: ${execution_time} seconds" > "$time_log_file"

# Notify the user
echo "Execution time saved in '$time_log_file'."