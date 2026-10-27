import Portal from './api.js';

const $ = (selector, parent = document) => parent.querySelector(selector);
const page = document.body.dataset.page || 'index';
if (!document.body.dataset.page) {
  const initial = $('#app');
  const container = el('div'); container.id = 'app';
  initial.replaceWith(container);
  document.querySelector('body > header')?.remove();
  document.querySelector('body > footer')?.remove();
}
const app = $('#app');
let employeeView = 0;
const defaultPositions = {
  'assembly-technician':'Assembly technician',
  'bodyshop-coating-technician':'Bodyshop coating technician',
  'production-employee-drones':'Production employee drones',
  'mbo-hbo-internship':'MBO/HBO internship',
  'open-application':'Open application',
};
const defaultDepartments = {
  'assembly-technician':'Assembly technician',
  'bodyshop-coating-technician':'Bodyshop coating technician',
  'production-employee-drones':'Production employee drones',
  'open/intern':'open/intern',
};
// Het fragment gaat niet naar de server. Verwijder het token ook uit de zichtbare URL.
const tokenLocation = new URL(location.href);
const activationToken = new URLSearchParams(tokenLocation.hash.slice(1)).get('token') || tokenLocation.searchParams.get('token') || '';
if (activationToken) {
  tokenLocation.searchParams.delete('token');
  tokenLocation.hash = '';
  history.replaceState(null,'',tokenLocation.pathname + tokenLocation.search);
}
const icons = {
  arrow:'<path d="M5 12h14m-5-5 5 5-5 5"/>', back:'<path d="m14 6-6 6 6 6"/>',
  brand:'<path d="M5 17V7l7 5 7-5v10M5 7l7-4 7 4M5 17l7 4 7-4"/>',
  file:'<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zm0 0v6h6M8 13h8m-8 4h5"/>',
  upload:'<path d="M12 16V3m-5 5 5-5 5 5M4 15v5h16v-5"/>',
  shield:'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zm-4 9 3 3 5-5"/>',
  lock:'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v2"/>',
  user:'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
  users:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>',
  calendar:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-13 4h2m4 0h2m-8 3h2"/>',
  check:'<path d="m5 12 4 4L19 6"/>', close:'<path d="m6 6 12 12M6 18 18 6"/>',
  clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  logout:'<path d="M9 4H4v16h5m5-13 5 5-5 5m-6-5h11"/>',
  building:'<path d="M4 21V5h12v16M16 10h4v11M2 21h20M8 9h4m-4 4h4m-4 4h4"/>',
  plus:'<path d="M12 5v14M5 12h14"/>', edit:'<path d="m16 3 5 5-12 12H4v-5zm-2 2 5 5"/>',
  mail:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
  info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/>',
  download:'<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
  copy:'<rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V3H3v13h5"/>',
};
function icon(name, className = '') { return `<svg class="${className}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name] || icons.info}</svg>`; }
function el(tag, className, text) { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = String(text); return node; }
function put(selector, value, parent = document) { const node = $(selector, parent); if (node) node.textContent = value ?? '—'; }
function initials(name) { return String(name || '?').trim().split(/\s+/).slice(0,2).map(part => part[0]).join('').toUpperCase(); }
function parseDate(value) {
  // MariaDB-tijdstempels zijn UTC; zonder offset zou de browser ze als lokale tijd lezen.
  const normalized = typeof value === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value) ? value.replace(' ','T') + 'Z' : value;
  return new Date(normalized);
}
function dateText(value, options = {}) { if (!value) return '—'; const date = parseDate(value); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('nl-NL',{day:'numeric',month:'short',year:'numeric',timeZone:'Europe/Amsterdam',...options}).format(date); }
function message(target, text, type = 'error') { const node = typeof target === 'string' ? $(target) : target; if (!node) return; node.className = 'message ' + type; node.textContent = text; node.hidden = !text; node.setAttribute('role',type === 'error' ? 'alert' : 'status'); }
function busy(button, loading) { button.disabled = loading; if (loading) { button.dataset.originalText = button.textContent; button.textContent = 'Even geduld…'; } else { button.textContent = button.dataset.originalText || button.textContent; } }
function statusBadge(status) { const badge = el('span','badge ' + status,({pending:'In afwachting',approved:'Goedgekeurd',rejected:'Afgewezen',scheduled:'Gepland',confirmed:'Gepland',cancelled:'Geannuleerd',active:'Actief',inactive:'Inactief'})[status] || status); return badge; }
function roleHome(role) { return role === 'employee' ? 'employee.html' : 'dashboard.html'; }
function choices(kind) { return Portal.session?.[kind] || (kind === 'positions' ? defaultPositions : defaultDepartments); }
function choiceLabel(kind, value) { return choices(kind)[value] || 'Nog niet gekozen'; }
function populateChoices(select, kind, selected = '') {
  select.replaceChildren();
  const placeholder = el('option','',kind === 'positions' ? 'Kies een functie of sollicitatietype' : 'Kies een afdeling'); placeholder.value = ''; placeholder.disabled = true; select.append(placeholder);
  for (const [value,label] of Object.entries(choices(kind))) { const option = el('option','',label); option.value = value; select.append(option); }
  select.value = selected || '';
}
function employeeDepartmentPanel() {
  return '<section class="card department-card"><div><h2>Mijn afdeling</h2><p id="department-description"></p></div><form id="account-department-form" class="department-form"><label class="field"><span class="field-label">Afdeling <span class="required">*</span></span><select class="input" name="department" required></select></label><button class="btn btn-secondary" type="submit">Afdeling opslaan</button></form><p class="field-help">Stages en open sollicitaties komen samen bij de afdeling open/intern.</p><div id="department-message" class="message" hidden></div></section>';
}
function logoUrl(theme) {
  const url = theme?.logo_url;
  return typeof url === 'string' && /^\/themes\/[a-z0-9-]+\/[a-zA-Z0-9_.-]+\.(png|jpe?g|svg|webp)$/i.test(url) ? url : '';
}
function updateBrands() {
  document.querySelectorAll('[data-brand]').forEach(node => {
    node.replaceChildren();
    const theme = Portal.theme || {};
    // Alleen servergevalideerde bestanden uit een themamap mogen als logo worden geladen.
    if (logoUrl(theme)) {
      const img = el('img','brand-logo'); img.src = logoUrl(theme); img.alt = Portal.companyInfo?.name || 'Bedrijfslogo'; node.append(img);
    } else {
      const mark = el('span','brand-mark'); mark.innerHTML = icon('brand');
      node.append(mark,el('span','brand-title',Portal.companyInfo?.name || 'Sollicitatieportaal'));
    }
  });
  document.querySelectorAll('[data-company-name]').forEach(node => { node.textContent = Portal.companyInfo?.name || 'het bedrijf'; });
  document.title = document.title.split(' · ')[0] + ' · ' + (Portal.companyInfo?.name || 'Sollicitatieportaal');
}
function publicShell(content, headerLink = true) {
  app.innerHTML = `<header class="public-header"><a class="brand" data-brand href="${Portal.validCompany ? Portal.link('apply.html') : '/index.html'}"></a>${headerLink ? `<a class="header-link" href="${Portal.link('login.html')}">Al een account? <strong>Inloggen</strong> ${icon('arrow')}</a>` : '<span class="header-tag">Sollicitatieportaal</span>'}</header>${content}<footer class="public-footer"><span>Een volgende stap begint met een kennismaking.</span><span>${icon('shield')} Jouw gegevens, veilig bewaard</span></footer>`;
  updateBrands();
}
function neutral(text = 'Open het portaal via de persoonlijke link van het bedrijf waar je wilt solliciteren.') {
  publicShell(`<main class="neutral-state card"><div class="center-icon">${icon('building')}</div><p class="eyebrow">Sollicitatieportaal</p><h1>Welkom. Waar wil je beginnen?</h1><p id="neutral-copy"></p><a href="/index.html" class="btn btn-secondary">Bekijk bedrijven ${icon('arrow')}</a></main>`,false);
  put('#neutral-copy',text);
}
function shell(title, subtitle, content, active, action = '') {
  const user = Portal.session.user;
  const links = user.role === 'employee' ? [['employee.html','file','Sollicitaties','employee']] : [['dashboard.html','user','Mijn overzicht','dashboard']];
  app.innerHTML = `<div class="app-layout"><aside class="sidebar"><a class="brand" data-brand href="${Portal.link(roleHome(user.role))}"></a><p class="sidebar-label">Werkruimte</p><nav class="nav-links" aria-label="Hoofdnavigatie">${links.map(([href,name,label,key]) => `<a class="nav-link ${key === active ? 'active' : ''}" href="${Portal.link(href)}" ${key === active ? 'aria-current="page"' : ''}>${icon(name)}${label}</a>`).join('')}</nav><div class="sidebar-bottom"><div class="user-box"><span class="avatar" id="user-initials"></span><div><span class="user-name" id="user-name"></span><span class="user-role"></span></div></div><button class="logout-btn" data-logout>${icon('logout')} Uitloggen</button></div></aside><main class="app-body"><div class="app-topbar"><div class="breadcrumb"><span data-company-name></span><span>/</span><span id="breadcrumb-page"></span></div><button class="text-btn mobile-logout" data-logout>Uitloggen ${icon('logout')}</button><span class="topbar-status">Beveiligde omgeving</span></div><div class="page-heading"><div><p class="eyebrow">${user.role === 'applicant' ? 'Jouw volgende stap' : 'Overzicht'}</p><h1 id="page-title"></h1><p id="page-subtitle"></p></div>${action || '<span class="heading-aside" id="today-date"></span>'}</div><div id="page-message" class="message" hidden></div>${user.role === 'employee' ? employeeDepartmentPanel() : ''}${content}<footer class="app-footer"><span>Sollicitatieportaal · <span data-company-name></span></span><span>Veilig verbonden</span></footer></main></div>`;
  put('#page-title',title); put('#breadcrumb-page',title); put('#page-subtitle',subtitle);
  put('#user-initials',initials(user.name)); put('#user-name',user.name); put('.user-role',({admin:'Beheerder',employee:'Medewerker',applicant:'Sollicitant'})[user.role]);
  put('#today-date',new Intl.DateTimeFormat('nl-NL',{weekday:'long',day:'numeric',month:'long',timeZone:'Europe/Amsterdam'}).format(new Date()));
  document.querySelectorAll('[data-logout]').forEach(button => button.addEventListener('click',async () => { busy(button,true); try { await Portal.api('/api/auth/logout',{method:'POST'}); location.href = Portal.link('login.html'); } catch (error) { message('#page-message',error.message); busy(button,false); } }));
  if (user.role === 'employee') {
    const form = $('#account-department-form');
    populateChoices(form.elements.department,'departments',user.department);
    put('#department-description',user.department ? 'Je ziet alleen sollicitaties voor ' + choiceLabel('departments',user.department) + '.' : 'Kies eerst je afdeling om de bijbehorende sollicitaties te zien.');
    form.addEventListener('submit',async event => {
      event.preventDefault(); const button = $('button',form); const target = $('#department-message'); busy(button,true); message(target,'');
      try {
        const data = await Portal.api('/api/account/department',{method:'PATCH',body:{department:form.elements.department.value}});
        Portal.session.user = data.user;
        if (!form.isConnected) return;
        if (new URLSearchParams(location.search).has('id')) history.replaceState(null,'',Portal.link('employee.html'));
        await employeePage(); message('#department-message','Je afdeling is bijgewerkt.','success');
      } catch (error) { if (form.isConnected) message(target,error.message); }
      finally { busy(button,false); }
    });
  }
  updateBrands();
}
function requireRole(roles) {
  const user = Portal.session?.user;
  if (!user) { location.replace(Portal.link('login.html')); return false; }
  if (!roles.includes(user.role)) { location.replace(Portal.link(roleHome(user.role))); return false; }
  return true;
}
function empty(target, title, description, name = 'file') {
  const node = typeof target === 'string' ? $(target) : target;
  node.innerHTML = `<div class="empty-state">${icon(name)}<h3></h3><p></p></div>`;
  put('h3',title,node); put('p',description,node);
}
function confirmDialog(title, description, label, onConfirm, danger = false) {
  const overlay = el('div','modal-backdrop'); overlay.innerHTML = `<section class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title"><h2 id="modal-title"></h2><p></p><div class="message" hidden></div><div class="modal-actions"><button class="btn btn-secondary" data-close>Annuleren</button><button class="btn ${danger ? 'btn-danger' : ''}" data-confirm></button></div></section>`;
  put('h2',title,overlay); put('p',description,overlay); put('[data-confirm]',label,overlay);
  const previous = document.activeElement;
  const close = () => { overlay.remove(); previous?.focus(); };
  $('[data-close]',overlay).addEventListener('click',close);
  overlay.addEventListener('click',event => { if (event.target === overlay) close(); });
  overlay.addEventListener('keydown',event => { if (event.key === 'Escape') close(); if (event.key === 'Tab') { const buttons = [...overlay.querySelectorAll('button:not(:disabled)')]; if (event.shiftKey && document.activeElement === buttons[0]) { event.preventDefault(); buttons.at(-1).focus(); } else if (!event.shiftKey && document.activeElement === buttons.at(-1)) { event.preventDefault(); buttons[0].focus(); } } });
  $('[data-confirm]',overlay).addEventListener('click',async event => { const button = event.currentTarget; busy(button,true); try { await onConfirm(); close(); } catch (error) { message($('.message',overlay),error.message); busy(button,false); } });
  document.body.append(overlay); $('[data-close]',overlay).focus();
}

async function indexPage() {
  publicShell(`<main><section class="index-hero"><span class="hero-tag"><span></span>Een nieuwe mogelijkheid</span><h1>De volgende stap<br>in jouw verhaal.</h1><p>Ontdek het sollicitatieportaal van jouw bedrijf. Van de eerste kennismaking tot een goed gesprek.</p><div id="company-choices" class="company-grid"><div class="loading"><span class="spinner"></span>Bedrijven laden…</div></div><p class="small muted">Heb je een persoonlijke uitnodiging? Gebruik dan de link uit je e-mail.</p></section><section class="theme-example"><div><p class="eyebrow">Eén portaal. Een eigen gezicht.</p><h2>Een vertrouwde omgeving voor elk bedrijf.</h2><p>Open verschillende bedrijven en bekijk hun eigen kleuren, logo en uitstraling.</p></div>${icon('building')}</section></main>`,false);
  try {
    const {companies = []} = await Portal.api('/api/companies');
    const container = $('#company-choices'); container.replaceChildren();
    if (!companies.length) { empty(container,'Nog geen bedrijven beschikbaar','Gebruik de link van het bedrijf waar je wilt solliciteren.','building'); return; }
    for (const company of companies) {
      const link = el('a','company-choice'); link.href = '/apply.html?company=' + encodeURIComponent(company.slug);
      const copy = el('div'); copy.append(el('strong','',company.name),el('span','','Bekijk het sollicitatieportaal'));
      const arrow = el('span'); arrow.innerHTML = icon('arrow'); link.append(copy,arrow); container.append(link);
    }
  } catch (error) { empty('#company-choices','Gebruik je bedrijfslink',error.message,'building'); }
}

function applyPage() {
  publicShell(`<main class="public-main hero-grid"><section class="hero-intro"><span class="hero-tag"><span></span>Ruimte voor jouw talent</span><h1>Een mooie stap<br>begint bij jou.</h1><p>Vertel ons wie je bent en waar je enthousiast van wordt. We kijken uit naar jouw verhaal.</p><div class="journey"><div class="journey-step"><span class="step-num">01</span><div><strong>Deel jouw verhaal</strong><p>Je gegevens, cv en motivatie.</p></div></div><div class="journey-step"><span class="step-num">02</span><div><strong>Wij maken kennis</strong><p>Ons team bekijkt je sollicitatie.</p></div></div><div class="journey-step"><span class="step-num">03</span><div><strong>Tijd voor een gesprek</strong><p>Bij een match hoor je van ons over de volgende stap.</p></div></div></div><div class="privacy-note">${icon('shield')}<span>Je documenten zijn alleen zichtbaar voor het betrokken team. We gaan zorgvuldig om met jouw gegevens.</span></div></section><section class="card form-card" id="application-card"><h2>Laat van je horen</h2><p class="card-description">Solliciteer bij <span data-company-name></span>. We zijn benieuwd naar je.</p><form id="application-form"><div id="form-message" class="message" hidden></div><div class="form-section-label">Over jou</div><label class="field"><span class="field-label">Volledige naam <span class="required">*</span></span><input class="input" name="name" autocomplete="name" placeholder="Voor- en achternaam" required maxlength="150"></label><label class="field"><span class="field-label">E-mailadres <span class="required">*</span></span><input class="input" type="email" name="email" autocomplete="email" placeholder="jij@voorbeeld.nl" required maxlength="254"><span class="field-help">Hier houden we je op de hoogte van je sollicitatie.</span></label><label class="field"><span class="field-label">Functie / afdeling <span class="required">*</span></span><select class="input" name="position" required></select><span class="field-help">Kies waar je interesse ligt, of kies een stage of open sollicitatie.</span></label><div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><div class="form-section-label">Jouw verhaal</div><div class="field"><span class="field-label" id="cv-label">Curriculum vitae <span class="required">*</span></span><label class="upload"><span class="upload-icon">${icon('upload')}</span><span class="upload-copy"><strong data-file-name>Kies je cv of sleep het hier</strong><span>PDF of DOCX · maximaal 5 MB</span></span><input type="file" name="cv" aria-labelledby="cv-label" accept=".pdf,.docx" required></label></div><div class="field"><span class="field-label" id="letter-label">Motivatiebrief <span class="required">*</span></span><label class="upload"><span class="upload-icon">${icon('file')}</span><span class="upload-copy"><strong data-file-name>Voeg je motivatiebrief toe</strong><span>PDF of DOCX · maximaal 5 MB</span></span><input type="file" name="letter" aria-labelledby="letter-label" accept=".pdf,.docx" required></label></div><button class="btn btn-block" type="submit">Verstuur mijn sollicitatie ${icon('arrow')}</button><p class="form-fineprint">Na verzenden ontvang je een e-mail om je adres te bevestigen.<br>Je hoeft nu nog geen account of wachtwoord aan te maken.</p></form></section></main>`);
  updateBrands();
  populateChoices($('#application-form').elements.position,'positions');
  document.querySelectorAll('.upload input').forEach(input => input.addEventListener('change',() => {
    const file = input.files[0]; const label = $('[data-file-name]',input.closest('.upload'));
    label.textContent = file ? file.name : 'Kies een bestand';
    input.setCustomValidity(file && file.size > 5*1024*1024 ? 'Het bestand mag maximaal 5 MB zijn.' : '');
  }));
  $('#application-form').addEventListener('submit',async event => {
    event.preventDefault(); const form = event.currentTarget; const button = $('button[type=submit]',form); busy(button,true); message('#form-message','');
    try {
      await Portal.api('/api/applications',{method:'POST',body:new FormData(form)});
      const card = $('#application-card'); card.className = 'card success-box'; card.innerHTML = `<div class="center-icon">${icon('check')}</div><p class="eyebrow">De eerste stap is gezet</p><h2>Bedankt voor je sollicitatie.</h2><p id="confirmation-email"></p><p>Bevestig je e-mailadres via de link in de e-mail. Daarna bekijkt ons team je cv en motivatie. Je hoort van ons zodra je sollicitatie is beoordeeld.</p><span class="badge approved">Sollicitatie ontvangen</span>`;
      put('#confirmation-email','We hebben een bevestiging gestuurd naar ' + form.elements.email.value + '.'); card.scrollIntoView({behavior:'smooth',block:'start'});
    } catch (error) { message('#form-message',error.message); busy(button,false); }
  });
}

function loginPage() {
  if (Portal.session.user) { location.replace(Portal.link(roleHome(Portal.session.user.role))); return; }
  publicShell(`<main class="center-main"><div class="center-intro"><div class="center-icon">${icon('lock')}</div><p class="eyebrow">Fijn dat je er bent</p><h1>Welkom terug.</h1><p>Log in op jouw omgeving bij <span data-company-name></span>.</p></div><section class="card form-card"><form id="login-form"><div id="form-message" class="message" hidden></div><label class="field"><span class="field-label">E-mailadres</span><input class="input" type="email" name="email" autocomplete="username" placeholder="jij@voorbeeld.nl" required maxlength="254"></label><label class="field"><span class="field-label">Wachtwoord</span><input class="input" type="password" name="password" autocomplete="current-password" placeholder="Jouw wachtwoord" required></label><button type="submit" class="btn btn-block">Inloggen ${icon('arrow')}</button></form><p class="form-fineprint">Je krijgt toegang nadat je sollicitatie is goedgekeurd.<br>Gebruik eerst de uitnodiging in je e-mail om een wachtwoord in te stellen.</p></section><p class="form-fineprint">Nog niet gesolliciteerd? <a href="${Portal.link('apply.html')}">Laat van je horen</a></p></main>`,false);
  updateBrands();
  $('#login-form').addEventListener('submit',async event => { event.preventDefault(); const form = event.currentTarget; const button = $('button',form); busy(button,true); message('#form-message',''); try { const data = await Portal.api('/api/auth/login',{method:'POST',body:{email:form.elements.email.value,password:form.elements.password.value}}); location.href = Portal.link(roleHome(data.user.role)); } catch (error) { message('#form-message',error.message); busy(button,false); } });
}

function setPasswordPage() {
  const token = activationToken;
  publicShell(`<main class="center-main"><div class="center-intro"><div class="center-icon">${icon('lock')}</div><p class="eyebrow">Jouw persoonlijke omgeving</p><h1>Maak het persoonlijk.</h1><p>Stel een wachtwoord in om veilig toegang te krijgen tot het portaal.</p></div><section class="card form-card" id="password-card"><form id="password-form"><div id="form-message" class="message" hidden></div><label class="field"><span class="field-label">Nieuw wachtwoord</span><input class="input" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required><span class="field-help">Gebruik 12 tot 72 tekens. Kies een uniek wachtwoord.</span></label><label class="field"><span class="field-label">Herhaal je wachtwoord</span><input class="input" type="password" name="confirmation" autocomplete="new-password" minlength="12" maxlength="72" required></label><button type="submit" class="btn btn-block">Wachtwoord opslaan ${icon('arrow')}</button></form></section></main>`,false);
  if (!token) { message('#form-message','Deze link is onvolledig. Gebruik de volledige link uit je uitnodigingsmail.'); $('button[type=submit]').disabled = true; }
  $('#password-form').addEventListener('submit',async event => { event.preventDefault(); const form = event.currentTarget; if (form.elements.password.value !== form.elements.confirmation.value) { message('#form-message','De wachtwoorden zijn niet gelijk.'); return; } const button = $('button',form); busy(button,true); try { await Portal.api('/api/auth/set-password',{method:'POST',body:{token,password:form.elements.password.value}}); $('#password-card').innerHTML = `<div class="center-icon">${icon('check')}</div><h2>Je bent er klaar voor.</h2><p class="muted small">Je wachtwoord is ingesteld. Log in om verder te gaan.</p><a class="btn btn-block" href="${Portal.link('login.html')}">Naar inloggen ${icon('arrow')}</a>`; } catch (error) { message('#form-message',error.message); busy(button,false); } });
}

async function verifyPage() {
  publicShell(`<main class="center-main"><section class="card success-box"><div class="center-icon">${icon('mail')}</div><p class="eyebrow">Even controleren</p><h1>Je e-mailadres bevestigen.</h1><div id="verify-message" class="message info" role="status">Je bevestiging wordt verwerkt…</div><a class="btn btn-secondary" href="${Portal.link('apply.html')}">Terug naar het portaal ${icon('arrow')}</a></section></main>`,false);
  const token = activationToken;
  if (!token) { message('#verify-message','De bevestigingslink is onvolledig. Gebruik de link uit je e-mail.'); return; }
  try { await Portal.api('/api/auth/verify-email',{method:'POST',body:{token}}); message('#verify-message','Je e-mailadres is bevestigd. Ons team bekijkt je sollicitatie. Je ontvangt een e-mail zodra er nieuws is.','success'); } catch (error) { message('#verify-message',error.message); }
}

async function employeePage() {
  if (!requireRole(['employee'])) return;
  const detailId = new URLSearchParams(location.search).get('id');
  if (detailId && /^\d+$/.test(detailId)) { await applicationDetail(detailId); return; }
  const view = ++employeeView;
  shell('Een kans om kennis te maken.','Bekijk sollicitaties voor jouw afdeling en geef talent een volgende stap.',`<section class="stats-grid"><div class="card stat-card"><div class="stat-top">Te beoordelen ${icon('clock')}</div><strong class="stat-value" id="count-pending">—</strong><span class="stat-caption">Wachten op jouw eerste indruk</span></div><div class="card stat-card"><div class="stat-top">Goedgekeurd ${icon('check')}</div><strong class="stat-value" id="count-approved">—</strong><span class="stat-caption">Klaar voor een kennismaking</span></div><div class="card stat-card"><div class="stat-top">Afgewezen ${icon('file')}</div><strong class="stat-value" id="count-rejected">—</strong><span class="stat-caption">Beoordeelde sollicitaties</span></div></section><section class="card"><div class="panel-header"><div><h2>Sollicitaties</h2><p>De sollicitaties voor jouw afdeling.</p></div><span class="badge" id="pending-badge">Nieuwe sollicitaties</span></div><div class="toolbar"><div class="tabs" role="tablist" aria-label="Filter op status"><button class="tab active" role="tab" aria-selected="true" data-filter="pending">Te beoordelen</button><button class="tab" role="tab" aria-selected="false" data-filter="approved">Goedgekeurd</button><button class="tab" role="tab" aria-selected="false" data-filter="rejected">Afgewezen</button><button class="tab" role="tab" aria-selected="false" data-filter="all">Alles</button></div><input id="application-search" class="input search-input" placeholder="Zoek op naam of e-mail…" aria-label="Zoek sollicitaties"></div><div id="applications-container"><div class="loading"><span class="spinner"></span>Sollicitaties laden…</div></div><div class="table-footer"><span id="application-total">Sollicitaties worden opgehaald</span><span id="application-scope"></span></div></section><div class="dashboard-note"><span>${icon('shield')} Documenten zijn beveiligd en alleen toegankelijk voor bevoegde medewerkers.</span><span>Bijgewerkt bij het openen van deze pagina</span></div>`,'employee');
  try {
    put('#application-scope',Portal.session.user.department ? 'Afdeling: ' + choiceLabel('departments',Portal.session.user.department) : 'Nog geen afdeling gekozen');
    const data = await Portal.api('/api/applications',{params:{status:'all'}}); if (view !== employeeView) return; const applications = data.applications || [];
    ['pending','approved','rejected'].forEach(status => put('#count-' + status,data.counts?.[status] ?? applications.filter(a => a.status === status).length));
    put('#pending-badge',(data.counts?.pending ?? applications.filter(a => a.status === 'pending').length) + ' te beoordelen');
    let filter = 'pending';
    const render = () => {
      const query = $('#application-search').value.toLocaleLowerCase('nl');
      const shown = applications.filter(a => (filter === 'all' || a.status === filter) && (a.name + ' ' + a.email + ' ' + choiceLabel('positions',a.position)).toLocaleLowerCase('nl').includes(query));
      put('#application-total',shown.length + (shown.length === 1 ? ' sollicitatie' : ' sollicitaties'));
      if (!Portal.session.user.department) { empty('#applications-container','Kies je afdeling','Sla hierboven je afdeling op om de bijbehorende sollicitaties te zien.','building'); return; }
      if (!shown.length) { empty('#applications-container',query ? 'Geen resultaten gevonden' : 'Alles is bijgewerkt',query ? 'Probeer een andere naam of een ander e-mailadres.' : 'Er zijn op dit moment geen sollicitaties in deze categorie.'); return; }
      const container = $('#applications-container'); container.innerHTML = '<div class="table-wrap"><table><thead><tr><th>Sollicitant</th><th>Functie / sollicitatietype</th><th>Ontvangen</th><th>Status</th><th>E-mail</th><th></th></tr></thead><tbody></tbody></table></div>';
      for (const application of shown) {
        const row = el('tr'); const person = el('td'); const personCell = el('div','person-cell'); const info = el('div'); info.append(el('strong','',application.name),el('span','',application.email)); personCell.append(el('div','avatar',initials(application.name)),info); person.append(personCell); row.append(person,el('td','',choiceLabel('positions',application.position)),el('td','muted',dateText(application.created_at)));
        const status = el('td'); status.append(statusBadge(application.status)); row.append(status);
        row.append(el('td','muted',application.email_verified_at ? 'Bevestigd' : 'Nog niet bevestigd'));
        const action = el('td'); const link = el('a','text-btn','Bekijken '); link.href = Portal.link('employee.html',{id:application.id}); const arrow = el('span'); arrow.innerHTML = icon('arrow'); link.append(arrow); action.append(link); row.append(action); $('tbody',container).append(row);
      }
    };
    $('.tabs').addEventListener('click',event => { const button = event.target.closest('[data-filter]'); if (!button) return; filter = button.dataset.filter; document.querySelectorAll('[data-filter]').forEach(tab => { tab.classList.toggle('active',tab === button); tab.setAttribute('aria-selected',String(tab === button)); }); render(); });
    $('#application-search').addEventListener('input',render); render();
  } catch (error) { if (view !== employeeView) return; message('#page-message',error.message); empty('#applications-container','Ophalen niet gelukt','Vernieuw de pagina om het opnieuw te proberen.'); }
}

async function applicationDetail(id) {
  const view = ++employeeView;
  shell('Het verhaal achter de sollicitatie.','Bekijk de documenten en maak een zorgvuldige afweging.','<div id="application-detail"><div class="loading"><span class="spinner"></span>Sollicitatie laden…</div></div>','employee');
  try {
    const {application} = await Portal.api('/api/applications/' + id); if (view !== employeeView) return;
    $('#application-detail').innerHTML = `<a class="back-link" href="${Portal.link('employee.html')}">${icon('back')} Terug naar het overzicht</a><div class="detail-grid"><section class="card detail-card"><div class="person-cell"><span class="avatar" id="applicant-initials"></span><div><h2 id="applicant-name"></h2><span id="applicant-email"></span></div></div><hr class="section-divider"><h3>Bijgevoegde documenten</h3><p class="small muted">Download de documenten om het verhaal van deze sollicitant te lezen.</p><div id="application-documents"></div><dl><div class="detail-item"><dt>Functie / sollicitatietype</dt><dd id="application-position"></dd></div><div class="detail-item"><dt>Afdeling</dt><dd id="application-department"></dd></div><div class="detail-item"><dt>Ontvangen op</dt><dd id="received-at"></dd></div><div class="detail-item"><dt>E-mailadres</dt><dd id="verified-status"></dd></div></dl></section><aside class="card detail-card"><h3>Beoordeling</h3><div id="application-status"></div><p class="small muted review-description">Na je beoordeling ontvangt de sollicitant automatisch een e-mail. Bij goedkeuring krijgt de sollicitant toegang tot het portaal.</p><div id="review-meta"></div><div class="review-actions" id="review-actions"></div><p class="small muted">Een beoordeling is definitief.</p></aside></div>`;
    put('#applicant-initials',initials(application.name)); put('#applicant-name',application.name); put('#applicant-email',application.email); put('#received-at',dateText(application.created_at,{hour:'2-digit',minute:'2-digit'})); put('#verified-status',application.email_verified_at ? 'Bevestigd op ' + dateText(application.email_verified_at) : 'Nog niet bevestigd');
    put('#application-position',choiceLabel('positions',application.position)); put('#application-department',choiceLabel('departments',application.department));
    $('#application-status').append(statusBadge(application.status));
    for (const [type,label] of [['cv','Curriculum vitae'],['letter','Motivatiebrief']]) {
      const link = el('a','download-card'); link.href = Portal.url('/api/documents/' + application.id,{type}); const mark = el('span'); mark.innerHTML = icon('file'); const copy = el('div'); copy.append(el('strong','',label),el('small','',application[type + '_name'] || 'Document downloaden')); const download = el('span'); download.innerHTML = icon('download'); link.append(mark,copy,download); $('#application-documents').append(link);
    }
    if (application.status === 'pending') {
      const approve = el('button','btn','Goedkeuren & uitnodigen'); const reject = el('button','btn btn-danger','Sollicitatie afwijzen'); $('#review-actions').append(approve,reject);
      if (!application.email_verified_at) { const note = el('p','small muted','De sollicitant moet eerst het e-mailadres bevestigen voordat je kunt beoordelen.'); $('#review-actions').prepend(note); approve.disabled = true; reject.disabled = true; }
      for (const [button,status] of [[approve,'approved'],[reject,'rejected']]) button.addEventListener('click',() => confirmDialog(status === 'approved' ? 'Deze sollicitatie goedkeuren?' : 'Deze sollicitatie afwijzen?',status === 'approved' ? 'De sollicitant ontvangt een persoonlijke link om een wachtwoord in te stellen en toegang te krijgen tot het portaal.' : 'De sollicitant ontvangt een afwijzingsmail. Deze beoordeling kan niet worden gewijzigd.',status === 'approved' ? 'Goedkeuren' : 'Definitief afwijzen',async () => { await Portal.api('/api/applications/' + id,{method:'PATCH',body:{status}}); await applicationDetail(id); message('#page-message','De beoordeling is opgeslagen. De sollicitant ontvangt een e-mail.','success'); },status === 'rejected'));
    } else { const info = el('p','small muted','Beoordeeld' + (application.reviewer_name ? ' door ' + application.reviewer_name : '') + (application.reviewed_at ? ' op ' + dateText(application.reviewed_at) : '') + '.'); $('#review-meta').append(info); }
  } catch (error) { if (view !== employeeView) return; message('#page-message',error.message); empty('#application-detail','Sollicitatie niet beschikbaar','Deze sollicitatie bestaat niet of is niet toegankelijk voor jouw account.'); }
}

function dashboardPage() {
  if (!requireRole(['applicant','admin'])) return;
  const user = Portal.session.user;
  if (user.role === 'admin') {
    shell('Welkom in het portaal.','Je bent ingelogd als beheerder.', '<section class="card detail-card"><h2>Je account is actief</h2><p class="small muted">Het portaal ontvangt sollicitaties en het team kan deze beoordelen. Het bedrijfsbeheer wordt later toegevoegd.</p><a class="btn btn-secondary" href="' + Portal.link('apply.html') + '">Bekijk het sollicitatieformulier ' + icon('arrow') + '</a></section>', 'dashboard');
    return;
  }
  shell('Een mooie volgende stap.','Je sollicitatie is goedgekeurd. Fijn dat je er bent.', '<section class="card detail-card"><h2>Welkom bij <span data-company-name></span></h2><p id="applicant-welcome"></p><p><span class="badge approved">Sollicitatie goedgekeurd</span></p><p class="small muted">Je persoonlijke account is klaar. Je hoort van ons over de volgende stap en een kennismakingsgesprek.</p><dl><div class="detail-item"><dt>Naam</dt><dd id="account-name"></dd></div><div class="detail-item"><dt>E-mailadres</dt><dd id="account-email"></dd></div></dl></section>', 'dashboard');
  put('#applicant-welcome','Bedankt voor je interesse, ' + user.name + '. Ons team heeft je sollicitatie bekeken en kijkt uit naar een kennismaking.');
  put('#account-name',user.name); put('#account-email',user.email);
}

async function start() {
  await Portal.themeReady;
  if (page === 'index' && !Portal.company) { await indexPage(); return; }
  if (!Portal.validCompany || !Portal.companyInfo) { neutral(Portal.themeError || undefined); return; }
  try { Portal.session = await Portal.api('/api/session'); }
  catch (error) { neutral(error.message); return; }
  const pages = {index:applyPage,apply:applyPage,login:loginPage,'set-password':setPasswordPage,'verify-email':verifyPage,employee:employeePage,dashboard:dashboardPage};
  try { await (pages[page] || applyPage)(); }
  catch (error) { neutral('Het portaal kon niet worden geladen. ' + error.message); }
}
start();
