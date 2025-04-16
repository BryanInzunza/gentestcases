#!/bin/bash

# Check if bito is installed
if ! command -v bito &> /dev/null; then
    echo '{"error": "bito could not be found. Please install it and try again."}'
    exit 1
fi

# Check if the file to process is provided
if [ "$#" -lt 2 ]; then
    echo '{"error": "Usage: ./script.sh <file_to_process> <output_dir>"}'
    exit 1
fi

# File to process
inputfile_for_ut_gen="$1"
output_dir="$2"

# Create the output directory if it doesn't exist
mkdir -p "$output_dir"

# List of files and directories to ignore
ignore_list=(
    ".git"
    ".gitignore"
    ".gitattributes"
    "package.json"
    "yarn.lock"
    "package-lock.json"
    ".env"
    "Dockerfile"
    "Containerfile"
    "docker-compose.yml"
    "node_modules"
    "Debug"
    "Release"
    ".vs"
    "init"
    ".ignore"
    "Pack"
    "x64"
    "x86"
)

# List of file extensions to ignore
ignore_extensions=(
    ".md"
    ".json"
    ".txt"
    ".csv"
    ".yaml"
    ".yml"
    ".ini"
    ".xml"
    ".properties"
    ".htaccess"
    ".sln"
    ".suo"
    ".sdf"
    "modernzr.js"
)

# Check if the file or directory is in the ignore list
for ignore_item in "${ignore_list[@]}"; do
    if [[ "$inputfile_for_ut_gen" == *"$ignore_item"* ]]; then
        echo "{\"status\": \"skipped\", \"reason\": \"Ignored file or directory: $inputfile_for_ut_gen\"}"
        exit 0
    fi
done

# Check if the file has an ignored extension
for ext in "${ignore_extensions[@]}"; do
    if [[ "$inputfile_for_ut_gen" == *"$ext" ]]; then
        echo "{\"status\": \"skipped\", \"reason\": \"File with ignored extension ($ext): $inputfile_for_ut_gen\"}"
        exit 0
    fi
done

# Check if the file exists
if [ ! -f "$inputfile_for_ut_gen" ]; then
    echo "{\"error\": \"File does not exist: $inputfile_for_ut_gen\"}"
    exit 1
fi

# Framework to use for testing
framework="PHPUnit"

# Read the prompts into variables
prompt=$(<prompts/gen_test_case_1.pmt)
prompt2=$(<prompts/gen_test_case_2.pmt)

# Extract the filename without the extension
filename=$(basename -- "$inputfile_for_ut_gen")
extension="${filename##*.}"
filename="${filename%.*}"

# Replace the placeholders in the prompt with the user's input and filename
prompt_instance=${prompt/\$framework/${framework}}
prompt_instance=${prompt_instance/\$filename/${filename}.${extension}}

# Create a temporary file and write the modified prompt to it
temp_prompt=$(mktemp --suffix=".pmt")
trap "rm -f $temp_prompt" EXIT
echo "$prompt_instance" > "$temp_prompt"

# Prepare output files
output_file="$output_dir/${filename}_test.php"
log_file="$output_dir/${filename}_test.json"

# Start logging
start_time=$(date +"%Y-%m-%d %H:%M:%S")
log_data="{\"file\": \"$inputfile_for_ut_gen\", \"start_time\": \"$start_time\", \"retries\": [], \"success\": false}"

# Maximum number of retries for processing
MAX_RETRIES=3
retries=0
success=false

while [ $retries -lt $MAX_RETRIES ]; do
    # Run the bito command with the first prompt
    if ! bito --agent gentestcase -p "$temp_prompt" -f "$inputfile_for_ut_gen" -c "context.txt" > /dev/null; then
        log_data=$(echo "$log_data" | jq --arg retry "$retries" --arg error "The bito command failed. Retrying..." '.retries += [{"attempt": $retry, "error": $error}]')
        retries=$((retries+1))
        sleep 2
        continue
    fi

    echo "$prompt2" > "$temp_prompt"

    # Run the bito command with the second prompt and store the output
    if ! bito --agent gentestcase -p "$temp_prompt" -f "$inputfile_for_ut_gen" -c "context.txt" > "$output_file"; then
        log_data=$(echo "$log_data" | jq --arg retry "$retries" --arg error "The bito command failed. Retrying..." '.retries += [{"attempt": $retry, "error": $error}]')
        retries=$((retries+1))
        sleep 2
        continue
    fi

    # Verify the output file exists and is not empty
    if [ -s "$output_file" ]; then
        log_data=$(echo "$log_data" | jq '.success = true')
        success=true
        break
    else
        log_data=$(echo "$log_data" | jq --arg retry "$retries" --arg error "Test file was not generated. Retrying..." '.retries += [{"attempt": $retry, "error": $error}]')
        retries=$((retries+1))
        sleep 2
    fi
done

if [ "$success" = false ]; then
    log_data=$(echo "$log_data" | jq '.error = "Failed to generate test file after maximum retries."')
    echo "$log_data" > "$log_file"
    exit 1
fi

# Calculate execution time
end_time=$(date +"%Y-%m-%d %H:%M:%S")
execution_time=$(( $(date +%s) - $(date +%s -d "$start_time") ))
log_data=$(echo "$log_data" | jq --arg end_time "$end_time" --argjson execution_time "$execution_time" '. + {"end_time": $end_time, "execution_time": $execution_time}')

# Count characters in the original file
char_count=$(wc -m < "$inputfile_for_ut_gen")
log_data=$(echo "$log_data" | jq --argjson char_count "$char_count" '. + {"char_count": $char_count}')

# Run extract_code.sh on the output file
extract_output=$(./extract_code.sh "$output_file" 2>&1)
log_data=$(echo "$log_data" | jq --arg extract_output "$extract_output" '. + {"extract_code_output": $extract_output}')

# Write the log data to the log file
echo "$log_data" > "$log_file"