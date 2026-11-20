/* BLJ — JavaScript umum (tanpa framework). */
(function () {
  // Toggle sidebar di HP
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toggle-menu]');
    if (t) { document.querySelector('.sidebar')?.classList.toggle('open'); }
    // Konfirmasi aksi berisiko: <button data-confirm="Yakin?">
    var c = e.target.closest('[data-confirm]');
    if (c && !confirm(c.getAttribute('data-confirm'))) { e.preventDefault(); e.stopPropagation(); }
  });
})();

/** Kirim JSON ke server dengan token CSRF Laravel. */
window.bljPost = function (url, data) {
  var token = document.querySelector('meta[name="csrf-token"]').content;
  return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
    credentials: 'same-origin',
    body: JSON.stringify(data || {})
  }).then(function (r) {
    if (r.status === 419 || r.status === 401) { alert('Sesi berakhir. Silakan login kembali.'); location.reload(); }
    return r.json().catch(function () { return {}; }).then(function (j) { j._status = r.status; return j; });
  });
};
