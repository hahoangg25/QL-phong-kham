{{--
  HT-06: kết nối WebSocket (Laravel Reverb qua giao thức Pusher) khi BROADCAST_CONNECTION=reverb.
  Trang dùng: window.onQueueUpdate(fn) - fn được gọi khi có sự kiện "queue.updated";
  nếu không có WebSocket, App.poll() vẫn tự làm mới dữ liệu định kỳ.
--}}
@if (config('broadcasting.default') === 'reverb')
  <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
  <script>
    (function () {
      try {
        const pusher = new Pusher(@json(config('broadcasting.connections.reverb.key')), {
          wsHost: @json(config('broadcasting.connections.reverb.options.host')),
          wsPort: @json((int) config('broadcasting.connections.reverb.options.port')),
          wssPort: @json((int) config('broadcasting.connections.reverb.options.port')),
          forceTLS: @json(config('broadcasting.connections.reverb.options.scheme') === 'https'),
          enabledTransports: ['ws', 'wss'],
          cluster: 'mt1',
        });
        const channel = pusher.subscribe('queue');
        window.App = window.App || {};
        App.realtime = true;
        channel.bind('queue.updated', (data) => (App.queueListeners || []).forEach((fn) => fn(data)));
        pusher.connection.bind('state_change', (s) => { App.realtime = s.current === 'connected'; });
      } catch (e) { console.warn('Realtime không khả dụng, dùng polling.', e); }
    })();
  </script>
@endif
