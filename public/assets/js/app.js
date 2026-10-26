import Portal from './theme.js';

const app = document.querySelector('#app');
function node(tag, text, className = '') {
  const element = document.createElement(tag);
  element.textContent = text;
  element.className = className;
  return element;
}
function showError(message) {
  const box = node('section', '', 'card status');
  box.setAttribute('role', 'alert');
  box.append(node('h1', 'Het portaal kan nu niet worden geopend'), node('p', message));
  const retry = node('a', 'Opnieuw proberen', 'button');
  retry.href = location.pathname + location.search;
  box.append(retry);
  app.replaceChildren(box);
}
async function companiesPage() {
  app.innerHTML = '<section class="hero"><p class="eyebrow">Een nieuwe mogelijkheid</p><h1>De volgende stap<br>in jouw verhaal.</h1><p>Ontdek het sollicitatieportaal van jouw bedrijf.</p></section><section class="company-grid" aria-label="Kies een bedrijf"></section>';
  const response = await fetch('/api/companies', {headers: {Accept: 'application/json'}});
  const result = await response.json();
  if (!response.ok || !result.success) throw new Error(result.error?.message || 'De bedrijven konden niet worden opgehaald.');
  const grid = document.querySelector('.company-grid');
  for (const company of result.data.companies) {
    const link = node('a', '', 'card company-choice');
    link.href = '/?company=' + encodeURIComponent(company.slug);
    link.append(node('h2', company.name), node('p', 'Bekijk het bedrijfsportaal →'));
    grid.append(link);
  }
  if (!grid.children.length) grid.append(node('p', 'Er zijn nog geen bedrijven beschikbaar.', 'status'));
}
function companyPage() {
  const company = Portal.companyInfo;
  document.title = company.name + ' · Sollicitatieportaal';
  const brand = document.querySelector('.brand');
  brand.textContent = company.name;
  brand.href = '/?company=' + encodeURIComponent(company.slug);
  if (Portal.theme.logo_url) {
    const logo = document.createElement('img');
    logo.src = Portal.theme.logo_url;
    logo.alt = company.name;
    logo.className = 'brand-logo';
    brand.replaceChildren(logo);
  }
  app.innerHTML = '<section class="hero"><p class="eyebrow">Ruimte voor jouw talent</p><h1>Een mooie stap<br>begint bij jou.</h1><p id="company-intro"></p></section><section class="card introduction"><h2>Van kennismaking naar een goed gesprek</h2><ol><li><strong>Deel jouw verhaal</strong><p>Je gegevens, cv en motivatie.</p></li><li><strong>Wij maken kennis</strong><p>Het team bekijkt je sollicitatie.</p></li><li><strong>Tijd voor een gesprek</strong><p>Bij een match kies je een geschikt moment.</p></li></ol><p class="notice">Online solliciteren is binnenkort beschikbaar.</p></section>';
  document.querySelector('#company-intro').textContent = 'Welkom bij het sollicitatieportaal van ' + company.name + '. Ontdek hoe jouw volgende stap eruit kan zien.';
}
async function start() {
  try {
    await Portal.themeReady;
    if (Portal.company && !Portal.validCompany) throw new Error(Portal.themeError || 'Open een geldige bedrijfslink.');
    if (Portal.validCompany) companyPage();
    else await companiesPage();
  } catch (error) {
    showError(error instanceof TypeError || error instanceof SyntaxError ? 'Er is geen verbinding met het portaal. Probeer het later opnieuw.' : error.message);
  }
}
start();
