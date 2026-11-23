<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Brief Bagian 7.1 — Organisasi, Karyawan, Akun & Peran. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('positions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('level', 40)->nullable(); // Staf / Senior Staf / Section Head / Dept. Head / Division Head
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('grades', function (Blueprint $t) {
            $t->id();
            $t->string('code', 5)->unique(); // 1A–7B
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('work_locations', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('type', 10); // HO / PLANT / SITE
            $t->string('region')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('nik', 30)->unique();
            $t->string('full_name');
            $t->string('email')->nullable();
            $t->string('phone', 30)->nullable();
            $t->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $t->string('supervisor_nik', 30)->nullable()->index();
            $t->string('employment_status', 10); // TETAP / KONTRAK / PROYEK
            $t->date('join_date');
            $t->date('end_date')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('external_ref')->nullable(); // ID di ESS
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('username', 50)->unique(); // = NIK
            $t->string('name'); // nama tampilan (untuk akun sistem tanpa employee)
            $t->string('password');
            $t->boolean('must_change_password')->default(true);
            $t->unsignedSmallInteger('failed_attempts')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->string('auth_provider', 20)->default('local'); // local / sso
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique(); // SUPER_ADMIN, ADMIN_LD, SUPERVISOR, EMPLOYEE
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        foreach (['user_roles', 'roles', 'users', 'employees', 'work_locations', 'grades', 'positions', 'departments', 'divisions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
