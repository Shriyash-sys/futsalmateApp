#!/usr/bin/env bash
# Run on the server (SSH) from the Laravel project root, or: bash scripts/cpanel-pull-migrate.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
git pull origin main
php artisan migrate --force
