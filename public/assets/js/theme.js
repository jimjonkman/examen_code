const Portal = {};
const defaultTheme = {primary_color:'#263c3b', secondary_color:'#b5d4bd'};
const validColor = value => /^#[0-9a-fA-F]{6}$/.test(value || '');
const channels = color => [1,3,5].map(start => parseInt(color.slice(start,start + 2),16));
function mix(color, base, amount) {
  const background = channels(base);
  return '#' + channels(color).map((value,index) => Math.round(value * (1 - amount) + background[index] * amount).toString(16).padStart(2,'0')).join('');
}
function luminance(color) {
  const values = channels(color).map(value => { const channel = value / 255; return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4; });
  return values[0] * .2126 + values[1] * .7152 + values[2] * .0722;
}
function contrast(first, second) {
  const values = [luminance(first),luminance(second)].sort((a,b) => b - a);
  return (values[0] + .05) / (values[1] + .05);
}
// Ook lichte huisstijlkleuren moeten leesbare knoppen en links opleveren.
Portal.colorText = color => {
  if (!validColor(color) || contrast(color,'#ffffff') >= 4.5) return '#ffffff';
  return contrast(color,'#17212b') >= 4.5 ? '#17212b' : '#000000';
};
function textOnLight(color) {
  let result = color;
  for (let step = 0; contrast(result,'#ffffff') < 6.5 && step < 20; step++) result = mix(result,'#000000',.1);
  return result;
}
Portal.fontStack = family => {
  if (!family || !/^[\p{L}\p{N}][\p{L}\p{N} .,_-]{0,79}$/u.test(family)) return 'ui-sans-serif, system-ui, sans-serif';
  const generics = new Set(['serif','sans-serif','monospace','cursive','fantasy','system-ui','ui-serif','ui-sans-serif','ui-monospace','ui-rounded']);
  return family.split(',').map(name => name.trim()).filter(Boolean).map(name => generics.has(name.toLowerCase()) ? name.toLowerCase() : '"' + name + '"').join(', ') + ', ui-sans-serif, system-ui, sans-serif';
};
// Dezelfde tokens gelden voor de openbare pagina's, de werkruimte en het live voorbeeld.
Portal.applyTheme = (theme = {}, target = document.documentElement) => {
  const primary = validColor(theme.primary_color) ? theme.primary_color : defaultTheme.primary_color;
  const secondary = validColor(theme.secondary_color) ? theme.secondary_color : defaultTheme.secondary_color;
  const ink = mix(primary,'#17212b',.9);
  const tokens = {
    '--primary':primary, '--secondary':secondary,
    '--on-primary':Portal.colorText(primary), '--on-secondary':Portal.colorText(secondary),
    '--primary-text':textOnLight(primary), '--secondary-text':textOnLight(secondary),
    '--ink':ink, '--muted':mix(primary,'#4c5660',.96),
    '--line':mix(primary,'#ffffff',.86), '--line-strong':mix(primary,'#ffffff',.7),
    '--paper':mix(secondary,'#ffffff',.96), '--white':'#ffffff',
    '--surface-soft':mix(primary,'#ffffff',.96), '--surface-hover':mix(primary,'#ffffff',.91),
    '--surface-accent':mix(secondary,'#ffffff',.88),
    '--focus':textOnLight(primary), '--focus-shadow':primary + '26',
    '--shadow':'0 12px 45px ' + ink + '0a', '--overlay':ink + '66',
    '--font':Portal.fontStack(theme.font_family)
  };
  for (const [key,value] of Object.entries(tokens)) target.style.setProperty(key,value);
  return tokens;
};
Portal.company = new URLSearchParams(location.search).get('company') || '';
Portal.validCompany = /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(Portal.company) && Portal.company.length <= 80;
Portal.themeReady = (async () => {
  if (!Portal.validCompany) return null;
  try {
    const response = await fetch('/api/theme.php?company=' + encodeURIComponent(Portal.company), {credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}});
    const result = await response.json();
    if (!response.ok || !result.success || !result.data || !result.data.company) throw new Error('Onbekend bedrijf.');
    const data = result.data;
    Portal.companyInfo = data.company;
    Portal.theme = data.theme || {};
    const theme = Portal.theme;
    Portal.applyTheme(theme);
    if (theme.font_url && /^\/themes\/[a-z0-9-]+\/[a-zA-Z0-9_.-]+\.(woff2?|ttf)$/i.test(theme.font_url)) {
      // Een vaste interne naam werkt ook wanneer de beheerder geen lettertypenaam invult.
      try {
        const face = new FontFace('PortalCompanyFont', 'url("' + theme.font_url + '")');
        face.load().then(loaded => {
          document.fonts.add(loaded);
          document.documentElement.style.setProperty('--font','"PortalCompanyFont", ' + Portal.fontStack(theme.font_family));
        }).catch(() => {});
      } catch (_) { /* System font remains available. */ }
    }
    return data;
  } catch (error) { Portal.validCompany = false; Portal.themeError = error instanceof TypeError || error instanceof SyntaxError ? 'Het bedrijfsportaal kan nu niet worden opgehaald. Probeer het later opnieuw.' : error.message; return null; }
})();
export default Portal;
