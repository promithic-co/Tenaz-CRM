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

# 3b. Reclaim disk before building
# Each deploy retags tenaz:latest and tenaz-landing:latest, orphaning the layer set the
# previous tag pointed at. Nothing ever reclaimed them, so the node filled to 100% and
# the Postgres stack — separate stack, same disk — dropped to 0 replicas. The app stayed
# 2/2 Running the whole time because the healthcheck hits /up, which never touches the
# database, so the outage was silent until a migration failed to resolve postgres_postgres.
#
# Reclaims run cheapest first, because the BuildKit cache is what makes the builds below
# fast — wiping it every deploy would undo the caching this script now relies on. So
# dangling images go unconditionally, and the build cache is sacrificed only if that was
# not enough to clear the floor.
#
# NOT `system prune -a` or `image prune -a`: those call an image unused when no *container*
# runs it, which during this very failure would have deleted the Postgres image while the
# service sat at 0 replicas, leaving nothing to restart from on a full disk.
disk_free() { df -Ph / | awk 'NR==2 {print $4" free ("$5" used)"}'; }
# Empty means df itself failed; report 0 so callers fall through to the abort rather than
# to a bare `[` error under set -e.
free_mb() { local m; m=$(df -Pm / | awk 'NR==2 {print $4}'); echo "${m:-0}"; }

MIN_FREE_MB="${MIN_FREE_MB:-5120}"

echo "[1b/5] Reclaiming disk before build..."
echo "  before: $(disk_free)"
docker image prune -f >/dev/null 2>&1 || true
echo "  after:  $(disk_free)"

if [ "$(free_mb)" -lt "$MIN_FREE_MB" ]; then
    echo "  below ${MIN_FREE_MB}MB — dropping the build cache too (this build will be slow)..."
    docker builder prune -af >/dev/null 2>&1 || true
    echo "  after:  $(disk_free)"
fi

# Refuse to build into a full disk. A half-written image next to a database that ran out
# of space is worse than a deploy that never started.
if [ "$(free_mb)" -lt "$MIN_FREE_MB" ]; then
    echo ""
    echo "ERROR: only $(free_mb)MB free on / after pruning (need ${MIN_FREE_MB}MB)."
    echo "The build will not fit. Find the space first:"
    echo "  du -xh --max-depth=1 / 2>/dev/null | sort -h | tail"
    echo "  docker system df"
    echo "  journalctl --vacuum-size=200M"
    echo "Then re-run. To override: MIN_FREE_MB=0 bash deploy.sh"
    exit 1
fi

# 4. Build Docker image
# This used to pass --no-cache "to ensure fresh assets after code changes", which
# misread how Docker caches: a COPY layer is keyed on the checksum of what it copies,
# so `COPY . .` invalidates on any changed file and everything after it — including
# `npm run build` — reruns on its own. --no-cache bought nothing and cost a full
# rebuild of the layers that never change between deploys: apk add, eight
# docker-php-ext-install compiles (intl links against icu and dominates), the Composer
# installer download, `composer install`, and `npm ci`. That was most of the ~10min.
#
# Safe to cache because composer.json declares no branch or path repositories and no
# wildcard constraints, so the composer layer is deterministic in composer.lock, and
# the VITE_* build args sit in their own ENV layer that invalidates when they change.
#
# Read only the vars we need (do NOT source .env — it can run commands and start a second deploy)
echo "[2/5] Building Docker image..."
REVERB_APP_KEY=$(grep -E '^REVERB_APP_KEY=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- || true)
APP_URL=$(grep -E '^APP_URL=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- || true)

docker build \
    --build-arg VITE_REVERB_APP_KEY="${REVERB_APP_KEY}" \
    --build-arg VITE_REVERB_HOST="${APP_URL#https://}" \
    --build-arg VITE_REVERB_PORT=443 \
    --build-arg VITE_REVERB_SCHEME=https \
    -t tenaz:latest .

# Build landing site image (static nginx — tenazcrm.com.br)
# Also cached now: the old comment claimed a COPY layer could serve a stale static
# site, but COPY is keyed on the content checksum of landing/, so edited HTML and
# assets invalidate it every time. The image is two COPY layers on nginx:alpine, so
# this one was never the slow half — it is dropped for consistency, not for speed.
echo "[2b/5] Building landing image..."
docker build -t tenaz-landing:latest -f docker/landing.Dockerfile .

# 5. Export env vars for stack (safe: grep/cut only — no sourcing)
# docker stack deploy expands ${VAR} from this shell; container needs DB_*, APP_KEY, etc.
export_env_from_file() {
    local f="$1"
    local line key val

    while IFS= read -r line || [ -n "$line" ]; do
        line="${line%$'\r'}"

        if [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]]; then
            continue
        fi

        key="${line%%=*}"
        val="${line#*=}"
        key="${key//[[:space:]]/}"

        if [[ ! "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]; then
            continue
        fi

        val="${val%\"}"; val="${val#\"}"; val="${val%\'}"; val="${val#\'}"
        export "$key=$val"
    done < "$f"
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
