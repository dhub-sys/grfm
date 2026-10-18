(() => {
  const valueOf = (form, key) => {
    const selector = `[name="sfv_fields[${CSS.escape(key)}]"]`;
    const el = form.querySelector(selector);
    if (!el) return '';
    if (el.type === 'radio') {
      const checked = form.querySelector(`${selector}:checked`);
      return checked ? checked.value : '';
    }
    return el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value;
  };
  const matches = (actual, op, expected) => ({
    equals: actual === expected,
    not_equals: actual !== expected,
    contains: actual.toLowerCase().includes(expected.toLowerCase()),
    checked: actual === '1', not_checked: actual !== '1', empty: actual === '', not_empty: actual !== ''
  }[op] ?? actual === expected);

  const initDatepickers = () => {
    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.datepicker) return;
    const $ = window.jQuery;
    $.datepicker.setDefaults({
      closeText: 'Κλείσιμο', prevText: 'Προηγούμενος', nextText: 'Επόμενος', currentText: 'Σήμερα',
      monthNames: ['Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος','Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'],
      monthNamesShort: ['Ιαν','Φεβ','Μαρ','Απρ','Μάι','Ιουν','Ιουλ','Αυγ','Σεπ','Οκτ','Νοε','Δεκ'],
      dayNames: ['Κυριακή','Δευτέρα','Τρίτη','Τετάρτη','Πέμπτη','Παρασκευή','Σάββατο'],
      dayNamesShort: ['Κυρ','Δευ','Τρι','Τετ','Πεμ','Παρ','Σαβ'],
      dayNamesMin: ['Κυ','Δε','Τρ','Τε','Πε','Πα','Σα'], firstDay: 1, isRTL: false
    });
    $('.sfv-datepicker').each(function () {
      const $display = $(this);
      if ($display.hasClass('hasDatepicker')) return;
      const targetId = $display.attr('data-sfv-date-target');
      const target = targetId ? document.getElementById(targetId) : null;
      $display.datepicker({
        dateFormat: 'dd/mm/yy',
        changeMonth: true,
        changeYear: true,
        showOtherMonths: true,
        selectOtherMonths: true,
        onSelect: function (displayValue) {
          if (target) {
            const date = $display.datepicker('getDate');
            target.value = date ? $.datepicker.formatDate('yy-mm-dd', date) : '';
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      });
      $display.on('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
          event.preventDefault();
          $display.datepicker('show');
        }
        if ((event.key === 'Backspace' || event.key === 'Delete') && target) {
          event.preventDefault();
          $display.val(''); target.value = '';
          target.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    });
  };


  const initPricing = () => {
    document.querySelectorAll('.sfv-form').forEach(form => {
      form.querySelectorAll('.sfv-price-calculator[data-sfv-pricing]').forEach(box => {
        let cfg; try { cfg = JSON.parse(box.dataset.sfvPricing || '{}'); } catch(e) { return; }
        const findControl = key => {
          const safeKey = String(key || '').trim();
          if (!safeKey) return null;
          const byName = form.elements.namedItem(`sfv_fields[${safeKey}]`);
          if (byName) return byName;
          const escape = window.CSS && typeof window.CSS.escape === 'function'
            ? window.CSS.escape(safeKey)
            : safeKey.replace(/([ #;?%&,.+*~\':"!^$[\]()=>|\/@])/g, '\\$1');
          return form.querySelector(`#sfv-${escape}`) || form.querySelector(`[name$="[${escape}]"]`);
        };
        const product = findControl(cfg.productField);
        const quantity = findControl(cfg.quantityField);
        const totalEl = box.querySelector('.sfv-price-total');
        const unitEl = box.querySelector('.sfv-price-unit');
        const controlValue = control => {
          if (!control) return '';
          if (typeof RadioNodeList !== 'undefined' && control instanceof RadioNodeList) return control.value || '';
          return control.value || '';
        };
        const normalize = value => String(value ?? '').trim().replace(/\s+/g, ' ');
        const update = () => {
          const productValue = cfg.mode === 'single' ? normalize(cfg.productLabel || '') : normalize(controlValue(product));
          const qty = Math.max(0, parseFloat(String(controlValue(quantity)).replace(',','.')) || 0);
          const rows = Array.isArray(cfg.rows) ? cfg.rows : [];
          const row = cfg.mode === 'single'
            ? (rows.find(r => normalize(r.product) === productValue) || rows[0] || null)
            : rows.find(r => normalize(r.product) === productValue);
          if (!row || !qty) {
            if (cfg.mode === 'single') {
              if (unitEl) unitEl.textContent = cfg.placeholder || 'Αυτόματος υπολογισμός κόστους παραγγελίας';
              totalEl.textContent = '';
              box.classList.add('sfv-price-is-placeholder');
            } else {
              totalEl.textContent='—';
              if (unitEl) unitEl.textContent='—';
              box.classList.remove('sfv-price-is-placeholder');
            }
            return;
          }
          box.classList.remove('sfv-price-is-placeholder');
          let unit = Number(row.base)||0; (row.tiers||[]).forEach(t=>{ if(qty>=Number(t.min)) unit=Number(t.price)||unit; });
          totalEl.textContent = new Intl.NumberFormat('el-GR',{minimumFractionDigits:2,maximumFractionDigits:2}).format(qty*unit)+' '+(cfg.currency||'€');
          if (unitEl) unitEl.textContent = `${new Intl.NumberFormat('el-GR',{minimumFractionDigits:2,maximumFractionDigits:3}).format(unit)} ${cfg.currency||'€'} / ${cfg.unitLabel||'μονάδα'}`;
        };
        const bind = (control, event) => {
          if (!control) return;
          if (typeof RadioNodeList !== 'undefined' && control instanceof RadioNodeList) {
            Array.from(control).forEach(el => el.addEventListener(event, update));
          } else {
            control.addEventListener(event, update);
          }
        };
        bind(product, 'change');
        bind(product, 'input');
        bind(quantity, 'input');
        bind(quantity, 'change');
        // Delegated fallback covers browser/form-builder edge cases and dynamically replaced inputs.
        form.addEventListener('input', event => {
          const target = event.target;
          if (target && (target === quantity || target.name === `sfv_fields[${cfg.quantityField || ''}]`)) update();
        });
        form.addEventListener('change', event => {
          const target = event.target;
          if (target && (target === quantity || target.name === `sfv_fields[${cfg.quantityField || ''}]`)) update();
        });
        update();
      });
    });
    document.querySelectorAll('.sfv-dynamic-price').forEach(async el => {
      if (!window.sfvFront?.ajaxUrl) return;
      const url = new URL(window.sfvFront.ajaxUrl); url.searchParams.set('action','sfv_price'); url.searchParams.set('form',el.dataset.form); url.searchParams.set('product',el.dataset.product); url.searchParams.set('quantity',el.dataset.quantity||'0'); url.searchParams.set('_',Date.now());
      try { const res=await fetch(url,{credentials:'same-origin',cache:'no-store'}); const json=await res.json(); if(json.success) el.textContent=new Intl.NumberFormat('el-GR',{minimumFractionDigits:2,maximumFractionDigits:3}).format(Number(json.data.price))+' '+(el.dataset.currency||'€'); else el.textContent='—'; } catch(e){ el.textContent='—'; }
    });
  };

  document.querySelectorAll('.sfv-form').forEach(form => {
    const update = () => form.querySelectorAll('[data-sfv-dependency]').forEach(box => {
      const show = matches(valueOf(form, box.dataset.sfvDependency), box.dataset.sfvOperator, box.dataset.sfvValue || '');
      box.hidden = !show; box.setAttribute('aria-hidden', show ? 'false' : 'true');
      box.querySelectorAll('input,select,textarea').forEach(el => { if (!show) { el.dataset.sfvRequired = el.required ? '1' : '0'; el.required = false; } else if (el.dataset.sfvRequired === '1') el.required = true; });
    });
    form.addEventListener('input', update); form.addEventListener('change', update); update();
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDatepickers);
  else initDatepickers();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPricing); else initPricing();
})();

// GRFuel interactive price-history charts.
document.addEventListener('click', async event => {
  const button = event.target.closest('[data-grfuel-chart-update]');
  if (!button || !window.grfuelChart) return;
  const chart = button.closest('[data-grfuel-chart]');
  const input = chart?.querySelector('[data-grfuel-chart-days]');
  const stage = chart?.querySelector('.grfuel-chart-stage');
  if (!chart || !input || !stage) return;
  const maxDays = Math.max(7, Number(chart.dataset.maxDays || 365));
  const days = Math.max(7, Math.min(maxDays, Number(input.value || 30)));
  input.value = String(days);
  button.disabled = true;
  button.textContent = 'Φόρτωση…';
  try {
    const url = new URL(grfuelChart.ajaxUrl, window.location.origin);
    url.searchParams.set('action', 'grfuel_price_chart');
    url.searchParams.set('product', chart.dataset.product || '');
    url.searchParams.set('days', String(days));
    url.searchParams.set('height', chart.dataset.height || '220');
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
    const json = await response.json();
    if (json?.success && json.data?.html) stage.innerHTML = json.data.html;
  } catch (error) {
    stage.innerHTML = '<div class="grfuel-chart-empty">Δεν ήταν δυνατή η ενημέρωση του γραφήματος.</div>';
  } finally {
    button.disabled = false;
    button.textContent = 'Ενημέρωση γραφήματος';
  }
});


// alpha.11: initialize chart animations in YOOtheme, AJAX and dynamically injected content.
(() => {
  const animateChart = chart => {
    if (!(chart instanceof Element) || chart.dataset.grfuelAnimated === '1') return;
    const line = chart.querySelector('.grfuel-chart-line');
    const points = chart.querySelectorAll('.grfuel-chart-point circle');
    if (line && typeof line.getTotalLength === 'function') {
      const length = Math.ceil(line.getTotalLength());
      line.style.setProperty('--grfuel-line-length', String(length));
    }
    points.forEach((point, index) => point.style.setProperty('--grfuel-point-delay', `${Math.min(index * 70, 700)}ms`));
    chart.dataset.grfuelAnimated = '1';
    requestAnimationFrame(() => requestAnimationFrame(() => chart.classList.add('is-animated')));
  };
  const scan = root => {
    if (root instanceof Element && root.matches('.grfuel-price-chart')) animateChart(root);
    (root.querySelectorAll ? root.querySelectorAll('.grfuel-price-chart') : []).forEach(animateChart);
  };
  const boot = () => {
    scan(document);
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
      if (node.nodeType === 1) scan(node);
    }))).observe(document.documentElement, { childList: true, subtree: true });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();

// GRFuel Manager 4.2.7: reference-style chart tooltip with animated numeric value on hover.
(() => {
  const animations = new WeakMap();
  const money = value => `${Number(value || 0).toLocaleString('el-GR', { minimumFractionDigits: 3, maximumFractionDigits: 3 })} €`;
  const parseLabel = label => {
    const parts = String(label || '').split(/\s+—\s+/);
    const numeric = parts.length > 1 ? Number(parts[1].replace(/\./g, '').replace(',', '.').replace(/[^0-9.-]/g, '')) : NaN;
    return { title: parts[0] || '', value: Number.isFinite(numeric) ? numeric : null, raw: label };
  };
  const animateValue = (node, target) => {
    const previous = animations.get(node);
    if (previous) cancelAnimationFrame(previous);
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
      node.textContent = money(target);
      node.dataset.value = String(target);
      return;
    }
    const start = performance.now();
    const duration = 460;
    const from = 0;
    node.dataset.value = '0';
    const tick = now => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      node.textContent = money(from + (target - from) * eased);
      if (progress < 1) animations.set(node, requestAnimationFrame(tick));
      else {
        node.textContent = money(target);
        node.dataset.value = String(target);
        animations.delete(node);
      }
    };
    animations.set(node, requestAnimationFrame(tick));
  };
  const closeTooltip = chart => {
    if (!(chart instanceof Element)) return;
    const tooltip = chart.querySelector('.grfuel-chart-tooltip');
    chart.querySelectorAll('.grfuel-chart-point circle.is-active').forEach(point => point.classList.remove('is-active'));
    if (!tooltip) return;
    tooltip.classList.remove('is-visible');
    tooltip.setAttribute('aria-hidden', 'true');
  };
  const showTooltip = point => {
    const chart = point.closest('.grfuel-price-chart');
    const tooltip = chart?.querySelector('.grfuel-chart-tooltip');
    if (!chart || !tooltip) return;
    const alreadyActive = point.classList.contains('is-active') && tooltip.classList.contains('is-visible');
    if (alreadyActive) return;
    closeTooltip(chart);
    const data = parseLabel(point.dataset.grfuelPointLabel || point.getAttribute('aria-label') || '');
    tooltip.classList.remove('is-visible');
    tooltip.innerHTML = data.value === null
      ? `<span class="grfuel-tooltip-value">${data.raw}</span>`
      : `<strong class="grfuel-tooltip-title">${data.title}</strong><span class="grfuel-tooltip-value" data-grfuel-tip-value>0,000 €</span>`;
    point.classList.add('is-active');
    tooltip.setAttribute('aria-hidden', 'false');
    const chartRect = chart.getBoundingClientRect();
    const pointRect = point.getBoundingClientRect();
    const x = pointRect.left + pointRect.width / 2 - chartRect.left;
    const y = pointRect.top - chartRect.top;
    tooltip.style.left = `${Math.max(72, Math.min(chart.clientWidth - 72, x))}px`;
    tooltip.style.top = `${Math.max(8, y - 10)}px`;
    const valueNode = tooltip.querySelector('[data-grfuel-tip-value]');
    if (valueNode && data.value !== null) animateValue(valueNode, data.value);
    requestAnimationFrame(() => requestAnimationFrame(() => tooltip.classList.add('is-visible')));
  };

  // Desktop: show and replay the count-up animation as soon as the pointer enters a point.
  document.addEventListener('pointerover', event => {
    const point = event.target.closest?.('.grfuel-chart-point circle[data-grfuel-point-label]');
    if (!point || event.pointerType === 'touch') return;
    showTooltip(point);
  });
  document.addEventListener('pointerout', event => {
    const point = event.target.closest?.('.grfuel-chart-point circle[data-grfuel-point-label]');
    if (!point || event.pointerType === 'touch') return;
    const related = event.relatedTarget;
    if (related instanceof Node && point.contains(related)) return;
    closeTooltip(point.closest('.grfuel-price-chart'));
  });

  // Keyboard accessibility and touch/mobile fallback.
  document.addEventListener('focusin', event => {
    const point = event.target.closest?.('.grfuel-chart-point circle[data-grfuel-point-label]');
    if (point) showTooltip(point);
  });
  document.addEventListener('focusout', event => {
    const point = event.target.closest?.('.grfuel-chart-point circle[data-grfuel-point-label]');
    if (point) closeTooltip(point.closest('.grfuel-price-chart'));
  });
  document.addEventListener('click', event => {
    const point = event.target.closest('.grfuel-chart-point circle[data-grfuel-point-label]');
    if (point) {
      event.stopPropagation();
      showTooltip(point);
      return;
    }
    document.querySelectorAll('.grfuel-price-chart').forEach(closeTooltip);
  });
  document.addEventListener('keydown', event => {
    const point = event.target.closest?.('.grfuel-chart-point circle[data-grfuel-point-label]');
    if (point && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      showTooltip(point);
    } else if (event.key === 'Escape') {
      document.querySelectorAll('.grfuel-price-chart').forEach(closeTooltip);
    }
  });
  document.addEventListener('scroll', event => {
    const scroller = event.target instanceof Element ? event.target.closest('.grfuel-chart-scroll') : null;
    if (scroller) closeTooltip(scroller.closest('.grfuel-price-chart'));
  }, true);
})();


// alpha.17: page through mobile chart windows without squeezing all dates together.
(() => {
  const closeMobileTooltip = root => {
    root.querySelectorAll('.grfuel-chart-tooltip.is-visible').forEach(t => {
      t.classList.remove('is-visible');
      t.setAttribute('aria-hidden', 'true');
    });
    root.querySelectorAll('.grfuel-chart-point circle.is-active').forEach(p => p.classList.remove('is-active'));
  };
  const showPage = (root, index) => {
    const pages = [...root.querySelectorAll('[data-grfuel-mobile-page]')];
    if (!pages.length) return;
    const next = Math.max(0, Math.min(pages.length - 1, index));
    closeMobileTooltip(root);
    pages.forEach((page, i) => { page.hidden = i !== next; });
    root.dataset.currentPage = String(next);
    const current = root.querySelector('[data-grfuel-mobile-current]');
    if (current) current.textContent = String(next + 1);
    const prev = root.querySelector('[data-grfuel-mobile-prev]');
    const nextButton = root.querySelector('[data-grfuel-mobile-next]');
    if (prev) prev.disabled = next === 0;
    if (nextButton) nextButton.disabled = next === pages.length - 1;
  };
  document.addEventListener('click', event => {
    const prev = event.target.closest('[data-grfuel-mobile-prev]');
    const next = event.target.closest('[data-grfuel-mobile-next]');
    const button = prev || next;
    if (!button) return;
    const root = button.closest('[data-grfuel-mobile-pages]');
    if (!root) return;
    const current = Number(root.dataset.currentPage || 0);
    showPage(root, current + (next ? 1 : -1));
  });
  const boot = () => document.querySelectorAll('[data-grfuel-mobile-pages]').forEach(root => showPage(root, 0));
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once:true}); else boot();
})();

// alpha.26: dependent delivery region/city selectors.
(() => {
  const initDeliveryLocations = () => {
    const locations = window.sfvFront?.deliveryLocations || {};
    document.querySelectorAll('.sfv-form').forEach(form => {
      form.querySelectorAll('[data-sfv-location-city]').forEach(city => {
        const regionKey = city.dataset.sfvRegionField || '';
        const region = regionKey ? form.querySelector(`[name="sfv_fields[${CSS.escape(regionKey)}]"]`) : form.querySelector('[data-sfv-location-region]');
        if (!region) return;
        const placeholder = city.dataset.sfvPlaceholder || 'Επιλέξτε πόλη…';
        const updateCities = () => {
          const selected = city.value;
          const cities = Array.isArray(locations[region.value]) ? locations[region.value] : [];
          city.innerHTML = `<option value="">${placeholder}</option>` + cities.map(name => `<option value="${String(name).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;')}">${String(name).replace(/&/g,'&amp;').replace(/</g,'&lt;')}</option>`).join('');
          city.disabled = !cities.length;
          if (cities.includes(selected)) city.value = selected;
          city.dispatchEvent(new Event('change', { bubbles: true }));
        };
        region.addEventListener('change', updateCities);
        updateCities();
      });
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDeliveryLocations);
  else initDeliveryLocations();
})();


// GRFuel Manager 4.3.16 — temporary radio-option override.
(() => {
  const initMessageOnlyOptions = root => {
    (root || document).querySelectorAll('.sfv-form').forEach(form => {
      form.querySelectorAll('[data-sfv-message-only-option]').forEach(triggerBox => {
        if (triggerBox.dataset.sfvMessageOnlyReady === '1') return;
        triggerBox.dataset.sfvMessageOnlyReady = '1';
        const key = triggerBox.dataset.sfvField || '';
        const blockedValue = triggerBox.dataset.sfvMessageOnlyOption || '';
        const notice = triggerBox.querySelector('.sfv-message-only-notice');
        const controls = Array.from(form.querySelectorAll(`[name="sfv_fields[${window.CSS?.escape ? CSS.escape(key) : key}]"]`));
        if (!key || !blockedValue || !controls.length || !notice) return;

        const otherFields = Array.from(form.querySelectorAll('.sfv-field')).filter(field => field !== triggerBox);
        const update = () => {
          const selected = controls.find(control => control.checked)?.value || '';
          const active = selected === blockedValue;
          notice.hidden = !active;
          triggerBox.classList.toggle('is-message-only-active', active);
          form.classList.toggle('sfv-message-only-active', active);
          otherFields.forEach(field => {
            field.classList.toggle('sfv-message-only-hidden', active);
            field.querySelectorAll('input,select,textarea,button').forEach(control => {
              if (active) {
                if (!('sfvMessageOnlyDisabled' in control.dataset)) control.dataset.sfvMessageOnlyDisabled = control.disabled ? '1' : '0';
                if (!('sfvMessageOnlyRequired' in control.dataset)) control.dataset.sfvMessageOnlyRequired = control.required ? '1' : '0';
                control.disabled = true;
                control.required = false;
              } else if ('sfvMessageOnlyDisabled' in control.dataset) {
                control.disabled = control.dataset.sfvMessageOnlyDisabled === '1';
                control.required = control.dataset.sfvMessageOnlyRequired === '1';
                delete control.dataset.sfvMessageOnlyDisabled;
                delete control.dataset.sfvMessageOnlyRequired;
              }
            });
          });
          form.dispatchEvent(new CustomEvent('sfv:availability-override', { bubbles: true, detail: { active, field: key, value: selected } }));
        };
        controls.forEach(control => control.addEventListener('change', update));
        update();
      });
    });
  };
  const boot = () => initMessageOnlyOptions(document);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
