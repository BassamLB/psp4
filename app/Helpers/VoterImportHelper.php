<?php

namespace App\Helpers;

use App\Models\Gender;
use App\Models\Town;
use App\Models\Doctrine;
use App\Models\Voter;

class VoterImportHelper
{
    private static $genderCache = null;
    private static $townCache = null;
    private static $doctrineCache = null;

    /**
     * Initialize all caches for performance
     */
    public static function initializeCaches(): void
    {
        self::$genderCache = Gender::all()->keyBy(function($item) {
            return $item->name ? self::normalizeArabic($item->name) : '';
        });

        self::$townCache = Town::all()->keyBy(function($item) {
            return $item->name ? self::normalizeArabic($item->name) : '';
        });

        self::$doctrineCache = Doctrine::all()->keyBy(function($item) {
            return $item->name ? self::normalizeArabic($item->name) : '';
        });
    }

    /**
     * Clear all caches
     */
    public static function clearCaches(): void
    {
        self::$genderCache = null;
        self::$townCache = null;
        self::$doctrineCache = null;
    }

    /**
     * Normalize Arabic text for matching
     * Removes diacritics, normalizes hamza, handles common variations
     */
    public static function normalizeArabic(string $text): string
    {
        // Remove diacritics (tashkeel)
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        
        // Normalize different forms of alef
        $text = preg_replace('/[أإآا]/u', 'ا', $text);
        
        // Normalize hamza
        $text = preg_replace('/[ؤئ]/u', 'ء', $text);
        
        // Normalize taa marbuta
        $text = preg_replace('/ة/u', 'ه', $text);
        
        // Normalize yaa
        $text = preg_replace('/ى/u', 'ي', $text);
        
        // Trim and lowercase
        return trim(mb_strtolower($text));
    }

    /**
     * Find gender ID by name (supports Arabic: ذكور/إناث or English: male/female)
     */
    public static function findGenderId(?string $name): ?int
    {
        if (empty($name)) {
            return null;
        }

        $normalized = self::normalizeArabic($name);
        
        // Check cache first
        if (self::$genderCache && self::$genderCache->has($normalized)) {
            return self::$genderCache->get($normalized)->id;
        }

        // Fallback: direct database query
        $gender = Gender::whereRaw('LOWER(name) = ?', [$normalized])->first();
        
        return $gender ? $gender->id : null;
    }

    /**
     * Find town ID by name and optionally district
     * For duplicate town names, district is required
     */
    public static function findTownId(?string $name, ?string $district = null): ?int
    {
        if (empty($name)) {
            return null;
        }

        // List of duplicate town names that require district
        $duplicateTowns = [
            'عميق', 'البيره', 'بكيفا', 'عين عرب', 'كوكبا', 
            'الخلوات', 'علمان', 'الخريبه', 'بسابا', 'عين الرمانة'
        ];

        $normalized = self::normalizeArabic($name);
        
        // Check if this is a duplicate town name
        $isDuplicate = false;
        foreach ($duplicateTowns as $dupTown) {
            if (self::normalizeArabic($dupTown) === $normalized) {
                $isDuplicate = true;
                break;
            }
        }

        // For duplicate towns, try to match with district name appended
        if ($isDuplicate && !empty($district)) {
            $townWithDistrict = $name . ' (' . $district . ')';
            $normalizedWithDistrict = self::normalizeArabic($townWithDistrict);
            
            if (self::$townCache && self::$townCache->has($normalizedWithDistrict)) {
                return self::$townCache->get($normalizedWithDistrict)->id;
            }

            $town = Town::whereRaw('LOWER(name) = ?', [$normalizedWithDistrict])->first();
            if ($town) {
                return $town->id;
            }
        }
        
        // Standard matching (for non-duplicate towns or when district not provided)
        if (self::$townCache && self::$townCache->has($normalized)) {
            return self::$townCache->get($normalized)->id;
        }

        $town = Town::whereRaw('LOWER(name) = ?', [$normalized])->first();
        
        return $town ? $town->id : null;
    }

    /**
     * Find doctrine ID by name
     */
    public static function findDoctrineId(?string $name): ?int
    {
        if (empty($name)) {
            return null;
        }

        $normalized = self::normalizeArabic($name);
        
        if (self::$doctrineCache && self::$doctrineCache->has($normalized)) {
            return self::$doctrineCache->get($normalized)->id;
        }

        $doctrine = Doctrine::whereRaw('LOWER(name) = ?', [$normalized])->first();
        
        return $doctrine ? $doctrine->id : null;
    }

    /**
     * Parse date from various formats
     */
    public static function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            // Try common date formats
            $formats = [
                'Y-m-d',
                'd/m/Y',
                'm/d/Y',
                'd-m-Y',
                'Y/m/d',
            ];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }

            // Fallback to strtotime
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }

        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Validate and prepare voter data for import
     */
    public static function prepareVoterData(array $row): array
    {
        return [
            'first_name' => trim($row['first_name'] ?? ''),
            'family_name' => trim($row['family_name'] ?? ''),
            'father_name' => trim($row['father_name'] ?? ''),
            'mother_full_name' => trim($row['mother_full_name'] ?? ''),
            'date_of_birth' => self::parseDate($row['date_of_birth'] ?? null),
            'gender_id' => self::findGenderId($row['gender'] ?? $row['sex'] ?? null),
            'doctrine_id' => self::findDoctrineId($row['doctrine'] ?? null),
            'sijil_number' => trim($row['sijil_number'] ?? ''),
            'sijil_additional_string' => trim($row['sijil_additional_string'] ?? ''),
            'town_id' => self::findTownId($row['town'] ?? null, $row['district'] ?? null),
            'profession_id' => null,
            'travelled' => false,
            'country_id' => null,
            'deceased' => false,
            'mobile_number' => null,
            'cheikh_dine' => false,
            'cheikh_reference' => null,
            'belong_id' => null,
        ];
    }

    /**
     * Check if a voter already exists in the database
     * Checks by the core identifying fields that are imported from CSV
     */
    public static function voterExists(array $voterData): bool
    {
        $query = Voter::query();

        // Match by core identifying fields only
        if (!empty($voterData['first_name'])) {
            $query->where('first_name', $voterData['first_name']);
        } else {
            return false; // Need at least first name
        }
        
        if (!empty($voterData['family_name'])) {
            $query->where('family_name', $voterData['family_name']);
        } else {
            return false; // Need family name
        }
        
        if (!empty($voterData['father_name'])) {
            $query->where('father_name', $voterData['father_name']);
        } else {
            return false; // Need father name
        }
        
        if (!empty($voterData['mother_full_name'])) {
            $query->where('mother_full_name', $voterData['mother_full_name']);
        } else {
            return false; // Need mother full name
        }
        
        if (!empty($voterData['date_of_birth'])) {
            $query->where('date_of_birth', $voterData['date_of_birth']);
        } else {
            return false; // Need date of birth
        }
        
        if (!empty($voterData['sijil_number'])) {
            $query->where('sijil_number', $voterData['sijil_number']);
        } else {
            return false; // Need sijil number
        }

        return $query->exists();
    }

    /**
     * Get statistics about unmatched values
     */
    public static function getUnmatchedStats(array $importedData): array
    {
        $stats = [
            'unmatched_genders' => [],
            'unmatched_towns' => [],
            'unmatched_doctrines' => [],
        ];

        foreach ($importedData as $row) {
            if (!empty($row['gender']) && !self::findGenderId($row['gender'])) {
                $stats['unmatched_genders'][] = $row['gender'];
            }
            if (!empty($row['town']) && !self::findTownId($row['town'])) {
                $stats['unmatched_towns'][] = $row['town'];
            }
            if (!empty($row['doctrine']) && !self::findDoctrineId($row['doctrine'])) {
                $stats['unmatched_doctrines'][] = $row['doctrine'];
            }
        }

        // Get unique values
        foreach ($stats as $key => $values) {
            $stats[$key] = array_unique($values);
        }

        return $stats;
    }
}
