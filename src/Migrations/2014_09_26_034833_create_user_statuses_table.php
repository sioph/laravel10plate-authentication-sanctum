<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\UserStatus;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('user_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $userStatuses = [
            ['name' => 'Active'],
            ['name' => 'Inactive'],
        ];

        foreach ($userStatuses as $userStatus) {
            UserStatus::create($userStatus);
        }
    }
	
	public function down(): void
    {
        Schema::dropIfExists('user_statuses');
    }

};