@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')
<div class="page-head"><div><h1>Notifikasi</h1></div>
    <form method="post" action="{{ route('notifications.readAll') }}">@csrf<button class="btn btn-secondary btn-sm">Tandai semua dibaca</button></form></div>
<div class="card">
    @forelse ($items as $n)
        <a href="{{ route('notifications.open', $n) }}" style="display:block;text-decoration:none;color:inherit;padding:.75rem 0;border-bottom:1px solid var(--neutral-200)">
            <div class="row"><b style="{{ $n->read_at ? 'font-weight:500' : '' }}">@unless($n->read_at)<span class="badge badge-info">Baru</span> @endunless{{ $n->title }}</b>
                <small class="muted right">{{ $n->created_at->diffForHumans() }}</small></div>
            <small class="muted">{{ $n->body }}</small>
        </a>
    @empty
        <div class="empty">Belum ada notifikasi.</div>
    @endforelse
    {{ $items->links() }}
</div>
@endsection
