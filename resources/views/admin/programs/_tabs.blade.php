<div class="page-head">
    <div>
        <h1>{{ $program->name }}</h1>
        <p class="muted">{{ $program->code }} · v{{ $program->version }} · {{ $program->typeLabel() }} · {{ $program->categoryLabel() }}
            · <span class="badge {{ ['DRAFT'=>'badge-warning','PUBLISHED'=>'badge-success','ARCHIVED'=>'badge-neutral'][$program->status] }}">{{ $program->statusLabel() }}</span>
            @if($program->is_blended)<span class="badge badge-info">Blended</span>@endif</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('admin.programs.index') }}">← Semua program</a>
</div>
<nav class="tabs">
    <a href="{{ route('admin.programs.show', $program) }}" class="{{ request()->routeIs('admin.programs.show') ? 'active' : '' }}">Konten &amp; Tes</a>
    <a href="{{ route('admin.questions.index', $program) }}" class="{{ request()->routeIs('admin.questions.*') ? 'active' : '' }}">Bank Soal</a>
    @if (Route::has('admin.rules.index'))<a href="{{ route('admin.rules.index', ['program_id' => $program->id]) }}">Aturan Penugasan</a>@endif
    @if (Route::has('admin.assignments.index'))<a href="{{ route('admin.assignments.index', ['program_id' => $program->id]) }}">Peserta</a>@endif
    @if (Route::has('admin.reports.evaluation'))<a href="{{ route('admin.reports.evaluation', $program) }}">Evaluasi L1/L2</a>@endif
</nav>
