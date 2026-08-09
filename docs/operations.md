# Operations runbook

Day-2 operations for the SBH production stack (Docker Compose:
`docker-compose.prod.yml`). Deploy/update lives in `scripts/update-vps.sh`;
this doc covers health, backups, the queue/scheduler, and common incidents.

## Health checks

Three layers, from shallowest to deepest:

| Endpoint | What it proves | Use |
| --- | --- | --- |
| `GET /up` | The Laravel app boots | Framework liveness |
| `GET /api/v1/status` | Public flags (maintenance, registration) | Frontend banners |
| `GET /api/v1/health` | **db + cache + storage + queue** are reachable | Uptime monitor / load balancer |

`/api/v1/health` returns **200** when the critical dependencies (database,
cache, storage) are healthy and **503** otherwise, so an external uptime monitor
(or a reverse proxy) can react. The `queue` block is informational — it reports
pending and failed job counts but never flips the overall status.

```json
{ "status": "ok",
  "checks": {
    "database": {"ok": true},
    "cache": {"ok": true},
    "storage": {"ok": true, "disk": "public"},
    "queue": {"ok": true, "connection": "database", "pending": 3, "failed": 0}
  },
  "time": "2026-08-09T10:00:00+00:00" }
```

Point an external monitor (UptimeRobot, BetterStack, a k8s readiness probe…) at
`/api/v1/health` and alert on non-200.

### Scheduled self-check

`system:health` runs every 5 minutes (see `routes/console.php`). It logs a
warning when a dependency is down, when `failed_jobs` reaches
`HEALTH_FAILED_JOBS_THRESHOLD` (default 25), or when the queue backlog reaches
`HEALTH_QUEUE_BACKLOG_THRESHOLD` (default 1000). Those warnings flow through the
normal logging channels, so wiring the `json`/`slack`/Sentry channels (see
`docs/observability.md`) escalates them. Run it by hand any time:

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T api php artisan system:health
```

### Container healthchecks

`api`, `web`, `nginx`, and `mysql` all declare Docker `healthcheck`s, so
`docker compose ps` shows `healthy`/`unhealthy` at a glance and `restart:
unless-stopped` recovers a wedged container. `nginx` waits on `api`/`web`.

## Backups

`scripts/backup-vps.sh` dumps the database (verified against mysqldump's
completion marker) and archives uploaded media, keeping the newest 7 by default
and pruning **only after** a run is verified good.

```bash
cd /opt/sbh-app
./scripts/backup-vps.sh                 # db + media, prune old
./scripts/backup-vps.sh --db-only       # skip the large media archive
./scripts/backup-vps.sh --keep 30       # override retention
./scripts/backup-vps.sh --remote s3://my-bucket/sbh   # also copy off-box
```

Cron it (minimum daily for a system that takes payments):

```
0 3 * * * cd /opt/sbh-app && ./scripts/backup-vps.sh >> /var/log/sbh-backup.log 2>&1
```

**Off-box copies matter.** An on-box backup survives a bad migration or an
accidental `demo:seed --fresh`, but **not** losing the VPS. Set `BACKUP_REMOTE`
in `.env.prod` (or pass `--remote`) to push each verified run to S3
(`s3://bucket/prefix`), rclone (`rclone:remote:path`), or an rsync target
(`user@host:/path`). The off-box copy runs after the local backup is verified,
so a failed upload never costs you the good local copy.

Restore with `scripts/restore-vps.sh` — read it before you need it. Note the
encrypted-column migrations are **not** cleanly reversible, so `migrate:rollback`
is not a substitute for a backup.

## Queue & scheduler

- Workers and the scheduler run under supervisord inside the `api` container
  (`apps/api/docker/supervisord.conf`): 2 `queue:work` workers and one
  `schedule:work`.
- The queue driver is `database`. Inspect depth/failures via `/api/v1/health`
  or `php artisan queue:failed`.
- Retry a failed job: `php artisan queue:retry <id>` (or `all`).
- Every scheduled task uses `withoutOverlapping()`. `onOneServer()` is
  deliberately **not** set — there is one scheduler process today; add it before
  scaling the scheduler horizontally.

## Common incidents

| Symptom | First checks |
| --- | --- |
| `/api/v1/health` → 503 | Which check is `ok:false`? DB down → `docker compose ps mysql`; storage → media volume permissions; cache → cache store reachable. |
| Uploads stuck "processing" | Queue workers alive? `docker compose ps api`; `php artisan queue:failed`; ffmpeg present in the image. |
| Upload marked "infected" | Expected when ClamAV is on and flagged the file — the file was removed. See the `av` profile in the compose file. |
| Rising `failed` job count | `php artisan queue:failed`; fix the cause; `queue:retry all`. |
| Reverb / realtime dropping | The web app falls back to polling automatically; check the `reverb` supervisord program and port 8080. |
