<?php

namespace Database\Seeders;

use App\Models\SurveyTemplate;
use Illuminate\Database\Seeder;

/** Template default survei reaksi (BR-31). */
class SurveyTemplateSeeder extends Seeder
{
    public function run(): void
    {
        SurveyTemplate::updateOrCreate(['name' => 'Survei Reaksi Standar'], [
            'is_default' => true,
            'items' => [
                ['code' => 'relevance', 'type' => 'LIKERT', 'text' => 'Materi relevan dengan pekerjaan saya.'],
                ['code' => 'clarity', 'type' => 'LIKERT', 'text' => 'Materi disampaikan dengan jelas dan mudah dipahami.'],
                ['code' => 'access', 'type' => 'LIKERT', 'text' => 'Platform mudah diakses dan digunakan.'],
                ['code' => 'duration', 'type' => 'LIKERT', 'text' => 'Durasi training sudah sesuai.'],
                ['code' => 'benefit', 'type' => 'LIKERT', 'text' => 'Training ini bermanfaat untuk saya.'],
                ['code' => 'comment', 'type' => 'TEXT', 'text' => 'Saran atau masukan untuk perbaikan training ini.'],
            ],
        ]);
    }
}
