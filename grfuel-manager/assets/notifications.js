(() => {
  if (!window.grfuelNotify) return;

  const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
  let audioReady = false;
  let polling = false;
  let titleTimer = null;
  const ctx = { value: null };

  const qsa = selector => Array.from(document.querySelectorAll(selector));
  let unreadCount = 0;
  const writeUnread = count => {
    const value = Math.max(0, Number(count) || 0);
    unreadCount = value;
    qsa('[data-grfuel-order-badge]').forEach(badge => {
      badge.textContent = value > 99 ? '99+' : String(value);
      badge.hidden = value < 1;
    });
  };

  const unlockAudio = () => {
    if (!grfuelNotify.sound || audioReady) return;
    try {
      ctx.value = ctx.value || new (window.AudioContext || window.webkitAudioContext)();
      if (ctx.value.state === 'suspended') ctx.value.resume();
      audioReady = true;
    } catch (e) {}
  };
  document.addEventListener('click', unlockAudio, { once: true, passive: true });
  document.addEventListener('keydown', unlockAudio, { once: true, passive: true });

  const bell = () => {
    if (!grfuelNotify.sound || !audioReady || !ctx.value) return;
    try {
      const audio = ctx.value;
      const now = audio.currentTime;
      const strike = (start, base, volume) => {
        [
          [1, 1, 1.15], [2.01, .34, .9], [2.72, .2, .72], [3.92, .11, .55]
        ].forEach(([ratio, gainValue, decay]) => {
          const oscillator = audio.createOscillator();
          const gain = audio.createGain();
          oscillator.type = 'sine';
          oscillator.frequency.setValueAtTime(base * ratio, start);
          gain.gain.setValueAtTime(.0001, start);
          gain.gain.exponentialRampToValueAtTime(volume * gainValue, start + .006);
          gain.gain.exponentialRampToValueAtTime(.0001, start + decay);
          oscillator.connect(gain);
          gain.connect(audio.destination);
          oscillator.start(start);
          oscillator.stop(start + decay + .04);
        });
      };
      const choice = grfuelNotify.soundChoice || 'bell';
      if (choice === 'soft') { strike(now, 523.25, .11); strike(now + .24, 659.25, .09); }
      else if (choice === 'digital') { strike(now, 1046.50, .13); strike(now + .14, 1318.51, .10); strike(now + .28, 1567.98, .08); }
      else if (choice === 'chime') { strike(now, 783.99, .14); strike(now + .30, 987.77, .12); }
      else { strike(now, 1318.51, .16); strike(now + .28, 1567.98, .13); }
    } catch (e) {}
  };

  const flashAttention = count => {
    window.clearInterval(titleTimer);
    let on = false;
    let cycles = 0;
    titleTimer = window.setInterval(() => {
      on = !on;
      document.title = on ? `🔔 ${count} νέα παραγγελία${count === 1 ? '' : 'ες'} — ${baseTitle}` : baseTitle;
      cycles += 1;
      if (cycles >= 16) {
        window.clearInterval(titleTimer);
        document.title = `(${count}) ${baseTitle}`;
      }
    }, 650);
  };

  const ensureToastHost = () => {
    let host = document.querySelector('.grfuel-notification-host');
    if (!host) {
      host = document.createElement('div');
      host.className = 'grfuel-notification-host';
      host.setAttribute('aria-live', 'assertive');
      document.body.appendChild(host);
    }
    return host;
  };

  const showToast = (count, order) => {
    const host = ensureToastHost();
    const toast = document.createElement('article');
    toast.className = 'grfuel-order-toast';
    const customer = order?.customer || 'Νέος πελάτης';
    const product = order?.product || 'Νέα παραγγελία';
    const quantity = order?.quantity ? ` · ${order.quantity} lt` : '';
    toast.innerHTML = `
      <button type="button" class="grfuel-toast-close" aria-label="Κλείσιμο">×</button>
      <div class="grfuel-toast-icon">🔔</div>
      <div class="grfuel-toast-copy">
        <small>GRFuel Manager</small>
        <strong>${count === 1 ? 'Νέα παραγγελία' : `${count} νέες παραγγελίες`}</strong>
        <span>${customer}</span>
        <p>${product}${quantity}</p>
        <a href="${grfuelNotify.ordersUrl}">Άνοιγμα παραγγελιών</a>
      </div>`;
    toast.querySelector('.grfuel-toast-close')?.addEventListener('click', () => toast.remove());
    host.prepend(toast);
    window.setTimeout(() => toast.classList.add('is-visible'), 30);
    window.setTimeout(() => { toast.classList.remove('is-visible'); window.setTimeout(() => toast.remove(), 250); }, 14000);
  };

  const showNativeNotification = (count, order) => {
    if (!grfuelNotify.desktop || !('Notification' in window) || Notification.permission !== 'granted') return;
    const title = count === 1 ? 'Νέα παραγγελία GRFuel' : `${count} νέες παραγγελίες GRFuel`;
    const lines = [order?.customer, order?.product, order?.quantity ? `${order.quantity} lt` : ''].filter(Boolean);
    const notification = new Notification(title, {
      body: lines.join(' · ') || 'Άνοιξε τις Παραγγελίες για να δεις τα στοιχεία.',
      tag: 'grfuel-order',
      renotify: true,
      requireInteraction: Boolean(grfuelNotify.requireInteraction),
      silent: true
    });
    notification.onclick = () => {
      window.focus();
      window.location.href = grfuelNotify.ordersUrl;
      notification.close();
    };
  };

  const notify = (count, order) => {
    bell();
    flashAttention(Math.max(1, unreadCount));
    showToast(count, order);
    showNativeNotification(count, order);
  };

  const poll = async () => {
    if (polling) return;
    polling = true;
    try {
      const body = new URLSearchParams({ action: 'grfuel_check_orders', nonce: grfuelNotify.nonce });
      const response = await fetch(grfuelNotify.ajaxUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body, cache: 'no-store'
      });
      const json = await response.json();
      if (!json?.success) return;
      const count = Number(json.data.newCount || 0);
      writeUnread(Number(json.data.unreadCount || 0));
      if (count > 0) notify(count, json.data.order || null);
    } catch (e) {} finally { polling = false; }
  };

  if (location.href.includes('page=sfv-submissions')) {
    writeUnread(0);
    document.title = baseTitle;
  }
  poll();
  window.setInterval(poll, Math.max(15000, Number(grfuelNotify.interval || 30000)));
})();
