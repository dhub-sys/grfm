(() => {
  'use strict';
  const cfg = window.grfuelPush || {};
  const statusText = document.getElementById('grfuel-push-status-text');
  const statusWrap = document.getElementById('grfuel-push-device-status');
  const help = document.getElementById('grfuel-push-help');
  const enableButton = document.getElementById('grfuel-enable-push');
  const testButton = document.getElementById('grfuel-test-push');
  let OneSignalClient = null;

  const setStatus = (message, state = '') => {
    if (statusText) statusText.textContent = message;
    if (statusWrap) {
      statusWrap.classList.remove('is-ready', 'is-error', 'is-loading');
      if (state) statusWrap.classList.add(`is-${state}`);
    }
  };

  const readOptedIn = async () => {
    if (!OneSignalClient) return false;
    try {
      return Boolean(OneSignalClient.User?.PushSubscription?.optedIn);
    } catch (_) {
      return false;
    }
  };

  const refresh = async () => {
    if (!cfg.isHttps) {
      setStatus('Απαιτείται HTTPS για push notifications.', 'error');
      return;
    }
    const optedIn = await readOptedIn();
    setStatus(optedIn ? 'Η συσκευή είναι συνδεδεμένη και λαμβάνει push.' : 'Η συσκευή δεν έχει ακόμη συνδεθεί.', optedIn ? 'ready' : '');
  };

  window.OneSignalDeferred = window.OneSignalDeferred || [];
  window.OneSignalDeferred.push(async (OneSignal) => {
    try {
      OneSignalClient = OneSignal;
      await OneSignal.init({
        appId: cfg.appId,
        serviceWorkerPath: cfg.workerPath,
        serviceWorkerParam: { scope: cfg.workerScope },
        notifyButton: { enable: false },
        allowLocalhostAsSecureOrigin: false,
      });
      await OneSignal.login(cfg.externalId);
      await refresh();
    } catch (error) {
      setStatus('Δεν ολοκληρώθηκε η σύνδεση με το OneSignal.', 'error');
      if (help) help.textContent = error?.message || 'Ελέγξτε το App ID και τη ρύθμιση Web Push στο OneSignal.';
    }
  });

  enableButton?.addEventListener('click', async () => {
    if (!OneSignalClient) {
      setStatus('Το OneSignal δεν έχει φορτωθεί ακόμη.', 'loading');
      return;
    }
    enableButton.disabled = true;
    setStatus('Ζητείται άδεια ειδοποιήσεων…', 'loading');
    try {
      await OneSignalClient.login(cfg.externalId);
      await OneSignalClient.Notifications.requestPermission();
      if (OneSignalClient.User?.PushSubscription?.optIn) await OneSignalClient.User.PushSubscription.optIn();
      await refresh();
    } catch (error) {
      setStatus('Η ενεργοποίηση δεν ολοκληρώθηκε.', 'error');
      if (help) help.textContent = error?.message || 'Ελέγξτε τις άδειες ειδοποιήσεων της συσκευής.';
    } finally {
      enableButton.disabled = false;
    }
  });

  testButton?.addEventListener('click', async () => {
    testButton.disabled = true;
    const original = testButton.innerHTML;
    testButton.textContent = 'Αποστολή…';
    try {
      const body = new URLSearchParams({ action: 'grfuel_push_test', nonce: cfg.nonce });
      const response = await fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body });
      const result = await response.json();
      if (!result.success) throw new Error(result.data?.message || 'Η αποστολή απέτυχε.');
      setStatus(result.data.message, 'ready');
    } catch (error) {
      setStatus(error.message || 'Η δοκιμή απέτυχε.', 'error');
    } finally {
      testButton.disabled = false;
      testButton.innerHTML = original;
    }
  });
})();
