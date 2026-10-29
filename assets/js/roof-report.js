/* Hometown Instant Roof Report - homeowner form.
 * Google Maps JavaScript API (satellite map, zoom, draggable pin) + Places API (New) PlaceAutocompleteElement.
 * The report itself is measured on the server (Google Solar API) - this file never sees the server key. */
(function () {
  'use strict';
  var C = window.HTR_RR || {};
  var root = document.getElementById('htr-rr');
  if (!root) return;

  var state = { lat: null, lng: null, address: '', city: '', zip: '', material: '', map: null, marker: null, report: null };
  var $ = function (s, el) { return (el || root).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || root).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var money = function (v) { return '$' + Number(v).toLocaleString('en-US'); };

  /* Send to the server: admin-ajax.php first (works when a site blocks the REST API for visitors), REST as fallback. */
  async function api(action, data) {
    var body = JSON.stringify(data), res, j;
    try {
      res = await fetch(C.ajaxUrl + '?action=htr_rr_' + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: body });
      j = await res.json();
      return { ok: res.ok, status: res.status, json: j };
    } catch (e) { /* not JSON (e.g. "0") or network: try REST */ }
    res = await fetch(C.restUrl + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body });
    j = await res.json().catch(function () { return {}; });
    return { ok: res.ok, status: res.status, json: j };
  }

  function unlock(n) {
    $$('.htr-rr-card').forEach(function (c) { if (Number(c.dataset.card) <= n) c.removeAttribute('aria-disabled'); });
    root.dataset.step = String(n);
  }
  function show(id, msg) { var el = $(id); el.textContent = msg || ''; el.hidden = !msg; }
  function focusCard(n) { var c = $('[data-card="' + n + '"]'); if (c && c.scrollIntoView) c.scrollIntoView({ behavior: 'smooth', block: 'start' }); }

  /* ---------- Google loader (official dynamic bootstrap) ---------- */
  function loadGoogle(key) {
    /* eslint-disable */
    (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({key:key,v:"weekly"});
    /* eslint-enable */
  }

  /* ---------- Step 1: address search ---------- */
  async function initSearch() {
    if (!C.browserKey) { show('#htr-rr-err1', 'Address search is not set up yet. Please call us' + (C.phone ? ' at ' + C.phone : '') + '.'); return; }
    loadGoogle(C.browserKey);
    try {
      var places = await google.maps.importLibrary('places');
      var ac = new places.PlaceAutocompleteElement({ includedRegionCodes: ['us'] });
      ac.id = 'htr-rr-ac';
      ac.setAttribute('placeholder', 'Enter your property address');
      $('#htr-rr-search').appendChild(ac);
      ac.addEventListener('gmp-select', async function (ev) {
        try {
          var place = ev.placePrediction.toPlace();
          await place.fetchFields({ fields: ['formattedAddress', 'location', 'addressComponents', 'types'] });
          pick(place.location.lat(), place.location.lng(), place.formattedAddress, place.addressComponents || []);
        } catch (e) { show('#htr-rr-err1', 'We could not load that address. Please try again.'); }
      });
      // Older Places event name, in case Google serves it.
      ac.addEventListener('gmp-placeselect', async function (ev) {
        try {
          await ev.place.fetchFields({ fields: ['formattedAddress', 'location', 'addressComponents'] });
          pick(ev.place.location.lat(), ev.place.location.lng(), ev.place.formattedAddress, ev.place.addressComponents || []);
        } catch (e) { show('#htr-rr-err1', 'We could not load that address. Please try again.'); }
      });
    } catch (e) {
      show('#htr-rr-err1', 'The map could not load. Please refresh the page or call us' + (C.phone ? ' at ' + C.phone : '') + '.');
    }
  }

  function comp(list, type, short) {
    for (var i = 0; i < list.length; i++) {
      var t = list[i].types || [];
      if (t.indexOf(type) >= 0) return short ? (list[i].shortText || list[i].short_name || '') : (list[i].longText || list[i].long_name || '');
    }
    return '';
  }

  function pick(lat, lng, address, comps) {
    // A city or ZIP alone would put the pin in the town centre and measure the wrong building.
    if (comps && comps.length && !comp(comps, 'street_number') && !comp(comps, 'premise')) {
      show('#htr-rr-err1', 'Please pick your full street address with the house number, not just the city.');
      return;
    }
    state.lat = lat; state.lng = lng; state.address = address || '';
    state.city = comp(comps, 'locality') || comp(comps, 'postal_town') || comp(comps, 'sublocality');
    state.zip = comp(comps, 'postal_code', true);
    var st = comp(comps, 'administrative_area_level_1', true);
    show('#htr-rr-err1', st && st !== 'TX' ? 'We serve Texas homes. Please check the address.' : '');
    $('#htr-rr-addr').textContent = state.address;
    var th = $('#htr-rr-thumb');
    th.src = 'https://maps.googleapis.com/maps/api/staticmap?center=' + lat + ',' + lng + '&zoom=19&size=144x112&scale=2&maptype=satellite&key=' + encodeURIComponent(C.browserKey);
    th.alt = 'Satellite photo of ' + state.address; th.hidden = false;
    unlock(2);
    showMap();
    preview();
    focusCard(2);
  }

  /* Step 2 property card: roof preview from Google (cached on the server, shared with the final report). */
  var pvTimer = null, pvSeq = 0;
  function previewSoon() { clearTimeout(pvTimer); pvTimer = setTimeout(preview, 700); }
  async function preview() {
    var seq = ++pvSeq, facts = $('#htr-rr-facts'), note = $('#htr-rr-propnote');
    facts.innerHTML = '<div><dt>Roof area</dt><dd>Measuring&hellip;</dd></div>';
    try {
      var j = (await api('preview', { lat: state.lat, lng: state.lng })).json || {};
      if (seq !== pvSeq) return; // a newer pin position is already being measured
      if (!j.ok) throw new Error(j.message || 'Preview not available.');
      if (!j.measured) { facts.innerHTML = ''; note.textContent = j.message; return; }
      var row = function (k, v) { return '<div><dt>' + esc(k) + '</dt><dd>' + esc(v) + '</dd></div>'; };
      facts.innerHTML = row('Estimated roof area', '~' + Number(j.areaSqft).toLocaleString('en-US') + ' sq ft') +
        (j.groundSqft ? row('Building footprint', '~' + Number(j.groundSqft).toLocaleString('en-US') + ' sq ft') : '') +
        row('Roof faces', j.faces) + row('Main pitch', j.pitch) + (j.imageryDate ? row('Imagery date', j.imageryDate.slice(0, 7)) : '');
      note.textContent = j.areaSqft < 400 ? 'Only ' + j.areaSqft + ' sq ft - that looks like a shed, carport or garage. Drag the pin onto the main house.'
        : (j.markerOnRoof ? 'From Google aerial imagery. Move the pin to update.' : 'The pin looks off the roof - drag it onto your house so we measure the right building.');
      note.classList.toggle('htr-rr-error', j.areaSqft < 400 || !j.markerOnRoof);
    } catch (e) { if (seq === pvSeq) { facts.innerHTML = ''; note.textContent = 'Preview not available right now. You can still continue.'; } }
  }
  $('#htr-rr-edit').addEventListener('click', function () {
    focusCard(1); var ac = document.getElementById('htr-rr-ac'); if (ac && ac.focus) ac.focus();
  });

  /* Use my current location: browser location -> reverse geocode. */
  $('#htr-rr-geo').addEventListener('click', function () {
    if (!navigator.geolocation) { show('#htr-rr-err1', 'Your browser cannot share location. Please type the address.'); return; }
    navigator.geolocation.getCurrentPosition(async function (pos) {
      try {
        var geo = await google.maps.importLibrary('geocoding');
        var res = await new geo.Geocoder().geocode({ location: { lat: pos.coords.latitude, lng: pos.coords.longitude } });
        var r = res.results && res.results[0];
        if (!r) throw new Error('none');
        pick(pos.coords.latitude, pos.coords.longitude, r.formatted_address, r.address_components || []);
      } catch (e) { show('#htr-rr-err1', 'We could not find an address for your location. Please type it.'); }
    }, function () { show('#htr-rr-err1', 'Location permission was not given. Please type the address.'); }, { enableHighAccuracy: true, timeout: 10000 });
  });

  /* ---------- Step 2: satellite map, zoom, draggable pin ---------- */
  async function showMap() {
    var maps = await google.maps.importLibrary('maps');
    var markerLib = await google.maps.importLibrary('marker');
    var pos = { lat: state.lat, lng: state.lng };
    if (!state.map) {
      state.map = new maps.Map($('#htr-rr-map'), {
        center: pos, zoom: 20, mapTypeId: 'satellite', tilt: 0, heading: 0,
        zoomControl: true, streetViewControl: false, mapTypeControl: false, fullscreenControl: true, rotateControl: false,
        gestureHandling: 'greedy', minZoom: 15, maxZoom: 21
      });
      state.marker = new markerLib.Marker({ position: pos, map: state.map, draggable: true, title: 'Drag onto your roof' });
      state.marker.addListener('dragend', function () { var p = state.marker.getPosition(); state.lat = p.lat(); state.lng = p.lng(); previewSoon(); });
      state.map.addListener('click', function (e) { state.marker.setPosition(e.latLng); state.lat = e.latLng.lat(); state.lng = e.latLng.lng(); previewSoon(); });
      $$('.htr-rr-maptype button').forEach(function (b) {
        b.addEventListener('click', function () {
          state.map.setMapTypeId(b.dataset.type);
          $$('.htr-rr-maptype button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
        });
      });
    } else {
      state.map.setCenter(pos); state.map.setZoom(20); state.marker.setPosition(pos);
    }
    $('#htr-rr-confirm').disabled = false;
  }
  $('#htr-rr-confirm').addEventListener('click', function () { unlock(3); focusCard(3); });

  /* ---------- Step 3: roof type ---------- */
  $$('.htr-rr-mat').forEach(function (b) {
    b.addEventListener('click', function () {
      state.material = b.dataset.mat;
      $$('.htr-rr-mat').forEach(function (x) { x.setAttribute('aria-checked', x === b ? 'true' : 'false'); });
      unlock(4); focusCard(4);
    });
  });

  /* ---------- Step 4: contact + submit ---------- */
  $('#htr-rr-form').addEventListener('submit', async function (ev) {
    ev.preventDefault();
    var f = ev.target, btn = $('#htr-rr-submit');
    show('#htr-rr-err4', '');
    if (!state.lat || !state.material) { show('#htr-rr-err4', 'Please finish steps 1 to 3 first.'); return; }
    var data = {
      first_name: f.first_name.value.trim(), last_name: f.last_name.value.trim(), email: f.email.value.trim(), phone: f.phone.value.trim(),
      website: f.website.value, sms_service: f.sms_service.checked, sms_marketing: f.sms_marketing.checked, interests: $$('input[name="interests"]:checked', f).map(function (x) { return x.value; }),
      address: state.address, city: state.city, zip: state.zip, lat: state.lat, lng: state.lng, material: state.material,
      source_url: location.href
    };
    if (!data.first_name || !data.last_name || !/^\S+@\S+\.\S+$/.test(data.email) || data.phone.replace(/\D/g, '').length < 10) {
      show('#htr-rr-err4', 'Please add your name, a valid email and a 10-digit phone number.'); return;
    }
    btn.disabled = true; btn.textContent = 'Working...';
    unlock(5); focusCard(5); progress(3);
    var timer = setTimeout(function () { progress(4); }, 1500);
    try {
      var out = await api('report', data), j = out.json || {};
      clearTimeout(timer);
      if (!out.ok || !j.ok) throw new Error(j.message || 'Something went wrong. Please try again or call us.');
      progress(6);
      state.report = j.report;
      unlock(6); renderResult(j.report); focusCard(6);
    } catch (e) {
      clearTimeout(timer);
      root.dataset.step = '4';
      show('#htr-rr-err4', e.message);
    } finally { btn.disabled = false; btn.textContent = 'Generate my roof report'; }
  });

  function progress(n) {
    $$('#htr-rr-progress li').forEach(function (li) {
      var p = Number(li.dataset.p);
      li.className = p < n ? 'is-done' : (p === n ? 'is-active' : '');
    });
  }
  progress(1);

  /* ---------- Step 6: result ---------- */
  function renderResult(r) {
    var h = '';
    if (r.measured) {
      h += '<div class="htr-rr-stats">' +
        tile('Total roof area', Number(r.areaSqft).toLocaleString('en-US') + ' sq ft') +
        tile('Predominant pitch', r.pitch) +
        tile('Facets', r.faces) +
        tile('Roofing squares', r.squares) +
        (r.priceLow ? '<div class="htr-rr-tile htr-rr-tile--price"><span>Estimated price range</span><b>' + money(r.priceLow) + ' &ndash; ' + money(r.priceHigh) + '</b><small>' + esc(r.roofType) + ' &middot; $' + Number(r.rateLow).toFixed(2) + ' &ndash; $' + Number(r.rateHigh).toFixed(2) + ' per sq ft</small></div>'
          : '<div class="htr-rr-tile htr-rr-tile--price"><span>Estimated price range</span><b>On-site quote</b><small>' + esc(r.roofType) + ' is priced after a free inspection.</small></div>') +
        '</div>' +
        (r.areaSqft < 400 ? '<p class="htr-rr-alert">This measured only ' + esc(r.areaSqft) + ' sq ft, which looks like a small structure (shed, carport or garage) rather than your house. Go back to step 2, drag the pin onto the main roof and generate the report again.</p>' : '') +
        '<div class="htr-rr-panels">' +
        '<div class="htr-rr-panel"><h4>3D roof diagram</h4>' + window.HTRRoofDiagram.iso(r) + '</div>' +
        '<div class="htr-rr-panel"><h4>Roof plan view</h4>' + window.HTRRoofDiagram.plan(r) + '</div>' +
        '<div class="htr-rr-panel htr-rr-panel--wide"><h4>Your home from above (Google 3D)</h4><div class="htr-rr-3d" id="htr-rr-3d" aria-label="Google 3D view of your home"></div></div>' +
        '</div>' +
        '<p class="htr-rr-note">Roof area, pitch and roof faces are measured from Google aerial imagery' + (r.imageryDate ? ' taken ' + esc(r.imageryDate.slice(0, 7)) : '') + '. The diagrams are drawn to the measured size and pitch; exact ridge, hip, valley and eave lines are confirmed during your free inspection.</p>';
      if (r.needsReview) h += '<p class="htr-rr-alert">Our team will double-check these measurements and confirm your estimate.</p>';
    } else {
      h += '<div class="htr-rr-alert">We could not measure this roof from aerial imagery. Our team has your details and will contact you to measure it in person, free of charge.</div>';
    }
    h += '<div class="htr-rr-actions">' +
      '<a class="htr-rr-btn" href="' + esc(r.url) + '&print=1" target="_blank" rel="noopener">Download PDF report</a>' +
      '<button type="button" class="htr-rr-btn htr-rr-btn--ghost" id="htr-rr-mail">Email me a copy</button>' +
      '<a class="htr-rr-btn htr-rr-btn--ghost" href="' + esc(r.url) + '" target="_blank" rel="noopener">View full details online</a>' +
      '</div><p class="htr-rr-msg" id="htr-rr-mailmsg" aria-live="polite"></p>' +
      '<div class="htr-rr-next"><h4>Next steps</h4><ul><li><b>Schedule a free inspection</b>: we confirm the measurements and give you a final quote.</li>' +
      (C.phone ? '<li><b>Call us</b>: <a href="tel:' + esc(C.phone.replace(/[^0-9+]/g, '')) + '">' + esc(C.phone) + '</a></li>' : '') +
      '<li><b>Financing</b>: flexible options for qualified homeowners.</li></ul></div>' +
      '<p class="htr-rr-fine">' + esc(r.disclaimer) + '</p>';
    $('#htr-rr-result').innerHTML = h;
    $('#htr-rr-mail').addEventListener('click', mailCopy);
    if (r.measured) show3d(r);
  }

  /* 3D view: Google photorealistic 3D (gmp-map-3d). Falls back to the satellite map with roof faces outlined. */
  async function show3d(r) {
    var box = $('#htr-rr-3d');
    try {
      await google.maps.importLibrary('maps3d');
      var el = document.createElement('gmp-map-3d');
      el.setAttribute('center', r.lat + ',' + r.lng + ',0');
      el.setAttribute('range', '220'); el.setAttribute('tilt', '55'); el.setAttribute('heading', '20'); el.setAttribute('mode', 'hybrid');
      box.appendChild(el);
      setTimeout(function () { if (!customElements.get('gmp-map-3d')) { box.innerHTML = ''; drawFaces(r, box); } }, 4000);
    } catch (e) { box.innerHTML = ''; drawFaces(r, box); }
  }
  function tile(label, val) { return '<div class="htr-rr-tile"><span>' + esc(label) + '</span><b>' + esc(val) + '</b></div>'; }

  async function drawFaces(r, el) {
    var maps = await google.maps.importLibrary('maps');
    var m = new maps.Map(el, { center: { lat: r.lat, lng: r.lng }, zoom: 20, mapTypeId: 'satellite', tilt: 0, disableDefaultUI: true, zoomControl: true, gestureHandling: 'cooperative' });
    var bounds = new google.maps.LatLngBounds();
    (r.facesDetail || []).forEach(function (f) {
      if (!f.box) return;
      var b = { south: f.box.sw.latitude, west: f.box.sw.longitude, north: f.box.ne.latitude, east: f.box.ne.longitude };
      new maps.Rectangle({ map: m, bounds: b, strokeColor: '#3fa9ff', strokeWeight: 2, fillColor: '#3fa9ff', fillOpacity: 0.12, clickable: false });
      bounds.union(b);
    });
    if (!bounds.isEmpty()) m.fitBounds(bounds, 40);
  }

  async function mailCopy() {
    var b = $('#htr-rr-mail'); b.disabled = true;
    try {
      var j = (await api('email', { token: state.report.token })).json || {};
      $('#htr-rr-mailmsg').textContent = j.message || (j.ok ? 'Sent.' : 'Could not send.');
    } catch (e) { $('#htr-rr-mailmsg').textContent = 'Could not send. Use Download PDF instead.'; }
    b.disabled = false;
  }

  initSearch();
})();
