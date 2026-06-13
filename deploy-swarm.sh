#!/bin/bash
# Rebuild Tenaz CRM image and update Docker Swarm service
set -e

cd "$(dirname "$0")"

echo "Building image tenaz:latest..."
docker build -t tenaz:latest .

echo "Updating tenaz_tenaz service..."
docker service update --image tenaz:latest tenaz_tenaz --force

echo "Done. Queue workers will restart with new code."
