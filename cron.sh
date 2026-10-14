#!/bin/bash
# -------------------------------------------------------------
# OviPanel Cron Execution Wrapper for Laravel Scheduler
# -------------------------------------------------------------
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR" || exit 1

/usr/bin/php82 artisan schedule:run >> storage/logs/cron.log 2>&1
