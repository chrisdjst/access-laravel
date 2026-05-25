# Benchmark Results

Performance baseline for `modularize-rbac/laravel`. Tracks the cost of
the hot read paths (`canAccess()`, list endpoints, cache-fronted
repositories) and write paths (bulk operations + audit log) across the
package's life-cycle of releases.

## Running locally

```bash
composer bench
```

`composer bench` runs every `benchmarks/*Bench.php` file via PHPBench
with 5 revolutions × 3 iterations per parameter set. Numbers below are
the **median** across iterations. Environment: PHP 8.2 + SQLite
in-memory (`:memory:`) + no opcache, no xdebug.

A full run takes 2–3 minutes locally.

## Reading the table

| Column | Meaning |
|---|---|
| `set` | Parameter set name (size axis) |
| `μs` | Time per iteration in microseconds, median |
| `Δ` | Variance band across iterations (lower = more stable) |
| `mem` | Peak memory per iteration in bytes |

## Results

Baseline column = v2.3.0 (when the `CachedLanguageRepository` +
`CachedModuleRepository` decorators landed). Post-v2.4.0 column =
after P1 (this scaffold) + P2 (role_id index) + P3 (batched parent
walk) + P4 (memoized module hierarchy index).

### `CanAccessBench` — direct role bindings, no hierarchy

The hot path that fires on every `$user->can('events.view')` when the
user holds N directly-assigned roles. P2 contributes the index win
here; P3/P4 don't apply since no hierarchy is touched.

| set | v2.3.0 | v2.4.0 |
|---|---|---|
| `roles_1` | 603μs | unchanged |
| `roles_5` | 834μs | unchanged |
| `roles_10` | 1,353μs | unchanged |

> SQLite's planner already used the existing composite unique key for these queries, so the explicit `role_id` index doesn't move the needle here. The win materializes on hosts where the planner's prefix matching is weaker.

### `CanAccessWithHierarchyBench` — leaf role inheriting through ancestors

User holds the leaf of a `parent_role_id` chain of depth N; only the
root has the binding. The pre-P3 path issued one query per chain
step; the post-P3 path batches via `whereIn` per level.

| set | v2.3.0 | v2.4.0 | Δ |
|---|---|---|---|
| `depth_1` | 579μs | 531μs | -8% |
| `depth_5` | 842μs | 813μs | -3% |
| `depth_10` | 1,405μs | **1,062μs** | **-24%** |

> Win concentrates at deeper hierarchies — exactly the workload that motivated the change. Depth_1 doesn't change much because it was already one query.

### `CanAccessWithInheritanceBench` — module hierarchy inheritance

`access.inheritance.enabled = true` + `access.cache.enabled = false`
(measures raw cost on top of P4's memoize alone). Pre-P4 loaded the
full `modules` table on every check; post-P4 reuses a per-request
`ModuleHierarchyIndex` instance.

| set | v2.3.0 | v2.4.0 | Δ |
|---|---|---|---|
| `modules_10` | 826μs | 626μs | -24% |
| `modules_100` | 2,000μs | 639μs | **-68%** |
| `modules_500` | 5,716μs | **672μs** | **-88%** |

> 500-module inheritance check drops from ~6ms to ~0.7ms. Within a single request, the second/third/Nth `$user->can()` calls now pay only the resolver's hash-map walk (microseconds), not a table reload.

### `ModuleTreeBench` — cache hot vs cold

`ModuleRepository::allActiveTree()` across module counts × cache state.
Documents the v2.3.0 cache layer's ongoing payoff (unchanged by v2.4).

| set | v2.3.0 / v2.4.0 |
|---|---|
| `modules_10_cold` | 470–490μs |
| `modules_10_hot` | 7μs |
| `modules_100_cold` | ~3,600μs |
| `modules_100_hot` | 7μs |
| `modules_500_cold` | ~18ms |
| `modules_500_hot` | 7μs |

> Cache layer pays for itself: **2,500× speedup** at 500 modules on warm reads. Cold reads scale linearly with module count — expected (full table scan + hydration over the wire).

### `RoleEnrichBench` — `GET /api/admin/roles` end-to-end

Full HTTP request → controller → `enrich()` per role → JSON.
Language repo cache (v2.3.0) plus the new `role_id` index keep this
flat across role counts.

| set | v2.3.0 | v2.4.0 |
|---|---|---|
| `roles_10` | 1,086μs | 1,020μs |
| `roles_50` | 983μs | 1,028μs |
| `roles_200` | 1,058μs | 1,135μs |

> Variance within noise band. The enrich loop's cost is dominated by HTTP-stack setup (request → middleware → resource serialization), not by the per-role binding query.

### `BulkCreateModulesBench` — audit cost per module

`BulkCreateModules` use-case at batch sizes 10/100 × `audit.enabled`
toggle. Runs `revs=1` so the per-batch DB churn isn't amortized.

| set | v2.4.0 |
|---|---|
| `count_10_audit_on` | varies — captured per run |
| `count_10_audit_off` | varies — captured per run |
| `count_100_audit_on` | varies — captured per run |
| `count_100_audit_off` | varies — captured per run |

> Run `composer bench -- benchmarks/BulkCreateModulesBench.php` for fresh numbers. Local rule-of-thumb: audit-on is ~2× audit-off at count=100. This is the next obvious perf candidate (move audit to a queued listener) but didn't make the v2.4.0 train.

## Methodology notes

- **Microbench, not macrobench**: The numbers above measure the **PHP code path** end-to-end including DB I/O against in-memory SQLite. Production-grade MySQL/Postgres with proper indices will be slower in absolute terms (network + disk) but the **shape of the curves** (linear/sublinear) and the **relative wins** between subjects are what matters here.
- **Why SQLite `:memory:`**: zero I/O variance → tighter Δ; fast enough to keep the suite under 3 minutes. We're not measuring "is this fast?" — we're measuring "did our code path get measurably faster after P2/P3/P4?".
- **No opcache, no xdebug**: PHPBench warns about opcache being off; we leave it that way so JIT warm-up doesn't skew μs counts during the first iteration. Production hosts have opcache on — these numbers are upper bounds.
- **Variance band**: kept revs=5 / iter=3 as a compromise between stability (lower Δ) and total runtime. If you need tighter bands, bump revs in `phpbench.json`.

## Updating after a perf PR

1. Run `composer bench` on the PR branch.
2. Copy the median μs for affected subjects into the "Post-v2.4.0" column below (added when first PR lands).
3. Commit `BENCHMARK.md` alongside the perf change.
4. Reference the cell in the PR description (e.g. "`CanAccessWithHierarchyBench@depth_10` drops from 1,405μs → 612μs").
