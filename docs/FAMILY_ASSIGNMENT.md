# Family Assignment Job

## Overview

The `AssignFamiliesToVoters` job assigns `family_id` to voters based on normalized family names combined with `town_id`, `sect_id`, and `sijil_number`. This job is optimized for processing 1M+ records with support for incremental updates.

## Features

- **Arabic Name Normalization**: Handles alef variants (أ, إ, آ → ا), removes diacritics, normalizes spacing
- **Chunked Processing**: Processes voters in batches to avoid memory issues
- **Incremental Mode**: Only processes voters without `family_id` (for adding new districts)
- **Conflict Detection**: Reports families with same canonical name but different sijil numbers
- **Progress Logging**: Comprehensive logging for monitoring
- **Automatic Table Creation**: Creates `families` table if it doesn't exist

## Database Schema

### families table
```sql
- id (bigint, primary key)
- canonical_name (varchar, indexed)
- sijil_number (bigint, indexed) 
- town_id (int)
- sect_id (int)
- slug (varchar)
- created_at, updated_at (timestamps)
- UNIQUE KEY (canonical_name, sijil_number, town_id, sect_id)
```

### voters.family_id
```sql
- family_id (bigint, nullable, indexed)
- FOREIGN KEY references families(id)
```

## Usage

### 1. Basic Usage (Full Processing)

Process all voters in the database:

```php
use App\Jobs\AssignFamiliesToVoters;

// Dispatch to queue
AssignFamiliesToVoters::dispatch();

// Or run synchronously (testing)
AssignFamiliesToVoters::dispatchSync();
```

### 2. Incremental Mode (New Voters Only)

Process only voters without `family_id`:

```php
AssignFamiliesToVoters::dispatch(['incremental' => true]);
```

**Use case**: After importing voters from a new district, run in incremental mode to only process the new voters.

### 3. Specific Voter IDs

Process specific voters:

```php
AssignFamiliesToVoters::dispatch([
    'voter_ids' => [1, 2, 3, 100, 500]
]);
```

### 4. After Import Pipeline

Chain this job after `MergeVotersFromTemp` in your import pipeline:

```php
// In MergeVotersFromTemp job
public function handle(): void
{
    // ... existing merge logic ...
    
    // Chain family assignment
    AssignFamiliesToVoters::dispatch(['incremental' => true])
        ->onQueue('imports')
        ->afterCommit();
}
```

## How It Works

### 1. Family Creation Phase

- Queries distinct combinations of: `family_name`, `town_id`, `sect_id`, `sijil_number`
- Normalizes each `family_name` to create `canonical_name`
- Inserts unique families into `families` table (uses `insertOrIgnore` for duplicates)
- Groups by key: `canonical_name|town_id|sect_id|sijil_number`

### 2. Family Assignment Phase

- Processes voters in chunks of 1000 rows
- Maps each voter to their family key using normalized name
- Fetches matching families for the chunk
- Bulk updates `family_id` using CASE statement:
  ```sql
  UPDATE voters SET family_id = CASE id 
    WHEN 1 THEN 100 
    WHEN 2 THEN 101 
    ...
  END WHERE id IN (1,2,...)
  ```

### 3. Conflict Detection Phase

- Finds families where same `canonical_name + town + sect` has multiple sijil numbers
- Writes conflicts to: `storage/app/private/imports/family_sijil_conflicts_YYYYMMDD_HHMMSS.csv`
- Logs warning with conflict count

## Name Normalization Rules

The `normalizeName()` method applies these transformations:

1. **Trim** whitespace
2. **Remove BOM** and control characters
3. **Lowercase** conversion
4. **Normalize alef** variants: أ, إ, آ → ا
5. **Remove diacritics** (harakat): ً ٌ ٍ َ ُ ِ ّ ْ ٓ ٔ ٕ
6. **Remove punctuation** (keep only letters, numbers, spaces)
7. **Collapse** multiple spaces to single space

### Examples

| Original | Normalized |
|----------|------------|
| أحمد | احمد |
| الأحمد | الاحمد |
| مُحَمَّد | محمد |
| السَّيِّد | السيد |

## Performance

### Benchmarks (based on import tests)

- **Import to temp**: ~15,000 rows/second
- **Family creation**: Depends on distinct count (~500 families/second)
- **Family assignment**: ~1,000 voters/second (bulk updates)

### Expected Performance for 1M Records

- **Family creation**: ~2-5 minutes (assuming 10,000 distinct families)
- **Family assignment**: ~15-20 minutes (1M voters in 1000-row chunks)
- **Total**: ~20-25 minutes

### Memory Usage

- Chunks voters (1000 at a time) - minimal memory footprint
- Loads distinct family combinations into memory (typically <10MB for 10K families)
- Suitable for processing multi-million record datasets

## Monitoring

### Log Messages

```
AssignFamiliesToVoters: Starting
Creating families table (if needed)
Adding family_id column (if needed)
Creating/updating families from voters
Found distinct family combinations: 8,542
Families created/updated: inserted=8,542
Assigning family_id to voters
Family assignment completed: total_assigned=1,047,392
No family sijil conflicts found (or conflicts detected: count=15)
AssignFamiliesToVoters: Completed: assigned_count=1,047,392, elapsed_seconds=1,247.18
```

### Queue Monitoring

```bash
# Check imports queue length
php artisan queue:monitor redis:imports

# Watch queue worker logs
tail -f storage/logs/laravel.log | grep AssignFamiliesToVoters
```

## Conflict Resolution

If conflicts are reported (same canonical but different sijil numbers):

1. Review the conflicts CSV in `storage/app/private/imports/`
2. Options:
   - **Accept as-is**: Different sijil numbers may be legitimate for same family name in different locations
   - **Manual cleanup**: Update sijil_number in families table to use most common value
   - **Split families**: Keep separate if they truly represent different family lineages

## Troubleshooting

### Issue: No families created

**Cause**: Voters missing `family_name` or all empty/null values

**Solution**: 
```php
DB::table('voters')->whereNotNull('family_name')->where('family_name', '!=', '')->count();
```

### Issue: family_id stays NULL

**Cause**: No matching family found (normalization mismatch)

**Solution**: Check normalization logic or manually create family records

### Issue: Timeout on large datasets

**Solution**: Increase job timeout:
```php
public $timeout = 7200; // 2 hours
```

### Issue: Memory exhaustion

**Cause**: Too many distinct family combinations

**Solution**: Reduce chunk size or process in batches by town_id

## Re-running the Job

The job is idempotent and can be safely re-run:

- `families` table uses `insertOrIgnore` - won't create duplicates
- `family_id` assignments overwrite previous values
- Use incremental mode to only update NULL family_id values

```bash
# Re-assign all families
php artisan tinker
>>> AssignFamiliesToVoters::dispatch();

# Only process unassigned voters
>>> AssignFamiliesToVoters::dispatch(['incremental' => true]);
```

## Integration with Import Pipeline

### Current Pipeline

1. **CleanVoterUpload** - Clean CSV data
2. **ImportVotersToTempTable** - Load to temp table
3. **MergeVotersFromTemp** - Upsert to voters table
4. **AssignFamiliesToVoters** ← NEW - Assign families

### Recommended Dispatch Points

**Option A: After Each Import** (incremental)
```php
// In MergeVotersFromTemp::handle()
AssignFamiliesToVoters::dispatch(['incremental' => true]);
```

**Option B: Manual/Scheduled** (full)
```php
// Run once after bulk imports
AssignFamiliesToVoters::dispatch();
```

**Option C: Nightly Job** (incremental)
```php
// In app/Console/Kernel.php
$schedule->job(new AssignFamiliesToVoters(['incremental' => true]))
    ->daily()
    ->at('02:00');
```

## Testing

Since the job requires a complex database setup with foreign keys, testing is best done manually:

```bash
# 1. Create test voters
php artisan tinker
>>> Voter::factory()->count(1000)->create();

# 2. Run job synchronously
>>> AssignFamiliesToVoters::dispatchSync();

# 3. Verify assignments
>>> DB::table('voters')->whereNotNull('family_id')->count();
>>> DB::table('families')->count();

# 4. Check a specific voter
>>> $voter = Voter::first();
>>> $voter->family_id;
>>> DB::table('families')->find($voter->family_id);
```

## Notes

- The job creates/ensures the `families` table and `voters.family_id` column exist
- Supports running on empty database or existing data
- Does not delete any existing data
- Logs all operations to Laravel log
- Queue: `imports` (can be changed in constructor)
- Timeout: 1 hour (can be increased for very large datasets)
- Retries: 3 attempts
