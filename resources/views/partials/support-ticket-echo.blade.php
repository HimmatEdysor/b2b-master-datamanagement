@php
    $useReverb = config('broadcasting.default') === 'reverb';
    $reverb = config('broadcasting.connections.reverb');
@endphp
@if($useReverb && $reverb)
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
(function () {
    const ticketId = document.getElementById('ticket-messages')?.dataset?.ticketId;
    if (!ticketId || typeof Echo === 'undefined') return;

    const reverb = @json($reverb);
    const opts = reverb.options || {};
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverb.key,
        wsHost: opts.host || window.location.hostname,
        wsPort: opts.port ?? 8080,
        wssPort: opts.port ?? 8080,
        forceTLS: (opts.scheme || 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        }
    });

    const container = document.getElementById('ticket-messages');
    const appendMessage = (payload) => {
        if (!container || document.querySelector('[data-message-id="' + payload.id + '"]')) return;
        const wrap = document.createElement('div');
        wrap.className = 'ticket-message ' + (payload.is_staff ? 'ticket-message-staff' : 'ticket-message-guest');
        wrap.dataset.messageId = payload.id;
        const name = payload.sender_name || (payload.is_staff ? 'Support team' : 'Guest');
        const when = payload.created_at ? new Date(payload.created_at).toLocaleString() : '';
        wrap.innerHTML = '<div class="ticket-message-meta"><strong>' + name + '</strong><span>' + when + '</span></div>'
            + '<div class="ticket-message-body">' + (payload.body || '').replace(/</g, '&lt;').replace(/\n/g, '<br>') + '</div>';
        container.appendChild(wrap);
        wrap.scrollIntoView({ behavior: 'smooth', block: 'end' });
    };

    window.Echo.private('support-ticket.' + ticketId)
        .listen('.message.sent', (e) => appendMessage(e));
})();
</script>
@endif
