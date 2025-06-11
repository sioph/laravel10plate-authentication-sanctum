<?php

namespace Laravelplate\Authentication\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    use FileMerger;
    protected $signature = 'laravelplate:install-auth {--force : Force installation even if already installed}';
    protected $description = 'Install Laravelplate Authentication package';

    public function handle()
    {
        // Check if already installed
        if ($this->isAlreadyInstalled() && !$this->option('force')) {
            $this->warn('Laravelplate Authentication is already installed!');
            $this->info('Use --force flag to reinstall: php artisan laravelplate:install-auth --force');
            return;
        }

        $this->info('Installing Laravelplate Authentication...');

        // Smart installation
        $this->handleUserModel();
        $this->handleUsersMigration();
        $this->publishOtherFiles();
        $this->addApiRoutes();
        $this->installSanctum();

        $this->info('Laravelplate Authentication installed successfully!');
    }

    protected function handleUserModel()
    {
        $userModelPath = app_path('Models/User.php');
        
        if (File::exists($userModelPath)) {
            $this->info('Existing User model found. Merging required modifications...');
            $this->mergeUserModel($userModelPath);
        } else {
            $this->info('No existing User model found. Creating new one...');
            File::copy(__DIR__.'/../Models/User.php', $userModelPath);
        }
    }

    protected function mergeUserModel($userModelPath)
    {
        $existingContent = File::get($userModelPath);
        $packageUserContent = File::get(__DIR__.'/../Models/User.php');
        
        // Check if already merged
        if ($this->isAlreadyModified($existingContent)) {
            $this->warn('User model appears to already have Laravelplate modifications. Skipping...');
            return;
        }

        // Create backup
        $this->createBackup($userModelPath);

        // Merge fillable fields in exact order
        $orderedFillable = [
            'first_name', 'middle_name', 'last_name', 'contact_number',
            'email', 'password', 'role_id', 'user_status_id', 
            'password_reset_token', 'password_reset_expires_at',
            'email_verified_at', 'remember_token'
        ];
        
        $existingContent = $this->addFillableFields($existingContent, $orderedFillable);
        
        // Add relationships and methods
        $relationshipsAndMethods = $this->extractRelationshipsAndMethods($packageUserContent);
        $existingContent = $this->addRelationshipsAndMethods($existingContent, $relationshipsAndMethods);
        
        // Add HasApiTokens if not present
        $existingContent = $this->addHasApiTokens($existingContent);
        
        // Add appends property
        $existingContent = $this->addAppendsProperty($existingContent);

        File::put($userModelPath, $existingContent);
        $this->info('User model updated successfully!');
    }

    protected function handleUsersMigration()
    {
        $migrationPath = database_path('migrations');
        $usersMigrationPattern = '*_create_users_table.php';
        $existingMigrations = glob($migrationPath . '/' . $usersMigrationPattern);
        
        if (!empty($existingMigrations)) {
            $this->info('Existing users migration found. Checking if modification is needed...');
            $existingMigrationPath = $existingMigrations[0];
            $this->modifyUsersMigration($existingMigrationPath);
        } else {
            $this->info('No existing users migration found. Creating new one...');
            File::copy(
                __DIR__.'/../Migrations/2014_10_12_000000_create_users_table.php',
                $migrationPath . '/2014_10_12_000000_create_users_table.php'
            );
        }
    }

    protected function modifyUsersMigration($migrationPath)
    {
        $existingContent = File::get($migrationPath);
        
        // Check if already modified
        if ($this->isAlreadyModified($existingContent)) {
            $this->warn('Users migration appears to already have Laravelplate modifications. Skipping...');
            return;
        }

        // Create backup
        $this->createBackup($migrationPath);

        $packageMigrationContent = File::get(__DIR__.'/../Migrations/2014_10_12_000000_create_users_table.php');
        
        // Extract the entire up() method content from package migration
        preg_match('/public function up\(\): void\s*\{(.*?)\}/s', $packageMigrationContent, $matches);
        
        if (isset($matches[1])) {
            $newUpMethodContent = $matches[1];
            
            // Replace the existing up() method content
            $pattern = '/(public function up\(\): void\s*\{)(.*?)(\})/s';
            $replacement = '$1' . $newUpMethodContent . '$3';
            $modifiedContent = preg_replace($pattern, $replacement, $existingContent);
            
            // Add the Hash import if not present
            if (!str_contains($modifiedContent, 'use Illuminate\Support\Facades\Hash;')) {
                $modifiedContent = str_replace(
                    'use Illuminate\Support\Facades\Schema;',
                    "use Illuminate\Support\Facades\Schema;\nuse Illuminate\Support\Facades\Hash;\nuse App\Models\User;",
                    $modifiedContent
                );
            }
            
            File::put($migrationPath, $modifiedContent);
            $this->info('Users migration updated successfully!');
        }
    }

    protected function publishOtherFiles()
    {
        // Publish other models (Role, UserStatus)
        $this->call('vendor:publish', [
            '--tag' => 'laravelplate-auth-models',
            '--force' => $this->option('force')
        ]);
        
        // Publish other migrations
        $otherMigrations = [
            '2014_09_25_055221_create_roles_table.php',
            '2014_09_26_034833_create_user_statuses_table.php'
        ];
        
        foreach ($otherMigrations as $migration) {
            $sourcePath = __DIR__.'/../Migrations/' . $migration;
            $destinationPath = database_path('migrations/' . $migration);
            
            if (!File::exists($destinationPath) || $this->option('force')) {
                File::copy($sourcePath, $destinationPath);
                $this->info("Migration {$migration} published successfully!");
            }
        }
        
        // Publish controllers
        $this->call('vendor:publish', [
            '--tag' => 'laravelplate-auth-controllers',
            '--force' => $this->option('force')
        ]);
    }

    protected function addFillableFields($content, $orderedFields)
    {
        // Find existing fillable array
        if (preg_match('/protected \$fillable = \[(.*?)\];/s', $content, $matches)) {
            $existingFillable = $matches[1];
            $existingFields = [];
            
            // Extract existing fields
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $existingFillable, $fieldMatches);
            if (isset($fieldMatches[1])) {
                $existingFields = $fieldMatches[1];
            }
            
            // Start with ordered fields
            $finalFields = $orderedFields;
            
            // Add any existing custom fields that aren't in orderedFields
            // but exclude 'name' since it's being replaced by first_name, middle_name, last_name
            foreach ($existingFields as $existingField) {
                if (!in_array($existingField, $finalFields) && $existingField !== 'name') {
                    $finalFields[] = $existingField;
                }
            }
            
            $newFillableString = "[\n        '" . implode("',\n        '", $finalFields) . "',\n    ]";
            
            $content = preg_replace(
                '/protected \$fillable = \[.*?\];/s',
                "protected \$fillable = {$newFillableString};",
                $content
            );
        }
        
        return $content;
    }

    protected function extractRelationshipsAndMethods($packageContent)
    {
        $methods = [];
        
        // Extract methods using regex
        preg_match_all('/\s+(public function \w+\(\).*?^\s+})/ms', $packageContent, $matches);
        
        if (isset($matches[1])) {
            foreach ($matches[1] as $method) {
                if (str_contains($method, 'role()') || 
                    str_contains($method, 'status()') || 
                    str_contains($method, 'isActive()') || 
                    str_contains($method, 'getFullNameAttribute()')) {
                    $methods[] = trim($method);
                }
            }
        }
        
        return $methods;
    }

    protected function addRelationshipsAndMethods($content, $methods)
    {
        foreach ($methods as $method) {
            // Check if method already exists
            $methodName = '';
            if (preg_match('/public function (\w+)\(/', $method, $matches)) {
                $methodName = $matches[1];
            }
            
            if ($methodName && !str_contains($content, "function {$methodName}(")) {
                // Add method with proper spacing before the closing class brace
                $content = preg_replace('/^}$/m', "\n    {$method}\n}", $content);
            }
        }
        
        return $content;
    }

    protected function addHasApiTokens($content)
    {
        // Add HasApiTokens trait if not present
        if (!str_contains($content, 'HasApiTokens')) {
            // Add import
            if (!str_contains($content, 'use Laravel\Sanctum\HasApiTokens;')) {
                $content = str_replace(
                    'use Illuminate\Notifications\Notifiable;',
                    "use Illuminate\Notifications\Notifiable;\nuse Laravel\\Sanctum\\HasApiTokens;",
                    $content
                );
            }
            
            // Add trait usage
            $content = preg_replace(
                '/(use\s+)([^;]+)(;)/',
                '$1HasApiTokens, $2$3',
                $content,
                1
            );
        }
        
        return $content;
    }

    protected function addAppendsProperty($content)
    {
        if (!str_contains($content, 'protected $appends')) {
            // Add appends property after casts
            $content = preg_replace(
                '/(protected \$casts = \[.*?\];)/s',
                '$1' . "\n\n    protected \$appends = ['full_name'];",
                $content
            );
        }
        
        return $content;
    }

    protected function isAlreadyInstalled()
    {
        // Check if key files from the package already exist
        return File::exists(app_path('Models/Role.php')) && 
               File::exists(app_path('Models/UserStatus.php')) &&
               File::exists(app_path('Models/User.php')) &&
               File::exists(database_path('migrations/2014_09_25_055221_create_roles_table.php')) &&
               File::exists(database_path('migrations/2014_09_26_034833_create_user_statuses_table.php')) &&
               File::exists(database_path('migrations/2014_10_12_000000_create_users_table.php'));
    }

    protected function addApiRoutes()
    {
        $apiRoutesPath = base_path('routes/api.php');
        $stubContent = file_get_contents(__DIR__.'/../Stubs/api_routes.stub');

        if (!File::exists($apiRoutesPath)) {
            $this->error('API routes file not found!');
            return;
        }

        $existingRoutes = File::get($apiRoutesPath);
        
        // Remove the default /user route if it exists
        $existingRoutes = preg_replace(
            '/Route::middleware\([\'"]auth:sanctum[\'"]\)->get\([\'"]\/user[\'"], function \(Request \$request\) \{.*?\}\);/s',
            '',
            $existingRoutes
        );
        
        // Check if authentication routes already exist
        if (!str_contains($existingRoutes, 'AuthenticationController') && 
            !str_contains($existingRoutes, '/register-account')) {
            
            // Extract import and routes from stub
            $stubLines = explode("\n", $stubContent);
            $import = $stubLines[0]; // use App\Http\Controllers\AuthenticationController;
            $routes = implode("\n", array_slice($stubLines, 2)); // Skip import and empty line
            
            // Add import after existing imports (no blank line)
            $pattern = '/(use\s+[^;]+;\s*\n)/';
            if (preg_match_all($pattern, $existingRoutes, $matches, PREG_OFFSET_CAPTURE)) {
                $lastImportEnd = end($matches[0])[1] + strlen(end($matches[0])[0]);
                $newContent = substr_replace($existingRoutes, $import . "\n", $lastImportEnd, 0);
            } else {
                $newContent = $existingRoutes;
            }
            
            // Add routes after the comment block
            $commentPattern = '/\/\*\s*\|[-]+\|\s*\|\s*API Routes.*?\*\//s';
            if (preg_match($commentPattern, $newContent, $commentMatches, PREG_OFFSET_CAPTURE)) {
                $commentEnd = $commentMatches[0][1] + strlen($commentMatches[0][0]);
                
                // Find and remove excessive whitespace after comment block
                $afterComment = substr($newContent, $commentEnd);
                $trimmedAfterComment = ltrim($afterComment, " \t\n\r");
                $whitespaceLength = strlen($afterComment) - strlen($trimmedAfterComment);
                
                // Replace excessive whitespace with exactly one blank line and add routes
                $newContent = substr_replace($newContent, "\n\n" . $routes, $commentEnd, $whitespaceLength);
            } else {
                // Fallback: append routes to the end
                $newContent .= "\n\n" . $routes;
            }
            
            File::put($apiRoutesPath, $newContent);
            $this->info('API routes added successfully!');
        } else {
            // Even if routes exist, still remove the default /user route
            File::put($apiRoutesPath, $existingRoutes);
            $this->warn('API routes already exist. Skipping addition but cleaned up default routes...');
        }
    }

    protected function installSanctum()
    {
        if (!File::exists(config_path('sanctum.php'))) {
            $this->info('Installing Laravel Sanctum...');
            $this->call('vendor:publish', ['--provider' => 'Laravel\Sanctum\SanctumServiceProvider']);
        }
    }
}