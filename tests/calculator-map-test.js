/* Isolated calculator map wiring; Google, network and DOM are test doubles. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
  constructor() { this.handlers = {}; this.hidden = false; this.dataset = {}; this.textContent = ''; this.innerHTML = ''; this.attributes = {}; this.classList = { remove() {}, toggle() {} }; }
  addEventListener(name, callback) { this.handlers[name] = callback; }
  addListener(name, callback) { this.handlers[name] = callback; }
  setAttribute(name, value) { this.attributes[name] = value; }
  appendChild(child) { this.child = child; }
  dispatchEvent(event) { if (this.handlers[event.type]) this.handlers[event.type](event); }
  focus() { this.focused = true; }
}
const elements = new Map();
function element(selector) {
  if (!elements.has(selector)) elements.set(selector, new Element());
  return elements.get(selector);
}
const root = new Element();
root.hasAttribute = name => name === 'data-htr-calculator';
root.querySelector = element;
const switches = ['satellite', 'roadmap'].map(type => { const button = new Element(); button.dataset.type = type; return button; });
root.querySelectorAll = selector => selector === '.htr-rr-maptype button' ? switches : [];
element('[data-calc-inputs]').elements = { area_sqft: { value: '2850' } };
let autocomplete, map, marker, nextTimer = 0;
const timers = new Map(), requests = [];
class MapDouble extends Element {
  constructor(container) { super(); assert.equal(container, element('[data-calc-map]')); map = this; }
  setMapTypeId(type) { this.type = type; }
  setCenter(position) { this.center = position; }
  setZoom() {}
}
class MarkerDouble extends Element {
  constructor() { super(); marker = this; }
  setPosition(position) { this.position = position; }
  getPosition() { return this.position; }
}
const google = { maps: { async importLibrary(name) {
  if (name === 'places') return { PlaceAutocompleteElement: class extends Element { constructor() { super(); autocomplete = this; } } };
  if (name === 'maps') return { Map: MapDouble };
  if (name === 'marker') return { Marker: MarkerDouble };
  throw new Error('Unexpected Google library');
} } };
const context = {
  window: { HTR_RR: { browserKey: 'fixture', ajaxUrl: '/ajax' }, google, location: { search: '' } }, google,
  document: { querySelectorAll: () => [root] }, URLSearchParams,
  Event: class { constructor(type) { this.type = type; } },
  setTimeout(callback) { timers.set(++nextTimer, callback); return nextTimer; },
  clearTimeout(id) { timers.delete(id); },
  async fetch(url, options) {
    requests.push(JSON.parse(options.body));
    return { ok: true, async json() { return { ok: true, measured: true, areaSqft: 3000, faces: 8, pitch: '6/12', markerOnRoof: true }; } };
  },
};
const settle = () => new Promise(resolve => setImmediate(resolve));
async function run() {
  vm.runInNewContext(fs.readFileSync(require.resolve('../assets/js/roof-report.js'), 'utf8'), context);
  await settle();
  const place = { formattedAddress: '123 Main Street', location: { lat: () => 29.42, lng: () => -98.49 }, addressComponents: [{ types: ['street_number'], longText: '123' }], async fetchFields() {} };
  await autocomplete.handlers['gmp-select']({ placePrediction: { toPlace: () => place } });
  await settle();
  assert.equal(element('[data-calc-property]').hidden, false);
  assert.ok(map && marker, 'Address selection creates calculator map and pin without wizard controls');
  switches[1].handlers.click();
  assert.equal(map.type, 'roadmap');
  assert.equal(switches[1].attributes['aria-pressed'], 'true');
  marker.position = { lat: () => 29.43, lng: () => -98.5 };
  marker.handlers.dragend();
  for (const [id, callback] of [...timers]) { timers.delete(id); callback(); }
  await settle();
  assert.equal(requests.length, 0, 'Calculator map never requests aerial roof measurements');
  assert.equal(element('[data-calc-inputs]').elements.area_sqft.value, '2850', 'Map interaction never overwrites entered dimensions');
  autocomplete.handlers.input();
  assert.equal(element('[data-calc-property]').hidden, true, 'Editing address hides stale map');
  assert.equal(element('[data-calc-address]').textContent, '');
  console.log('Calculator map wiring checks passed (isolated doubles; no live calls).');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
