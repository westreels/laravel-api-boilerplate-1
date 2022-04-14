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
        Schema::dropIfExists(static::TABLE_NAME);
        Schema::create(static::TABLE_NAME, function (Blueprint $table) {
            $table->id('id');
            $table->string('provider');
            $table->string('price');
            $table->uuid('access_profile');
            $table->foreign('access_profile')->references('id')->on('access_profiles')->onDelete('cascade');

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
