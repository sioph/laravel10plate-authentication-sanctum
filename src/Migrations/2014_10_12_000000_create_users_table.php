<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
		Schema::create('users', function (Blueprint $table) {
			$table->id();
			$table->foreignId('role_id')->nullable()->constrained('roles');
			$table->foreignId('user_status_id')->nullable()->constrained('user_statuses');
			$table->string('first_name');
			$table->string('middle_name')->nullable();
			$table->string('last_name');
			$table->string('contact_number')->unique();
			$table->string('email')->unique();
			$table->string('password');
			$table->string('password_reset_token')->nullable();
			$table->timestamp('password_reset_expires_at')->nullable();
			$table->timestamp('email_verified_at')->nullable();
			$table->rememberToken();
			$table->timestamps();
		});
		
		// Automated creation of admin user account if the web application feature doesn't have register
		$users = [
			[
				'first_name' => 'Admin',
				'last_name' => 'One',
				'email' => 'admin@gmail.com',
				'contact_number' => '09123456789',
				'password' => Hash::make('@Password1234'),
				'role_id' => 1,
				'user_status_id' => 1,
			],
		];
			
		foreach ($users as $user) {
			User::create($user);
		}
	}
	
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};