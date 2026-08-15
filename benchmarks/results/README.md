# Benchmark Results

This directory is populated automatically by the `.github/workflows/benchmarks.yml` CI pipeline on every merge to `main`.

- `latest.json` / `latest.md` — the most recent benchmark run in JSON/Markdown format.
- `main-<short-sha>-<timestamp>.{json,md}` — historical snapshots for that commit.

Run benchmarks locally with the same settings the pipeline uses:

```bash
php bin/tondbad benchmark --format=md --output=benchmarks/results/latest.md --iterations=100 --warmup=1 --forks=1
```
