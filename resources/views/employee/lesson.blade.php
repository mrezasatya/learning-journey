@extends('layouts.app')
@section('title', $lesson->title)
@section('content')
@php
    $file = $lesson->file;
    $progressUrl = route('learn.progress', [$a, $lesson]);
    $done = (bool) $progress?->completed_at;
@endphp
<div class="page-head">
    <div><small class="muted"><a href="{{ route('learn.program', $a) }}">{{ $a->program->name }}</a> › {{ $lesson->module->title }} · Materi {{ $idx + 1 }} dari {{ $list->count() }}</small>
        <h1>{{ $lesson->title }}</h1></div>
    <span id="done-badge" class="badge badge-success {{ $done ? '' : 'hidden' }}">✓ Selesai</span>
</div>

<div class="card player">
@if ($file && ! $file->isReady())
    <div class="empty">Materi sedang disiapkan. Silakan coba beberapa saat lagi.</div>
@elseif ($lesson->type === 'VIDEO')
    @php $variants = $file->videoVariants(); @endphp
    <video id="video" controls playsinline controlslist="nodownload noplaybackrate" disablepictureinpicture preload="metadata"></video>
    <div class="row" style="margin-top:.5rem">
        <label for="quality" style="margin:0">Kualitas</label>
        <select id="quality" style="width:auto;min-height:36px">
            <option value="auto">Otomatis</option>
            @foreach ($variants as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
        </select>
        <small class="muted right" id="video-hint">Tonton minimal {{ round($lesson->completion_threshold * 100) }}% untuk menyelesaikan. Video tidak dapat dipercepat melewati bagian yang belum ditonton.</small>
    </div>
    <div class="progress" style="margin-top:.5rem" aria-label="Bagian yang sudah ditonton"><span id="watched" style="width:0%"></span></div>
@elseif (in_array($lesson->type, ['PDF', 'SLIDE']))
    @php $pageCount = (int) $file->page_count; $viewed = array_map('intval', array_keys($progress?->pages_viewed ?? [])); @endphp
    <div class="row" style="margin-bottom:.5rem">
        <button class="btn btn-secondary btn-sm" id="pg-prev">‹ Sebelumnya</button>
        <b id="pg-label">Halaman 1 / {{ $pageCount }}</b>
        <button class="btn btn-sm" id="pg-next">Berikutnya ›</button>
        <small class="muted right" id="pg-timer"></small>
    </div>
    <img id="pg-img" class="doc-page" alt="Halaman dokumen">
    <div class="row" style="margin-top:.5rem"><small class="muted">Halaman dibaca: <span id="pg-count">{{ count($viewed) }}</span>/{{ $pageCount }} · minimal {{ $lesson->effectiveMinSeconds() }} detik per halaman</small>
        <a class="right" href="{{ $file->signedUrl('original') }}" target="_blank" rel="noopener">Buka berkas asli</a></div>
@else
    @if ($lesson->type === 'IMAGE' && $file)<img src="{{ $file->signedUrl() }}" alt="{{ $lesson->title }}" style="border-radius:8px;margin-bottom:1rem">@endif
    <div class="rich-text">{!! $lesson->body !!}</div>
    @if ($tracking && ! $done)
        @php $wait = max(0, $lesson->effectiveMinSeconds() - (int) now()->diffInSeconds($progress->started_at, true)); @endphp
        <button class="btn" id="mark-done" style="margin-top:1rem" disabled data-wait="{{ $wait }}">Tandai selesai</button>
    @endif
@endif
</div>

<div class="row">
    @if ($prev)<a class="btn btn-secondary" href="{{ route('learn.lesson', [$a, $prev['lesson']]) }}">‹ {{ \Illuminate\Support\Str::limit($prev['lesson']->title, 28) }}</a>@endif
    <span class="right"></span>
    <a class="btn {{ $done ? '' : 'disabled' }}" id="next-btn" href="{{ $next && ($done || ! $a->program->sequential) ? route('learn.lesson', [$a, $next['lesson']]) : route('learn.program', $a) }}"
       @unless($done) aria-disabled="true" @endunless>{{ $next ? 'Materi berikutnya ›' : 'Kembali ke program' }}</a>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var tracking = @json($tracking), progressUrl = @json($progressUrl);
  var nextBtn = document.getElementById('next-btn');
  function markDone(nextUrl) {
    document.getElementById('done-badge').classList.remove('hidden');
    nextBtn.classList.remove('disabled'); nextBtn.removeAttribute('aria-disabled');
    @if ($next) nextBtn.href = @json(route('learn.lesson', [$a, $next['lesson'] ?? $lesson])); @else if (nextUrl) nextBtn.href = nextUrl; @endif
  }
  nextBtn.addEventListener('click', function (e) { if (nextBtn.classList.contains('disabled')) { e.preventDefault(); alert('Selesaikan materi ini terlebih dahulu.'); } });

@if ($lesson->type === 'VIDEO' && $file?->isReady())
  // ---------------- VIDEO (BR-19: ≥ threshold, tidak bisa seek melewati posisi terjauh) ----------------
  var v = document.getElementById('video'), q = document.getElementById('quality');
  var sources = @json(collect($file->videoVariants())->mapWithKeys(fn ($k) => [$k => $file->signedUrl($k)]));
  var duration = {{ (int) $file->duration_seconds }}, maxPos = {{ (int) ($progress?->max_position_seconds ?? 0) }};
  var resumeAt = {{ (int) ($progress?->last_position_seconds ?? 0) }}, threshold = {{ (float) $lesson->completion_threshold }};
  var completed = @json($done), sending = false, lastSent = 0;
  function pickAuto() {
    var keys = Object.keys(sources), dl = navigator.connection && navigator.connection.downlink;
    var want = !dl ? '480p' : dl < 1.5 ? '360p' : dl < 4 ? '480p' : '720p';
    return keys.indexOf(want) >= 0 ? want : keys[0];
  }
  function load(variant, at, play) {
    v.src = sources[variant]; v.load();
    v.addEventListener('loadedmetadata', function once() { v.removeEventListener('loadedmetadata', once); v.currentTime = Math.min(at, maxPos); if (play) v.play(); });
  }
  try { var saved = localStorage.getItem('blj-quality'); if (saved && (saved === 'auto' || sources[saved])) q.value = saved; } catch (e) {}
  load(q.value === 'auto' ? pickAuto() : q.value, resumeAt, false);
  q.addEventListener('change', function () { try { localStorage.setItem('blj-quality', q.value); } catch (e) {} load(q.value === 'auto' ? pickAuto() : q.value, v.currentTime, !v.paused); });
  v.addEventListener('ratechange', function () { if (v.playbackRate > 1) v.playbackRate = 1; });
  v.addEventListener('timeupdate', function () {
    if (!v.seeking && v.currentTime <= maxPos + 2) maxPos = Math.max(maxPos, v.currentTime);
    document.getElementById('watched').style.width = Math.min(100, maxPos / Math.max(1, duration) * 100) + '%';
    if (tracking && !v.paused && Date.now() - lastSent > 10000) send(); // progres tersimpan ≤ 10 detik (US-16 AC2)
  });
  v.addEventListener('seeking', function () {
    if (v.currentTime > maxPos + 1) { v.currentTime = maxPos; document.getElementById('video-hint').textContent = 'Anda belum menonton bagian tersebut.'; }
  });
  ['pause', 'ended'].forEach(function (ev) { v.addEventListener(ev, function () { if (tracking) send(); }); });
  document.addEventListener('visibilitychange', function () { if (document.hidden && tracking) send(); });
  function send() {
    if (sending) return; sending = true; lastSent = Date.now();
    bljPost(progressUrl, { position: v.currentTime }).then(function (r) {
      sending = false;
      if (r.completed && !completed) { completed = true; markDone(r.next_url); }
    }).catch(function () { sending = false; });
  }
  if (tracking) bljPost(progressUrl, { position: Math.min(resumeAt, maxPos) });
  if (completed) markDone();
@elseif (in_array($lesson->type, ['PDF', 'SLIDE']) && $file?->isReady())
  // ---------------- DOKUMEN (BR-19: semua halaman + waktu baca minimum per halaman) ----------------
  var pages = @json(collect(range(1, max(1, (int) $file->page_count)))->map(fn ($i) => $file->signedUrl('page-'.$i))), total = pages.length;
  var viewed = new Set(@json($viewed)), minSec = {{ $lesson->effectiveMinSeconds() }}, cur = 1, timer = null, left = 0;
  var img = document.getElementById('pg-img'), nextP = document.getElementById('pg-next'), prevP = document.getElementById('pg-prev'), lbl = document.getElementById('pg-timer');
  function show(p) {
    cur = p; img.src = pages[p - 1]; if (pages[p]) (new Image()).src = pages[p];
    document.getElementById('pg-label').textContent = 'Halaman ' + p + ' / ' + total;
    prevP.disabled = p === 1; clearInterval(timer); lbl.textContent = '';
    if (!tracking || viewed.has(p)) { nextP.disabled = false; return; }
    bljPost(progressUrl, { page: p, action: 'open' });
    left = minSec; nextP.disabled = true; tick();
    timer = setInterval(tick, 1000);
  }
  function tick() {
    if (left <= 0) { clearInterval(timer); lbl.textContent = ''; nextP.disabled = false; return; }
    lbl.textContent = 'Baca halaman ini… ' + left + ' dtk'; left--;
  }
  nextP.addEventListener('click', function () {
    var go = function () { if (cur < total) show(cur + 1); else window.scrollTo(0, 0); };
    if (!tracking || viewed.has(cur)) return go();
    nextP.disabled = true;
    bljPost(progressUrl, { page: cur, action: 'done' }).then(function (r) {
      if (!r.ok) { left = Math.max(1, r.wait || 3); nextP.disabled = true; timer = setInterval(tick, 1000); return; }
      viewed.add(cur); document.getElementById('pg-count').textContent = viewed.size;
      if (r.completed) markDone(r.next_url);
      go();
    });
  });
  prevP.addEventListener('click', function () { if (cur > 1) show(cur - 1); });
  var firstUnviewed = 1; while (viewed.has(firstUnviewed) && firstUnviewed < total) firstUnviewed++;
  show(firstUnviewed);
  if (@json($done)) markDone();
@else
  // ---------------- TEKS / GAMBAR (BR-19: tombol aktif setelah waktu minimum) ----------------
  var btn = document.getElementById('mark-done');
  if (btn) {
    var w = parseInt(btn.dataset.wait, 10);
    var t = setInterval(function () {
      if (w <= 0) { clearInterval(t); btn.disabled = false; btn.textContent = 'Tandai selesai'; return; }
      btn.textContent = 'Tandai selesai (' + w + ' dtk)'; w--;
    }, 1000);
    btn.addEventListener('click', function () {
      btn.disabled = true;
      bljPost(progressUrl, {}).then(function (r) {
        if (r.ok) { btn.classList.add('hidden'); markDone(r.next_url); } else { w = r.wait || 5; btn.disabled = true; t = setInterval(function () { if (w-- <= 0) { clearInterval(t); btn.disabled = false; } }, 1000); }
      });
    });
  }
  if (@json($done)) markDone();
@endif
})();
</script>
@endpush
