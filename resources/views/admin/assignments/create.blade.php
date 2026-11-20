@extends('layouts.app')
@section('title', 'Penugasan Manual')
@section('content')
<div class="page-head"><div><h1>Penugasan manual</h1><p class="muted">Untuk karyawan tertentu di luar aturan (US-12). Duplikasi pada periode yang sama otomatis dilewati.</p></div></div>
<form method="post" action="{{ route('admin.assignments.store') }}" enctype="multipart/form-data" class="card">
    @csrf
    <div class="form-grid">
        <div class="field"><label for="program_id">Program</label><select id="program_id" name="program_id" required><option value="">— pilih —</option>
            @foreach($programs as $p)<option value="{{ $p->id }}" @selected(old('program_id')==$p->id)>{{ $p->code }} — {{ $p->name }} ({{ $p->typeLabel() }})</option>@endforeach</select></div>
        <div class="field"><label for="due_at">Tenggat khusus (opsional)</label><input id="due_at" type="date" name="due_at" value="{{ old('due_at') }}">
            <div class="hint">Kosongkan untuk mengikuti aturan tenggat program (BR-14).</div></div>
        <div class="field"><label for="period_year">Periode (khusus Wajib Tahunan)</label><input id="period_year" type="number" name="period_year" value="{{ old('period_year', now()->year) }}"></div>
    </div>
    <div class="field"><label for="emp-search">Cari &amp; pilih karyawan</label>
        <input id="emp-search" type="search" placeholder="Ketik NIK atau nama (min. 2 huruf)" autocomplete="off">
        <div id="emp-results" class="stack" style="margin-top:.4rem"></div>
        <div id="emp-selected" class="row" style="margin-top:.5rem"></div>
    </div>
    <div class="field"><label for="niks">… atau tempel daftar NIK</label><textarea id="niks" name="niks" rows="3" placeholder="100101, 100102 atau satu NIK per baris">{{ old('niks') }}</textarea></div>
    <div class="field"><label for="file">… atau impor berkas (.xlsx/.csv dengan kolom "nik")</label><input id="file" type="file" name="file" accept=".xlsx,.csv"></div>
    <button class="btn">Tugaskan</button>
</form>
@push('scripts')
<script>
(function () {
  var input = document.getElementById('emp-search'), res = document.getElementById('emp-results'), sel = document.getElementById('emp-selected'), t;
  input.addEventListener('input', function () {
    clearTimeout(t); t = setTimeout(function () {
      fetch('{{ route('admin.assignments.search') }}?q=' + encodeURIComponent(input.value), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(function (list) {
        res.innerHTML = '';
        list.forEach(function (e) {
          var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-secondary';
          b.textContent = '+ ' + e.nik + ' — ' + e.name + (e.department ? ' (' + e.department + ')' : '');
          b.onclick = function () {
            if (sel.querySelector('[data-id="' + e.id + '"]')) return;
            var chip = document.createElement('span'); chip.className = 'badge badge-info'; chip.dataset.id = e.id;
            chip.innerHTML = e.nik + ' ' + e.name + ' <input type="hidden" name="employee_ids[]" value="' + e.id + '"> <a href="#" aria-label="Hapus">✕</a>';
            chip.querySelector('a').onclick = function (ev) { ev.preventDefault(); chip.remove(); };
            sel.appendChild(chip);
          };
          res.appendChild(b);
        });
      });
    }, 250);
  });
})();
</script>
@endpush
@endsection
