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
let adminView = null;
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
function timeText(value) { const date = parseDate(value); return !value || Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('nl-NL',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Amsterdam'}).format(date); }
function localDate(date) { const shifted = new Date(date.getTime() - date.getTimezoneOffset()*60000); return shifted.toISOString().slice(0,10); }
function todayValue() { const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA',{timeZone:'Europe/Amsterdam',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).map(part => [part.type,part.value])); return parts.year + '-' + parts.month + '-' + parts.day; }
function message(target, text, type = 'error') { const node = typeof target === 'string' ? $(target) : target; if (!node) return; node.className = 'message ' + type; node.textContent = text; node.hidden = !text; node.setAttribute('role',type === 'error' ? 'alert' : 'status'); }
function busy(button, loading) { button.disabled = loading; if (loading) { button.dataset.originalText = button.textContent; button.textContent = 'Even geduld…'; } else { button.textContent = button.dataset.originalText || button.textContent; } }
function statusBadge(status) { const badge = el('span','badge ' + status,({pending:'In afwachting',approved:'Goedgekeurd',rejected:'Afgewezen',scheduled:'Gepland',confirmed:'Gepland',cancelled:'Geannuleerd',active:'Actief',inactive:'Inactief'})[status] || status); return badge; }
function roleHome(role) { return role === 'employee' ? 'employee.html' : role === 'applicant' ? 'schedule.html' : 'admin.html'; }
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
  const links = user.role === 'employee' ? [['employee.html','file','Sollicitaties','employee'],['employee.html','calendar','Mijn gesprekken','appointments',{view:'appointments'}]] : user.role === 'applicant' ? [['dashboard.html','user','Mijn overzicht','dashboard'],['schedule.html','calendar','Mijn gesprek','schedule']] : [['admin.html','building','Bedrijven & beheer','admin']];
  app.innerHTML = `<div class="app-layout"><aside class="sidebar"><a class="brand" data-brand href="${Portal.link(roleHome(user.role))}"></a><p class="sidebar-label">Werkruimte</p><nav class="nav-links" aria-label="Hoofdnavigatie">${links.map(([href,name,label,key,params]) => `<a class="nav-link ${key === active ? 'active' : ''}" href="${Portal.link(href,params)}" ${key === active ? 'aria-current="page"' : ''}>${icon(name)}${label}</a>`).join('')}</nav><div class="sidebar-bottom"><div class="user-box"><span class="avatar" id="user-initials"></span><div><span class="user-name" id="user-name"></span><span class="user-role"></span></div></div><button class="logout-btn" data-logout>${icon('logout')} Uitloggen</button></div></aside><main class="app-body"><div class="app-topbar"><div class="breadcrumb"><span data-company-name></span><span>/</span><span id="breadcrumb-page"></span></div><button class="text-btn mobile-logout" data-logout>Uitloggen ${icon('logout')}</button><span class="topbar-status">Beveiligde omgeving</span></div><div class="page-heading"><div><p class="eyebrow">${user.role === 'applicant' ? 'Jouw volgende stap' : 'Overzicht'}</p><h1 id="page-title"></h1><p id="page-subtitle"></p></div>${action || '<span class="heading-aside" id="today-date"></span>'}</div><div id="page-message" class="message" hidden></div>${user.role === 'employee' ? employeeDepartmentPanel() : ''}${content}<footer class="app-footer"><span>Sollicitatieportaal · <span data-company-name></span></span><span>Veilig verbonden</span></footer></main></div>`;
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
  publicShell(`<main class="public-main hero-grid"><section class="hero-intro"><span class="hero-tag"><span></span>Ruimte voor jouw talent</span><h1>Een mooie stap<br>begint bij jou.</h1><p>Vertel ons wie je bent en waar je enthousiast van wordt. We kijken uit naar jouw verhaal.</p><div class="journey"><div class="journey-step"><span class="step-num">01</span><div><strong>Deel jouw verhaal</strong><p>Je gegevens, cv en motivatie.</p></div></div><div class="journey-step"><span class="step-num">02</span><div><strong>Wij maken kennis</strong><p>Ons team bekijkt je sollicitatie.</p></div></div><div class="journey-step"><span class="step-num">03</span><div><strong>Tijd voor een gesprek</strong><p>Bij een match plan je zelf een moment.</p></div></div></div><div class="privacy-note">${icon('shield')}<span>Je documenten zijn alleen zichtbaar voor het betrokken team. We gaan zorgvuldig om met jouw gegevens.</span></div></section><section class="card form-card" id="application-card"><h2>Laat van je horen</h2><p class="card-description">Solliciteer bij <span data-company-name></span>. We zijn benieuwd naar je.</p><form id="application-form"><div id="form-message" class="message" hidden></div><div class="form-section-label">Over jou</div><label class="field"><span class="field-label">Volledige naam <span class="required">*</span></span><input class="input" name="name" autocomplete="name" placeholder="Voor- en achternaam" required maxlength="150"></label><label class="field"><span class="field-label">E-mailadres <span class="required">*</span></span><input class="input" type="email" name="email" autocomplete="email" placeholder="jij@voorbeeld.nl" required maxlength="254"><span class="field-help">Hier houden we je op de hoogte van je sollicitatie.</span></label><label class="field"><span class="field-label">Functie / afdeling <span class="required">*</span></span><select class="input" name="position" required></select><span class="field-help">Kies waar je interesse ligt, of kies een stage of open sollicitatie.</span></label><div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><div class="form-section-label">Jouw verhaal</div><div class="field"><span class="field-label" id="cv-label">Curriculum vitae <span class="required">*</span></span><label class="upload"><span class="upload-icon">${icon('upload')}</span><span class="upload-copy"><strong data-file-name>Kies je cv of sleep het hier</strong><span>PDF of DOCX · maximaal 5 MB</span></span><input type="file" name="cv" aria-labelledby="cv-label" accept=".pdf,.docx" required></label></div><div class="field"><span class="field-label" id="letter-label">Motivatiebrief <span class="required">*</span></span><label class="upload"><span class="upload-icon">${icon('file')}</span><span class="upload-copy"><strong data-file-name>Voeg je motivatiebrief toe</strong><span>PDF of DOCX · maximaal 5 MB</span></span><input type="file" name="letter" aria-labelledby="letter-label" accept=".pdf,.docx" required></label></div><button class="btn btn-block" type="submit">Verstuur mijn sollicitatie ${icon('arrow')}</button><p class="form-fineprint">Na verzenden ontvang je een e-mail om je adres te bevestigen.<br>Je hoeft nu nog geen account of wachtwoord aan te maken.</p></form></section></main>`);
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
  if (new URLSearchParams(location.search).get('view') === 'appointments') { await employeeAppointments(); return; }
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
    $('#application-detail').innerHTML = `<a class="back-link" href="${Portal.link('employee.html')}">${icon('back')} Terug naar het overzicht</a><div class="detail-grid"><section class="card detail-card"><div class="person-cell"><span class="avatar" id="applicant-initials"></span><div><h2 id="applicant-name"></h2><span id="applicant-email"></span></div></div><hr class="section-divider"><h3>Bijgevoegde documenten</h3><p class="small muted">Download de documenten om het verhaal van deze sollicitant te lezen.</p><div id="application-documents"></div><dl><div class="detail-item"><dt>Functie / sollicitatietype</dt><dd id="application-position"></dd></div><div class="detail-item"><dt>Afdeling</dt><dd id="application-department"></dd></div><div class="detail-item"><dt>Ontvangen op</dt><dd id="received-at"></dd></div><div class="detail-item"><dt>E-mailadres</dt><dd id="verified-status"></dd></div></dl></section><aside class="card detail-card"><h3>Beoordeling</h3><div id="application-status"></div><p class="small muted review-description">Na je beoordeling ontvangt de sollicitant automatisch een e-mail. Bij goedkeuring krijgt de sollicitant toegang om een gesprek met jou te plannen.</p><div id="review-meta"></div><div class="review-actions" id="review-actions"></div><p class="small muted">Een beoordeling is definitief.</p></aside></div>`;
    put('#applicant-initials',initials(application.name)); put('#applicant-name',application.name); put('#applicant-email',application.email); put('#received-at',dateText(application.created_at,{hour:'2-digit',minute:'2-digit'})); put('#verified-status',application.email_verified_at ? 'Bevestigd op ' + dateText(application.email_verified_at) : 'Nog niet bevestigd');
    put('#application-position',choiceLabel('positions',application.position)); put('#application-department',choiceLabel('departments',application.department));
    $('#application-status').append(statusBadge(application.status));
    for (const [type,label] of [['cv','Curriculum vitae'],['letter','Motivatiebrief']]) {
      const link = el('a','download-card'); link.href = Portal.url('/api/documents/' + application.id,{type}); const mark = el('span'); mark.innerHTML = icon('file'); const copy = el('div'); copy.append(el('strong','',label),el('small','',application[type + '_name'] || 'Document downloaden')); const download = el('span'); download.innerHTML = icon('download'); link.append(mark,copy,download); $('#application-documents').append(link);
    }
    if (application.status === 'pending') {
      const approve = el('button','btn','Goedkeuren & uitnodigen'); const reject = el('button','btn btn-danger','Sollicitatie afwijzen'); $('#review-actions').append(approve,reject);
      if (!application.email_verified_at) { const note = el('p','small muted','De sollicitant moet eerst het e-mailadres bevestigen voordat je kunt beoordelen.'); $('#review-actions').prepend(note); approve.disabled = true; reject.disabled = true; }
      for (const [button,status] of [[approve,'approved'],[reject,'rejected']]) button.addEventListener('click',() => confirmDialog(status === 'approved' ? 'Deze sollicitatie goedkeuren?' : 'Deze sollicitatie afwijzen?',status === 'approved' ? 'De sollicitant ontvangt een persoonlijke link om een wachtwoord in te stellen en een gesprek met jou te plannen.' : 'De sollicitant ontvangt een afwijzingsmail. Deze beoordeling kan niet worden gewijzigd.',status === 'approved' ? 'Goedkeuren' : 'Definitief afwijzen',async () => { await Portal.api('/api/applications/' + id,{method:'PATCH',body:{status}}); await applicationDetail(id); message('#page-message','De beoordeling is opgeslagen. De sollicitant ontvangt een e-mail.','success'); },status === 'rejected'));
    } else { const info = el('p','small muted','Beoordeeld' + (application.reviewer_name ? ' door ' + application.reviewer_name : '') + (application.reviewed_at ? ' op ' + dateText(application.reviewed_at) : '') + '.'); $('#review-meta').append(info); }
  } catch (error) { if (view !== employeeView) return; message('#page-message',error.message); empty('#application-detail','Sollicitatie niet beschikbaar','Deze sollicitatie bestaat niet of is niet toegankelijk voor jouw account.'); }
}

async function employeeAppointments() {
  const view = ++employeeView;
  shell('Ruimte voor een goed gesprek.','Een overzicht van de kennismakingen die met jou zijn ingepland.','<section class="card"><div class="panel-header"><div><h2>Mijn gesprekken</h2><p>Alle tijden zijn in de Nederlandse tijdzone.</p></div></div><div id="employee-appointments"><div class="loading"><span class="spinner"></span>Gesprekken laden...</div></div></section>','appointments');
  try {
    const {appointments = []} = await Portal.api('/api/appointments'); if (view !== employeeView) return;
    if (!appointments.length) { empty('#employee-appointments','Je agenda heeft nog ruimte','Zodra een goedgekeurde sollicitant een gesprek plant, zie je dat hier.','calendar'); return; }
    const container = $('#employee-appointments'); container.innerHTML = '<div class="table-wrap"><table><thead><tr><th>Sollicitant</th><th>Datum</th><th>Tijd</th><th>Status</th><th>Actie</th></tr></thead><tbody></tbody></table></div>';
    for (const appointment of appointments) {
      const row = el('tr'); const person = el('td'); const info = el('div','person-cell'); const copy = el('div');
      const name = appointment.applicant_name || appointment.name || 'Sollicitant'; copy.append(el('strong','',name),el('span','',appointment.email || '')); info.append(el('span','avatar',initials(name)),copy); person.append(info);
      row.append(person,el('td','',dateText(appointment.starts_at)),el('td','',timeText(appointment.starts_at) + ' - ' + timeText(appointment.ends_at)));
      const status = el('td'); status.append(statusBadge(appointment.status)); row.append(status);
      const actions = el('td');
      if (appointment.status === 'scheduled') {
        const cancel = el('button','text-btn','Gesprek annuleren'); cancel.type = 'button';
        cancel.addEventListener('click',() => confirmDialog('Dit gesprek annuleren?',name + ' ontvangt een e-mail en kan daarna een nieuw moment kiezen.','Gesprek annuleren',async () => {
          await Portal.api('/api/appointments/' + appointment.id,{method:'DELETE'});
          if (view !== employeeView) return;
          await employeeAppointments(); message('#page-message','Het gesprek is geannuleerd. De sollicitant ontvangt een e-mail.','success');
        },true)); actions.append(cancel);
      } else actions.append(el('span','muted','Geen actie nodig'));
      row.append(actions); $('tbody',container).append(row);
    }
  } catch (error) { if (view !== employeeView) return; message('#page-message',error.message); empty('#employee-appointments','Ophalen niet gelukt','Vernieuw de pagina om het opnieuw te proberen.','calendar'); }
}

async function schedulePage() {
  if (!requireRole(['applicant'])) return;
  shell('Laten we kennismaken.','Kies een moment dat bij je past. We kijken uit naar je verhaal.',`<div class="schedule-grid"><section class="card calendar-card"><div class="panel-header"><div><h2>Kies jouw moment</h2><p>Alle tijden zijn in de Nederlandse tijdzone.</p></div></div><div class="panel-header"><h2 id="calendar-month"></h2><div class="calendar-controls"><button class="btn btn-secondary btn-small" id="previous-month" aria-label="Vorige maand">${icon('back')}</button><button class="btn btn-secondary btn-small" id="next-month" aria-label="Volgende maand">${icon('arrow')}</button></div></div><div class="calendar-weekdays"><span>Ma</span><span>Di</span><span>Wo</span><span>Do</span><span>Vr</span><span>Za</span><span>Zo</span></div><div class="calendar-days" id="calendar-days" aria-label="Kies een datum"></div><div class="slots-section"><div class="slots-heading"><strong id="slot-date"></strong><span>Gesprek · 30 minuten</span></div><div id="slots-container"><div class="loading"><span class="spinner"></span>Beschikbaarheid laden…</div></div><div class="slot-legend"><span><i></i>Beschikbaar</span><span><i></i>Niet beschikbaar</span></div></div></section><aside><section class="card appointment-card"><h2>Jouw kennismaking</h2><div class="interviewer"><span class="avatar" id="reviewer-initials">—</span><div><strong id="reviewer-name">Medewerker laden…</strong><span>Jouw contactpersoon</span></div></div><div id="booking-summary"><div class="appointment-info">${icon('calendar')}<div><strong id="booking-date">Kies een datum</strong><small id="booking-time">En een beschikbaar moment</small></div></div><div class="appointment-info">${icon('clock')}<div><strong>Een eerste kennismaking</strong><small>We nemen de tijd voor jouw verhaal.</small></div></div></div><div id="booking-message" class="message" hidden></div><button class="btn btn-block" id="book-button" disabled>Bevestig mijn gesprek ${icon('arrow')}</button><p class="form-fineprint" id="booking-fineprint">Je ontvangt een bevestiging per e-mail.</p></section><section class="help-card"><h3>Goed om te weten</h3><p>Je spreekt met de medewerker die jouw sollicitatie heeft beoordeeld. Grijze momenten zijn bezet of vallen buiten werktijd.</p></section></aside></div><div class="dashboard-note"><span>${icon('shield')} Alleen jij en je contactpersoon kunnen jouw afspraak bekijken.</span><button class="text-btn" id="remove-account">Mijn gegevens verwijderen</button></div>`,'schedule');
  let selectedDate = todayValue(); let month = new Date(selectedDate + 'T12:00:00'); let selectedSlot = null; let appointment = null; let loadingId = 0;
  const horizon = new Date(selectedDate + 'T12:00:00'); horizon.setDate(horizon.getDate()+90); const lastDate = localDate(horizon);
  const renderCalendar = () => {
    put('#calendar-month',new Intl.DateTimeFormat('nl-NL',{month:'long',year:'numeric'}).format(month));
    const container = $('#calendar-days'); container.replaceChildren(); const first = new Date(month.getFullYear(),month.getMonth(),1,12); const count = new Date(month.getFullYear(),month.getMonth()+1,0).getDate(); const offset = (first.getDay()+6)%7;
    for (let i=0;i<offset;i++) container.append(el('span','calendar-day blank',''));
    const today = todayValue();
    for (let day=1;day<=count;day++) { const value = localDate(new Date(month.getFullYear(),month.getMonth(),day,12)); const button = el('button','calendar-day' + (value === selectedDate ? ' selected' : '') + (value === today ? ' today' : ''),day); button.type = 'button'; button.disabled = value < today || value > lastDate; button.setAttribute('aria-label',dateText(value + 'T12:00:00Z',{month:'long',weekday:'long'})); button.setAttribute('aria-pressed',String(value === selectedDate)); button.addEventListener('click',() => { selectedDate = value; selectedSlot = null; renderCalendar(); loadSlots(); }); container.append(button); }
    const currentMonth = new Date(today + 'T12:00:00'); $('#previous-month').disabled = month.getFullYear() === currentMonth.getFullYear() && month.getMonth() === currentMonth.getMonth();
    $('#next-month').disabled = month.getFullYear() === horizon.getFullYear() && month.getMonth() === horizon.getMonth();
  };
  function renderBooking() {
    const button = $('#book-button');
    if (appointment) { put('#booking-date',dateText(appointment.starts_at,{month:'long',weekday:'long'})); put('#booking-time',timeText(appointment.starts_at) + ' – ' + timeText(appointment.ends_at)); button.textContent = 'Gesprek annuleren'; button.className = 'btn btn-secondary btn-block'; button.disabled = false; put('#booking-fineprint','Je gesprek staat gepland. De bevestiging staat in je e-mail.'); }
    else { put('#booking-date',dateText(selectedDate + 'T12:00:00Z',{month:'long',weekday:'long'})); put('#booking-time',selectedSlot ? timeText(selectedSlot.starts_at) + ' – ' + timeText(selectedSlot.ends_at) : 'Kies een beschikbaar tijdstip'); button.textContent = 'Bevestig mijn gesprek'; button.className = 'btn btn-block'; button.disabled = !selectedSlot; put('#booking-fineprint','Je ontvangt een bevestiging per e-mail.'); }
  }
  async function loadSlots() {
    const request = ++loadingId; message('#page-message',''); selectedSlot = null; renderBooking(); put('#slot-date',dateText(selectedDate + 'T12:00:00Z',{month:'long',weekday:'long'})); $('#slots-container').innerHTML = '<div class="loading"><span class="spinner"></span>Beschikbaarheid laden…</div>';
    try {
      const data = await Portal.api('/api/slots',{params:{date:selectedDate}}); if (request !== loadingId) return;
      appointment = data.appointment && data.appointment.status !== 'cancelled' ? data.appointment : null;
      put('#reviewer-name',data.employee?.name || 'Jouw contactpersoon'); put('#reviewer-initials',initials(data.employee?.name));
      const slots = data.slots || []; const container = $('#slots-container'); container.replaceChildren();
      if (!slots.length) empty(container,'Deze dag heeft geen beschikbare momenten','Kies een andere dag in de kalender.','calendar');
      else { const grid = el('div','slots-grid'); for (const slot of slots) { const button = el('button','slot-btn',timeText(slot.starts_at)); button.type = 'button'; button.disabled = !slot.available || !!appointment; button.setAttribute('aria-label',timeText(slot.starts_at) + (slot.available ? ', beschikbaar' : ', niet beschikbaar')); button.setAttribute('aria-pressed','false'); button.addEventListener('click',() => { selectedSlot = slot; grid.querySelectorAll('button').forEach(item => { item.classList.toggle('selected',item === button); item.setAttribute('aria-pressed',String(item === button)); }); renderBooking(); }); grid.append(button); } container.append(grid); }
      renderBooking(); if (appointment) message('#booking-message','Je kennismaking staat gepland.','success'); else message('#booking-message','');
    } catch (error) { if (request !== loadingId) return; message('#page-message',error.message); empty('#slots-container','Geen beschikbaarheid opgehaald','Probeer een andere dag of vernieuw de pagina.','calendar'); $('#book-button').disabled = true; }
  }
  $('#previous-month').addEventListener('click',() => { month = new Date(month.getFullYear(),month.getMonth()-1,1,12); renderCalendar(); });
  $('#next-month').addEventListener('click',() => { month = new Date(month.getFullYear(),month.getMonth()+1,1,12); renderCalendar(); });
  $('#book-button').addEventListener('click',() => {
    if (appointment) { const id = appointment.id; confirmDialog('Je gesprek annuleren?','Het tijdstip komt weer vrij. Je kunt daarna een nieuw moment kiezen.','Gesprek annuleren',async () => { await Portal.api('/api/appointments/' + id,{method:'DELETE'}); appointment = null; await loadSlots(); message('#booking-message','Je gesprek is geannuleerd. Kies gerust een nieuw moment.','info'); },true); }
    else if (selectedSlot) { const slot = selectedSlot; confirmDialog('Dit moment vastleggen?',dateText(slot.starts_at,{weekday:'long',month:'long'}) + ' van ' + timeText(slot.starts_at) + ' tot ' + timeText(slot.ends_at) + '. Je ontvangt een bevestiging per e-mail.','Bevestig mijn gesprek',async () => { try { await Portal.api('/api/appointments',{method:'POST',body:{starts_at:slot.starts_at}}); await loadSlots(); } catch (error) { if (error.status === 409) await loadSlots(); throw error; } }); }
  });
  $('#remove-account').addEventListener('click',removeAccountDialog);
  renderCalendar(); await loadSlots();
}

function removeAccountDialog() {
  const overlay = el('div','modal-backdrop'); overlay.innerHTML = '<section class="modal" role="dialog" aria-modal="true" aria-labelledby="delete-title"><h2 id="delete-title">Je gegevens verwijderen?</h2><p>Je account, sollicitatie en documenten worden verwijderd. Een geplande afspraak wordt geannuleerd. Dit kan niet ongedaan worden gemaakt.</p><form><label class="field"><span class="field-label">Bevestig met je wachtwoord</span><input class="input" type="password" name="password" autocomplete="current-password" required></label><div class="message" hidden></div><div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Behouden</button><button class="btn btn-danger" type="submit">Definitief verwijderen</button></div></form></section>';
  const previous = document.activeElement; const close = () => { overlay.remove(); previous?.focus(); };
  $('[data-close]',overlay).addEventListener('click',close); overlay.addEventListener('keydown',event => { if (event.key === 'Escape') close(); });
  $('form',overlay).addEventListener('submit',async event => { event.preventDefault(); const button = $('button[type=submit]',overlay); busy(button,true); try { await Portal.api('/api/account',{method:'DELETE',body:{password:$('input',overlay).value}}); location.href = Portal.link('apply.html'); } catch (error) { message($('.message',overlay),error.message); busy(button,false); } });
  document.body.append(overlay); $('input',overlay).focus();
}

async function adminPage() {
  if (!requireRole(['admin'])) return;
  const currentView = {}; adminView = currentView;
  shell('Een eigen plek voor elk bedrijf.','Beheer bedrijven, geef het portaal een eigen gezicht en nodig je team uit.','<div id="admin-content"><div class="loading"><span class="spinner"></span>Bedrijven laden…</div></div>','admin','<button class="btn" id="add-company">' + icon('plus') + ' Bedrijf toevoegen</button>');
  $('#add-company').addEventListener('click',() => companyForm());
  try {
    const {companies = []} = await Portal.api('/api/admin/companies');
    if (adminView !== currentView) return;
    const container = $('#admin-content'); container.innerHTML = '<section class="stats-grid"><div class="card stat-card"><div class="stat-top">Bedrijven ' + icon('building') + '</div><strong class="stat-value" id="company-count"></strong><span class="stat-caption">Elk bedrijf heeft een eigen portaal</span></div><div class="card stat-card"><div class="stat-top">Actieve portalen ' + icon('check') + '</div><strong class="stat-value" id="active-company-count"></strong><span class="stat-caption">Klaar om talent te ontvangen</span></div><div class="card stat-card"><div class="stat-top">Eén omgeving ' + icon('shield') + '</div><strong class="stat-value">100%</strong><span class="stat-caption">Gescheiden toegang per bedrijf</span></div></section><section class="card"><div class="panel-header"><div><h2>Bedrijven & portalen</h2><p>Een eigen uitstraling, dezelfde vertrouwde ervaring.</p></div></div><div class="company-card"><div class="company-list" id="company-list"></div></div></section><div class="dashboard-note"><span>' + icon('info') + ' Een nieuw bedrijf is na het opslaan direct bereikbaar via zijn eigen sollicitatielink.</span></div>';
    put('#company-count',companies.length); put('#active-company-count',companies.filter(company => Number(company.is_active) === 1 || company.is_active === true).length);
    if (!companies.length) { empty('#company-list','Het begint met een bedrijf','Voeg het eerste bedrijf toe en richt het sollicitatieportaal in.','building'); return; }
    for (const company of companies) {
      const row = el('article','company-admin-row'); const swatch = el('span','company-swatch',initials(company.name)); const color = company.theme?.primary_color; if (/^#[0-9a-f]{6}$/i.test(color || '')) { swatch.style.setProperty('--swatch',color); swatch.style.setProperty('--swatch-ink',Portal.colorText(color)); }
      if (logoUrl(company.theme)) { const logo = el('img','brand-logo'); logo.src = logoUrl(company.theme); logo.alt = ''; swatch.replaceChildren(logo); swatch.classList.add('has-logo'); }
      const copy = el('div','company-name'); copy.append(el('h3','',company.name),el('p','',company.slug));
      const edit = el('button','btn btn-secondary btn-small','Beheren'); edit.addEventListener('click',() => companyForm(company));
      const open = el('a','text-btn','Portaal bekijken'); open.href = '/apply.html?company=' + encodeURIComponent(company.slug); open.target = '_blank'; open.rel = 'noopener';
      row.append(swatch,copy,statusBadge(Number(company.is_active) === 1 || company.is_active === true ? 'active' : 'inactive'),open,edit); $('#company-list').append(row);
    }
  } catch (error) { if (adminView !== currentView) return; message('#page-message',error.message); empty('#admin-content','Bedrijven niet opgehaald','Vernieuw de pagina om het opnieuw te proberen.','building'); }
}

async function companyForm(company = null) {
  adminView = {};
  const editing = !!company; const container = $('#admin-content'); $('#add-company').hidden = true; message('#page-message','');
  put('#page-title',editing ? 'Geef je portaal een eigen gezicht.' : 'Een nieuw verhaal begint hier.');
  put('#page-subtitle',editing ? 'Pas het bedrijf aan en beheer wie toegang krijgt.' : 'Voeg een bedrijf toe. Het portaal is na opslaan direct beschikbaar.');
  container.innerHTML = `<button class="back-link text-btn" id="back-to-companies">${icon('back')} Terug naar bedrijven</button><div class="admin-grid"><div><section class="card admin-form-card"><h2 id="company-form-title"></h2><p class="small muted">Alles wat je nodig hebt voor een herkenbaar sollicitatieportaal.</p><form id="company-form" enctype="multipart/form-data"><div class="form-section-label">Bedrijfsgegevens</div><div class="form-row stack-mobile"><label class="field"><span class="field-label">Bedrijfsnaam *</span><input class="input" name="name" required maxlength="150" placeholder="Naam van het bedrijf"></label><label class="field"><span class="field-label">Unieke slug *</span><input class="input" name="slug" required maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="jouw-bedrijf"><span class="field-help">Alleen kleine letters, cijfers en koppeltekens.</span></label></div><label class="checkbox-label"><input type="checkbox" name="is_active" value="1" checked>Dit bedrijf is actief en ontvangt sollicitaties</label><div class="copy-link"><a id="application-link" target="_blank" rel="noopener"></a><button type="button" id="copy-application-link">Kopiëren</button></div><div class="form-section-label">Een eigen uitstraling</div><div class="form-row"><label class="field"><span class="field-label">Primaire kleur</span><span class="color-field"><input type="color" id="primary-picker" value="#263c3b" aria-label="Kies primaire kleur"><input class="input" name="primary_color" value="#263c3b" pattern="#[0-9a-fA-F]{6}" required maxlength="7" aria-label="Primaire hexkleur"></span></label><label class="field"><span class="field-label">Accentkleur</span><span class="color-field"><input type="color" id="secondary-picker" value="#b5d4bd" aria-label="Kies accentkleur"><input class="input" name="secondary_color" value="#b5d4bd" pattern="#[0-9a-fA-F]{6}" required maxlength="7" aria-label="Accent hexkleur"></span></label></div><label class="field"><span class="field-label">Lettertypenaam</span><input class="input" name="font_family" maxlength="80" placeholder="Bijvoorbeeld: Arial"><span class="field-help">Zonder eigen lettertype gebruikt het portaal het systeemlettertype.</span></label><div class="form-row stack-mobile"><div class="field"><span class="field-label">Bedrijfslogo</span><div class="company-logo-preview"><img id="company-logo-image" alt="Bedrijfslogo" hidden><span id="company-logo-empty" class="small muted">Nog geen logo toegevoegd</span></div><label class="field-help" for="company-logo-upload">Logo kiezen of vervangen</label><input class="input" id="company-logo-upload" type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp"><span class="field-help">PNG, JPG, SVG of WebP · maximaal 2 MB. Dit logo verschijnt voor alle accounts en op het sollicitatieportaal van dit bedrijf.</span><label class="checkbox-label" id="remove-logo-field" hidden><input type="checkbox" name="remove_logo" value="1">Huidig logo verwijderen</label><button type="button" class="text-btn" id="reset-logo-choice" hidden>Bestandskeuze wissen</button><span id="logo-status" class="field-help" role="status"></span></div><label class="field"><span class="field-label">Lettertypebestand (optioneel)</span><input class="input" type="file" name="font_file" accept=".ttf,.woff2"><span class="field-help">TTF of WOFF2 · maximaal 1 MB</span></label></div><div class="form-section-label">E-mail & communicatie</div><div class="form-row stack-mobile"><label class="field"><span class="field-label">Afzendernaam *</span><input class="input" name="mail_from_name" maxlength="150" placeholder="Naam in de inbox" required></label><label class="field"><span class="field-label">Afzenderadres *</span><input class="input" type="email" name="mail_from_address" maxlength="254" placeholder="sollicitaties@bedrijf.nl" required></label></div><details><summary class="small">E-mailsjablonen aanpassen</summary><label class="field"><span class="field-help">Optioneel. Sleutels: verify_email, application_received, application_rejected, set_password, appointment_confirmation, appointment_cancelled, employee_appointment_confirmation en employee_appointment_cancelled. Per type: {"subject":"Onderwerp","body":"Bericht"}. Placeholders: {company_name}, {name}, {verification_url}, {setup_url}, {starts_at}, {employee_name} en {applicant_name}.</span><textarea class="input" name="mail_templates" rows="8" spellcheck="false" aria-label="E-mailsjablonen als JSON"></textarea></label></details><div class="form-section-label">Agenda-uitnodigingen</div><p class="small muted">Bij het plannen of annuleren van een gesprek ontvangen de sollicitant en medewerker een e-mail met een agenda-uitnodiging. Deze kan worden geopend in onder andere Outlook en Google Agenda.</p><div id="company-form-message" class="message" hidden></div><button class="btn" type="submit" id="save-company">${icon('check')} Bedrijf opslaan</button></form></section><section class="card admin-form-card" id="employee-management" ${editing ? '' : 'hidden'}><h2>Het team achter het portaal</h2><p class="small muted">Medewerkers ontvangen per e-mail een uitnodiging om hun wachtwoord in te stellen. Nieuwe medewerkers zijn standaard beschikbaar van maandag tot en met vrijdag, 09:00 tot 17:00. Pas hun werktijden hieronder aan.</p><div id="employee-list"><div class="loading"><span class="spinner"></span>Medewerkers laden…</div></div><hr class="section-divider"><h3>Medewerker uitnodigen</h3><form id="employee-form" class="inline-form employee-invite-form"><label class="field"><span class="field-label">Naam</span><input class="input" name="name" required maxlength="150" placeholder="Voor- en achternaam"></label><label class="field"><span class="field-label">E-mailadres</span><input class="input" type="email" name="email" required maxlength="254" placeholder="collega@bedrijf.nl"></label><label class="field"><span class="field-label">Afdeling <span class="required">*</span></span><select class="input" name="department" required></select></label><button class="btn" type="submit">${icon('plus')} Uitnodigen</button></form><div id="employee-message" class="message" hidden></div></section></div><aside><section class="card preview-card"><div class="preview-label">Live voorbeeld van je portaal</div><div class="preview-inner" id="theme-preview"><div class="preview-brand"><span class="preview-mark" id="preview-mark"></span><img id="preview-logo" alt="Logovoorbeeld" hidden><span id="preview-company-name">Jouw bedrijf</span></div><span class="hero-tag">Ruimte voor jouw talent</span><h3>Een mooie stap<br>begint bij jou.</h3><p>Laat van je horen. We zijn benieuwd naar jouw verhaal.</p><div class="preview-field"></div><div class="preview-field"></div><div class="preview-btn">Verstuur mijn sollicitatie →</div></div><div class="preview-accent" id="preview-accent"></div></section><section class="help-card"><h3>Direct klaar voor gebruik</h3><p>De sollicitatielink, het thema en e-mails gebruiken de gegevens van dit bedrijf. Een eigen portaal, zonder extra stappen.</p></section></aside></div>`;
  put('#company-form-title',editing ? 'Bedrijf wijzigen' : 'Bedrijf toevoegen');
  $('#back-to-companies').addEventListener('click',adminPage);
  const form = $('#company-form');
  const formMessage = $('#company-form-message');
  if (editing) {
    for (const name of ['name','slug','mail_from_name','mail_from_address']) form.elements[name].value = company[name] || '';
    form.elements.is_active.checked = Number(company.is_active) === 1 || company.is_active === true;
    for (const name of ['primary_color','secondary_color','font_family']) if (company.theme?.[name]) form.elements[name].value = company.theme[name];
    if (company.mail_templates && Object.keys(company.mail_templates).length) form.elements.mail_templates.value = JSON.stringify(company.mail_templates,null,2);
  }
  const storedLogo = logoUrl(company?.theme);
  let selectedLogo = '';
  let logoRequest = 0;
  let previewFont = '';
  let fontRequest = 0;
  const previewFontPrefix = 'PortalPreviewFont' + Date.now();
  $('#remove-logo-field').hidden = !storedLogo;
  function previewLogo() {
    const removed = form.elements.remove_logo.checked;
    const url = removed ? '' : selectedLogo || storedLogo;
    for (const id of ['company-logo-image','preview-logo']) {
      const image = $('#' + id); image.hidden = !url;
      image.alt = 'Logo van ' + (form.elements.name.value || 'dit bedrijf');
      if (url) image.src = url; else image.removeAttribute('src');
    }
    $('#company-logo-empty').hidden = !!url;
    $('#company-logo-empty').textContent = removed ? 'Het logo wordt na opslaan verwijderd.' : 'Nog geen logo toegevoegd';
    $('#preview-mark').hidden = !!url;
    $('#reset-logo-choice').hidden = !form.elements.logo.files.length;
    const file = form.elements.logo.files[0];
    put('#logo-status',form.elements.logo.validationMessage || (removed ? 'Klik op Bedrijf opslaan om het logo te verwijderen.' : file ? 'Gekozen: ' + file.name + '. Klik op Bedrijf opslaan om dit logo te gebruiken.' : storedLogo ? 'Huidig bedrijfslogo. Kies een bestand om het te vervangen.' : 'Kies een bestand en klik op Bedrijf opslaan.'));
  }
  function preview() {
    const name = form.elements.name.value || 'Jouw bedrijf'; put('#preview-company-name',name);
    const primary = form.elements.primary_color.value; const secondary = form.elements.secondary_color.value;
    if (/^#[a-f0-9]{6}$/i.test(primary)) $('#primary-picker').value = primary;
    if (/^#[a-f0-9]{6}$/i.test(secondary)) $('#secondary-picker').value = secondary;
    Portal.applyTheme({primary_color:primary,secondary_color:secondary,font_family:form.elements.font_family.value},$('.preview-card'));
    $('#theme-preview').style.fontFamily = previewFont ? '"' + previewFont + '",system-ui,sans-serif' : '';
    previewLogo();
    const slug = form.elements.slug.value; const valid = /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug); const link = $('#application-link'); link.textContent = valid ? location.origin + '/apply.html?company=' + slug : 'Vul een slug in om de sollicitatielink te maken'; if (valid) link.href = '/apply.html?company=' + encodeURIComponent(slug); else link.removeAttribute('href'); $('#copy-application-link').disabled = !valid;
  }
  ['name','slug','primary_color','secondary_color','font_family'].forEach(name => form.elements[name].addEventListener('input',preview));
  for (const color of ['primary','secondary']) $('#' + color + '-picker').addEventListener('input',event => { form.elements[color + '_color'].value = event.currentTarget.value; preview(); });
  form.elements.logo.addEventListener('change',() => {
    const request = ++logoRequest; const file = form.elements.logo.files[0]; selectedLogo = '';
    if (file) form.elements.remove_logo.checked = false;
    form.elements.logo.setCustomValidity(file && file.size > 2*1024*1024 ? 'Het logo mag maximaal 2 MB zijn.' : file && !/\.(png|jpe?g|svg|webp)$/i.test(file.name) ? 'Kies een PNG-, JPG-, SVG- of WebP-bestand.' : '');
    previewLogo();
    if (file && form.elements.logo.validity.valid) {
      const reader = new FileReader();
      reader.addEventListener('load',() => { if (!form.isConnected || request !== logoRequest) return; selectedLogo = String(reader.result); previewLogo(); });
      reader.addEventListener('error',() => { if (form.isConnected && request === logoRequest) put('#logo-status','Het voorbeeld kon niet worden geladen. Kies het bestand opnieuw.'); });
      reader.readAsDataURL(file);
    }
  });
  form.elements.remove_logo.addEventListener('change',() => { ++logoRequest; selectedLogo = ''; form.elements.logo.value = ''; form.elements.logo.setCustomValidity(''); previewLogo(); });
  $('#reset-logo-choice').addEventListener('click',() => { ++logoRequest; selectedLogo = ''; form.elements.logo.value = ''; form.elements.logo.setCustomValidity(''); previewLogo(); });
  form.elements.font_file.addEventListener('change',async () => {
    const request = ++fontRequest; const file = form.elements.font_file.files[0]; previewFont = '';
    form.elements.font_file.setCustomValidity(file && file.size > 1024*1024 ? 'Het lettertype mag maximaal 1 MB zijn.' : ''); preview();
    if (file && file.size <= 1024*1024) {
      try { const name = previewFontPrefix + request; const face = new FontFace(name,await file.arrayBuffer()); await face.load(); if (!form.isConnected || request !== fontRequest) return; document.fonts.add(face); previewFont = name; preview(); }
      catch (_) { if (form.isConnected && request === fontRequest) message(formMessage,'Het lettertype kan niet in het voorbeeld worden getoond. De server controleert het bestand bij opslaan.','info'); }
    }
  });
  $('#copy-application-link').addEventListener('click',async event => { const button = event.currentTarget; try { await navigator.clipboard.writeText($('#application-link').href); button.textContent = 'Gekopieerd'; } catch (_) { message('#company-form-message','Selecteer en kopieer de link handmatig.','info'); } });
  form.addEventListener('submit',async event => {
    event.preventDefault(); const button = $('#save-company'); message('#company-form-message','');
    const templates = form.elements.mail_templates.value.trim(); if (templates) { try { const value = JSON.parse(templates); if (!value || Array.isArray(value) || typeof value !== 'object') throw new Error(); } catch (_) { message('#company-form-message','De e-mailsjablonen moeten een geldig JSON-object zijn.'); return; } }
    busy(button,true);
    try { const data = new FormData(form); data.set('is_active',form.elements.is_active.checked ? '1' : '0'); if (!templates) data.set('mail_templates','{}'); if (editing) data.set('_method','PATCH'); const saved = await Portal.api('/api/admin/companies' + (editing ? '/' + company.id : ''),{method:'POST',body:data}); if (editing && Number(company.id) === Number(Portal.companyInfo.id)) { const savedSlug = saved.company?.slug || data.get('slug'); const savedActive = Number(saved.company?.is_active ?? data.get('is_active')) === 1; location.href = savedActive ? '/admin.html?company=' + encodeURIComponent(savedSlug) : '/index.html'; return; } if (!form.isConnected) return; await adminPage(); message('#page-message',editing ? 'De bedrijfsgegevens en het thema zijn bijgewerkt.' : 'Het bedrijf is toegevoegd. Het sollicitatieportaal is direct beschikbaar.','success'); }
    catch (error) { if (form.isConnected) message(formMessage,error.message); busy(button,false); }
  });
  preview(); window.scrollTo({top:0,behavior:'smooth'});
  const storedFont = company?.theme?.font_url;
  if (typeof storedFont === 'string' && /^\/themes\/[a-z0-9-]+\/[a-zA-Z0-9_.-]+\.(woff2|ttf)$/i.test(storedFont)) {
    const request = ++fontRequest; const name = previewFontPrefix + request;
    const face = new FontFace(name,'url("' + storedFont + '")');
    face.load().then(() => { if (!form.isConnected || request !== fontRequest) return; document.fonts.add(face); previewFont = name; preview(); }).catch(() => {});
  }
  if (editing) {
    populateChoices($('#employee-form').elements.department,'departments');
    await loadEmployees(company.id);
    if (!form.isConnected) return;
    $('#employee-form').addEventListener('submit',async event => { event.preventDefault(); const employeeForm = event.currentTarget; const button = $('button',employeeForm); const employeeMessage = $('#employee-message'); busy(button,true); message(employeeMessage,''); try { await Portal.api('/api/admin/employees',{method:'POST',body:{company_id:company.id,name:employeeForm.elements.name.value,email:employeeForm.elements.email.value,department:employeeForm.elements.department.value}}); if (!employeeForm.isConnected) return; employeeForm.reset(); populateChoices(employeeForm.elements.department,'departments'); await loadEmployees(company.id); if (employeeForm.isConnected) message(employeeMessage,'De medewerker is toegevoegd en ontvangt een uitnodiging.','success'); } catch (error) { if (employeeForm.isConnected) message(employeeMessage,error.message); } finally { busy(button,false); } });
  }
}

function normalizeWorkingHours(hours) {
  if (!Array.isArray(hours) || hours.length > 28) throw new Error('Gebruik maximaal 28 werktijden per week.');
  const normalized = hours.map(hour => {
    const weekday = Number(hour.weekday);
    const start = String(hour.start_time || ''); const end = String(hour.end_time || '');
    const validTime = /^(?:[01]\d|2[0-3]):[0-5]\d$/;
    if (!Number.isInteger(weekday) || weekday < 1 || weekday > 7 || !validTime.test(start) || !validTime.test(end) || start >= end) {
      throw new Error('Kies een weekdag en een eindtijd die na de begintijd ligt.');
    }
    return {weekday,start_time:start,end_time:end};
  }).sort((first,second) => first.weekday - second.weekday || first.start_time.localeCompare(second.start_time));
  for (let index = 1; index < normalized.length; index++) {
    const previous = normalized[index - 1]; const current = normalized[index];
    if (previous.weekday === current.weekday && current.start_time < previous.end_time) throw new Error('Werktijden op dezelfde dag mogen elkaar niet overlappen.');
  }
  return normalized;
}

function employeeHoursEditor(employee, companyId) {
  const details = el('details','employee-hours');
  const summary = el('summary','small','Werktijden van ' + employee.name); details.append(summary);
  const form = el('form','employee-hours-form');
  form.append(el('p','field-help','Alle tijden zijn in de Nederlandse tijdzone. Je kunt meerdere tijdvakken per dag toevoegen. Zonder werktijden kunnen er geen nieuwe gesprekken worden geboekt. Bestaande afspraken blijven staan.'));
  const rows = el('div','hours-rows'); const emptyHours = el('p','small muted','Nog geen werktijden ingesteld.');
  const actions = el('div','hours-actions');
  const add = el('button','btn btn-secondary btn-small','Tijdvak toevoegen'); add.type = 'button';
  const save = el('button','btn btn-small','Werktijden opslaan'); save.type = 'submit';
  const result = el('div','message'); result.hidden = true;
  const weekdays = ['Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag'];
  const update = () => { emptyHours.hidden = rows.children.length > 0; add.disabled = rows.children.length >= 28; };
  function addHour(hour = {weekday:1,start_time:'09:00',end_time:'17:00'}) {
    if (rows.children.length >= 28) return;
    const row = el('div','hours-row');
    const dayField = el('label','field'); const day = el('select','input'); day.name = 'weekday'; day.required = true;
    weekdays.forEach((label,index) => { const option = el('option','',label); option.value = String(index + 1); day.append(option); }); day.value = String(hour.weekday);
    dayField.append(el('span','field-label','Weekdag'),day); row.append(dayField);
    for (const [name,label] of [['start_time','Van'],['end_time','Tot']]) {
      const field = el('label','field'); const input = el('input','input'); input.type = 'time'; input.name = name; input.required = true; input.step = '60'; input.value = String(hour[name] || '').slice(0,5);
      field.append(el('span','field-label',label),input); row.append(field);
    }
    const remove = el('button','text-btn','Verwijderen'); remove.type = 'button'; remove.setAttribute('aria-label','Dit tijdvak verwijderen');
    remove.addEventListener('click',() => { row.remove(); update(); }); row.append(remove); rows.append(row); update();
  }
  add.addEventListener('click',() => addHour());
  for (const hour of employee.working_hours || []) addHour(hour);
  update(); actions.append(add,save); form.append(rows,emptyHours,actions,result); details.append(form);
  form.addEventListener('submit',async event => {
    event.preventDefault(); message(result,'');
    let workingHours;
    try { workingHours = normalizeWorkingHours([...rows.children].map(row => ({weekday:$('[name=weekday]',row).value,start_time:$('[name=start_time]',row).value,end_time:$('[name=end_time]',row).value}))); }
    catch (error) { message(result,error.message); return; }
    busy(save,true);
    try {
      await Portal.api('/api/admin/employees/' + employee.id,{method:'PATCH',body:{company_id:companyId,working_hours:workingHours}});
      if (!form.isConnected) return;
      employee.working_hours = workingHours; message(result,'De werktijden zijn opgeslagen.','success');
    } catch (error) { if (form.isConnected) message(result,error.message); }
    finally { busy(save,false); }
  });
  return details;
}

async function loadEmployees(companyId) {
  const container = $('#employee-list');
  if (!container) return;
  try {
    const {employees = []} = await Portal.api('/api/admin/employees',{params:{company_id:companyId}});
    if (!container.isConnected) return;
    container.replaceChildren();
    if (!employees.length) { empty(container,'Nog niemand uitgenodigd','Voeg hieronder een medewerker toe om sollicitaties te beoordelen.','users'); return; }
    for (const employee of employees) {
      const row = el('div','employee-row'); const info = el('div','employee-info'); info.append(el('strong','',employee.name),el('small','',employee.email)); const active = Number(employee.is_active) === 1 || employee.is_active === true;
      row.append(el('span','avatar',initials(employee.name)),info,statusBadge(active ? 'active' : 'inactive'));
      if (active) { const deactivate = el('button','text-btn','Deactiveren'); deactivate.addEventListener('click',() => confirmDialog('Medewerker deactiveren?',employee.name + ' kan hierna niet meer inloggen in dit portaal.','Deactiveren',async () => { await Portal.api('/api/admin/employees/' + employee.id,{method:'PATCH',body:{company_id:companyId,is_active:false}}); if (!row.isConnected) return; await loadEmployees(companyId); message('#employee-message','De medewerker is gedeactiveerd.','success'); },true)); row.append(deactivate); }
      const departmentForm = el('form','employee-department-form');
      const departmentField = el('label','field'); const departmentSelect = el('select','input'); departmentSelect.name = 'department'; departmentSelect.required = true;
      departmentField.append(el('span','field-label','Afdeling van ' + employee.name),departmentSelect); populateChoices(departmentSelect,'departments',employee.department);
      const saveDepartment = el('button','btn btn-secondary btn-small','Afdeling opslaan'); saveDepartment.type = 'submit'; departmentForm.append(departmentField,saveDepartment);
      departmentForm.addEventListener('submit',async event => {
        event.preventDefault(); const target = $('#employee-message'); busy(saveDepartment,true); message(target,'');
        try {
          await Portal.api('/api/admin/employees/' + employee.id,{method:'PATCH',body:{company_id:companyId,department:departmentSelect.value}});
          if (!row.isConnected) return;
          await loadEmployees(companyId); if (target?.isConnected) message(target,'De afdeling van ' + employee.name + ' is bijgewerkt.','success');
        } catch (error) { if (target?.isConnected) message(target,error.message); }
        finally { busy(saveDepartment,false); }
      });
      row.append(departmentForm,employeeHoursEditor(employee,companyId));
      container.append(row);
    }
  } catch (error) { if (container.isConnected) empty(container,'Medewerkers niet opgehaald',error.message,'users'); }
}


function dashboardPage() {
  if (!requireRole(['applicant'])) return;
  const user = Portal.session.user;
  shell('Een mooie volgende stap.','Je sollicitatie is goedgekeurd. Fijn dat je er bent.', '<section class="card detail-card"><h2>Welkom bij <span data-company-name></span></h2><p id="applicant-welcome"></p><p><span class="badge approved">Sollicitatie goedgekeurd</span></p><p class="small muted">Je persoonlijke account is klaar. Kies een beschikbaar moment voor een kennismakingsgesprek.</p><a class="btn" href="' + Portal.link('schedule.html') + '">Naar mijn gesprek ' + icon('calendar') + '</a><dl><div class="detail-item"><dt>Naam</dt><dd id="account-name"></dd></div><div class="detail-item"><dt>E-mailadres</dt><dd id="account-email"></dd></div></dl></section>', 'dashboard');
  put('#applicant-welcome','Bedankt voor je interesse, ' + user.name + '. Ons team heeft je sollicitatie bekeken en kijkt uit naar een kennismaking.');
  put('#account-name',user.name); put('#account-email',user.email);
}

async function start() {
  await Portal.themeReady;
  if (page === 'index' && !Portal.company) { await indexPage(); return; }
  if (!Portal.validCompany || !Portal.companyInfo) { neutral(Portal.themeError || undefined); return; }
  try { Portal.session = await Portal.api('/api/session'); }
  catch (error) { neutral(error.message); return; }
  const pages = {index:applyPage,apply:applyPage,login:loginPage,'set-password':setPasswordPage,'verify-email':verifyPage,employee:employeePage,dashboard:dashboardPage,schedule:schedulePage,admin:adminPage};
  try { await (pages[page] || applyPage)(); }
  catch (error) { neutral('Het portaal kon niet worden geladen. ' + error.message); }
}
start();
