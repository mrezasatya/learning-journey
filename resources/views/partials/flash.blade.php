@foreach (['success' => 'alert-success', 'info' => 'alert-info', 'warning' => 'alert-warning', 'error' => 'alert-danger'] as $key => $class)
    @if (session($key))
        <div class="alert {{ $class }}" role="status">{!! nl2br(e(session($key))) !!}</div>
    @endif
@endforeach
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            Periksa kembali isian berikut:
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
    </div>
@endif
