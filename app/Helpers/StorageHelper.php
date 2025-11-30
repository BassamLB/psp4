<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Store a file in public storage and return the public URL
     * This works without symlinks by storing directly in public folder
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $directory
     * @return string Public URL
     */
    public static function storePublicly($file, $directory = 'uploads')
    {
        // Store in public disk (which should point to public folder)
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $directory . '/' . $filename;
        
        // Store directly in public folder
        $file->move(public_path($directory), $filename);
        
        // Return public URL
        return asset($path);
    }
    
    /**
     * Delete a file from public storage
     * 
     * @param string $url Full URL or path
     * @return bool
     */
    public static function deletePublicFile($url)
    {
        // Extract path from URL
        $path = str_replace(url('/'), '', $url);
        $fullPath = public_path($path);
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }
}
