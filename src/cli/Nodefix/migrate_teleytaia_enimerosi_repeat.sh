#!/bin/bash
# Example usage: ./migrate_teleytaia_enimerosi_repeat.sh 2000 100 5

# Set the initial values
load_nodes=$1
chunk=$2
delay_seconds=$3

# Loop until all nodes are processed
while true; do
  # Execute the Drush command with the provided arguments and capture the output
  result=$(drush php:script migrate_teleytaia_enimerosi.php load-nodes=$load_nodes chunk=$chunk)

  # Check if the PHP script output contains the specific message
  if [[ "$result" == *"Found no nodes to process."* ]]; then
    echo "Found no nodes to process. Exiting..."
    break
  fi

  # Use the captured result as needed
  echo "Result script: $result"

  # Wait for the specified delay before the next run
  sleep $delay_seconds
done
