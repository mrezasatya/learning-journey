<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Brief Bagian 7.2 & 7.3 — Program, Konten, Bank Soal, Tes, Survei. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $t) {
            $t->id();
            $t->string('disk', 20)->default('local');
            $t->string('storage_key'); // path di disk (bukan URL publik)
            $t->string('original_name');
            $t->string('mime_type', 120)->nullable();
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->string('checksum', 64)->nullable();
            $t->string('processing_status', 20)->default('READY'); // PENDING / PROCESSING / READY / FAILED
            $t->text('processing_error')->nullable();
            $t->json('renditions')->nullable(); // {"360p": "key", ...} atau {"pages": ["key",...]}
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->unsignedInteger('page_count')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('survey_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('items'); // [{"code":"relevance","text":"...","type":"LIKERT"}, {"type":"TEXT"}]
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('programs', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30);
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('type', 15);      // ONBOARDING / ONCE / ANNUAL  (BR-06)
            $t->string('category', 15);  // ISO / K3 / ONBOARDING / OTHER
            $t->unsignedSmallInteger('deadline_days')->nullable(); // BR-15, maks 365
            $t->boolean('sequential')->default(true);  // BR-18
            $t->boolean('is_blended')->default(false); // BR-45
            $t->string('status', 12)->default('DRAFT'); // DRAFT / PUBLISHED / ARCHIVED (BR-08)
            $t->unsignedSmallInteger('version')->default(1);
            $t->foreignId('parent_program_id')->nullable()->constrained('programs')->nullOnDelete();
            $t->foreignId('thumbnail_file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->foreignId('survey_template_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['code', 'version']);
        });

        Schema::create('modules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('module_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('type', 10); // VIDEO / PDF / SLIDE / TEXT / IMAGE
            $t->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->longText('body')->nullable();
            // VIDEO: tidak dipakai; PDF/SLIDE: detik minimum PER HALAMAN; TEXT/IMAGE: detik minimum total
            $t->unsignedSmallInteger('min_seconds')->nullable();
            $t->decimal('completion_threshold', 3, 2)->default(0.90); // video
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('question_banks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bank_id')->constrained('question_banks')->cascadeOnDelete();
            $t->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 12); // SINGLE / MULTI / TRUE_FALSE (BR-27)
            $t->text('text');
            $t->string('difficulty', 8)->default('MEDIUM'); // EASY / MEDIUM / HARD
            $t->text('explanation')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('question_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_id')->constrained()->cascadeOnDelete();
            $t->text('text');
            $t->boolean('is_correct')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 4); // PRE / POST
            $t->unsignedSmallInteger('question_count')->default(10);
            $t->unsignedSmallInteger('duration_minutes')->default(20);
            $t->unsignedTinyInteger('passing_score')->default(80);
            $t->unsignedTinyInteger('max_attempts')->default(3);
            $t->unsignedSmallInteger('retry_cooldown_minutes')->default(60);
            $t->json('difficulty_mix')->nullable(); // {"EASY":30,"MEDIUM":50,"HARD":20} dalam persen
            $t->boolean('show_explanation')->default(false);
            $t->timestamps();
            $t->unique(['program_id', 'kind']);
        });
    }

    public function down(): void
    {
        foreach (['assessments', 'question_options', 'questions', 'question_banks', 'lessons', 'modules', 'programs', 'survey_templates', 'files'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
