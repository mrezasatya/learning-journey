@php
    $flow = app(\App\Services\LearningFlow::class);
    $lessons = $flow->lessons($a);
@endphp
<div class="grid grid-2">
    <div class="card">
        <h2>Progres materi (siklus {{ $a->cycle_no }})</h2>
        <table><tbody>
        @foreach ($lessons as $x)
            <tr><td>{{ $x['lesson']->icon() }} {{ $x['lesson']->title }}</td>
                <td>@if($x['done'])<span class="badge badge-success">Selesai</span>@elseif($x['progress'])<span class="badge badge-info">Dibuka</span>@else<span class="badge badge-neutral">Belum</span>@endif</td>
                <td><small>@if($x['progress']){{ gmdate('H:i:s', $x['progress']->time_spent_seconds) }}@endif</small></td></tr>
        @endforeach
        </tbody></table>
    </div>
    <div class="card">
        <h2>Percobaan tes</h2>
        <table><thead><tr><th>Tes</th><th>Siklus</th><th>#</th><th>Waktu</th><th class="num">Nilai</th></tr></thead><tbody>
        @forelse ($a->attempts as $t)
            <tr><td>{{ $t->assessment->label() }}</td><td>{{ $t->cycle_no }}</td><td>{{ $t->attempt_no }}</td>
                <td>{{ $t->started_at->format('d/m/Y H:i') }}</td>
                <td class="num">{{ $t->score ?? 'berjalan' }} @if($t->assessment->kind==='POST' && $t->submitted_at)<span class="badge {{ $t->passed ? 'badge-success':'badge-danger' }}">{{ $t->passed ? 'Lulus':'Tidak' }}</span>@endif</td></tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada.</td></tr>
        @endforelse
        </tbody></table>
        @if ($a->surveyResponse)<p style="margin-top:.75rem"><small>Survei diisi {{ $a->surveyResponse->submitted_at->format('d/m/Y H:i') }}</small></p>@endif
    </div>
</div>
