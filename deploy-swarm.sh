#!/bin/bash
# Rebuild ARIA image and update Docker Swarm service
set -e

cd "$(dirname "$0")"

echo "Building image aria:latest..."
docker build -t aria:latest .

echo "Updating aria_aria service..."
docker service update --image aria:latest aria_aria --force

echo "Done. Queue workers will restart with new code."
