# AssignFamiliesToVoters - Quick Reference

## Basic Commands

```php
// Full processing (all voters)
AssignFamiliesToVoters::dispatch();

// Incremental (new voters only)
AssignFamiliesToVoters::dispatch(['incremental' => true]);

// Synchronous (testing)
AssignFamiliesToVoters::dispatchSync();
```

## Command Line

```bash
# Dispatch via tinker
php artisan tinker
>>> AssignFamiliesToVoters::dispatch();

# Monitor queue
php artisan queue:work redis --queue=imports --max-time=3600

# Check status
php artisan queue:monitor redis:imports
```

## Query Status

```php
// Check assigned voters
DB::table('voters')->whereNotNull('family_id')->count();

// Check total families
DB::table('families')->count();

// Find unassigned voters
DB::table('voters')->whereNull('family_id')->count();

// View recent conflicts
storage_path('app/private/imports/family_sijil_conflicts_*.csv');
```

## Integration Points

### After Import (Recommended)
```php
// In MergeVotersFromTemp job
AssignFamiliesToVoters::dispatch(['incremental' => true])
    ->afterCommit();
```

### Scheduled (Nightly)
```php
// In routes/console.php or bootstrap/app.php
Schedule::job(new AssignFamiliesToVoters(['incremental' => true]))
    ->daily()
    ->at('02:00');
```

## Performance

| Dataset Size | Expected Time | Memory Usage |
|--------------|---------------|--------------|
| 100K voters | ~2 minutes | <100MB |
| 500K voters | ~8 minutes | <200MB |
| 1M voters | ~20 minutes | <300MB |

## Key Features

✓ Normalizes Arabic names (alef variants, diacritics)
✓ Groups by canonical_name + town + sect + sijil
✓ Processes in chunks (memory-safe for 1M+ records)
✓ Supports incremental updates (new districts)
✓ Detects and reports conflicts
✓ Comprehensive logging
✓ Idempotent (safe to re-run)

## Troubleshooting

| Issue | Solution |
|-------|----------|
| No families created | Check voters have family_name populated |
| family_id stays NULL | Check normalization or create families manually |
| Timeout | Increase `$timeout` property in job |
| Memory error | Reduce chunk size in `assignFamilyIds()` |

## Log File

```bash
tail -f storage/logs/laravel.log | grep AssignFamiliesToVoters
```

## Example Output

```
AssignFamiliesToVoters: Starting
Found distinct family combinations: 8,542
Families created/updated: inserted=8,542
Family assignment completed: total_assigned=1,047,392
No family sijil conflicts found
AssignFamiliesToVoters: Completed: assigned_count=1,047,392, elapsed_seconds=1,247.18
```

For detailed documentation, see: `docs/FAMILY_ASSIGNMENT.md`
