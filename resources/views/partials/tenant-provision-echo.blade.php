@php
    $echoConfig = master_reverb_echo_config();
@endphp
@if($echoConfig)
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
(function () {
    if (typeof Echo === 'undefined' || window.Echo) {
        return;
    }

    const cfg = @json($echoConfig);
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: cfg.key,
        wsHost: cfg.wsHost,
        wsPort: cfg.wsPort,
        wssPort: cfg.wssPort,
        forceTLS: cfg.forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: cfg.authEndpoint,
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });
})();
</script>
@endif
