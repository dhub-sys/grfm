(() => {
  const root = document.getElementById('sfv-builder');
  const hidden = document.getElementById('sfv_fields_json');
  if (!root || !hidden) return;

  const fieldTypes = [
    { type: 'text', label: 'Κείμενο', icon: 'T', group: 'fields' },
    { type: 'email', label: 'Email', icon: '@', group: 'fields' },
    { type: 'phone', label: 'Τηλέφωνο', icon: '☎', group: 'fields' },
    { type: 'number', label: 'Αριθμός / Ποσότητα', icon: '#', group: 'fields' },
    { type: 'date', label: 'Ημερομηνία', icon: '▣', group: 'fields' },
    { type: 'dropdown', label: 'Dropdown', icon: '▾', group: 'fields' },
    { type: 'delivery_region', label: 'Νομός παράδοσης', icon: 'Ν', group: 'fields' },
    { type: 'delivery_city', label: 'Πόλη παράδοσης', icon: 'Π', group: 'fields' },
    { type: 'radio', label: 'Radio buttons', icon: '◉', group: 'fields' },
    { type: 'checkbox', label: 'Πλαίσιο επιλογής', icon: '✓', group: 'fields' },
    { type: 'textarea', label: 'Κείμενοarea', icon: '¶', group: 'fields' },
    { type: 'heading', label: 'Τίτλος', icon: 'H', group: 'content' },
    { type: 'content_text', label: 'Κείμενο', icon: '≡', group: 'content' },
    { type: 'divider', label: 'Διαχωριστική γραμμή', icon: '—', group: 'content' },
    { type: 'price_calculator', label: 'Product price calculator', icon: '€', group: 'content' },
    { type: 'single_price_calculator', label: 'Single product calculator', icon: 'Σ', group: 'content' },
    { type: 'turnstile', label: 'Turnstile', icon: '◈', group: 'actions', unique: true },
    { type: 'submit', label: 'Κουμπί υποβολής', icon: '→', group: 'actions', unique: true }
  ];
  const types = fieldTypes.map(item => item.type);
  const inputTypes = ['text', 'email', 'phone', 'number', 'date', 'dropdown', 'radio', 'delivery_region', 'delivery_city', 'checkbox', 'textarea'];
  const contentTypes = ['heading', 'content_text', 'divider', 'price_calculator', 'single_price_calculator'];
  const widths = ['1-1', '1-2', '1-3', '2-3'];
  const breakpoints = [{ value: 's', label: 'Small' }, { value: 'm', label: 'Medium' }];
  const ops = ['equals', 'not_equals', 'contains', 'checked', 'not_checked', 'empty', 'not_empty'];
  let fields = [];
  let draggingFieldIndex = null;
  try { fields = JSON.parse(root.dataset.fields || '[]'); } catch (e) { fields = []; }
  fields = fields.map(field => {
    if (field && field.type === 'date') {
      if (typeof field.hide_label === 'undefined') field.hide_label = true;
      if (!String(field.placeholder || '').trim()) field.placeholder = 'Επιθυμητή ημερομηνία παράδοσης';
    }
    return field;
  });

  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));
  const optionList = (values, selected) => values.map(value =>
    `<option value="${esc(value)}" ${value === selected ? 'selected' : ''}>${esc(value)}</option>`
  ).join('');
  const syncConfirmationEmailFields = () => {
    const select = document.getElementById('sfv_confirmation_email_field');
    if (!select) return;
    const selected = select.value || select.dataset.selected || '';
    const emails = fields.filter(field => field && field.type === 'email');
    select.innerHTML = '<option value="">Χωρίς αποστολή email</option>' + emails.map(field => `<option value="${esc(field.key)}">${esc(field.label || 'Email')} · ${esc(field.key)}</option>`).join('');
    if (emails.some(field => field.key === selected)) select.value = selected;
    select.dataset.selected = select.value;
  };
  const writeHidden = () => { hidden.value = JSON.stringify(fields); syncConfirmationEmailFields(); };
  const slugifyName = value => String(value || '').toLowerCase().trim().replace(/[^a-z0-9_\-]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 64);
  const uniqueName = (value, currentIndex) => {
    const base = slugifyName(value) || `field_${currentIndex + 1}`;
    let candidate = base; let suffix = 2;
    while (fields.some((item, index) => index !== currentIndex && item.key === candidate)) candidate = `${base}_${suffix++}`.slice(0, 64);
    return candidate;
  };

  const normalizeLayout = field => {
    if (field.layout && widths.includes(field.layout.width) && ['s', 'm'].includes(field.layout.breakpoint)) return field.layout;
    return { width: '1-1', breakpoint: 's' };
  };
  const typeInfo = type => fieldTypes.find(item => item.type === type) || fieldTypes[0];
  const canAdd = type => !typeInfo(type).unique || !fields.some(field => field.type === type);

  const newField = type => {
    const base = {
      key: 'field_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
      type: types.includes(type) ? type : 'text',
      label: typeInfo(type).label,
      placeholder: type === 'date' ? 'Επιθυμητή ημερομηνία παράδοσης' : (type === 'delivery_region' ? 'Επιλέξτε νομό…' : (type === 'delivery_city' ? 'Επιλέξτε πόλη…' : '')), required: false, hide_label: type === 'date', options: [], content: '',
      heading_tag: 'h2', heading_style: 'default', divider_style: 'default',
      layout: { width: '1-1', breakpoint: 's' },
      dependency: { field: '', operator: 'equals', value: '' },
      product_field: '', product_label: 'Πετρέλαιο θέρμανσης', quantity_field: '', pricing_rows: [], currency: '€', unit_label: 'λίτρο', total_label: 'Σύνολο', calculator_placeholder: 'Αυτόματος υπολογισμός κόστους παραγγελίας',
      location_region_field: '',
      message_only_enabled: false,
      message_only_option: '',
      message_only_text: 'Οι παραδόσεις σε οικιακούς πελάτες αφορούν αποκλειστικά πετρέλαιο θέρμανσης, το οποίο δεν είναι διαθέσιμο αυτή την περίοδο.',
      checkbox_value: type === 'checkbox' ? 'Συμφωνία με την Πολιτική Απορρήτου' : '', suffix: type === 'number' ? 'lt' : ''
    };
    if (type === 'delivery_region') base.label = 'Νομός παράδοσης';
    if (type === 'delivery_city') base.label = 'Πόλη παράδοσης';
    if (type === 'submit') base.label = 'Υποβολή';
    if (type === 'turnstile') base.label = 'Security verification';
    if (type === 'heading') { base.label = 'Τίτλος'; base.content = 'Τίτλος'; }
    if (type === 'content_text') { base.label = 'Κείμενο'; base.content = 'Ελεύθερο κείμενο'; }
    if (type === 'divider') base.label = 'Διαχωριστική γραμμή';
    if (type === 'price_calculator') base.label = 'Product price calculator';
    if (type === 'single_price_calculator') base.label = 'Single product calculator';
    return base;
  };

  function preview(field) {
    const label = esc(field.label || typeInfo(field.type).label);
    const hideLabel = Boolean(field.hide_label) && ['text', 'email', 'phone', 'date', 'number', 'dropdown', 'delivery_region', 'delivery_city', 'textarea'].includes(field.type);
    const visiblePlaceholder = hideLabel ? esc((field.placeholder || field.label || typeInfo(field.type).label) + (field.required ? ' *' : '')) : esc(field.placeholder);
    if (field.type === 'heading') { const style = field.heading_style && field.heading_style !== 'default' ? ` uk-heading-${esc(field.heading_style)}` : ''; return `<div class="sfv-preview-content-heading${style}">${esc(field.content || 'Τίτλος')}</div>`; }
    if (field.type === 'content_text') return `<div class="sfv-preview-content-text">${esc(field.content || 'Ελεύθερο κείμενο').replace(/\n/g, '<br>')}</div>`;
    if (['price_calculator','single_price_calculator'].includes(field.type)) return `<div class="sfv-preview-price ${field.hide_label ? 'sfv-preview-price-no-label' : ''}">${field.hide_label ? '' : `<span>${esc(field.total_label || 'Σύνολο')}</span>`}<strong>545,00 €</strong><small>500 × 1,09 €/λίτρο</small></div>`;
    if (field.type === 'divider') return `<hr class="sfv-preview-divider ${field.divider_style === 'small' ? 'uk-divider-small' : field.divider_style === 'icon' ? 'uk-divider-icon' : ''}">`;
    if (field.type === 'submit') return `<button type="button" class="uk-button uk-button-primary sfv-preview-submit" tabindex="-1">${label}</button>`;
    if (field.type === 'turnstile') return `<div class="sfv-preview-turnstile"><span>Cloudflare</span><strong>Turnstile</strong></div>`;
    if (field.type === 'radio') return `<fieldset class="sfv-preview-radio"><legend>${label}</legend><label><input type="radio" disabled> Option 1</label><label><input type="radio" disabled> Option 2</label></fieldset>`;
    if (field.type === 'checkbox') return `<label class="sfv-preview-checkbox"><input type="checkbox" disabled> ${label}</label>`;
    if (field.type === 'textarea') return `<label class="${hideLabel ? 'sfv-preview-label-hidden' : ''}">${hideLabel ? '' : label}<textarea disabled placeholder="${visiblePlaceholder}" aria-label="${label}"></textarea></label>`;
    if (field.type === 'delivery_region') return `<label class="${hideLabel ? 'sfv-preview-label-hidden' : ''}">${hideLabel ? '' : label}<select disabled aria-label="${label}"><option>${esc(field.placeholder || 'Επιλέξτε νομό…')}</option></select></label>`;
    if (field.type === 'delivery_city') return `<label class="${hideLabel ? 'sfv-preview-label-hidden' : ''}">${hideLabel ? '' : label}<select disabled aria-label="${label}"><option>${esc(field.placeholder || 'Επιλέξτε πόλη…')}</option></select></label>`;
    if (field.type === 'dropdown') return `<label class="${hideLabel ? 'sfv-preview-label-hidden' : ''}">${hideLabel ? '' : label}<select disabled aria-label="${label}"><option>${hideLabel ? visiblePlaceholder : esc(field.placeholder || 'Λίστα επιλογών…')}</option></select></label>`;
    const previewType = field.type === 'phone' ? 'tel' : (field.type === 'date' ? 'text' : field.type);
    const dateClass = field.type === 'date' ? ' class="sfv-preview-date"' : '';
    if (field.type === 'number') return `<label class="${hideLabel ? 'sfv-preview-label-hidden' : ''}">${hideLabel ? '' : label}<span class="sfv-preview-affix"><input disabled type="number" placeholder="${visiblePlaceholder}" aria-label="${label}"><span>${esc(field.suffix || 'lt')}</span></span></label>`;
    return `<label class="${hideLabel ? 'sfv-preview-label-hidden' : ''}">${hideLabel ? '' : label}<input${dateClass} disabled type="${previewType}" placeholder="${visiblePlaceholder}" aria-label="${label}"></label>`;
  }


  function pricingEditorRows(field, single = false) {
    const source = Array.isArray(field.pricing_rows) ? field.pricing_rows : [];
    const rows = [];
    source.forEach(row => {
      const product = String(row.product || field.product_label || '');
      rows.push({ product, quantity: '0', price: row.base ?? '' });
      (Array.isArray(row.tiers) ? row.tiers : []).forEach(tier => rows.push({ product, quantity: tier.min ?? '', price: tier.price ?? '' }));
    });
    if (!rows.length) rows.push({ product: single ? (field.product_label || 'Πετρέλαιο θέρμανσης') : '', quantity: '0', price: '' });
    return rows;
  }

  // Optional link of a single-product calculator to a product of Τιμές Ημέρας.
  function dailyPriceLink(field) {
    const products = (window.sfvBuilder && Array.isArray(window.sfvBuilder.dailyPrices)) ? window.sfvBuilder.dailyPrices : [];
    const selected = String(field.daily_price_product || '');
    const linked = products.find(product => product.slug === selected);
    const options = [`<option value="">Χειροκίνητη τιμή (από τον πίνακα παρακάτω)</option>`]
      .concat(products.map(product => `<option value="${esc(product.slug)}" ${product.slug === selected ? 'selected' : ''}>Τιμή ημέρας: ${esc(product.name)} (${esc(product.price)} €)</option>`));
    if (selected && !linked) options.push(`<option value="${esc(selected)}" selected>Τιμή ημέρας: ${esc(selected)} (δεν βρέθηκε)</option>`);
    const note = linked
      ? `Η βασική τιμή (ποσότητα 0) παίρνεται αυτόματα από τις Τιμές Ημέρας: τώρα ${esc(linked.price)} €. Οι κλιμακωτές τιμές ακολουθούν με την ίδια διαφορά από τη βασική που ορίζεται στον πίνακα.`
      : (selected ? 'Το προϊόν δεν υπάρχει πλέον στις Τιμές Ημέρας ή έχει τιμή 0· χρησιμοποιείται η τιμή του πίνακα.' : 'Επιλέξτε προϊόν για να ενημερώνεται αυτόματα η τιμή από τις Τιμές Ημέρας.');
    return `<div class="sfv-daily-price-link"><label class="sfv-control sfv-control-select sfv-wide" data-hint="Πηγή τιμής"><span class="screen-reader-text">Πηγή τιμής</span><select data-k="daily_price_product" aria-label="Πηγή τιμής">${options.join('')}</select></label><small>${note}</small></div>`;
  }

  function pricingRowsEditor(field, single = false) {
    const rows = pricingEditorRows(field, single);
    return `<div class="sfv-pricing-table" data-pricing-editor="${single ? 'single' : 'product'}">
      <div class="sfv-pricing-table-head"><span>Προϊόν</span><span>Ποσότητα</span><span>Τιμή</span><span></span></div>
      <div class="sfv-pricing-table-body">${rows.map((row, rowIndex) => `<div class="sfv-pricing-row" data-pricing-row>
        <label class="sfv-control"><span class="screen-reader-text">Προϊόν</span><input data-pricing-col="product" value="${esc(row.product)}" placeholder="Προϊόν" ${single && rowIndex > 0 ? 'aria-label="Προϊόν"' : ''}></label>
        <label class="sfv-control"><span class="screen-reader-text">Ποσότητα</span><input data-pricing-col="quantity" inputmode="decimal" value="${esc(row.quantity)}" placeholder="0"></label>
        <label class="sfv-control"><span class="screen-reader-text">Τιμή</span><input data-pricing-col="price" inputmode="decimal" value="${esc(row.price)}" placeholder="1.09"></label>
        <button type="button" class="sfv-pricing-remove" data-pricing-remove title="Αφαίρεση σειράς" aria-label="Αφαίρεση σειράς">×</button>
      </div>`).join('')}</div>
      <button type="button" class="button sfv-pricing-add" data-pricing-add>+ Προσθήκη νέας σειράς</button>
      <small>Η ποσότητα 0 ορίζει τη βασική τιμή. Για κλιμακωτή τιμή προσθέτουμε νέα σειρά με την ελάχιστη ποσότητα.</small>
    </div>`;
  }

  function syncPricingEditor(card, index) {
    const editor = card.querySelector('[data-pricing-editor]');
    if (!editor) return;
    const single = editor.dataset.pricingEditor === 'single';
    const raw = Array.from(editor.querySelectorAll('[data-pricing-row]')).map(row => ({
      product: String(row.querySelector('[data-pricing-col="product"]')?.value || '').trim(),
      quantity: String(row.querySelector('[data-pricing-col="quantity"]')?.value || '').trim().replace(',', '.'),
      price: String(row.querySelector('[data-pricing-col="price"]')?.value || '').trim().replace(',', '.')
    })).filter(row => row.product || row.quantity || row.price);
    const grouped = new Map();
    raw.forEach(row => {
      const product = row.product || (single ? (fields[index].product_label || 'Πετρέλαιο θέρμανσης') : '');
      if (!product || row.price === '') return;
      if (!grouped.has(product)) grouped.set(product, { product, base: '', tiers: [] });
      const target = grouped.get(product);
      const quantity = Number(row.quantity || 0);
      if (!quantity || quantity <= 0) target.base = row.price;
      else target.tiers.push({ min: row.quantity, price: row.price });
    });
    let rows = Array.from(grouped.values()).map(row => {
      row.tiers.sort((a,b) => Number(a.min) - Number(b.min));
      if (row.base === '' && row.tiers.length) row.base = row.tiers[0].price;
      return row;
    });
    if (single && rows.length) {
      rows = [rows[0]];
      fields[index].product_label = rows[0].product;
    }
    fields[index].pricing_rows = rows;
    writeHidden();
  }

  function fieldCard(field, index) {
    field.layout = normalizeLayout(field);
    field.dependency = field.dependency || { field: '', operator: 'equals', value: '' };
    const info = typeInfo(field.type);
    const isInput = inputTypes.includes(field.type);
    const span = field.layout.width === '1-2' ? 3 : field.layout.width === '1-3' ? 2 : field.layout.width === '2-3' ? 4 : 6;
    return `<details class="sfv-field-card sfv-type-${esc(field.type)}" data-i="${index}" style="--sfv-span:${span}">
      <summary>
        <span class="sfv-field-drag" draggable="true" title="Drag to move" aria-label="Drag to move">⋮⋮</span>
        <span class="sfv-field-title"><strong>${esc(field.label || info.label)}</strong><small>${esc(info.label)}</small></span>
        <span class="sfv-field-width">${field.type === 'radio' && field.message_only_enabled ? 'Μήνυμα μη διαθεσιμότητας ενεργό · ' : ''}${esc(field.layout.width)} · ${field.layout.breakpoint === 'm' ? 'Medium' : 'Small'}</span>
        <span class="sfv-chevron" aria-hidden="true"></span>
      </summary>
      <div class="sfv-field-preview">${preview(field)}</div>
      <div class="sfv-field-settings">
        <div class="sfv-settings-grid">
          ${isInput ? `<label class="sfv-control sfv-control-select" data-hint="Τύπος πεδίου"><span class="screen-reader-text">Type</span><select data-k="type" aria-label="Type" title="Τύπος πεδίου">${optionList(inputTypes, field.type)}</select></label>` : ''}
          ${field.type === 'heading' ? `<label class="sfv-wide sfv-control" data-hint="Κείμενο heading"><span class="screen-reader-text">Τίτλος text</span><input data-k="content" value="${esc(field.content)}" placeholder="Κείμενο heading" aria-label="Τίτλος text"></label><label class="sfv-control sfv-control-select"><span class="screen-reader-text">HTML heading</span><select data-k="heading_tag" aria-label="HTML heading">${optionList(['h1','h2','h3','h4','h5','h6'], field.heading_tag || 'h2')}</select></label><label class="sfv-control sfv-control-select"><span class="screen-reader-text">YOOtheme heading style</span><select data-k="heading_style" aria-label="YOOtheme heading style">${[['default','Default'],['small','Small'],['medium','Medium'],['large','Large'],['xlarge','X-Large'],['2xlarge','2X-Large'],['3xlarge','3X-Large']].map(([v,t]) => `<option value="${v}" ${v === (field.heading_style || 'default') ? 'selected' : ''}>${t}</option>`).join('')}</select></label>` : field.type === 'content_text' ? `<label class="sfv-wide sfv-control"><span class="screen-reader-text">Ελεύθερο κείμενο</span><textarea data-k="content" placeholder="Ελεύθερο κείμενο" aria-label="Ελεύθερο κείμενο">${esc(field.content)}</textarea></label>` : field.type === 'divider' ? `<label class="sfv-control sfv-control-select"><span class="screen-reader-text">Διαχωριστική γραμμή style</span><select data-k="divider_style" aria-label="Διαχωριστική γραμμή style">${[['default','Default'],['small','Small'],['icon','Icon']].map(([v,t]) => `<option value="${v}" ${v === (field.divider_style || 'default') ? 'selected' : ''}>${t}</option>`).join('')}</select></label>` : field.type === 'price_calculator' ? `<div class="sfv-wide sfv-pricing-settings"><div class="sfv-pricing-grid"><label class="sfv-control sfv-control-select"><select data-k="product_field"><option value="">Dropdown προϊόντος</option>${fields.filter(x=>x.type==='dropdown'||x.type==='radio').map(x=>`<option value="${esc(x.key)}" ${x.key===field.product_field?'selected':''}>${esc(x.label)} · ${esc(x.key)}</option>`).join('')}</select></label><label class="sfv-control sfv-control-select"><select data-k="quantity_field"><option value="">Πεδίο ποσότητας</option>${fields.filter(x=>x.type==='number'||x.type==='text').map(x=>`<option value="${esc(x.key)}" ${x.key===field.quantity_field?'selected':''}>${esc(x.label)} · ${esc(x.key)}</option>`).join('')}</select></label><label class="sfv-control"><input data-k="total_label" value="${esc(field.total_label||'Σύνολο')}" placeholder="Τίτλος συνόλου"></label><label class="sfv-control"><input data-k="currency" value="${esc(field.currency||'€')}" placeholder="Νόμισμα"></label><label class="sfv-control"><input data-k="unit_label" value="${esc(field.unit_label||'λίτρο')}" placeholder="Μονάδα"></label></div>${pricingRowsEditor(field, false)}</div>` : field.type === 'single_price_calculator' ? `<div class="sfv-wide sfv-pricing-settings"><div class="sfv-pricing-grid"><label class="sfv-control sfv-control-select"><select data-k="quantity_field"><option value="">Πεδίο ποσότητας</option>${fields.filter(x=>x.type==='number'||x.type==='text').map(x=>`<option value="${esc(x.key)}" ${x.key===field.quantity_field?'selected':''}>${esc(x.label)} · ${esc(x.key)}</option>`).join('')}</select></label><label class="sfv-control"><input data-k="total_label" value="${esc(field.total_label||'Σύνολο')}" placeholder="Τίτλος συνόλου"></label><label class="sfv-control"><input data-k="currency" value="${esc(field.currency||'€')}" placeholder="Νόμισμα"></label><label class="sfv-control"><input data-k="unit_label" value="${esc(field.unit_label||'λίτρο')}" placeholder="Μονάδα"></label><label class="sfv-control sfv-wide"><input data-k="calculator_placeholder" value="${esc(field.calculator_placeholder||'Αυτόματος υπολογισμός κόστους παραγγελίας')}" placeholder="Κείμενο πριν τον υπολογισμό"></label></div>${dailyPriceLink(field)}${pricingRowsEditor(field, true)}</div>` : `<label class="sfv-control" data-hint="${field.type === 'submit' ? 'Κείμενο κουμπιού' : 'Ετικέτα πεδίου'}"><span class="screen-reader-text">${field.type === 'submit' ? 'Button text' : 'Label'}</span><input data-k="label" value="${esc(field.label)}" placeholder="${field.type === 'submit' ? 'Κείμενο κουμπιού' : 'Ετικέτα πεδίου'}" aria-label="${field.type === 'submit' ? 'Button text' : 'Label'}" title="${field.type === 'submit' ? 'Κείμενο κουμπιού' : 'Ετικέτα πεδίου'}"></label>`}
          ${field.type === 'checkbox' ? `<label class="sfv-wide sfv-control" data-hint="Η σύντομη τιμή που θα αποθηκεύεται όταν το checkbox επιλεγεί"><span class="screen-reader-text">Τιμή υποβολής</span><input data-k="checkbox_value" value="${esc(field.checkbox_value || 'Συμφωνία με την Πολιτική Απορρήτου')}" placeholder="Τιμή υποβολής, π.χ. Αποδοχή Πολιτικής Απορρήτου" aria-label="Τιμή υποβολής" title="Αποθηκεύεται στα submissions, PDF, XLSX και XML όταν το checkbox είναι επιλεγμένο."></label><small class="sfv-wide">Στο κείμενο του checkbox επιτρέπεται ασφαλής σύνδεσμος <code>&lt;a href=&quot;...&quot; target=&quot;_blank&quot;&gt;...&lt;/a&gt;</code>.</small>` : ''}
          ${['price_calculator','single_price_calculator'].includes(field.type) ? `<label class="sfv-required-control sfv-hide-label-control" title="Αποκρύπτει τον τίτλο του συνόλου."><input data-k="hide_label" type="checkbox" ${field.hide_label ? 'checked' : ''}><span class="sfv-required-box" aria-hidden="true"></span><span>Απόκρυψη label</span></label>` : ''}
          ${isInput ? `<label class="sfv-control" data-hint="Μοναδικό τεχνικό όνομα για dependencies και αποθήκευση"><span class="screen-reader-text">Field name</span><input data-k="key" value="${esc(field.key)}" maxlength="64" spellcheck="false" placeholder="Field name, π.χ. company_name" aria-label="Field name" title="Χρησιμοποιείται στα dependencies και στις υποβολές. Επιτρέπονται λατινικά, αριθμοί, _ και -."></label>` : ''}
          ${isInput && !['checkbox'].includes(field.type) ? `<label class="sfv-control" data-hint="Κείμενο που εμφανίζεται μέσα στο κενό πεδίο"><span class="screen-reader-text">Placeholder</span><input data-k="placeholder" value="${esc(field.placeholder)}" placeholder="Placeholder (προαιρετικό)" aria-label="Placeholder" title="Κείμενο που εμφανίζεται μέσα στο κενό πεδίο"></label>` : ''}
          ${field.type === 'number' ? `<label class="sfv-control" data-hint="Μονάδα που εμφανίζεται δεξιά από τον αριθμό"><span class="screen-reader-text">Suffix</span><input data-k="suffix" value="${esc(field.suffix || 'lt')}" placeholder="Μονάδα, π.χ. lt" aria-label="Suffix" title="Εμφανίζεται μόνο οπτικά και δεν αποθηκεύεται μαζί με την αριθμητική τιμή."></label>` : ''}
          ${isInput ? `<label class="sfv-required-control" title="Όταν είναι ενεργό, η συμπλήρωση του πεδίου είναι υποχρεωτική"><input data-k="required" type="checkbox" ${field.required ? 'checked' : ''}><span class="sfv-required-box" aria-hidden="true"></span><span>Required</span></label>` : ''}
          ${['text', 'email', 'phone', 'date', 'number', 'dropdown', 'delivery_region', 'delivery_city', 'textarea'].includes(field.type) ? `<label class="sfv-required-control sfv-hide-label-control" title="Αποκρύπτει την ορατή ετικέτα. Αν το πεδίο είναι υποχρεωτικό, ο αστερίσκος εμφανίζεται στο placeholder."><input data-k="hide_label" type="checkbox" ${field.hide_label ? 'checked' : ''}><span class="sfv-required-box" aria-hidden="true"></span><span>Απόκρυψη label</span></label>` : ''}
          ${field.type === 'delivery_city' ? `<label class="sfv-wide sfv-control sfv-control-select" data-hint="Το πεδίο Νομού που ελέγχει τις πόλεις"><span class="screen-reader-text">Πεδίο νομού</span><select data-k="location_region_field"><option value="">Επιλέξτε πεδίο Νομού</option>${fields.filter(x=>x.type==='delivery_region').map(x=>`<option value="${esc(x.key)}" ${x.key===field.location_region_field?'selected':''}>${esc(x.label)} · ${esc(x.key)}</option>`).join('')}</select></label>` : ''}
          ${['dropdown', 'radio'].includes(field.type) ? `<label class="sfv-wide sfv-control" data-hint="Επιλογές χωρισμένες με κόμμα"><span class="screen-reader-text">Options</span><input data-k="options" value="${esc((field.options || []).join(', '))}" placeholder="Επιλογές, χωρισμένες με κόμμα" aria-label="Options" title="Επιλογές χωρισμένες με κόμμα"></label>` : ''}
          ${field.type === 'radio' ? `<fieldset class="sfv-wide sfv-settings-section sfv-message-only-settings"><legend><span class="dashicons dashicons-randomize" aria-hidden="true"></span> Λογική επιλογής</legend><div class="sfv-message-only-heading"><strong>Προσωρινή μη διαθεσιμότητα</strong><span class="sfv-message-only-badge">Υπερισχύει των dependencies</span></div><p class="sfv-message-only-intro">Ενεργοποιήστε τη λειτουργία για μία συγκεκριμένη επιλογή του radio. Όταν επιλεγεί, τα υπόλοιπα πεδία και το κουμπί υποβολής κρύβονται και εμφανίζεται μόνο το μήνυμα που ορίζετε παρακάτω.</p><label class="sfv-required-control sfv-message-only-toggle"><input data-k="message_only_enabled" type="checkbox" ${field.message_only_enabled ? 'checked' : ''}><span class="sfv-required-box" aria-hidden="true"></span><span>Ενεργοποίηση προσωρινής μη διαθεσιμότητας</span></label><label class="sfv-control sfv-control-select"><span class="sfv-visible-control-label">Επιλογή που θα απενεργοποιηθεί</span><select data-k="message_only_option" ${field.message_only_enabled ? '' : 'disabled'}><option value="">Επιλέξτε επιλογή</option>${(field.options || []).map(option => `<option value="${esc(option)}" ${option === field.message_only_option ? 'selected' : ''}>${esc(option)}</option>`).join('')}</select></label><label class="sfv-wide sfv-control"><span class="sfv-visible-control-label">Μήνυμα προς τον χρήστη</span><textarea data-k="message_only_text" placeholder="Ενημερωτικό μήνυμα" ${field.message_only_enabled ? '' : 'disabled'}>${esc(field.message_only_text || 'Οι παραδόσεις σε οικιακούς πελάτες αφορούν αποκλειστικά πετρέλαιο θέρμανσης, το οποίο δεν είναι διαθέσιμο αυτή την περίοδο.')}</textarea></label><small class="sfv-wide">Η ρύθμιση δεν διαγράφει ή αλλάζει κανένα dependency. Αν την απενεργοποιήσετε και αποθηκεύσετε τη φόρμα, η κανονική ροή επανέρχεται ακριβώς όπως ήταν.</small></fieldset>` : ''}
          <fieldset class="sfv-wide sfv-settings-section"><legend>Layout</legend><div class="sfv-widths">
            <label class="sfv-control sfv-control-select" data-hint="Πλάτος πεδίου"><span class="screen-reader-text">Width</span><select data-layout="width" aria-label="Width" title="Πλάτος πεδίου">${optionList(widths, field.layout.width)}</select></label>
            <label class="sfv-control sfv-control-select" data-hint="Responsive breakpoint"><span class="screen-reader-text">Breakpoint</span><select data-layout="breakpoint" aria-label="Breakpoint" title="Responsive breakpoint">${breakpoints.map(bp => `<option value="${bp.value}" ${bp.value === field.layout.breakpoint ? 'selected' : ''}>${bp.label}</option>`).join('')}</select></label>
          </div></fieldset>
          ${(isInput || ['price_calculator','single_price_calculator'].includes(field.type)) ? `<fieldset class="sfv-wide sfv-settings-section"><legend>Dependency</legend><div class="sfv-deps">
            <label class="sfv-control sfv-control-select" data-hint="Πεδίο που ελέγχει την εμφάνιση"><span class="screen-reader-text">Source field</span><select data-dep="field" aria-label="Source field" title="Πεδίο που ελέγχει την εμφάνιση"><option value="">Πάντα ορατό</option>${fields.filter((item, other) => other !== index && inputTypes.includes(item.type)).map(item => `<option value="${esc(item.key)}" ${item.key === field.dependency.field ? 'selected' : ''}>${esc(item.label || typeInfo(item.type).label)} · ${esc(item.key)}</option>`).join('')}</select></label>
            <label class="sfv-control sfv-control-select" data-hint="Συνθήκη σύγκρισης"><span class="screen-reader-text">Operator</span><select data-dep="operator" aria-label="Operator" title="Συνθήκη σύγκρισης">${optionList(ops, field.dependency.operator)}</select></label>
            <label class="sfv-control" data-hint="Τιμή που ενεργοποιεί τη συνθήκη"><span class="screen-reader-text">Value</span><input data-dep="value" value="${esc(field.dependency.value)}" placeholder="Τιμή dependency" aria-label="Dependency value" title="Τιμή που ενεργοποιεί τη συνθήκη"></label>
          </div></fieldset>` : ''}
        </div>
        <div class="sfv-field-actions"><button type="button" class="button-link-delete" data-delete="${index}">Remove στοιχείο</button></div>
      </div>
    </details>`;
  }

  function paletteGroup(group, title) {
    return `<div class="sfv-palette-group"><h4>${title}</h4>${fieldTypes.filter(item => item.group === group).map(item => {
      const disabled = !canAdd(item.type);
      return `<button type="button" class="sfv-palette-item" draggable="${disabled ? 'false' : 'true'}" data-new-type="${item.type}" ${disabled ? 'disabled' : ''}><span>${item.icon}</span><b>${item.label}</b>${item.unique ? '<small>one only</small>' : ''}</button>`;
    }).join('')}</div>`;
  }

  function render(openIndex = null) {
    root.innerHTML = `<div class="sfv-builder-toolbar"><div><strong>Οπτικός σχεδιαστής φόρμας</strong><span>Δημιουργήστε οπτικά τη διάταξη και σύρετε τις κάρτες για να αλλάξετε τη σειρά εμφάνισης.</span></div><span class="sfv-count">${fields.length} ${fields.length === 1 ? 'στοιχείο' : 'στοιχεία'}</span></div>
      <div class="sfv-builder-layout">
      <aside class="sfv-palette" aria-label="Available fields"><h3>Προσθήκη στοιχείων</h3><p class="description">Σύρετε στον καμβά ή πατήστε μία φορά.</p>${paletteGroup('fields', 'Form fields')}${paletteGroup('content', 'Content στοιχείοs')}${paletteGroup('actions', 'Form actions')}</aside>
      <section class="sfv-canvas-wrap"><div class="sfv-canvas-heading"><h3>Διάταξη φόρμας</h3><span>1/1 · 1/2 · 1/3 · 2/3</span></div>
        <div class="sfv-canvas ${fields.length ? '' : 'is-empty'}" data-dropzone="true">${fields.length ? fields.map(fieldCard).join('') : '<div class="sfv-empty-state"><strong>Αφήστε τα στοιχεία εδώ</strong><span>Ο καμβάς προβάλλει το πραγματικό πλάτος κάθε πεδίου.</span></div>'}</div>
      </section></div>`;
    if (openIndex !== null) {
      const card = root.querySelector(`.sfv-field-card[data-i="${openIndex}"]`);
      if (card) card.open = true;
    }
    bindEvents(); writeHidden();
  }

  function addField(type, atIndex = fields.length) {
    if (!canAdd(type)) return;
    const index = Math.max(0, Math.min(atIndex, fields.length));
    fields.splice(index, 0, newField(type)); render(index);
  }
  function dropIndexFromEvent(event) {
    const card = event.target.closest('.sfv-field-card');
    if (!card) return fields.length;
    const rect = card.getBoundingClientRect();
    const index = Number(card.dataset.i);
    return event.clientY > rect.top + rect.height / 2 ? index + 1 : index;
  }

  function bindEvents() {
    root.querySelectorAll('[data-new-type]:not(:disabled)').forEach(item => {
      item.addEventListener('click', () => addField(item.dataset.newType));
      item.addEventListener('dragstart', event => { event.dataTransfer.effectAllowed = 'copy'; event.dataTransfer.setData('application/x-sfv-field-type', item.dataset.newType); });
    });
    root.querySelectorAll('[data-k]').forEach(στοιχείο => {
      const update = event => {
        const card = event.target.closest('.sfv-field-card'); const index = Number(card.dataset.i); const key = event.target.dataset.k;
        if (key === 'key') {
          const previousKey = fields[index].key;
          const nextKey = uniqueName(event.target.value, index);
          fields[index].key = nextKey;
          event.target.value = nextKey;
          fields.forEach((item, itemIndex) => {
            if (itemIndex !== index && item.dependency && item.dependency.field === previousKey) item.dependency.field = nextKey;
          });
          writeHidden();
          render(index);
          return;
        }
        if (key === 'single_base_price' || key === 'single_tiers_text') {
          const existing = (fields[index].pricing_rows && fields[index].pricing_rows[0]) || { product: fields[index].product_label || 'Πετρέλαιο θέρμανσης', base: '', tiers: [] };
          if (key === 'single_base_price') existing.base = String(event.target.value || '').replace(',', '.');
          if (key === 'single_tiers_text') existing.tiers = String(event.target.value || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean).map(line => {
            const separator = line.indexOf(':');
            if (separator < 1) return null;
            const min = line.slice(0, separator).trim().replace(',', '.');
            const price = line.slice(separator + 1).trim().replace(',', '.');
            return min && price ? { min, price } : null;
          }).filter(Boolean);
          existing.product = fields[index].product_label || 'Πετρέλαιο θέρμανσης';
          fields[index].pricing_rows = [existing];
        } else if (key === 'pricing_text') {
          const rows = String(event.target.value || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean).map(line => {
            const parts = line.split('|').map(part => part.trim()).filter(Boolean);
            if (parts.length < 2) return null;
            const product = parts.shift();
            const base = String(parts.shift() || '').replace(',', '.');
            const tiers = parts.map(part => {
              const separator = part.indexOf(':');
              if (separator < 1) return null;
              const min = part.slice(0, separator).trim().replace(',', '.');
              const price = part.slice(separator + 1).trim().replace(',', '.');
              return min && price ? { min, price } : null;
            }).filter(Boolean);
            return product && base !== '' ? { product, base, tiers } : null;
          }).filter(Boolean);
          fields[index].pricing_rows = rows;
        } else {
          fields[index][key] = ['required', 'hide_label', 'message_only_enabled'].includes(key) ? event.target.checked : key === 'options' ? event.target.value.split(',').map(v => v.trim()).filter(Boolean) : event.target.value;
          if (key === 'message_only_enabled') render();
          if (key === 'daily_price_product') render(index);
          if (key === 'product_label' && fields[index].type === 'single_price_calculator' && fields[index].pricing_rows && fields[index].pricing_rows[0]) fields[index].pricing_rows[0].product = event.target.value;
        }
        if (key === 'type' && fields[index].type === 'date') {
          if (!String(fields[index].placeholder || '').trim()) fields[index].placeholder = 'Επιθυμητή ημερομηνία παράδοσης';
          fields[index].hide_label = true;
        }
        writeHidden();
        if (['label', 'content', 'placeholder', 'required', 'hide_label', 'heading_tag', 'heading_style', 'divider_style'].includes(key)) {
          card.querySelector('.sfv-field-title strong').textContent = contentTypes.includes(fields[index].type) ? (fields[index].content || typeInfo(fields[index].type).label) : (fields[index].label || typeInfo(fields[index].type).label);
          card.querySelector('.sfv-field-preview').innerHTML = preview(fields[index]);
        }
        if (key === 'type') render(index);
      };
      στοιχείο.addEventListener(στοιχείο.dataset.k === 'key' ? 'change' : ((['INPUT','TEXTAREA'].includes(στοιχείο.tagName) && στοιχείο.type !== 'checkbox') ? 'input' : 'change'), update);
    });
    root.querySelectorAll('[data-pricing-col]').forEach(στοιχείο => στοιχείο.addEventListener('input', event => {
      const card = event.target.closest('.sfv-field-card');
      syncPricingEditor(card, Number(card.dataset.i));
    }));
    root.querySelectorAll('[data-pricing-add]').forEach(button => button.addEventListener('click', event => {
      const card = event.target.closest('.sfv-field-card');
      const index = Number(card.dataset.i);
      const body = card.querySelector('.sfv-pricing-table-body');
      const firstProduct = card.querySelector('[data-pricing-col="product"]')?.value || (fields[index].product_label || 'Πετρέλαιο θέρμανσης');
      body.insertAdjacentHTML('beforeend', `<div class="sfv-pricing-row" data-pricing-row><label class="sfv-control"><span class="screen-reader-text">Προϊόν</span><input data-pricing-col="product" value="${esc(firstProduct)}" placeholder="Προϊόν"></label><label class="sfv-control"><span class="screen-reader-text">Ποσότητα</span><input data-pricing-col="quantity" inputmode="decimal" value="" placeholder="1000"></label><label class="sfv-control"><span class="screen-reader-text">Τιμή</span><input data-pricing-col="price" inputmode="decimal" value="" placeholder="1.08"></label><button type="button" class="sfv-pricing-remove" data-pricing-remove title="Αφαίρεση σειράς" aria-label="Αφαίρεση σειράς">×</button></div>`);
      const row = body.lastElementChild;
      row.querySelectorAll('[data-pricing-col]').forEach(el => el.addEventListener('input', () => syncPricingEditor(card, index)));
      row.querySelector('[data-pricing-remove]').addEventListener('click', () => { row.remove(); syncPricingEditor(card, index); });
      row.querySelector('[data-pricing-col="quantity"]').focus();
    }));
    root.querySelectorAll('[data-pricing-remove]').forEach(button => button.addEventListener('click', event => {
      const card = event.target.closest('.sfv-field-card');
      const index = Number(card.dataset.i);
      const rows = card.querySelectorAll('[data-pricing-row]');
      if (rows.length === 1) {
        rows[0].querySelectorAll('input').forEach(input => input.value = input.dataset.pricingCol === 'quantity' ? '0' : '');
      } else event.target.closest('[data-pricing-row]').remove();
      syncPricingEditor(card, index);
    }));
    root.querySelectorAll('[data-layout]').forEach(στοιχείο => στοιχείο.addEventListener('change', event => {
      const index = Number(event.target.closest('.sfv-field-card').dataset.i);
      fields[index].layout[event.target.dataset.layout] = event.target.value;
      render(index);
    }));
    root.querySelectorAll('[data-dep]').forEach(στοιχείο => στοιχείο.addEventListener('change', event => { const index = Number(event.target.closest('.sfv-field-card').dataset.i); fields[index].dependency[event.target.dataset.dep] = event.target.value; writeHidden(); }));
    root.querySelectorAll('[data-delete]').forEach(στοιχείο => στοιχείο.addEventListener('click', () => { fields.splice(Number(στοιχείο.dataset.delete), 1); render(); }));
    root.querySelectorAll('.sfv-field-drag').forEach(handle => {
      handle.addEventListener('click', event => event.preventDefault());
      handle.addEventListener('dragstart', event => { draggingFieldIndex = Number(handle.closest('.sfv-field-card').dataset.i); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('application/x-sfv-existing-field', String(draggingFieldIndex)); handle.closest('.sfv-field-card').classList.add('is-dragging'); });
      handle.addEventListener('dragend', () => { root.querySelectorAll('.is-dragging,.is-drag-over').forEach(node => node.classList.remove('is-dragging', 'is-drag-over')); draggingFieldIndex = null; });
    });
    const canvas = root.querySelector('.sfv-canvas');
    canvas.addEventListener('dragover', event => { event.preventDefault(); event.dataTransfer.dropEffect = draggingFieldIndex === null ? 'copy' : 'move'; root.querySelectorAll('.is-drag-over').forEach(node => node.classList.remove('is-drag-over')); const card = event.target.closest('.sfv-field-card'); (card || canvas).classList.add('is-drag-over'); });
    canvas.addEventListener('dragleave', event => { if (!canvas.contains(event.relatedTarget)) canvas.classList.remove('is-drag-over'); });
    canvas.addEventListener('drop', event => {
      event.preventDefault(); const targetIndex = dropIndexFromEvent(event); const newType = event.dataTransfer.getData('application/x-sfv-field-type');
      if (types.includes(newType)) { addField(newType, targetIndex); return; }
      const rawIndex = event.dataTransfer.getData('application/x-sfv-existing-field'); const fromIndex = rawIndex !== '' ? Number(rawIndex) : draggingFieldIndex;
      if (!Number.isInteger(fromIndex) || fromIndex < 0 || fromIndex >= fields.length) return;
      const [moved] = fields.splice(fromIndex, 1); const adjustedIndex = fromIndex < targetIndex ? targetIndex - 1 : targetIndex; fields.splice(Math.max(0, Math.min(adjustedIndex, fields.length)), 0, moved); render(adjustedIndex);
    });
  }
  render();
})();

(() => {
  const boot = () => {
    const button = document.querySelector('.grfuel-session-refresh');
    const toast = document.querySelector('.grfuel-session-toast');
    const countdown = button?.querySelector('.grfuel-session-countdown');
    if (!button || !toast) return;
    let timer;
    let expires = Number(button.dataset.expires || 0);
    const tick = () => {
      if (!countdown || !expires) return;
      const remaining = Math.max(0, expires - Math.floor(Date.now() / 1000));
      const hours = Math.floor(remaining / 3600);
      const minutes = Math.floor((remaining % 3600) / 60);
      const seconds = remaining % 60;
      countdown.textContent = [hours, minutes, seconds].map(value => String(value).padStart(2, '0')).join(':');
      button.setAttribute('aria-label', `Ανανέωση διάρκειας συνεδρίας. Απομένουν ${countdown.textContent}`);
      if (remaining === 0) {
        button.disabled = true;
        window.top.location.href = button.dataset.loginUrl || window.location.href;
      }
    };
    tick();
    window.setInterval(tick, 1000);
    const show = (message, error = false) => {
      toast.textContent = message;
      toast.classList.toggle('is-error', error);
      toast.classList.add('is-visible');
      clearTimeout(timer);
      timer = setTimeout(() => toast.classList.remove('is-visible'), 4200);
    };
    button.addEventListener('click', async () => {
      button.disabled = true;
      button.classList.add('is-loading');
      try {
        const body = new URLSearchParams({ action: 'grfuel_refresh_manager_session', nonce: button.dataset.nonce || '' });
        const response = await fetch(button.dataset.url || '', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data?.data?.message || 'Η ανανέωση δεν ολοκληρώθηκε.');
        expires = Number(data?.data?.expires || expires);
        button.dataset.expires = String(expires);
        tick();
        show(data.data.message || 'Η συνεδρία ανανεώθηκε.');
      } catch (error) { show(error.message || 'Η ανανέωση δεν ολοκληρώθηκε.', true); }
      finally { button.disabled = false; button.classList.remove('is-loading'); }
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true }); else boot();
})();

/* GRFuel Manager 4.3.18 — submit destructive row actions with POST. */
(() => {
  document.addEventListener('click', event => {
    const link = event.target.closest?.('a.sfv-delete-link');
    if (!link) return;
    event.preventDefault();
    const url = new URL(link.href, window.location.href);
    const form = document.createElement('form');
    form.method = 'post';
    form.action = url.origin + url.pathname;
    ['action', 'id', '_wpnonce'].forEach(name => {
      const value = url.searchParams.get(name);
      if (!value) return;
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  });
})();

(() => {
  const page = document.querySelector('.sfv-settings-page');
  if (!page) return;
  const tabs = [...page.querySelectorAll('[data-settings-tab]')];
  const panes = [...page.querySelectorAll('[data-settings-pane]')];
  tabs.forEach(tab => tab.addEventListener('click', () => {
    tabs.forEach(item => item.classList.toggle('is-active', item === tab));
    panes.forEach(pane => pane.classList.toggle('is-active', pane.dataset.settingsPane === tab.dataset.settingsTab));
  }));

  const preview = page.querySelector('#sfv-live-preview');
  if (!preview) return;
  const previewThemeInputs = [...page.querySelectorAll('input[name$="[preview_theme]"]')];
  const applyPreviewTheme = input => {
    if (!input || !input.checked) return;
    preview.classList.toggle('is-dark', input.value === 'dark');
    preview.classList.toggle('is-light', input.value !== 'dark');
    previewThemeInputs.forEach(item => {
      const label = item.closest('label');
      if (label) label.classList.toggle('is-active', item.checked);
    });
  };
  previewThemeInputs.forEach(input => {
    applyPreviewTheme(input);
    input.addEventListener('change', () => applyPreviewTheme(input));
  });
  const fontMap = { inherit: 'inherit', system: 'system-ui, sans-serif', arial: 'Arial, sans-serif', georgia: 'Georgia, serif', monospace: 'ui-monospace, monospace' };
  const map = {
    'label-color':['--sfv-preview-label-color','raw'],'label-font':['--sfv-preview-label-font','font'],'label-size':['--sfv-preview-label-size','px'],'label-weight':['--sfv-preview-label-weight','raw'],
    'placeholder-color':['--sfv-preview-placeholder-color','raw'],'placeholder-font':['--sfv-preview-placeholder-font','font'],'placeholder-size':['--sfv-preview-placeholder-size','px'],'placeholder-weight':['--sfv-preview-placeholder-weight','raw'],
    'field-text-color':['--sfv-preview-field-color','raw'],'field-background':['--sfv-preview-field-bg','raw'],'field-border-color':['--sfv-preview-field-border','raw'],'field-hover-text-color':['--sfv-preview-field-hover-color','raw'],'field-hover-background':['--sfv-preview-field-hover-bg','raw'],'field-hover-border-color':['--sfv-preview-field-hover-border','raw'],'field-focus-text-color':['--sfv-preview-field-focus-color','raw'],'field-focus-background':['--sfv-preview-field-focus-bg','raw'],'field-focus-color':['--sfv-preview-field-focus-border','raw'],'field-focus-outline-color':['--sfv-preview-field-focus-outline','raw'],'field-active-text-color':['--sfv-preview-field-active-color','raw'],'field-active-background':['--sfv-preview-field-active-bg','raw'],'field-active-border-color':['--sfv-preview-field-active-border','raw'],'field-font':['--sfv-preview-field-font','font'],'field-size':['--sfv-preview-field-size','px'],'field-weight':['--sfv-preview-field-weight','raw'],'field-height':['--sfv-preview-field-height','px'],'field-radius':['--sfv-preview-field-radius','px'],
    'submit-text-color':['--sfv-preview-submit-color','raw'],'submit-background':['--sfv-preview-submit-bg','raw'],'submit-font':['--sfv-preview-submit-font','font'],'submit-size':['--sfv-preview-submit-size','px'],'submit-weight':['--sfv-preview-submit-weight','raw'],'submit-height':['--sfv-preview-submit-height','px'],'submit-radius':['--sfv-preview-submit-radius','px']
  };
  const update = input => {
    const config = map[input.dataset.preview]; if (!config) return;
    let value = input.value;
    if (config[1] === 'px') value = `${value}px`;
    if (config[1] === 'font') value = fontMap[value] || 'inherit';
    preview.style.setProperty(config[0], value);
    if (input.type === 'color') { const code = input.parentElement.querySelector('code'); if (code) code.textContent = input.value; }
  };
  page.querySelectorAll('[data-preview]').forEach(input => { update(input); input.addEventListener('input', () => update(input)); input.addEventListener('change', () => update(input)); });
  page.querySelectorAll('[data-field-state-tab]').forEach(tab => {
    tab.addEventListener('click', () => {
      const state = tab.dataset.fieldStateTab;
      page.querySelectorAll('[data-field-state-tab]').forEach(item => item.classList.toggle('is-active', item === tab));
      page.querySelectorAll('[data-field-state-panel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.fieldStatePanel === state));
      const target = preview.querySelector('.sfv-preview-live-field');
      if (target) {
        target.classList.remove('is-preview-hover', 'is-preview-focus', 'is-preview-active');
        if (state !== 'default') target.classList.add(`is-preview-${state}`);
      }
    });
  });

})();

(() => {
  const select = document.getElementById('sfv_select_pdf_logo');
  const clear = document.getElementById('sfv_clear_pdf_logo');
  const input = document.getElementById('sfv_pdf_logo_url');
  if (!select || !input) return;
  select.addEventListener('click', () => {
    if (!window.wp || !wp.media) return;
    const frame = wp.media({ title: 'Επιλογή logo PDF', button: { text: 'Χρήση logo' }, multiple: false, library: { type: 'image' } });
    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      if (attachment && attachment.url) input.value = attachment.url;
    });
    frame.open();
  });
  if (clear) clear.addEventListener('click', () => { input.value = ''; input.focus(); });
})();

(() => {
  const manager = document.getElementById('grfuel-price-manager');
  if (!manager) return;
  const rows = manager.querySelector('.grfuel-price-rows');
  const template = document.getElementById('grfuel-price-template');
  const add = document.getElementById('grfuel-add-price');
  const slugify = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'neo-proion';
  const reindex = () => {
    rows.querySelectorAll('.grfuel-price-row').forEach((row, index) => {
      row.querySelectorAll('[data-name]').forEach(input => input.name = `sfv_settings[daily_prices][${index}][${input.dataset.name}]`);
      row.querySelectorAll('input[name]').forEach(input => {
        const match = input.name.match(/\[daily_prices\]\[\d+\]\[([^\]]+)\]/);
        if (match) input.name = `sfv_settings[daily_prices][${index}][${match[1]}]`;
      });
    });
  };
  const updateShortcode = row => {
    const name = row.querySelector('[data-grfuel-name]');
    const slug = row.querySelector('[data-grfuel-slug]');
    const code = row.querySelector('code');
    if (!slug || !code) return;
    if (!slug.value.trim() && name?.value.trim()) slug.value = slugify(name.value);
    code.textContent = `[grfuel_price product="${slugify(slug.value)}"]`;
  };
  manager.addEventListener('input', event => {
    const row = event.target.closest('.grfuel-price-row');
    if (row) updateShortcode(row);
  });
  manager.addEventListener('click', event => {
    const remove = event.target.closest('.grfuel-remove-price');
    if (!remove) return;
    remove.closest('.grfuel-price-row')?.remove();
    reindex();
  });
  add?.addEventListener('click', () => {
    if (!template) return;
    rows.appendChild(template.content.cloneNode(true));
    reindex();
    const row = rows.lastElementChild;
    if (row) updateShortcode(row);
  });
  rows.querySelectorAll('.grfuel-price-row').forEach(updateShortcode);


})();

(() => {
  const notificationPanel = document.getElementById('grfuel-browser-notification-panel');
  const enable = document.getElementById('grfuel-enable-browser-notifications');
  const test = document.getElementById('grfuel-test-browser-notification');
  const status = document.getElementById('grfuel-browser-notification-status');
  const statusText = document.getElementById('grfuel-browser-notification-status-text');
  const help = document.getElementById('grfuel-browser-notification-help');

  const updateBrowserNotificationStatus = (message = '') => {
    if (!notificationPanel || !status || !statusText) return;
    status.classList.remove('is-active', 'is-blocked', 'is-unsupported', 'is-pending');

    if (!('Notification' in window)) {
      status.classList.add('is-unsupported');
      statusText.textContent = 'Δεν υποστηρίζονται από αυτόν τον browser';
      if (help) help.textContent = 'Χρησιμοποίησε έναν σύγχρονο browser που υποστηρίζει ειδοποιήσεις επιφάνειας εργασίας.';
      if (enable) enable.hidden = true;
      if (test) test.hidden = true;
      return;
    }

    if (Notification.permission === 'granted') {
      status.classList.add('is-active');
      statusText.textContent = 'Ενεργές';
      if (help) help.textContent = message || 'Οι ειδοποιήσεις νέων παραγγελιών μπορούν να εμφανίζονται στην επιφάνεια εργασίας.';
      if (enable) enable.hidden = true;
      if (test) test.hidden = false;
      return;
    }

    if (Notification.permission === 'denied') {
      status.classList.add('is-blocked');
      statusText.textContent = 'Αποκλεισμένες από τον browser';
      if (help) help.textContent = 'Άλλαξε την άδεια ειδοποιήσεων από τις ρυθμίσεις του browser για αυτόν τον ιστότοπο και ανανέωσε τη σελίδα.';
      if (enable) enable.hidden = true;
      if (test) test.hidden = true;
      return;
    }

    status.classList.add('is-pending');
    statusText.textContent = 'Δεν έχουν ενεργοποιηθεί';
    if (help) help.textContent = message || 'Πάτησε «Ενεργοποίηση» και αποδέξου το αίτημα άδειας του browser.';
    if (enable) enable.hidden = false;
    if (test) test.hidden = true;
  };

  enable?.addEventListener('click', async () => {
    if (!('Notification' in window)) {
      updateBrowserNotificationStatus();
      return;
    }
    try {
      const result = await Notification.requestPermission();
      updateBrowserNotificationStatus(result === 'granted' ? 'Οι ειδοποιήσεις browser ενεργοποιήθηκαν επιτυχώς.' : 'Δεν δόθηκε άδεια για ειδοποιήσεις browser.');
    } catch (error) {
      updateBrowserNotificationStatus('Δεν ήταν δυνατή η ενεργοποίηση των ειδοποιήσεων.');
    }
  });

  test?.addEventListener('click', () => {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
      updateBrowserNotificationStatus();
      return;
    }
    const notification = new Notification('GRFuel Manager', {
      body: 'Δοκιμαστική ειδοποίηση: οι browser notifications λειτουργούν κανονικά.',
      tag: `grfuel-test-${Date.now()}`,
      requireInteraction: false
    });
    notification.onclick = () => {
      window.focus();
      notification.close();
    };
    updateBrowserNotificationStatus('Η δοκιμαστική ειδοποίηση στάλθηκε.');
  });

  updateBrowserNotificationStatus();
})();

(() => {
  const initSidebarToggle = () => {
    const sidebar = document.querySelector('.grfuel-app-sidebar');
    const toggle = sidebar?.querySelector('.grfuel-sidebar-toggle');
    if (!sidebar || !toggle || toggle.dataset.grfuelReady === '1') return;

    toggle.dataset.grfuelReady = '1';
    const key = 'grfuelSidebarCollapsed';
    const apply = collapsed => {
      document.body.classList.toggle('grfuel-sidebar-collapsed', collapsed);
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      const label = collapsed ? 'Άνοιγμα μενού' : 'Σύμπτυξη μενού';
      toggle.setAttribute('aria-label', label);
      toggle.setAttribute('title', label);
      const text = toggle.querySelector('span');
      if (text) text.textContent = collapsed ? 'Άνοιγμα' : 'Σύμπτυξη';
    };

    let saved = false;
    try { saved = window.localStorage.getItem(key) === '1'; } catch (error) {}
    apply(saved);

    toggle.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      const collapsed = !document.body.classList.contains('grfuel-sidebar-collapsed');
      apply(collapsed);
      try { window.localStorage.setItem(key, collapsed ? '1' : '0'); } catch (error) {}
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarToggle, { once: true });
  } else {
    initSidebarToggle();
  }
})();

(() => {
  const save = document.getElementById('sfv-save-form');
  save?.addEventListener('click', () => {
    const returnField = document.getElementById('sfv_return_to_forms');
    if (returnField) returnField.value = '1';
    const publish = document.getElementById('publish');
    if (publish) publish.click();
  });

  const preview = document.getElementById('grfuel-preview-sound');
  const select = document.getElementById('grfuel-notification-sound');
  const play = choice => {
    try {
      const audio = new (window.AudioContext || window.webkitAudioContext)();
      const now = audio.currentTime;
      const patterns = {
        bell: [[0, 880, .16], [.24, 1175, .13]],
        soft: [[0, 523, .10], [.22, 659, .09]],
        digital: [[0, 1047, .12], [.12, 1319, .10], [.24, 1568, .08]],
        chime: [[0, 784, .13], [.30, 988, .12]]
      };
      (patterns[choice] || patterns.bell).forEach(([delay, freq, volume]) => {
        const osc = audio.createOscillator(); const gain = audio.createGain();
        osc.type = choice === 'digital' ? 'square' : 'sine';
        osc.frequency.setValueAtTime(freq, now + delay);
        gain.gain.setValueAtTime(.0001, now + delay);
        gain.gain.exponentialRampToValueAtTime(volume, now + delay + .01);
        gain.gain.exponentialRampToValueAtTime(.0001, now + delay + .45);
        osc.connect(gain); gain.connect(audio.destination); osc.start(now + delay); osc.stop(now + delay + .5);
      });
    } catch (e) {}
  };
  preview?.addEventListener('click', () => play(select?.value || 'bell'));
})();


(() => {
  const dialog = document.getElementById('grfuel-new-form-dialog');
  if (!dialog) return;
  const openers = document.querySelectorAll('.grfuel-open-form-dialog');
  const close = () => dialog.open && dialog.close();
  openers.forEach(opener => opener.addEventListener('click', event => {
    event.preventDefault();
    dialog.showModal();
    window.setTimeout(() => dialog.querySelector('input[name="form_name"]')?.focus(), 20);
  }));
  dialog.querySelector('.grfuel-dialog-close')?.addEventListener('click', close);
  dialog.querySelector('.grfuel-dialog-cancel')?.addEventListener('click', close);
  dialog.addEventListener('click', event => {
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
  });
  if (window.location.hash === '#grfuel-new-form-dialog') dialog.showModal();
})();

// 4.0.0-alpha.8 — visible multi-row daily price entry editor.
document.querySelectorAll('[data-grfuel-price-entry-form]').forEach(form => {
  const rows = form.querySelector('[data-grfuel-history-rows]');
  const template = form.querySelector('[data-grfuel-history-template]');
  form.addEventListener('click', event => {
    const add = event.target.closest('[data-grfuel-add-history]');
    if (add && rows && template) {
      rows.appendChild(template.content.cloneNode(true));
      const newRow = rows.lastElementChild;
      newRow?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      newRow?.querySelector('input[type="date"]')?.focus();
      return;
    }
    const remove = event.target.closest('[data-grfuel-history-remove]');
    if (remove) {
      const allRows = rows?.querySelectorAll('.grfuel-history-entry-row') || [];
      if (allRows.length > 1) remove.closest('.grfuel-history-entry-row')?.remove();
      else {
        remove.closest('.grfuel-history-entry-row')?.querySelectorAll('input').forEach(input => { if (input.type !== 'date') input.value = ''; });
      }
    }
  });
});


(() => {
  const dialog = document.getElementById('grfuel-import-form-dialog');
  if (!dialog) return;
  document.querySelectorAll('.grfuel-open-import-dialog').forEach(button => button.addEventListener('click', event => { event.preventDefault(); dialog.showModal(); }));
  dialog.querySelectorAll('.grfuel-dialog-close,.grfuel-dialog-cancel').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
})();

(() => {
  const dialog = document.getElementById('grfuel-import-form-dialog');
  if (!dialog) return;
  document.querySelectorAll('.grfuel-open-import-dialog').forEach(button => button.addEventListener('click', event => {
    event.preventDefault();
    dialog.showModal();
  }));
  dialog.querySelectorAll('.grfuel-dialog-close,.grfuel-dialog-cancel').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
})();

// 4.1.4 — visual color picker with opacity support for form appearance settings.
(() => {
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const hexToRgb = hex => {
    const match = String(hex || '').trim().match(/^#([0-9a-f]{6})$/i);
    if (!match) return null;
    const number = parseInt(match[1], 16);
    return { r: (number >> 16) & 255, g: (number >> 8) & 255, b: number & 255 };
  };
  const rgbToHex = (r, g, b) => `#${[r, g, b].map(value => clamp(parseInt(value, 10) || 0, 0, 255).toString(16).padStart(2, '0')).join('')}`;
  const parseValue = value => {
    const text = String(value || '').trim();
    const hex = text.match(/^#([0-9a-f]{6})$/i);
    if (hex) return { hex: `#${hex[1].toLowerCase()}`, alpha: 1 };
    const rgba = text.match(/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*((?:0(?:\.\d+)?)|(?:1(?:\.0+)?))\s*\)$/i);
    if (!rgba) return null;
    return {
      hex: rgbToHex(rgba[1], rgba[2], rgba[3]),
      alpha: clamp(parseFloat(rgba[4]), 0, 1)
    };
  };
  const format = (hex, opacity) => {
    const rgb = hexToRgb(hex) || { r: 0, g: 0, b: 0 };
    const alpha = clamp(Number(opacity) / 100, 0, 1);
    if (alpha >= 1) return hex.toLowerCase();
    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${Number(alpha.toFixed(2))})`;
  };

  document.querySelectorAll('[data-rgba-control]').forEach(control => {
    const color = control.querySelector('.sfv-rgba-color');
    const opacity = control.querySelector('.sfv-rgba-opacity');
    const output = control.querySelector('output');
    const value = control.querySelector('.sfv-rgba-value');
    if (!color || !opacity || !value) return;

    const refresh = () => {
      value.value = format(color.value, opacity.value);
      if (output) output.textContent = `${opacity.value}%`;
      control.style.setProperty('--sfv-picker-color', value.value);
      value.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const syncFromText = () => {
      const parsed = parseValue(value.value);
      if (!parsed) {
        value.setCustomValidity('Χρησιμοποιήστε HEX ή RGBA χρώμα.');
        return;
      }
      value.setCustomValidity('');
      color.value = parsed.hex;
      opacity.value = String(Math.round(parsed.alpha * 100));
      if (output) output.textContent = `${opacity.value}%`;
      control.style.setProperty('--sfv-picker-color', value.value);
    };

    color.addEventListener('input', refresh);
    opacity.addEventListener('input', refresh);
    value.addEventListener('change', syncFromText);
    value.addEventListener('blur', syncFromText);
    syncFromText();
  });
})();

// GRFuel Manager 4.2.0 mobile off-canvas navigation.
(() => {
  const init = () => {
    const burger = document.querySelector('.grfuel-mobile-burger');
    const overlay = document.querySelector('.grfuel-mobile-overlay');
    const closeButton = document.querySelector('.grfuel-mobile-menu-close');
    const sidebar = document.querySelector('.grfuel-app-sidebar');
    if (!burger || !overlay || !sidebar || burger.dataset.ready === '1') return;
    burger.dataset.ready = '1';
    const setOpen = open => {
      document.body.classList.toggle('grfuel-mobile-menu-open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', open ? 'Κλείσιμο μενού' : 'Άνοιγμα μενού');
    };
    burger.addEventListener('click', () => setOpen(!document.body.classList.contains('grfuel-mobile-menu-open')));
    overlay.addEventListener('click', () => setOpen(false));
    closeButton?.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
    window.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });
    window.addEventListener('resize', () => { if (window.innerWidth > 782) setOpen(false); });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true}); else init();
})();

// GRFuel Manager 4.2.3 — reliable admin-order dependencies.
(() => {
  const initAdminOrderDependencies = () => {
    document.querySelectorAll('.grfuel-admin-order-form').forEach(form => {
      if (form.dataset.grfuelDependenciesReady === '1') return;
      form.dataset.grfuelDependenciesReady = '1';

      const escapeName = value => window.CSS?.escape ? CSS.escape(value) : String(value).replace(/(["\\[\]])/g, '\\$1');
      const fieldBox = key => form.querySelector(`[data-sfv-field="${escapeName(key)}"]`);
      const valueOf = key => {
        const box = fieldBox(key);
        if (box?.hidden) return '';
        const name = `sfv_fields[${key}]`;
        const controls = Array.from(form.elements).filter(el => el.name === name);
        if (!controls.length) return '';
        const first = controls[0];
        if (first.type === 'radio') return controls.find(el => el.checked)?.value || '';
        if (first.type === 'checkbox') return first.checked ? '1' : '';
        return String(first.value ?? '');
      };
      const matches = (actual, operator, expected) => {
        const a = String(actual ?? '');
        const e = String(expected ?? '');
        switch (operator) {
          case 'not_equals': return a !== e;
          case 'contains': return a.toLocaleLowerCase('el').includes(e.toLocaleLowerCase('el'));
          case 'checked': return a === '1';
          case 'not_checked': return a !== '1';
          case 'empty': return a === '';
          case 'not_empty': return a !== '';
          default: return a === e;
        }
      };

      const dependentBoxes = Array.from(form.querySelectorAll('[data-sfv-dependency]'));
      dependentBoxes.forEach(box => {
        box.querySelectorAll('input,select,textarea').forEach(control => {
          control.dataset.grfuelOriginallyRequired = control.required ? '1' : '0';
        });
      });

      const update = () => {
        // Multiple passes allow chained dependencies to settle in one interaction.
        for (let pass = 0; pass < Math.max(2, dependentBoxes.length); pass += 1) {
          let changed = false;
          dependentBoxes.forEach(box => {
            const visible = matches(valueOf(box.dataset.sfvDependency), box.dataset.sfvOperator || 'equals', box.dataset.sfvValue || '');
            if (box.hidden === visible) changed = true;
            box.hidden = !visible;
            box.setAttribute('aria-hidden', visible ? 'false' : 'true');
            box.classList.toggle('is-dependency-visible', visible);
            box.querySelectorAll('input,select,textarea').forEach(control => {
              control.disabled = !visible;
              control.required = visible && control.dataset.grfuelOriginallyRequired === '1';
            });
          });
          if (!changed) break;
        }
      };

      form.addEventListener('input', update);
      form.addEventListener('change', update);
      update();
    });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAdminOrderDependencies, { once: true });
  else initAdminOrderDependencies();
})();

(() => {
  const select = document.getElementById('sfv_select_pdf_watermark');
  const clear = document.getElementById('sfv_clear_pdf_watermark');
  const input = document.getElementById('sfv_pdf_watermark_url');
  const opacity = document.getElementById('sfv_pdf_watermark_opacity');
  const opacityValue = document.getElementById('sfv_pdf_watermark_opacity_value');
  if (select && input) {
    select.addEventListener('click', () => {
      if (!window.wp || !wp.media) return;
      const frame = wp.media({ title: 'Επιλογή υδατογραφήματος PDF', button: { text: 'Χρήση ως υδατογράφημα' }, multiple: false, library: { type: 'image' } });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        if (attachment && attachment.url) input.value = attachment.url;
      });
      frame.open();
    });
    if (clear) clear.addEventListener('click', () => { input.value = ''; input.focus(); });
  }
  if (opacity && opacityValue) opacity.addEventListener('input', () => { opacityValue.textContent = `${opacity.value}%`; });
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
