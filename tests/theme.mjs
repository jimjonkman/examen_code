import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {setImmediate as flushPromises} from 'node:timers/promises';
import {test} from 'node:test';
import vm from 'node:vm';

// No browser, database or network needed: execute the actual browser module in
// an isolated page context, replacing only its ESM export and browser surfaces.
const source = (await readFile(new URL('../public/assets/js/theme.js',import.meta.url),'utf8'))
  .replace(/export default Portal;\s*$/,'globalThis.Portal = Portal;');
const scabro = {primary_color:'#110d37',secondary_color:'#f58322',font_family:'system-ui'};
const demo = {primary_color:'#12463b',secondary_color:'#cfda79',font_family:'Georgia'};

function surface() {
  const values = new Map();
  return {values,style:{setProperty:(name,value) => values.set(name,value)}};
}

function page({slug = 'scabro',theme = scabro,ok = true,success = true} = {}) {
  const root = surface();
  const requests = [];
  const fontFaces = [];
  const pendingFonts = [];
  const registeredFonts = [];
  const context = vm.createContext({
    URLSearchParams,
    location:{search:slug ? '?company=' + encodeURIComponent(slug) : ''},
    document:{documentElement:root,fonts:{add:font => registeredFonts.push(font)}},
    fetch:async (url,options) => {
      requests.push({url,options});
      return {ok,json:async () => ({success,data:{company:{name:slug,slug},theme}})};
    },
    FontFace:class {
      constructor(family,url) { this.family = family; this.url = url; fontFaces.push(this); }
      load() {
        return new Promise((resolve,reject) => pendingFonts.push({resolve:() => resolve(this),reject}));
      }
    }
  });
  vm.runInContext(source,context,{filename:'public/assets/js/theme.js'});
  return {Portal:context.Portal,root,requests,fontFaces,pendingFonts,registeredFonts};
}

// WCAG relative luminance verifies rendered color relationships independently
// of the particular mixing/darkening choices used to derive a company palette.
function contrastRatio(first,second) {
  const luminance = color => {
    assert.match(color,/^#[a-f0-9]{6}$/i);
    const rgb = color.slice(1).match(/../g).map(value => Number.parseInt(value,16) / 255);
    const linear = rgb.map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
    return linear.reduce((sum,value,index) => sum + value * [.2126,.7152,.0722][index],0);
  };
  const light = Math.max(luminance(first),luminance(second));
  const dark = Math.min(luminance(first),luminance(second));
  return (light + .05) / (dark + .05);
}

test('each company fetches and applies its own primary, accent and font',async () => {
  const first = page({slug:'scabro',theme:scabro});
  const second = page({slug:'demo',theme:demo});
  await Promise.all([first.Portal.themeReady,second.Portal.themeReady]);

  for (const [current,slug,theme] of [[first,'scabro',scabro],[second,'demo',demo]]) {
    assert.equal(current.requests.length,1);
    assert.equal(current.requests[0].url,'/api/theme.php?company=' + slug);
    assert.equal(current.requests[0].options.cache,'no-store');
    assert.equal(current.Portal.companyInfo.slug,slug);
    assert.equal(current.root.values.get('--primary'),theme.primary_color);
    assert.equal(current.root.values.get('--secondary'),theme.secondary_color);
  }
  assert.notEqual(first.root.values.get('--surface-accent'),second.root.values.get('--surface-accent'));
  assert.match(second.root.values.get('--font'),/^"Georgia",/);
});

test('preview themes stay local and remain readable for dark, pale and saturated colors',async () => {
  const current = page();
  await current.Portal.themeReady;
  const savedRoot = [...current.root.values];
  const preview = surface();
  const edgeCases = [
    scabro,
    demo,
    {primary_color:'#ffffff',secondary_color:'#000000'},
    {primary_color:'#ffff00',secondary_color:'#0000ff'},
    {primary_color:'#777777',secondary_color:'#ffffff'}
  ];
  const textPairs = [
    ['--on-primary','--primary'],
    ['--on-secondary','--secondary'],
    ['--primary-text','--paper'],
    ['--primary-text','--surface-accent'],
    ['--muted','--surface-accent'],
    ['--ink','--paper']
  ];
  for (const theme of edgeCases) {
    current.Portal.applyTheme(theme,preview);
    assert.equal(preview.values.get('--primary'),theme.primary_color);
    assert.equal(preview.values.get('--secondary'),theme.secondary_color);
    for (const [text,background] of textPairs) {
      const ratio = contrastRatio(preview.values.get(text),preview.values.get(background));
      assert.ok(ratio >= 4.5,`${theme.primary_color}: ${text} on ${background} must have 4.5:1 contrast; got ${ratio.toFixed(2)}`);
    }
  }
  assert.deepEqual([...current.root.values],savedRoot,'Editing another company must not recolor the admin workspace');
});

test('invalid company links and failed theme responses do not apply another company theme',async () => {
  for (const slug of ['', '../scabro']) {
    const current = page({slug});
    assert.equal(await current.Portal.themeReady,null);
    assert.equal(current.requests.length,0);
    assert.equal(current.root.values.size,0);
  }
  const missing = page({slug:'unknown-company',ok:false,success:false});
  assert.equal(await missing.Portal.themeReady,null);
  assert.equal(missing.Portal.validCompany,false);
  assert.equal(missing.Portal.companyInfo,undefined);
  assert.equal(missing.root.values.size,0);
});

test('external, traversing and unsupported font URLs are never loaded',async () => {
  const rejected = [
    'https://example.invalid/font.woff2',
    '//example.invalid/font.woff2',
    '/themes/scabro/../font.woff2',
    '/themes/scabro/font.woff2?redirect=external',
    'data:font/woff2;base64,AAAA',
    '/themes/scabro/font.svg'
  ];
  for (const font_url of rejected) {
    const current = page({theme:{...scabro,font_url}});
    await current.Portal.themeReady;
    assert.equal(current.fontFaces.length,0,font_url);
    assert.equal(current.Portal.validCompany,true);
  }
});

test('an uploaded font works with the default family and does not hold up the page',async () => {
  const current = page({theme:{...scabro,font_url:'/themes/scabro/custom.woff2'}});
  let timer;
  try {
    await Promise.race([
      current.Portal.themeReady,
      new Promise((_,reject) => { timer = setTimeout(() => reject(new Error('A pending font download blocked themeReady')),500); })
    ]);
  } finally { clearTimeout(timer); }
  assert.equal(current.root.values.get('--primary'),scabro.primary_color);
  assert.equal(current.fontFaces.length,1);
  assert.equal(current.registeredFonts.length,0);
  assert.notEqual(current.fontFaces[0].family,'system-ui','FontFace must not use a CSS generic family as its name');
  assert.match(current.root.values.get('--font'),/^system-ui,/);

  current.pendingFonts[0].resolve();
  await flushPromises();
  assert.equal(current.registeredFonts[0],current.fontFaces[0]);
  assert.ok(current.root.values.get('--font').startsWith('"' + current.fontFaces[0].family + '",'));
});

test('a broken font keeps the fallback font and a usable company portal',async () => {
  const current = page({theme:{...scabro,font_url:'/themes/scabro/missing.ttf'}});
  await current.Portal.themeReady;
  const fallback = current.root.values.get('--font');
  current.pendingFonts[0].reject(new Error('Font unavailable'));
  await flushPromises();
  assert.equal(current.Portal.validCompany,true);
  assert.equal(current.registeredFonts.length,0);
  assert.equal(current.root.values.get('--font'),fallback);
  assert.equal(current.root.values.get('--primary'),scabro.primary_color);
});

test('font lists remain separate fallbacks and malformed theme values cannot enter CSS',async () => {
  const current = page({theme:{primary_color:'url(evil)',secondary_color:'red;display:none',font_family:'<script>'}});
  await current.Portal.themeReady;
  assert.match(current.root.values.get('--primary'),/^#[a-f0-9]{6}$/i);
  assert.match(current.root.values.get('--secondary'),/^#[a-f0-9]{6}$/i);
  assert.equal(current.root.values.get('--font'),'ui-sans-serif, system-ui, sans-serif');
  assert.match(current.Portal.fontStack('Arial, Georgia, sans-serif'),/^"Arial", "Georgia", sans-serif,/);
});
