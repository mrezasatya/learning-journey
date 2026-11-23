@extends('layouts.app')
@section('title', $attempt->assessment->label())
@section('content')
@php $answers = $attempt->answers; $total = $answers->count(); @endphp
<div class="card" style="position:sticky;top:62px;z-index:5">
    <div class="row">
        <b>{{ $attempt->assessment->label() }}</b><small class="muted">{{ $a->program->code }}</small>
        <span class="right">⏱ <span class="timer" id="timer">--:--</span></span>
    </div>
    <div class="qnav" id="qnav" style="margin-top:.5rem">
        @foreach ($answers as $i => $ans)<button type="button" data-go="{{ $i }}" class="{{ $ans->selected_option_ids ? 'answered' : '' }}">{{ $i + 1 }}</button>@endforeach
    </div>
</div>
<form method="post" action="{{ route('learn.test.submit', [$a, $attempt]) }}" id="test-form">
    @csrf
    @foreach ($answers as $i => $ans)
        @php $q = $ans->question; $opts = $q->options->keyBy('id'); $multi = $q->type === 'MULTI'; $sel = $ans->selected_option_ids ?? []; @endphp
        <section class="card question {{ $i === 0 ? 'active' : '' }}" data-q="{{ $q->id }}" data-index="{{ $i }}">
            <small class="muted">Soal {{ $i + 1 }} dari {{ $total }} @if($multi)· <b>pilih semua jawaban yang benar</b>@endif</small>
            <p style="font-size:1.05rem;font-weight:600;margin:.4rem 0 .8rem">{{ $q->text }}</p>
            @foreach ($ans->option_order as $oid)
                @continue(! isset($opts[$oid]))
                <label class="option"><input type="{{ $multi ? 'checkbox' : 'radio' }}" name="q{{ $q->id }}" value="{{ $oid }}" @checked(in_array($oid, $sel))> <span>{{ $opts[$oid]->text }}</span></label>
            @endforeach
            <div class="row" style="margin-top:.75rem">
                @if ($i > 0)<button type="button" class="btn btn-secondary" data-go="{{ $i - 1 }}">‹ Sebelumnya</button>@endif
                <span class="right"></span>
                @if ($i < $total - 1)<button type="button" class="btn" data-go="{{ $i + 1 }}">Berikutnya ›</button>
                @else<button type="button" class="btn btn-success" id="finish">Kumpulkan jawaban</button>@endif
            </div>
        </section>
    @endforeach
</form>
<p class="muted" id="save-state" aria-live="polite"></p>
@endsection
@push('scripts')
<script>
(function () {
  var remaining = {{ $remaining }}, answerUrl = @json(route('learn.test.answer', [$a, $attempt]));
  var form = document.getElementById('test-form'), qs = form.querySelectorAll('.question'), navBtns = document.querySelectorAll('#qnav button');
  var submitting = false, endAt = Date.now() + remaining * 1000;
  function go(i) {
    qs.forEach(function (q) { q.classList.toggle('active', +q.dataset.index === i); });
    navBtns.forEach(function (b) { b.classList.toggle('current', +b.dataset.go === i); });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-go]'); if (b) go(+b.dataset.go); });
  go(0);
  form.addEventListener('change', function (e) {
    var sec = e.target.closest('.question'), qid = +sec.dataset.q;
    var ids = Array.from(sec.querySelectorAll('input:checked')).map(function (i) { return +i.value; });
    navBtns[+sec.dataset.index].classList.toggle('answered', ids.length > 0);
    var st = document.getElementById('save-state'); st.textContent = 'Menyimpan…';
    bljPost(answerUrl, { question_id: qid, option_ids: ids }).then(function (r) {
      st.textContent = r.ok ? 'Jawaban tersimpan ✓' : 'Gagal menyimpan — periksa koneksi.';
      if (typeof r.remaining === 'number') endAt = Date.now() + r.remaining * 1000; // sinkron dengan jam server
    }).catch(function () { st.textContent = 'Gagal menyimpan — periksa koneksi. Jawaban akan dikirim ulang saat Anda memilih lagi.'; });
  });
  function submit() { if (submitting) return; submitting = true; form.submit(); }
  document.getElementById('finish').addEventListener('click', function () {
    var empty = Array.from(qs).filter(function (q) { return !q.querySelector('input:checked'); }).length;
    if (confirm(empty ? empty + ' soal belum dijawab. Kumpulkan sekarang?' : 'Kumpulkan jawaban sekarang?')) submit();
  });
  var t = document.getElementById('timer');
  setInterval(function () {
    var s = Math.max(0, Math.round((endAt - Date.now()) / 1000));
    t.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    t.classList.toggle('low', s <= 60);
    if (s <= 0) submit();
  }, 500);
  window.addEventListener('beforeunload', function (e) { if (!submitting) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
@endpush
