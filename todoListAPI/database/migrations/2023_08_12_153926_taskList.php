<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('taskList', function (Blueprint $table) {
            $table->string("userToken")->default("");
            $table->integer("taskId", true);
            $table->string('status')->default("");
            $table->tinyInteger("priority")->default(0);
            $table->string('title')->default("");
            $table->string('description')->default("");
            $table->integer('createdAt')->default(0);
            $table->integer('completedAt')->default(0);
            $table->integer("parrentlyTaskId")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
