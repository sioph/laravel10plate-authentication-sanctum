<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $roles = [
            ['name' => 'Admin'],
			['name' => 'Staff'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
	
	public function down(): void
    {
        Schema::dropIfExists('roles');
    }

};