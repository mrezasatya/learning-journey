@extends('layouts.app')
@section('title', 'Template Survei')
@section('content')
<div class="page-head"><div><h1>Template survei reaksi</h1><p class="muted">Kirkpatrick level 1 — skala Likert 1–5 + pertanyaan terbuka (BR-31).</p></div>
    <a class="btn" href="{{ route('admin.surveys.create') }}">+ Template baru</a></div>
<div class="card table-wrap"><table>
    <thead><tr><th>Nama</th><th>Butir</th><th class="num">Jawaban</th><th></th></tr></thead>
    <tbody>@foreach ($templates as $t)
        <tr><td>{{ $t->name }} @if($t->is_default)<span class="badge badge-info">Default</span>@endif</td>
            <td><ol style="margin:0;padding-left:1.1rem">@foreach($t->items as $i)<li>{{ $i['text'] }} <small class="muted">({{ $i['type']==='LIKERT'?'1–5':'terbuka' }})</small></li>@endforeach</ol></td>
            <td class="num">{{ $t->responses_count }}</td>
            <td>@if(!$t->responses_count)<a class="btn btn-sm btn-secondary" href="{{ route('admin.surveys.edit', $t) }}">Ubah</a>@endif</td></tr>
    @endforeach</tbody>
</table></div>
@endsection
