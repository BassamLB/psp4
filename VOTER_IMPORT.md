# Voter Import Guide

## 📋 Overview
This system allows you to import millions of voter records from CSV files with intelligent matching of text values to database IDs.

## 🎯 Key Features
- ✅ **Smart Matching**: Automatically matches Arabic text to database IDs
- ✅ **Batch Processing**: Handles millions of records efficiently
- ✅ **Arabic Normalization**: Handles diacritics, different hamza forms, etc.
- ✅ **Error Handling**: Detailed error reporting with optional log files
- ✅ **Dry Run Mode**: Preview import without saving data
- ✅ **Progress Tracking**: Real-time progress bar
- ✅ **Unmatched Detection**: Shows which values couldn't be matched

## 📁 CSV File Format

### Required Columns
- `first_name` - First name (الاسم الأول)
- `family_name` - Family name (اسم العائلة)

### Optional Columns
- `father_name` - Father's name (اسم الأب)
- `mother_full_name` - Mother's full name (اسم الأم الكامل)
- `date_of_birth` - Date of birth (تاريخ الميلاد) - Format: YYYY-MM-DD, DD/MM/YYYY, etc.
- `gender` or `sex` - Gender (النوع) - Values: "ذكور" or "إناث"
- `doctrine` - Doctrine (المذهب) - Match your doctrine names
- `sijil_number` - Sijil number (رقم السجل)
- `sijil_additional_string` - Additional sijil info
- `town` - Town name (اسم البلدة) - Must match database
- `district` - District name (اسم القضاء) - **REQUIRED for duplicate town names** (see duplicate towns list below)
- `profession` - Profession (المهنة) - Must match database
- `travelled` - Travelled status (مسافر) - Values: "نعم"/"لا" or "yes"/"no"
- `country` or `country_of_travel` - Travel country (بلد السفر)
- `deceased` - Deceased status (متوفى) - Values: "نعم"/"لا" or "yes"/"no"
- `mobile_number` - Mobile number (رقم الجوال)
- `cheikh_dine` - Cheikh Dine
- `cheikh_reference` - Cheikh Reference
- `belong` - Political belonging (الانتماء) - Must match database

### Sample CSV
See `storage/app/voter_import_template.csv` for example format.

## 🚀 Import Commands

### Basic Import
```bash
php artisan voters:import path/to/voters.csv
```

### Dry Run (Preview Only)
```bash
php artisan voters:import path/to/voters.csv --dry-run
```

### Show Unmatched Values
```bash
php artisan voters:import path/to/voters.csv --show-unmatched
```

### Custom Batch Size
```bash
php artisan voters:import path/to/voters.csv --batch=2000
```

### Skip Header Row
```bash
php artisan voters:import path/to/voters.csv --skip-header
```

### Complete Example
```bash
php artisan voters:import storage/app/voters.csv --batch=1000 --skip-header --show-unmatched
```

## 📊 Import Process

1. **Initialization**: Loads all reference data (genders, towns, professions, etc.) into memory cache
2. **Reading**: Reads CSV file row by row
3. **Normalization**: Normalizes Arabic text (removes diacritics, handles variations)
4. **Matching**: Matches text values to database IDs
5. **Validation**: Validates required fields
6. **Batch Insert**: Inserts records in batches for performance
7. **Reporting**: Shows statistics and errors

## 🔍 Smart Arabic Matching

The system handles:
- **Diacritics**: "مُحَمَّد" matches "محمد"
- **Alef Variations**: "أحمد", "إحمد", "آحمد" all match "احمد"
- **Hamza Variations**: "ؤ", "ئ" normalize to "ء"
- **Taa Marbuta**: "ة" matches "ه"
- **Yaa**: "ى" matches "ي"
- **Case Insensitive**: "BEIRUT" matches "beirut"

## ⚠️ Common Issues & Solutions

### Issue: "Unmatched gender values"
**Solution**: Ensure your CSV uses exact names from database:
- Check: `php artisan tinker` then `App\Models\Gender::pluck('name')`
- Common values: "ذكور", "إناث"

### Issue: "Unmatched town names"
**Solution**: Towns must exist in database first
- List towns: `App\Models\Town::pluck('name')`
- Add missing towns via admin panel before import

**⚠️ IMPORTANT - Duplicate Town Names**: Some town names exist in multiple districts. When importing voter data, you must provide **BOTH the town name AND the district name** to ensure correct matching:

The following towns appear in multiple districts:
1. **عميق** (Amiq) - in البقاع الغربي & الشوف
2. **البيره** (Al-Birah) - in راشيا & الشوف
3. **بكيفا** (Bkifa) - in راشيا & الشوف
4. **عين عرب** (Ain Arab) - in راشيا & مرجعيون
5. **كوكبا** (Kawkaba) - in راشيا & حاصبيا
6. **الخلوات** (Al-Khalawat) - in حاصبيا & بعبدا
7. **علمان** (Alman) - in مرجعيون & الشوف
8. **الخريبه** (Al-Khreibeh) - in بعبدا & الشوف
9. **بسابا** (Bsaba) - in بعبدا & الشوف
10. **عين الرمانة** (Ain el-Remmaneh) - in بعبدا & عاليه

For these towns, include a `district` column in your CSV to ensure proper matching, otherwise the system may match to the wrong town.

### Issue: "Missing required fields"
**Solution**: Ensure every row has `first_name` and `family_name`

### Issue: "Import is slow"
**Solutions**:
- Increase batch size: `--batch=5000`
- Ensure database indexes are created (done automatically)
- Run on server with good disk I/O

## 📈 Performance Tips

### For Large Files (1M+ records)
1. Use large batch sizes: `--batch=5000`
2. Run on server (not local development)
3. Ensure adequate RAM (cache holds reference data)
4. Use SSD storage for database
5. Consider splitting file into smaller chunks

### Optimal Settings
```bash
# For 1 million records
php artisan voters:import voters.csv --batch=5000 --skip-header

# Expected time: ~10-30 minutes depending on hardware
```

## 🔧 Troubleshooting

### Check Import Logs
```bash
# View Laravel logs
tail -f storage/logs/laravel.log

# View error log (created during import)
cat storage/logs/voter_import_errors_*.json
```

### Verify Data After Import
```bash
php artisan tinker

# Count voters
App\Models\Voter::count()

# Check sample data
App\Models\Voter::with(['gender', 'town', 'profession'])->first()

# Count by gender
App\Models\Voter::selectRaw('gender_id, count(*) as total')->groupBy('gender_id')->get()
```

### Common Queries
```bash
# Active voters
App\Models\Voter::active()->count()

# Travelled voters
App\Models\Voter::travelled()->count()

# Voters by town
App\Models\Voter::selectRaw('town_id, count(*) as total')->groupBy('town_id')->orderByDesc('total')->take(10)->get()
```

## 📝 CSV Preparation Checklist

Before importing:
- [ ] Ensure CSV is UTF-8 encoded
- [ ] First row contains headers (use `--skip-header`)
- [ ] Required fields (first_name, family_name) are filled
- [ ] Gender values match database ("ذكور" or "إناث")
- [ ] Town names match exactly (case-insensitive OK)
- [ ] **District column included for duplicate town names** (see duplicate towns list)
- [ ] Profession names match database
- [ ] Country names match database
- [ ] Doctrine names match database
- [ ] Belong names match database
- [ ] Date format is consistent
- [ ] Boolean fields use "نعم"/"لا" or "yes"/"no"

## 🎓 Advanced Usage

### Programmatic Import
```php
use App\Helpers\VoterImportHelper;
use App\Models\Voter;

// Initialize caches
VoterImportHelper::initializeCaches();

// Prepare single voter
$voterData = VoterImportHelper::prepareVoterData([
    'first_name' => 'أحمد',
    'family_name' => 'الخطيب',
    'gender' => 'ذكور',
    'town' => 'بيروت',
    // ... other fields
]);

// Create voter
$voter = Voter::create($voterData);

// Clear caches when done
VoterImportHelper::clearCaches();
```

### Custom Matching Logic
Edit `app/Helpers/VoterImportHelper.php` to customize:
- `normalizeArabic()` - Modify text normalization
- `find*Id()` methods - Add custom matching logic
- `parseBoolean()` - Handle different boolean formats
- `parseDate()` - Add custom date formats

## 📚 Related Documentation
- Model: `app/Models/Voter.php`
- Helper: `app/Helpers/VoterImportHelper.php`
- Command: `app/Console/Commands/ImportVoters.php`
- Migration: `database/migrations/*_create_voters_table.php`

## 🆘 Support
For issues or questions, check:
1. Laravel logs: `storage/logs/laravel.log`
2. Import error logs: `storage/logs/voter_import_errors_*.json`
3. Database: Verify reference data exists before import

---

**Last Updated**: November 10, 2025
