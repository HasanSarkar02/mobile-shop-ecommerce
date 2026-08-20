@if (session()->has('support_mode'))
    @php $isWrite = session('support_mode.is_write_enabled', false); @endphp
    <div style="background-color:{{ $isWrite ? '#dc2626' : '#d97706' }};color:#fff;padding:0.625rem 1rem;display:flex;align-items:center;justify-content:center;gap:0.75rem;font-size:0.875rem;font-weight:600;position:relative;z-index:50;">
        <span>{{ $isWrite ? '⚠️ READ/WRITE Support Mode Active - Proceed with Caution' : 'Read-Only Support Mode' }} — Viewing {{ tenant()?->name ?? 'tenant' }}</span>
        <form method="POST" action="{{ route('support.exit') }}" style="margin:0;">
            @csrf
            <button type="submit" style="background:#fff;color:#dc2626;border:0;border-radius:0.375rem;padding:0.25rem 0.75rem;font-weight:700;cursor:pointer;">Exit Support Mode</button>
        </form>
    </div>
@endif