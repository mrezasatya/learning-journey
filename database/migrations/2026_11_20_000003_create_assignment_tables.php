<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Brief Bagian 7.3 (percobaan & jawaban), 7.4, 7.5, 7.6 & 7.7. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            // {"logic":"AND","items":[{"field":"position_id","op":"in","values":[1,2]}]}
            $t->json('conditions');
            $t->date('effective_from');
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('program_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('program_version')->default(1);
            // Tahun periode untuk Wajib Tahunan; 0 untuk Onboarding/Wajib Sekali.
            // (Brief memakai NULL; 0 dipakai agar UNIQUE di database benar-benar mencegah duplikasi — BR-12.)
            $t->unsignedSmallInteger('period_year')->default(0);
            $t->string('source', 8); // RULE / MANUAL
            $t->foreignId('rule_id')->nullable()->constrained('assignment_rules')->nullOnDelete();
            $t->timestamp('assigned_at');
            $t->timestamp('due_at');
            $t->string('status', 16)->default('NOT_STARTED');
            // NOT_STARTED / IN_PROGRESS / COMPLETED / COMPLETED_LATE / OVERDUE / EXEMPTED (BR-37)
            $t->unsignedSmallInteger('cycle_no')->default(1); // naik saat reset materi (BR-24)
            $t->unsignedTinyInteger('pre_score')->nullable();
            $t->unsignedTinyInteger('final_score')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('completion_method', 8)->nullable(); // ONLINE / MANUAL
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['employee_id', 'program_id', 'period_year']);
            $t->index(['status', 'due_at']);
        });

        Schema::create('lesson_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('cycle_no')->default(1);
            $t->unsignedInteger('max_position_seconds')->default(0);   // video: posisi terjauh
            $t->unsignedInteger('last_position_seconds')->default(0);  // video: posisi terakhir (lanjutkan)
            $t->json('pages_viewed')->nullable(); // {"1": 31, "2": 45} = halaman => detik
            $t->unsignedInteger('time_spent_seconds')->default(0);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('last_heartbeat_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['assignment_id', 'lesson_id', 'cycle_no']);
        });

        Schema::create('assessment_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('attempt_no');
            $t->unsignedSmallInteger('cycle_no')->default(1);
            $t->timestamp('started_at');
            $t->timestamp('deadline_at'); // timer berbasis waktu server (BR-29)
            $t->timestamp('submitted_at')->nullable();
            $t->unsignedTinyInteger('score')->nullable();
            $t->boolean('passed')->nullable();
            $t->timestamps();
        });

        Schema::create('attempt_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $t->foreignId('question_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->json('option_order'); // urutan opsi yang diacak (BR-26)
            $t->json('selected_option_ids')->nullable();
            $t->boolean('is_correct')->nullable();
            $t->timestamps();
        });

        Schema::create('survey_responses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('template_id')->constrained('survey_templates');
            $t->json('answers');
            $t->timestamp('submitted_at');
            $t->timestamps();
        });

        Schema::create('exemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->string('type', 8); // EXTEND / EXEMPT
            $t->timestamp('old_due_at')->nullable();
            $t->timestamp('new_due_at')->nullable();
            $t->string('reason_category', 30); // CUTI_PANJANG / SAKIT / TUGAS_KHUSUS / MUTASI / LAINNYA
            $t->text('reason');
            $t->foreignId('evidence_file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('manual_completions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('evidence_file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->unsignedTinyInteger('score')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Brief menamai tabel ini `sessions`; nama itu sudah dipakai Laravel untuk sesi login,
        // sehingga di sini dinamai `training_sessions`.
        Schema::create('training_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->timestamp('start_at');
            $t->timestamp('end_at');
            $t->string('location');
            $t->unsignedSmallInteger('capacity')->default(30);
            $t->string('qr_token', 64)->unique();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('session_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('session_id')->constrained('training_sessions')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('attendance_status', 8)->default('INVITED'); // INVITED / PRESENT / ABSENT
            $t->timestamp('checkin_at')->nullable();
            $t->string('checkin_method', 8)->nullable(); // QR / MANUAL
            $t->string('correction_reason')->nullable();
            $t->unsignedTinyInteger('offline_score')->nullable(); // BR-44
            $t->timestamps();
            $t->unique(['session_id', 'employee_id']);
        });

        Schema::create('certificates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('certificate_no', 60)->unique();
            $t->timestamp('issued_at');
            $t->date('valid_until')->nullable(); // BR-36
            $t->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->string('verify_token', 64)->unique();
            $t->timestamps();
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 40);
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('url')->nullable();
            $t->string('channel', 8)->default('IN_APP'); // IN_APP / EMAIL
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });

        Schema::create('reminder_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->string('reminder_code', 12); // ASSIGNED, H-90 … H-1, OVERDUE
            $t->timestamp('sent_at');
            $t->timestamps();
            $t->unique(['assignment_id', 'reminder_code']); // mencegah pengingat ganda
        });

        Schema::create('disciplinary_followups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('status', 12)->default('RECOMMENDED'); // RECOMMENDED / VERIFIED / ISSUED / CANCELLED
            $t->string('letter_no')->nullable();
            $t->date('letter_date')->nullable();
            $t->foreignId('document_file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 60);
            $t->string('entity', 60)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('created_at')->useCurrent(); // append-only: tanpa updated_at
            $t->index(['entity', 'entity_id']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();
            $t->json('value');
            $t->timestamps();
        });

        // 7.7 — Persiapan fase kompetensi (dibuat kosong, belum dipakai)
        Schema::create('competencies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('type', 12); // CORE / LEADERSHIP / BEHAVIORAL / TECHNICAL
            $t->text('definition')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('competency_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('level');
            $t->text('behavioral_indicators')->nullable();
            $t->timestamps();
        });
        Schema::create('position_competency_requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('position_id')->constrained()->cascadeOnDelete();
            $t->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('required_level');
            $t->timestamps();
        });
        Schema::create('program_competencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained()->cascadeOnDelete();
            $t->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('target_level');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'program_competencies', 'position_competency_requirements', 'competency_levels', 'competencies',
            'settings', 'audit_logs', 'disciplinary_followups', 'reminder_logs', 'notifications', 'certificates',
            'session_participants', 'training_sessions', 'manual_completions', 'exemptions', 'survey_responses',
            'attempt_answers', 'assessment_attempts', 'lesson_progress', 'assignments', 'assignment_rules',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
