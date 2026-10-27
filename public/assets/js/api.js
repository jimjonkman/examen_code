import Portal from './theme.js';
Portal.session = null;
Portal.url = function(path, params = {}) {
  const url = new URL(path, location.origin);
  if (Portal.validCompany) url.searchParams.set('company', Portal.company);
  for (const [name, value] of Object.entries(params)) if (value !== undefined && value !== null) url.searchParams.set(name, value);
  return url.pathname + url.search;
};
Portal.link = (page, params = {}) => Portal.url('/' + page, params);
Portal.api = async function(path, options = {}) {
  const headers = {Accept:'application/json', ...(options.headers || {})};
  const method = options.method || 'GET';
  let body = options.body;
  if (body && !(body instanceof FormData)) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(body); }
  if (method !== 'GET' && method !== 'HEAD' && Portal.session?.csrf_token) headers['X-CSRF-Token'] = Portal.session.csrf_token;
  let response;
  try { response = await fetch(Portal.url(path, options.params), {method,headers,body,credentials:'same-origin'}); }
  catch (_) { throw new Error('Er is geen verbinding met de server. Probeer het opnieuw.'); }
  let payload;
  try { payload = await response.json(); } catch (_) { throw new Error('De server gaf geen geldig antwoord. Probeer het later opnieuw.'); }
  if (!response.ok || !payload.success) {
    const details = payload.error;
    const error = new Error(typeof details === 'string' ? details : details?.message || 'De aanvraag kon niet worden verwerkt.');
    error.status = response.status;
    error.details = details;
    throw error;
  }
  return payload.data || {};
};
export default Portal;
