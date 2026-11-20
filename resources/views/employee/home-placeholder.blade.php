@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
<h1>Halo, {{ auth()->user()->displayName() }}</h1>
<div class="card empty">Daftar training wajib akan tampil di sini (dibangun pada Tahap 6).</div>
@endsection
