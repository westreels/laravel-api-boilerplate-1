<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProvidersTable extends Migration
{
    const TABLE_NAME = 'access_providers';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(static::TABLE_NAME, function (Blueprint $table) {
            $table->uuid('uuid')->unique();
            $table->string('provider');
            $table->foreign('provider')->references('provider')->on('providers')->onDelete('cascade');
            $table->uuid('profile_id');
            $table->foreign('profile_id')->references('id')->on('access_profiles')->onDelete('cascade');
            $table->string('price');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(static::TABLE_NAME);
    }
}
