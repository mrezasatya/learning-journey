<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Database\Seeder;

/**
 * Empat program training wajib contoh beserta modul, materi teks, dan bank soal.
 * Materi video/PDF ditambahkan oleh DemoMediaSeeder (Tahap 4) bila FFmpeg/Poppler tersedia.
 * Isi materi bersifat ringkasan umum untuk demo — bukan materi resmi perusahaan.
 */
class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->programs() as $data) {
            $program = Program::updateOrCreate(['code' => $data['code'], 'version' => 1], [
                'name' => $data['name'],
                'description' => $data['description'],
                'type' => $data['type'],
                'category' => $data['category'],
                'deadline_days' => $data['deadline_days'] ?? null,
                'sequential' => true,
                'is_blended' => $data['blended'] ?? false,
                'status' => 'PUBLISHED',
                'published_at' => now(),
            ]);

            if ($program->modules()->exists()) {
                continue; // idempoten
            }

            foreach ($data['modules'] as $mi => [$title, $lessons]) {
                $module = $program->modules()->create(['title' => $title, 'sort_order' => $mi + 1]);
                foreach ($lessons as $li => [$ltitle, $body, $minSeconds]) {
                    $module->lessons()->create([
                        'title' => $ltitle, 'type' => 'TEXT', 'body' => $body,
                        'min_seconds' => $minSeconds, 'sort_order' => $li + 1,
                    ]);
                }
            }

            $bank = QuestionBank::create(['program_id' => $program->id, 'name' => 'Bank Soal '.$program->code]);
            foreach ($data['questions'] as [$type, $diff, $text, $options, $correct]) {
                $q = Question::create(['bank_id' => $bank->id, 'type' => $type, 'difficulty' => $diff, 'text' => $text]);
                foreach ($options as $oi => $opt) {
                    $q->options()->create(['text' => $opt, 'is_correct' => in_array($oi, (array) $correct, true), 'sort_order' => $oi]);
                }
            }

            $count = min(10, count($data['questions']));
            foreach (['PRE', 'POST'] as $kind) {
                Assessment::create([
                    'program_id' => $program->id, 'kind' => $kind,
                    'question_count' => $count, 'duration_minutes' => 20,
                    'passing_score' => 80, 'max_attempts' => 3, 'retry_cooldown_minutes' => 60,
                    'difficulty_mix' => ['EASY' => 30, 'MEDIUM' => 50, 'HARD' => 20],
                ]);
            }
        }
    }

    private function programs(): array
    {
        $tf = ['Benar', 'Salah'];

        return [
            [
                'code' => 'ONB-01', 'name' => 'Onboarding Karyawan Baru', 'type' => 'ONBOARDING', 'category' => 'ONBOARDING',
                'deadline_days' => 90,
                'description' => 'Pengenalan perusahaan, peraturan, dan budaya kerja untuk karyawan baru.',
                'modules' => [
                    ['Mengenal Perusahaan', [
                        ['Sejarah, Visi & Misi', '<h2>Selamat bergabung!</h2><p>Modul ini memperkenalkan perjalanan perusahaan, visi, dan misi yang menjadi arah kerja seluruh karyawan.</p><ul><li><b>Visi</b> — menjadi perusahaan rekayasa dan manufaktur terkemuka.</li><li><b>Misi</b> — menghasilkan produk dan layanan berkualitas, tepat waktu, dan aman.</li></ul><p>Pahami bagaimana pekerjaan Anda berkontribusi pada visi tersebut.</p>', 30],
                        ['Struktur Organisasi', '<p>Perusahaan terdiri dari beberapa divisi: Human Capital & GA, Operasi & Manufaktur, Proyek & Konstruksi, serta Keuangan.</p><p>Setiap karyawan memiliki <b>atasan langsung</b> yang menjadi rujukan pertama untuk pertanyaan pekerjaan, cuti, dan pengembangan.</p>', 30],
                    ]],
                    ['Peraturan & Budaya Kerja', [
                        ['Peraturan Perusahaan', '<p>Peraturan Perusahaan (PP) mengatur hak dan kewajiban karyawan: jam kerja, cuti, disiplin, dan sanksi.</p><p>Training wajib termasuk kewajiban karyawan. Keterlambatan menyelesaikan training wajib dapat ditindaklanjuti sesuai ketentuan yang berlaku.</p>', 30],
                        ['Nilai & Budaya Kerja', '<p>Nilai utama: <b>Integritas</b>, <b>Keselamatan</b>, <b>Kualitas</b>, <b>Kerja sama</b>.</p><p>Keselamatan adalah tanggung jawab semua orang — setiap karyawan berhak dan wajib menghentikan pekerjaan yang tidak aman (<i>stop work authority</i>).</p>', 30],
                    ]],
                ],
                'questions' => [
                    ['SINGLE', 'EASY', 'Siapa rujukan pertama karyawan untuk pertanyaan terkait pekerjaan sehari-hari?', ['Atasan langsung', 'Direktur utama', 'Rekan kerja divisi lain', 'Petugas keamanan'], 0],
                    ['TRUE_FALSE', 'EASY', 'Training wajib merupakan salah satu kewajiban karyawan.', $tf, 0],
                    ['SINGLE', 'MEDIUM', 'Dokumen yang mengatur hak dan kewajiban karyawan secara umum adalah…', ['Peraturan Perusahaan', 'Brosur produk', 'Laporan keuangan', 'Kontrak vendor'], 0],
                    ['MULTI', 'MEDIUM', 'Manakah yang termasuk nilai utama perusahaan? (pilih semua yang benar)', ['Integritas', 'Keselamatan', 'Kecepatan tanpa prosedur', 'Kerja sama'], [0, 1, 3]],
                    ['TRUE_FALSE', 'MEDIUM', 'Hanya petugas HSE yang boleh menghentikan pekerjaan yang tidak aman.', $tf, 1],
                    ['SINGLE', 'EASY', 'Divisi yang mengelola pengembangan karyawan adalah…', ['Human Capital & GA', 'Keuangan', 'Proyek & Konstruksi', 'Operasi & Manufaktur'], 0],
                    ['SINGLE', 'HARD', 'Apa yang dimaksud dengan stop work authority?', ['Hak & kewajiban setiap karyawan menghentikan pekerjaan yang tidak aman', 'Wewenang atasan memberhentikan karyawan', 'Izin lembur', 'Prosedur cuti'], 0],
                    ['TRUE_FALSE', 'EASY', 'Visi perusahaan menjadi arah kerja seluruh karyawan.', $tf, 0],
                    ['SINGLE', 'MEDIUM', 'Keterlambatan menyelesaikan training wajib dapat…', ['Ditindaklanjuti sesuai ketentuan yang berlaku', 'Diabaikan', 'Otomatis dianggap lulus', 'Menghapus akun karyawan'], 0],
                    ['MULTI', 'HARD', 'Hal yang diatur dalam Peraturan Perusahaan antara lain… (pilih semua yang benar)', ['Jam kerja', 'Cuti', 'Harga saham', 'Disiplin & sanksi'], [0, 1, 3]],
                    ['SINGLE', 'MEDIUM', 'Misi perusahaan menekankan produk dan layanan yang…', ['Berkualitas, tepat waktu, dan aman', 'Murah tanpa standar', 'Cepat tanpa pengecekan', 'Hanya untuk pasar ekspor'], 0],
                    ['TRUE_FALSE', 'MEDIUM', 'Setiap karyawan memiliki atasan langsung.', $tf, 0],
                ],
            ],
            [
                'code' => 'K3-01', 'name' => 'Induksi K3 Dasar', 'type' => 'ANNUAL', 'category' => 'K3',
                'description' => 'Penyegaran tahunan Keselamatan & Kesehatan Kerja: identifikasi bahaya, APD, dan tanggap darurat.',
                'modules' => [
                    ['Bahaya & Risiko', [
                        ['Identifikasi Bahaya (HIRADC)', '<p><b>Bahaya</b> adalah sumber potensi cedera atau sakit; <b>risiko</b> adalah kombinasi kemungkinan dan keparahan.</p><p>Gunakan pendekatan HIRADC: <i>Hazard Identification, Risk Assessment, and Determining Control</i>.</p><p>Hierarki pengendalian: <b>Eliminasi → Substitusi → Rekayasa teknik → Administratif → APD</b>.</p>', 45],
                        ['Alat Pelindung Diri (APD)', '<p>APD adalah lapisan pengendalian <b>terakhir</b>. Contoh: helm, sepatu keselamatan, kacamata, sarung tangan, full body harness untuk kerja di ketinggian ≥ 1,8 m.</p><p>Periksa kondisi APD sebelum dipakai; laporkan APD rusak.</p>', 45],
                    ]],
                    ['Tanggap Darurat', [
                        ['Kebakaran & APAR', '<p>Teknik penggunaan APAR: <b>PASS</b> — <i>Pull</i> (tarik pin), <i>Aim</i> (arahkan ke pangkal api), <i>Squeeze</i> (tekan tuas), <i>Sweep</i> (sapukan).</p><p>Kenali jalur evakuasi dan titik kumpul di area kerja Anda.</p>', 45],
                        ['Pelaporan Insiden', '<p>Laporkan setiap insiden dan <i>near miss</i> segera ke atasan dan HSE. Near miss adalah kejadian yang hampir menimbulkan cedera — pelaporannya membantu mencegah kecelakaan.</p>', 30],
                    ]],
                ],
                'questions' => [
                    ['SINGLE', 'EASY', 'Urutan hierarki pengendalian risiko yang benar adalah…', ['Eliminasi, substitusi, rekayasa teknik, administratif, APD', 'APD, administratif, rekayasa teknik, substitusi, eliminasi', 'Substitusi, APD, eliminasi, administratif, rekayasa', 'Administratif, APD, eliminasi, substitusi, rekayasa'], 0],
                    ['TRUE_FALSE', 'EASY', 'APD adalah lapisan pengendalian risiko yang terakhir.', $tf, 0],
                    ['SINGLE', 'MEDIUM', 'Arti huruf "A" pada teknik PASS penggunaan APAR adalah…', ['Arahkan ke pangkal api', 'Angkat tabung', 'Amankan area', 'Aktifkan alarm'], 0],
                    ['SINGLE', 'MEDIUM', 'Full body harness wajib digunakan untuk kerja di ketinggian minimal…', ['1,8 meter', '5 meter', '10 meter', '0,5 meter'], 0],
                    ['MULTI', 'MEDIUM', 'Yang termasuk APD adalah… (pilih semua yang benar)', ['Helm keselamatan', 'Sepatu keselamatan', 'Rambu peringatan', 'Kacamata pelindung'], [0, 1, 3]],
                    ['TRUE_FALSE', 'MEDIUM', 'Near miss tidak perlu dilaporkan karena tidak ada yang cedera.', $tf, 1],
                    ['SINGLE', 'HARD', 'Risiko didefinisikan sebagai kombinasi dari…', ['Kemungkinan dan keparahan', 'Biaya dan waktu', 'Jumlah pekerja dan alat', 'Lokasi dan cuaca'], 0],
                    ['SINGLE', 'EASY', 'Apa yang harus dilakukan bila APD rusak?', ['Laporkan dan ganti sebelum bekerja', 'Tetap dipakai', 'Disimpan saja', 'Dipinjamkan ke rekan'], 0],
                    ['SINGLE', 'HARD', 'Mengganti bahan kimia berbahaya dengan bahan yang lebih aman termasuk pengendalian…', ['Substitusi', 'Eliminasi', 'Administratif', 'APD'], 0],
                    ['MULTI', 'HARD', 'Langkah teknik PASS meliputi… (pilih semua yang benar)', ['Tarik pin', 'Tekan tuas', 'Sapukan', 'Kocok tabung terus-menerus'], [0, 1, 2]],
                    ['TRUE_FALSE', 'EASY', 'Setiap karyawan perlu mengetahui jalur evakuasi dan titik kumpul di area kerjanya.', $tf, 0],
                    ['SINGLE', 'MEDIUM', 'HIRADC adalah singkatan dari…', ['Hazard Identification, Risk Assessment, and Determining Control', 'Health Inspection, Risk Audit, and Data Collection', 'Hazard Inventory, Response, and Damage Control', 'High Risk Area Data Control'], 0],
                ],
            ],
            [
                'code' => 'ISO-01', 'name' => 'Kesadaran Sistem Manajemen Mutu ISO 9001:2015', 'type' => 'ANNUAL', 'category' => 'ISO',
                'description' => 'Pemahaman dasar ISO 9001:2015: prinsip mutu, pendekatan proses, dan peran karyawan.',
                'modules' => [
                    ['Dasar ISO 9001', [
                        ['Prinsip Manajemen Mutu', '<p>ISO 9001:2015 dibangun di atas 7 prinsip: fokus pelanggan, kepemimpinan, keterlibatan orang, pendekatan proses, perbaikan, pengambilan keputusan berbasis bukti, dan manajemen hubungan.</p>', 45],
                        ['Siklus PDCA', '<p>PDCA: <b>Plan</b> (rencanakan) – <b>Do</b> (laksanakan) – <b>Check</b> (periksa) – <b>Act</b> (tindak lanjuti). Siklus ini menjadi dasar perbaikan berkelanjutan.</p>', 30],
                    ]],
                    ['Peran Karyawan', [
                        ['Dokumen & Rekaman', '<p>Ikuti prosedur/instruksi kerja versi terbaru. Rekaman (form, checklist) adalah bukti pekerjaan dilakukan sesuai standar — isi dengan lengkap dan jujur.</p><p>Temuan ketidaksesuaian (<i>nonconformity</i>) harus dilaporkan dan ditindaklanjuti dengan tindakan korektif.</p>', 45],
                    ]],
                ],
                'questions' => [
                    ['SINGLE', 'EASY', 'PDCA adalah singkatan dari…', ['Plan, Do, Check, Act', 'Prepare, Deliver, Control, Audit', 'Plan, Design, Check, Approve', 'Process, Do, Correct, Act'], 0],
                    ['TRUE_FALSE', 'EASY', 'Fokus pelanggan adalah salah satu prinsip manajemen mutu.', $tf, 0],
                    ['SINGLE', 'MEDIUM', 'Jumlah prinsip manajemen mutu dalam ISO 9001:2015 adalah…', ['7', '5', '10', '3'], 0],
                    ['MULTI', 'MEDIUM', 'Manakah yang termasuk prinsip manajemen mutu? (pilih semua yang benar)', ['Kepemimpinan', 'Pendekatan proses', 'Keputusan berbasis bukti', 'Keputusan berbasis senioritas'], [0, 1, 2]],
                    ['TRUE_FALSE', 'MEDIUM', 'Boleh memakai instruksi kerja versi lama selama masih tersimpan.', $tf, 1],
                    ['SINGLE', 'HARD', 'Apa yang harus dilakukan bila menemukan ketidaksesuaian?', ['Melaporkan dan menindaklanjuti dengan tindakan korektif', 'Menyembunyikan agar tidak menjadi temuan audit', 'Menunggu audit eksternal', 'Mengabaikan bila kecil'], 0],
                    ['SINGLE', 'EASY', 'Rekaman mutu berfungsi sebagai…', ['Bukti pekerjaan dilakukan sesuai standar', 'Dekorasi kantor', 'Pengganti prosedur', 'Arsip pribadi'], 0],
                    ['SINGLE', 'MEDIUM', 'Tahap "Check" dalam PDCA berarti…', ['Memeriksa hasil terhadap rencana', 'Membuat rencana', 'Melaksanakan pekerjaan', 'Menutup proyek'], 0],
                    ['TRUE_FALSE', 'HARD', 'Perbaikan berkelanjutan hanya tanggung jawab bagian QA.', $tf, 1],
                    ['MULTI', 'HARD', 'Contoh rekaman mutu adalah… (pilih semua yang benar)', ['Checklist inspeksi', 'Form hasil uji', 'Daftar hadir training', 'Poster motivasi'], [0, 1, 2]],
                    ['SINGLE', 'MEDIUM', 'Pendekatan proses berarti…', ['Mengelola aktivitas sebagai proses yang saling terkait', 'Bekerja tanpa prosedur', 'Fokus hanya pada hasil akhir', 'Menghapus dokumen'], 0],
                ],
            ],
            [
                'code' => 'K3-02', 'name' => 'Praktik APAR & Simulasi Evakuasi', 'type' => 'ANNUAL', 'category' => 'K3', 'blended' => true,
                'description' => 'Program blended: materi online singkat + sesi praktik tatap muka penggunaan APAR dan simulasi evakuasi.',
                'modules' => [
                    ['Persiapan Praktik', [
                        ['Jenis APAR & Kelas Kebakaran', '<p>Kelas kebakaran: <b>A</b> (benda padat), <b>B</b> (cairan mudah terbakar), <b>C</b> (listrik), <b>D</b> (logam). Pilih jenis APAR sesuai kelas kebakaran.</p><p>Sesi praktik dilakukan tatap muka bersama tim HSE.</p>', 30],
                    ]],
                ],
                'questions' => [
                    ['SINGLE', 'EASY', 'Kebakaran kelas C disebabkan oleh…', ['Listrik', 'Kayu', 'Bensin', 'Logam'], 0],
                    ['SINGLE', 'MEDIUM', 'Kebakaran kelas B melibatkan…', ['Cairan mudah terbakar', 'Kertas', 'Logam', 'Listrik'], 0],
                    ['TRUE_FALSE', 'EASY', 'Jenis APAR harus disesuaikan dengan kelas kebakaran.', $tf, 0],
                    ['SINGLE', 'HARD', 'Kebakaran kelas D melibatkan…', ['Logam', 'Kain', 'Solar', 'Panel listrik'], 0],
                    ['TRUE_FALSE', 'MEDIUM', 'Air aman digunakan untuk memadamkan kebakaran listrik bertegangan.', $tf, 1],
                    ['SINGLE', 'EASY', 'Kebakaran kelas A disebabkan oleh…', ['Benda padat seperti kayu & kertas', 'Gas', 'Listrik', 'Logam'], 0],
                ],
            ],
        ];
    }
}
