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

## Baseline (v2.3.0)

This is the pre-perf-PR baseline captured at v2.3.0 (the release that
shipped the `CachedLanguageRepository` + `CachedModuleRepository`
decorators with version-key invalidation).

### `CanAccessBench` — direct role bindings, no hierarchy

The hot path that fires on every `$user->can('events.view')` when the
user holds N directly-assigned roles. Last role gets the binding;
earlier roles each contribute an extra row to the `whereIn`.

| set | μs (median) | Δ | mem |
|---|---|---|---|
| `roles_1` | 603 | ±5% | 22.4 MB |
| `roles_5` | 834 | ±2% | 22.4 MB |
| `roles_10` | 1,353 | ±2% | 22.4 MB |

### `CanAccessWithHierarchyBench` — leaf role inheriting through ancestors

User holds the leaf of a `parent_role_id` chain of depth N. Only the
root has the binding; the answer must walk to the top to find it.

| set | μs (median) | Δ | mem | queries (estimated) |
|---|---|---|---|---|
| `depth_1` | 579 | ±5% | 22.5 MB | 4 |
| `depth_5` | 842 | ±2% | 22.5 MB | 8 |
| `depth_10` | 1,405 | ±4% | 22.5 MB | 13 |

> The walk currently issues one query per chain step (see `expandRoleIdsWithAncestors()` in `src/Concerns/HasAccessPermissions.php`). **P3** will collapse this to ~2 queries via a batched `whereIn` round.

### `CanAccessWithInheritanceBench` — module hierarchy inheritance

`access.inheritance.enabled = true` + `access.cache.enabled = false` (to
measure raw cost, no read-cache help). Tree: 1 root + N-1 flat children.
Query targets a child slug so the resolver actually walks the tree.

| set | μs (median) | Δ | mem |
|---|---|---|---|
| `modules_10` | 826 | ±4% | 22.4 MB |
| `modules_100` | 2,000 | ±1% | 22.6 MB |
| `modules_500` | 5,716 | ±3% | 23.4 MB |

> The bridge currently loads the entire `modules` table on every call to build the parent map. **P4** will route through `ModuleRepository::allActiveTree()` (cache-fronted) + memoize per-request.

### `ModuleTreeBench` — cache hot vs cold

`ModuleRepository::allActiveTree()` across module counts × cache state.
Demonstrates the v2.3.0 cache layer's payoff at steady state.

| set | μs (median) | Δ | mem |
|---|---|---|---|
| `modules_10_cold` | 492 | ±3% | 21.4 MB |
| `modules_10_hot` | 7 | ±0% | 21.4 MB |
| `modules_100_cold` | 3,577 | ±2% | 21.6 MB |
| `modules_100_hot` | 7 | ±3% | 21.7 MB |
| `modules_500_cold` | 18,158 | ±5% | 22.9 MB |
| `modules_500_hot` | 7 | ±5% | 23.4 MB |

> Cache layer pays for itself: **2,500× speedup** at 500 modules on warm reads. Cold reads scale linearly with module count (expected — full table scan + hydration).

### `RoleEnrichBench` — `GET /api/admin/roles` end-to-end

Full HTTP request → controller → `enrich()` per role → JSON response.
With the v2.3.0 language cache warm, the per-role loop only hits the
binding repository, so larger role counts stay relatively flat.

| set | μs (median) | Δ | mem |
|---|---|---|---|
| `roles_10` | 1,086 | ±3% | 24.1 MB |
| `roles_50` | 983 | ±2% | 24.3 MB |
| `roles_200` | 1,058 | ±3% | 25.1 MB |

### `BulkCreateModulesBench` — audit cost per module

`BulkCreateModules` use-case batch sizes × `access.audit.enabled` toggle.
Measures the inline audit listener cost on N module creations.

| set | μs (median) | Δ |
|---|---|---|
| `count_10_audit_on` | _captured at runtime_ | |
| `count_10_audit_off` | _captured at runtime_ | |
| `count_100_audit_on` | _captured at runtime_ | |
| `count_100_audit_off` | _captured at runtime_ | |

> Numbers will be filled in after a stable run — local environment shows audit-on roughly 2× audit-off at count=100. Refresh via `composer bench`.

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
