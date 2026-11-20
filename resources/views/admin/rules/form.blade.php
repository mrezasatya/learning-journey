@extends('layouts.app')
@section('title', $rule->exists ? 'Ubah Aturan' : 'Aturan Baru')
@section('content')
<div class="page-head"><div><h1>{{ $rule->exists ? 'Ubah aturan penugasan' : 'Aturan penugasan baru' }}</h1>
    <p class="muted">Gabungkan kondisi atribut karyawan. Kosongkan kondisi = semua karyawan aktif. Penugasan yang sudah ada tidak pernah digandakan (BR-12).</p></div></div>
<form method="post" id="rule-form" action="{{ $rule->exists ? route('admin.rules.update', $rule) : route('admin.rules.store') }}" class="card">
    @csrf @if($rule->exists) @method('put') @endif
    <div class="form-grid">
        <div class="field"><label for="program_id">Program (hanya yang Terbit)</label>
            <select id="program_id" name="program_id" required><option value="">— pilih —</option>
            @foreach($programs as $p)<option value="{{ $p->id }}" data-type="{{ $p->type }}" @selected(old('program_id', $rule->program_id)==$p->id)>{{ $p->code }} — {{ $p->name }} ({{ $p->typeLabel() }})</option>@endforeach</select></div>
        <div class="field"><label for="name">Nama aturan</label><input id="name" name="name" value="{{ old('name', $rule->name) }}" required placeholder="mis. K3 untuk seluruh karyawan plant & site"></div>
        <div class="field"><label for="effective_from">Berlaku sejak</label><input id="effective_from" type="date" name="effective_from" value="{{ old('effective_from', $rule->effective_from?->format('Y-m-d')) }}" required>
            <div class="hint" id="onb-hint">Untuk Onboarding: hanya karyawan dengan tanggal masuk ≥ tanggal ini (BR-13).</div></div>
        <div class="field"><label for="logic">Gabungan kondisi</label><select id="logic" name="logic">
            <option value="AND" @selected(old('logic', $rule->conditions['logic'] ?? 'AND')==='AND')>DAN — semua kondisi terpenuhi</option>
            <option value="OR" @selected(old('logic', $rule->conditions['logic'] ?? 'AND')==='OR')>ATAU — salah satu kondisi terpenuhi</option></select></div>
    </div>

    <h3>Kondisi</h3>
    <div id="conditions"></div>
    <button type="button" class="btn btn-sm btn-secondary" id="add-cond">+ Tambah kondisi</button>

    <div class="row" style="margin-top:1rem">
        <button type="button" class="btn btn-secondary" id="btn-preview">👁 Pratinjau peserta</button>
        <button class="btn" id="btn-save" disabled title="Lakukan pratinjau terlebih dahulu">Simpan &amp; terapkan</button>
        <a class="btn btn-secondary" href="{{ route('admin.rules.index') }}">Batal</a>
    </div>
    <div id="preview" class="hidden" style="margin-top:1rem"></div>
</form>

<template id="cond-tpl">
    <div class="card cond" style="padding:.75rem;margin-bottom:.5rem;box-shadow:none">
        <div class="form-grid" style="align-items:end">
            <div class="field"><label>Atribut</label><select class="f-field">
                @foreach(\App\Models\AssignmentRule::FIELDS as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="field"><label>Operator</label><select class="f-op"><option value="in">adalah salah satu dari</option><option value="not_in">bukan salah satu dari</option></select></div>
            <div class="field" style="grid-column: span 2"><label>Nilai (bisa pilih lebih dari satu)</label><select class="f-values" multiple size="5"></select></div>
            <div class="field"><button type="button" class="btn btn-sm btn-danger f-remove">Hapus kondisi</button></div>
        </div>
    </div>
</template>
@push('scripts')
<script>
(function () {
  var OPTIONS = @json($options);
  var initial = @json(old('items') ? array_values(old('items')) : ($rule->conditions['items'] ?? []));
  var box = document.getElementById('conditions'), tpl = document.getElementById('cond-tpl');
  var saveBtn = document.getElementById('btn-save'), form = document.getElementById('rule-form');

  function fillValues(sel, field, selected) {
    sel.innerHTML = '';
    Object.entries(OPTIONS[field] || {}).forEach(function (kv) {
      var o = new Option(kv[1], kv[0]); o.selected = (selected || []).map(String).indexOf(String(kv[0])) >= 0; sel.add(o);
    });
  }
  function addCond(c) {
    var node = tpl.content.firstElementChild.cloneNode(true);
    var f = node.querySelector('.f-field'), op = node.querySelector('.f-op'), v = node.querySelector('.f-values');
    f.value = c && c.field || 'position_id'; op.value = c && c.op || 'in';
    fillValues(v, f.value, c && c.values);
    f.addEventListener('change', function () { fillValues(v, f.value, []); invalidate(); });
    [op, v].forEach(function (el) { el.addEventListener('change', invalidate); });
    node.querySelector('.f-remove').addEventListener('click', function () { node.remove(); renumber(); invalidate(); });
    box.appendChild(node); renumber();
  }
  function renumber() {
    box.querySelectorAll('.cond').forEach(function (n, i) {
      n.querySelector('.f-field').name = 'items[' + i + '][field]';
      n.querySelector('.f-op').name = 'items[' + i + '][op]';
      n.querySelector('.f-values').name = 'items[' + i + '][values][]';
    });
  }
  function invalidate() { saveBtn.disabled = true; document.getElementById('preview').classList.add('hidden'); }
  initial.forEach(addCond);
  document.getElementById('add-cond').addEventListener('click', function () { addCond(null); invalidate(); });
  ['program_id', 'effective_from', 'logic'].forEach(function (id) { document.getElementById(id).addEventListener('change', invalidate); });
  var prog = document.getElementById('program_id');
  function onbHint() { var o = prog.selectedOptions[0]; document.getElementById('onb-hint').classList.toggle('hidden', !o || o.dataset.type !== 'ONBOARDING'); }
  prog.addEventListener('change', onbHint); onbHint();

  document.getElementById('btn-preview').addEventListener('click', function () {
    var fd = new FormData(form); fd.delete('_method');
    var out = document.getElementById('preview');
    out.classList.remove('hidden'); out.innerHTML = '<p class="muted">Menghitung…</p>';
    fetch('{{ route('admin.rules.preview') }}', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok) { out.innerHTML = '<div class="alert alert-danger">' + Object.values(res.j.errors || { e: [res.j.message] }).flat().join('<br>') + '</div>'; return; }
        var j = res.j, rows = j.employees.map(function (e) {
          return '<tr><td>' + e.nik + '</td><td>' + e.name + '</td><td>' + (e.position || '') + '</td><td>' + (e.department || '') + '</td><td>' + (e.location || '') + '</td><td>' +
            (e.already ? '<span class="badge badge-neutral">Sudah ditugaskan</span>' : '<span class="badge badge-success">Baru</span>') + '</td></tr>';
        }).join('');
        out.innerHTML = '<div class="alert alert-info"><b>' + j.total + '</b> karyawan memenuhi aturan · <b>' + j.new + '</b> akan mendapat penugasan baru saat disimpan.</div>' +
          '<div class="table-wrap" style="max-height:360px;overflow:auto"><table><thead><tr><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Departemen</th><th>Lokasi</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
          (j.total > 200 ? '<p class="muted">Menampilkan 200 pertama.</p>' : '');
        saveBtn.disabled = false;
      });
  });
})();
</script>
@endpush
@endsection
