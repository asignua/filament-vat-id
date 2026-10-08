<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('country', 2);
            $table->string('type')->default('eu_vat');
            $table->string('tax_id')->nullable();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('regon')->nullable();
            $table->string('iban')->nullable();
            $table->timestamps();
        });
    }
};
