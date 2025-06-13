# Laravel10plate Authentication Sanctum

A modular Laravel Sanctum authentication package for the Laravel10plate boilerplate system. This package provides a complete authentication system with user management, role-based access, and API token authentication.

## 🚀 Features

- **Laravel Sanctum Token-based Authentication**
- **User Registration & Login**
- **Role-based Access Control** (Admin/Staff)
- **User Status Management** (Active/Inactive)
- **Secure API Endpoints**
- **Auto-generated Migrations, Models, and Controllers**
- **Pre-configured Admin Account**

## ⚠️ Important Warning

> **🚨 RECOMMENDED FOR FRESH PROJECTS ONLY**
> 
> This package is designed to work best with **fresh Laravel 10 installations** that do not have existing authentication features. Installing this package on projects with existing authentication systems may cause conflicts or unexpected behavior.
>
> **Before Installation:**
> - Ensure you're using **Laravel 10.x**
> - Verify you don't have existing authentication scaffolding (Laravel Breeze, Jetstream, UI, etc.)
> - Consider using this package on a **newly created Laravel project** for best results
> - Backup your project before installation if you have existing authentication code
>
> **If you have existing authentication:**
> - Review the conflicts that may arise with your current User model and migrations
> - Test thoroughly in a development environment before deploying to production

## 📋 Requirements

- PHP ^8.1
- Laravel ^10.0
- Laravel Sanctum ^3.0

## 🛠 Installation

### Step 1: Install the Package

```bash
composer require sioph/laravel10plate-authentication-sanctum
```

### Step 2: Run Smart Installation Command

```bash
php artisan laravel10plate:install-auth
```

> 💡 **Smart Installation**: The installer automatically detects existing `User.php` model and users migration, then merges required code instead of overwriting. Backup files are created automatically.

### Step 3: Configure Database Connection

Configure your database in the `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

> 📝 **Note**: Make sure your database exists and the credentials are correct before proceeding to the next step.

### Step 4: Run Migrations

```bash
php artisan migrate
```

## 📋 Backup Files Management

During installation, the package automatically creates backup files of your existing models and migrations to prevent data loss:

### Backup Location:
- `app/Models/User.php.backup` - Backup of your original User model
- `database/migrations/*_create_users_table.php.backup` - Backup of your original users migration

### After Installation:
1. **Verify the installation** - Check that your User model and migration are working correctly
2. **Test the functionality** - Ensure authentication is working as expected
3. **Review the changes** - Compare the modified files with the backup files
4. **Clean up backups** - Once you've verified everything is working correctly, you can safely delete the backup files:

```bash
# Remove User model backup (after verification)
rm app/Models/User.php.backup

# Remove migration backup (after verification)
rm database/migrations/*_create_users_table.php.backup
```

> 💡 **Tip**: Keep backup files until you're completely satisfied with the installation. They serve as a safety net in case you need to revert changes.

> ⚠️ **Important**: Only delete backup files after thorough testing in your development environment.

## 📁 What Gets Installed

### Migrations:
- `2014_09_25_055221_create_roles_table.php`
- `2014_09_26_034833_create_user_statuses_table.php`
- `2014_10_12_000000_create_users_table.php` (if not exists)

### Models:
- `app/Models/User.php` (if not exists)
- `app/Models/Role.php`
- `app/Models/UserStatus.php`

### Controllers:
- `app/Http/Controllers/AuthenticationController.php`

### API Routes:
- Registration endpoint
- Login endpoint
- Logout endpoint (protected)

## 🔐 Default Admin Account

After migration, a default admin account is automatically created:

- **Email:** `admin@gmail.com`
- **Password:** `@Password1234`
- **Role:** Admin
- **Status:** Active

> ⚠️ **Important:** Change the default password in production!

## 📡 API Endpoints

### Public Endpoints

#### Register Account

```http
POST /api/register-account
Content-Type: application/json

{
    "first_name": "John",
    "middle_name": "",
    "last_name": "Doe",
    "email": "john@example.com",
    "mobile_number": "1234567890",
    "password": "password123",
    "password_confirmation": "password123"
}
```

#### Login

```http
POST /api/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response:**

```json
{
    "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "role": {
            "id": 2,
            "name": "Staff"
        },
        "status": {
            "id": 1,
            "name": "Active"
        }
    },
    "token": "1|abcdef123456..."
}
```

### Protected Endpoints (Requires Bearer Token)

#### Logout

```http
POST /api/logout
Authorization: Bearer {your-token}
```

## 🔧 Configuration

### Using the Token

After login, include the token in your API requests:

```javascript
// JavaScript/Vue.js example
axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

// Or per request
axios.get('/api/protected-route', {
    headers: {
        'Authorization': `Bearer ${token}`
    }
});
```

### Token Expiration

Tokens expire after 8 hours by default. You can modify this in the `AuthenticationController.php`:

```php
$token = $user->createToken('auth_token', ['*'], now()->addHours(8))->plainTextToken;
```

## 👥 User Roles & Status

### Default Roles:
- **Admin** - Full access
- **Staff** - Limited access

### User Statuses:
- **Active** - Can login
- **Inactive** - Cannot login

## 🔄 Re-installation

If you accidentally run the install command again:

```bash
# Will show warning if already installed
php artisan laravel10plate:install-auth

# Force reinstall (overwrites existing files)
php artisan laravel10plate:install-auth --force
```

## 🛡 Security Features

- Password hashing using Laravel's Hash facade
- Token-based authentication with expiration
- Account status validation on login
- Input validation on registration and login
- Protected routes using Sanctum middleware

## 🗃 Database Schema

### Users Table:

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    role_id BIGINT (Foreign Key -> roles.id),
    user_status_id BIGINT (Foreign Key -> user_statuses.id),
    first_name VARCHAR(255),
    middle_name VARCHAR(255) NULLABLE,
    last_name VARCHAR(255),
    contact_number VARCHAR(255) UNIQUE,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255) HASHED,
    password_reset_token VARCHAR(255) NULLABLE,
    password_reset_expires_at TIMESTAMP NULLABLE,
    email_verified_at TIMESTAMP NULLABLE,
    remember_token VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## 🐛 Troubleshooting

### Issue: "Table 'users' already exists"
**Solution:** The package checks if the users table exists and skips creating it if it does.

### Issue: "Route already defined"
**Solution:** The package checks for existing routes before adding new ones.

### Issue: "Class 'Laravel\Sanctum\HasApiTokens' not found"
**Solution:** Make sure Laravel Sanctum is installed:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### Issue: Token not working
**Solution:** Check if you have the Sanctum middleware in your API routes:

```php
Route::middleware('auth:sanctum')->group(function () {
    // Protected routes here
});
```

## 🔮 Future Features

- [ ] Password reset functionality
- [ ] Email verification
- [ ] Two-factor authentication
- [ ] Social login integration
- [ ] Audit logging

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 👨‍💻 Author

**Cilin John Rey**

- GitHub: [@cilinjohnrey](https://github.com/cilinjohnrey)
- Email: cilinjohnrey@gmail.com

## 🙏 Acknowledgments

- Laravel team for the amazing framework
- Laravel Sanctum for secure API authentication
- Laravel10plate boilerplate system contributors