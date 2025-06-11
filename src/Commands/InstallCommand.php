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

        // Mark as installed
        $this->markAsInstalled();

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

        // Merge fillable fields
        $requiredFillable = [
            'first_name', 'middle_name', 'last_name', 'contact_number',
            'role_id', 'user_status_id', 'password_reset_token', 'password_reset_expires_at'
        ];
        
        $existingContent = $this->addFillableFields($existingContent, $requiredFillable);
        
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
        
        // Extract the table schema from package migration
        preg_match('/Schema::create\(\'users\', function \(Blueprint \$table\) \{(.*?)\}\);/s', $packageMigrationContent, $matches);
        
        if (isset($matches[1])) {
            $newTableSchema = $matches[1];
            
            // Replace the existing table schema
            $pattern = '/(Schema::create\(\'users\', function \(Blueprint \$table\) \{)(.*?)(\}\);)/s';
            $replacement = '$1' . $newTableSchema . '$3';
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

    protected function addFillableFields($content, $requiredFields)
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
            
            // Add missing fields
            $fieldsToAdd = array_diff($requiredFields, $existingFields);
            
            if (!empty($fieldsToAdd)) {
                $newFields = array_merge($existingFields, $fieldsToAdd);
                $newFillableString = "[\n        '" . implode("',\n        '", $newFields) . "',\n    ]";
                
                $content = preg_replace(
                    '/protected \$fillable = \[.*?\];/s',
                    "protected \$fillable = {$newFillableString};",
                    $content
                );
            }
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
                // Add method before the closing class brace
                $content = preg_replace('/^}$/m', "    {$method}\n}", $content);
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
        return File::exists(base_path('.laravelplate-auth-installed'));
    }

    protected function markAsInstalled()
    {
        File::put(base_path('.laravelplate-auth-installed'), now()->toDateTimeString());
    }

    protected function addApiRoutes()
    {
        $apiRoutesPath = base_path('routes/api.php');
        $routesToAdd = file_get_contents(__DIR__.'/../Stubs/api_routes.stub');

        if (!File::exists($apiRoutesPath)) {
            $this->error('API routes file not found!');
            return;
        }

        $existingRoutes = File::get($apiRoutesPath);
        
        if (!str_contains($existingRoutes, 'AuthenticationController') && 
            !str_contains($existingRoutes, '/register-account')) {
            
            File::append($apiRoutesPath, "\n" . $routesToAdd);
            $this->info('API routes added successfully!');
        } else {
            $this->warn('API routes already exist. Skipping...');
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