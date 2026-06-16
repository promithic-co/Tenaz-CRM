#!/bin/bash
# Deploy script — run on VPS (Ubuntu/Debian)
# Usage: bash deploy.sh

set -e

APP_DIR="/srv/tenaz"
STACK_NAME="tenaz"
ENV_FILE="/srv/tenaz/.env"
LOCK_FILE="/tmp/tenaz-deploy.lock"

# Allow only one deploy at a time (prevents loop when build triggers another process)
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "Another deploy is already running (lock: $LOCK_FILE). Exiting."
    echo "Blocked PID=$$ PPID=$PPID at $(date -Iseconds)" >> /tmp/tenaz-deploy-blocked.log
    echo "  To see what started this: ps -p $PPID -o pid,ppid,cmd"
    exit 1
fi

echo "=============================="
echo " Deploy: Tenaz CRM"
echo "=============================="

# 1. Go to app directory
cd "$APP_DIR" || { echo "ERROR: directory $APP_DIR not found. Run first:"; echo "  git clone https://github.com/promithic-co/Tenaz-CRM.git $APP_DIR"; exit 1; }

# 2. Pull latest code
echo "[1/5] Pulling latest code from GitHub..."
git pull origin main

# 3. Check .env
if [ ! -f "$ENV_FILE" ]; then
    echo ""
    echo "WARNING: $ENV_FILE not found!"
    echo "Create the file based on .env.example before continuing:"
    echo "  cp .env.example .env"
    echo "  nano .env"
    exit 1
fi

# 4. Build Docker image (--no-cache ensures fresh assets after code changes)
# Read only the vars we need (do NOT source .env — it can run commands and start a second deploy)
echo "[2/5] Building Docker image (no cache)..."
REVERB_APP_KEY=$(grep -E '^REVERB_APP_KEY=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- || true)
APP_URL=$(grep -E '^APP_URL=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- || true)

docker build --no-cache \
    --build-arg VITE_REVERB_APP_KEY="${REVERB_APP_KEY}" \
    --build-arg VITE_REVERB_HOST="${APP_URL#https://}" \
    --build-arg VITE_REVERB_PORT=443 \
    --build-arg VITE_REVERB_SCHEME=https \
    -t tenaz:latest .

# Build landing site image (static nginx — tenazcrm.com.br)
echo "[2b/5] Building landing image..."
docker build -t tenaz-landing:latest -f docker/landing.Dockerfile .

# 5. Export env vars for stack (safe: grep/cut only — no sourcing)
# docker stack deploy expands ${VAR} from this shell; container needs DB_*, APP_KEY, etc.
export_env_from_file() {
    local f="$1"
    local vars="APP_KEY APP_URL DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD REDIS_HOST REDIS_PORT OPENAI_API_KEY OPENROUTER_API_KEY CREDFLOW_API_KEY EVOLUTION_API_URL EVOLUTION_API_KEY EVOLUTION_INSTANCE EVOLUTION_WEBHOOK_SECRET CREDFLOW_WEBHOOK_CONSULTA CREDFLOW_WEBHOOK_ESCALAR CREDFLOW_WEBHOOK_REGISTRAR REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET META_APP_ID META_APP_SECRET META_WEBHOOK_VERIFY_TOKEN META_APP_CONFIG_ID META_APP_CONFIG_ID_COEXISTENCE META_GRAPH_API_VERSION"
    for key in $vars; do
        val=$(grep -E "^${key}=" "$f" 2>/dev/null | cut -d= -f2- || true)
        val="${val%\"}"; val="${val#\"}"; val="${val%\'}"; val="${val#\'}"
        export "$key=$val"
    done
}
export_env_from_file "$ENV_FILE"

# 6. Deploy to Swarm
echo "[3/5] Deploying to Swarm..."
docker stack deploy \
    --compose-file docker-stack.yml \
    --with-registry-auth \
    --prune \
    "$STACK_NAME"

# Force Swarm to use the newly built image (same tag = no auto-update otherwise)
echo "Forcing service update to use new image..."
docker service update --force "${STACK_NAME}_tenaz" 2>/dev/null || true
docker service update --force "${STACK_NAME}_landing" 2>/dev/null || true

# 7. Run migrations via docker exec no primeiro container running
# Estratégia: aguarda até 3 min por um container em estado Running, então executa migrate.
# Usa docker exec para aproveitar a rede overlay (network_public) já configurada no serviço.
echo ""
echo "[4/5] Waiting for a running container to execute migrations..."
CONTAINER_ID=""
for i in $(seq 1 24); do
    # Filtra apenas containers do serviço tenaz que estejam em estado running
    CID=$(docker ps --filter "name=${STACK_NAME}_tenaz" --filter "status=running" -q | head -1)
    if [ -n "$CID" ]; then
        CONTAINER_ID="$CID"
        echo "  Container found: $CONTAINER_ID"
        break
    fi
    echo "  $i/24 — no running container yet, waiting 5s..."
    sleep 5
done

if [ -n "$CONTAINER_ID" ]; then
    echo "  Running migrations..."
    docker exec "$CONTAINER_ID" php artisan migrate --force
    # Phase 56: seed template configs BEFORE cache:clear and BEFORE app fully serves traffic (Pitfall C1/M2)
    echo "  Seeding template configs (idempotent)..."
    docker exec "$CONTAINER_ID" php artisan db:seed --class=Database\\Seeders\\AgentTemplateConfigSeeder --force
    # optimize:clear wipes cache store AND route/config/view/compiled caches.
    # Clears stale agent_config_id_* shape (Pitfall M2) and any stale route/view
    # cache from a previous image (e.g. an old `/` welcome page). Routes stay
    # runtime-resolved (web.php has closure routes that cannot be route:cache'd),
    # so the new `/` redirect is always picked up fresh.
    echo "  Clearing application + route/config/view cache (optimize:clear)..."
    docker exec "$CONTAINER_ID" php artisan optimize:clear
else
    echo "WARNING: No running container found after 2 minutes."
    echo "Run manually: docker exec \$(docker ps -q -f name=${STACK_NAME}_tenaz) php artisan migrate --force"
fi

echo ""
echo "[5/5] Deploy complete."
docker service ps "${STACK_NAME}_tenaz" --no-trunc | head -5

echo ""
echo "Recent logs:"
docker service logs "${STACK_NAME}_tenaz" --tail 20 2>/dev/null || true

echo ""
echo "Test app:     curl -s https://app.tenazcrm.com.br/up"
echo "Test landing: curl -sI https://tenazcrm.com.br/"
