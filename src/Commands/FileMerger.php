<?php

namespace Laravel10plate\Authentication\Commands;

trait FileMerger
{
    /**
     * Create a backup of the original file
     */
    protected function createBackup($filePath)
    {
        $backupPath = $filePath . '.laravel10plate-backup-' . date('Y-m-d-H-i-s');
        copy($filePath, $backupPath);
        $this->info("Backup created: " . basename($backupPath));
        return $backupPath;
    }

    /**
     * Check if content contains specific markers indicating it's already been modified
     */
    protected function isAlreadyModified($content, $markers = ['role_id', 'user_status_id'])
    {
        foreach ($markers as $marker) {
            if (!str_contains($content, $marker)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Add import statements to a PHP file
     */
    protected function addImports($content, $imports)
    {
        foreach ($imports as $import) {
            if (!str_contains($content, $import)) {
                // Find the last use statement and add after it
                $pattern = '/(use [^;]+;)(?!\s*use)/';
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, '$1' . "\n" . $import, $content, 1);
                } else {
                    // No use statements found, add after namespace
                    $content = preg_replace(
                        '/(namespace [^;]+;)/',
                        '$1' . "\n\n" . $import,
                        $content,
                        1
                    );
                }
            }
        }
        return $content;
    }

    /**
     * Merge traits into a class
     */
    protected function addTraits($content, $traitsToAdd)
    {
        foreach ($traitsToAdd as $trait) {
            if (!str_contains($content, $trait)) {
                // Find existing use statement for traits
                if (preg_match('/use\s+([^;]+);/', $content, $matches)) {
                    $existingTraits = array_map('trim', explode(',', $matches[1]));
                    $existingTraits[] = $trait;
                    $newTraitsString = implode(', ', $existingTraits);
                    
                    $content = preg_replace(
                        '/use\s+[^;]+;/',
                        "use {$newTraitsString};",
                        $content,
                        1
                    );
                } else {
                    // No existing traits, add new use statement
                    $content = preg_replace(
                        '/(class\s+\w+[^{]*\{)/',
                        '$1' . "\n    use {$trait};\n",
                        $content,
                        1
                    );
                }
            }
        }
        return $content;
    }

    /**
     * Get the difference between two arrays of fields
     */
    protected function getNewFields($existingFields, $requiredFields)
    {
        // Clean and normalize field names
        $existingFields = array_map(function($field) {
            return trim($field, "\"' ");
        }, $existingFields);
        
        return array_diff($requiredFields, $existingFields);
    }

    /**
     * Validate PHP syntax
     */
    protected function isValidPhpSyntax($content)
    {
        return @php_check_syntax($content) !== false;
    }
} 