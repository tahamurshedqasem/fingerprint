// database/migrations/2024_01_01_create_contracts_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->unique();
            $table->text('terms');
            $table->string('user_name');
            $table->string('user_phone');
            $table->decimal('discount_percentage', 5, 2)->default(0); // نسبة الخصم
            $table->text('signature_hash')->nullable();
            $table->text('signature_data')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('qr_code')->nullable();
            $table->boolean('is_signed')->default(false);
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contracts');
    }
};