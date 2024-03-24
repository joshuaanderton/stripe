<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stripe_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')
                ->constrained()
                ->onCreate('cascade')
                ->onUpdate('cascade')
                ->cascadeOnDelete();
            $table->string('stripe_customer_id');
            $table->string('stripe_account_id')->nullable();
            $table->string('stripe_plan_id')->nullable();
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
        Schema::dropIfExists('stripe_profiles');
    }
};
