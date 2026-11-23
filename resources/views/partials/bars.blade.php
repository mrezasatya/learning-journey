{{-- Batang horizontal completion rate. $rows: [label, rate, total, done, overdue, param, id]; $target dalam %; $linkBase: query dasar --}}
<div class="legend" aria-hidden="true"><span><i></i>Completion rate</span><span><i class="target"></i>Target {{ $target }}%</span></div>
<div class="bars" role="table" aria-label="{{ $title ?? 'Completion rate' }}">
    @foreach ($rows as $r)
        @php $href = route('admin.assignments.index', array_filter(array_merge($linkBase, [$r['param'] => $r['id']]))); @endphp
        <a class="bar-row" role="row" href="{{ $href }}" title="{{ $r['label'] }}: {{ $r['done'] }} dari {{ $r['total'] }} selesai ({{ $r['rate'] }}%), {{ $r['overdue'] }} overdue" style="text-decoration:none;color:inherit">
            <span role="cell" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r['label'] }}</span>
            <span role="cell" class="bar-track"><span class="bar-fill" style="display:block;width:{{ $r['rate'] }}%"></span><span class="bar-target" style="left:{{ $target }}%"></span></span>
            <span role="cell" class="val">{{ rtrim(rtrim(number_format($r['rate'], 1, ',', ''), '0'), ',') }}%</span>
        </a>
    @endforeach
</div>
