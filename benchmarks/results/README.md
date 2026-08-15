# Benchmark Results

This directory is populated automatically by the `.github/workflows/benchmarks.yml` CI pipeline on every merge to `main`.

- `latest.json` / `latest.md` — the most recent SQLite (in-memory) benchmark run.
- `latest-mysql.json` / `latest-mysql.md` — the most recent MySQL-backed benchmark run using Testcontainers.
- `main-<short-sha>-<timestamp>.{json,md}` — historical SQLite snapshots for that commit.
- `main-<short-sha>-<timestamp>-mysql.{json,md}` — historical MySQL snapshots for that commit.

Run benchmarks locally with the same settings the pipeline uses:

```bash
# SQLite / in-memory defaults
php bin/tondbad benchmark --format=md --output=benchmarks/results/latest.md --iterations=100 --warmup=1 --forks=1

# MySQL via Testcontainers (requires Docker, pdo_mysql, and RUN_INTEGRATION_TESTS=1)
BENCHMARK_MYSQL=1 RUN_INTEGRATION_TESTS=1 php bin/tondbad benchmark --format=md --output=benchmarks/results/latest-mysql.md --iterations=100 --warmup=1 --forks=1
```

MySQL-backed benchmarks exercise the real database driver and give more realistic numbers for the ORM, queue, auth, and scheduler modules.
