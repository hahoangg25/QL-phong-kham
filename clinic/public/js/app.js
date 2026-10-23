/* Tiện ích JS dùng chung cho cổng bệnh nhân và hệ thống quản lý */
window.App = window.App || {};

(function (App) {
  App.csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  /** Gọi API JSON (GET/POST) kèm CSRF; ném lỗi với message từ server. */
  App.fetchJson = async function (url, options = {}) {
    const opts = Object.assign({ method: 'GET', headers: {} }, options);
    opts.headers = Object.assign({
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': App.csrf,
    }, opts.headers);
    if (opts.body && !(opts.body instanceof FormData) && typeof opts.body !== 'string') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const err = new Error(data.message || 'Có lỗi xảy ra, vui lòng thử lại.');
      err.data = data;
      throw err;
    }
    return data;
  };

  App.money = (n) => new Intl.NumberFormat('vi-VN').format(Number(n) || 0) + ' đ';

  App.escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /** Đăng ký nhận sự kiện hàng đợi realtime (HT-06). */
  App.queueListeners = App.queueListeners || [];
  App.onQueueUpdate = (fn) => App.queueListeners.push(fn);

  /**
   * Làm mới định kỳ: chạy fn ngay, sau đó mỗi `ms` (khi có WebSocket thì giãn ra 5 lần để chỉ làm dự phòng).
   * Bỏ qua khi tab đang ẩn.
   */
  App.poll = function (fn, ms = 5000) {
    fn();
    let last = Date.now();
    setInterval(() => {
      const interval = App.realtime ? ms * 5 : ms;
      if (!document.hidden && Date.now() - last >= interval) {
        last = Date.now();
        fn();
      }
    }, 1000);
    App.onQueueUpdate(() => { last = Date.now(); fn(); });
  };

  /** Đọc thông báo bằng giọng nói tiếng Việt (màn hình sảnh, gọi bệnh nhân). */
  App.speak = function (text) {
    if (!('speechSynthesis' in window)) return;
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'vi-VN';
    const vi = speechSynthesis.getVoices().find((v) => v.lang && v.lang.toLowerCase().startsWith('vi'));
    if (vi) u.voice = vi;
    speechSynthesis.speak(u);
  };

  // <form data-confirm="..."> : hỏi xác nhận trước khi gửi
  // <form data-prompt="Nhập lý do" data-prompt-field="reason"> : hỏi lý do và gán vào input ẩn
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      e.preventDefault();
      return;
    }
    if (form.dataset.prompt) {
      const value = window.prompt(form.dataset.prompt, '');
      if (value === null || (form.dataset.promptRequired !== 'false' && !value.trim())) {
        e.preventDefault();
        return;
      }
      let input = form.querySelector(`[name="${form.dataset.promptField || 'reason'}"]`);
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = form.dataset.promptField || 'reason';
        form.appendChild(input);
      }
      input.value = value.trim();
    }
    const btn = form.querySelector('[type="submit"]:not([data-no-disable])');
    if (btn && !form.dataset.noDisable) {
      setTimeout(() => { btn.disabled = true; }, 0);
    }
  });

  // Tự ẩn thông báo thành công sau 6 giây
  document.querySelectorAll('.alert-success.alert-dismissible').forEach((el) => {
    setTimeout(() => { try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch (e) {} }, 6000);
  });

  // Bật tooltip Bootstrap
  document.addEventListener('DOMContentLoaded', () => {
    if (window.bootstrap) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
    }
  });
})(window.App);
