/* Hometown Instant Roof Report - roof diagrams (one source for the results page AND the printable report).
 * Built from REAL measurements (Google Solar roof faces: overall size + main pitch + number of faces).
 * The roof SHAPE is an illustration (flat / gable / hip chosen from pitch and face count): Google's free data does
 * not include exact ridge, hip and valley lines, so the drawings are labeled as illustrations. */
(function () {
  'use strict';
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var FT = 3.28084;

  /* Overall roof size in metres from the measured faces (fallback: building box). L = long side, S = short side. */
  function geometry(r) {
    var boxes = (r.facesDetail || []).map(function (f) { return f.box; }).filter(Boolean);
    if (!boxes.length && r.buildingBox) boxes = [r.buildingBox];
    if (!boxes.length) return null;
    var cos = Math.cos(r.lat * Math.PI / 180);
    var minLat = Math.min.apply(null, boxes.map(function (b) { return b.sw.latitude; })), maxLat = Math.max.apply(null, boxes.map(function (b) { return b.ne.latitude; }));
    var minLng = Math.min.apply(null, boxes.map(function (b) { return b.sw.longitude; })), maxLng = Math.max.apply(null, boxes.map(function (b) { return b.ne.longitude; }));
    var ew = (maxLng - minLng) * 111320 * cos, ns = (maxLat - minLat) * 110540;
    var pitch = parseInt(String(r.pitch || '0'), 10) || 0, faces = Number(r.faces) || boxes.length;
    var type = pitch <= 1 ? 'flat' : (faces <= 2 ? 'gable' : 'hip');
    return { L: Math.max(ew, ns), S: Math.min(ew, ns), pitch: pitch, faces: faces, type: type, longIsEW: ew >= ns };
  }

  function fit(w, h, W, H, pad) { return Math.min((W - 2 * pad) / Math.max(w, 0.1), (H - 2 * pad) / Math.max(h, 0.1)); }
  var DEFS = '<defs><pattern id="htrShingle" width="8" height="6" patternUnits="userSpaceOnUse"><rect width="8" height="6" fill="#6b7480"/><path d="M0 5.5H8M4 0V3M0 3H8" stroke="#56606b" stroke-width="0.8"/></pattern></defs>';

  /* Roof plan (top view) with roof lines and dimensions on all four sides. */
  function plan(r) {
    var g = geometry(r); if (!g) return '<p class="htr-rr-note">No roof outline available.</p>';
    var W = 380, H = 270, pad = 46, s = fit(g.L, g.S, W, H, pad);
    var w = g.L * s, h = g.S * s, x0 = (W - w) / 2, y0 = (H - h) / 2 - 6, x1 = x0 + w, y1 = y0 + h, ym = (y0 + y1) / 2;
    var o = '<svg class="htr-rr-plan" viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Roof plan illustration, ' + Math.round(g.L * FT) + ' by ' + Math.round(g.S * FT) + ' feet">' + DEFS;
    o += '<rect x="' + x0 + '" y="' + y0 + '" width="' + w + '" height="' + h + '" fill="url(#htrShingle)" stroke="#0b2d4f" stroke-width="1.6"/>';
    var line = function (ax, ay, bx, by) { return '<line x1="' + ax.toFixed(1) + '" y1="' + ay.toFixed(1) + '" x2="' + bx.toFixed(1) + '" y2="' + by.toFixed(1) + '" stroke="#e9edf2" stroke-width="1.6"/>'; };
    if (g.type === 'hip') { var inset = h / 2; o += line(x0 + inset, ym, x1 - inset, ym) + line(x0, y0, x0 + inset, ym) + line(x0, y1, x0 + inset, ym) + line(x1, y0, x1 - inset, ym) + line(x1, y1, x1 - inset, ym); }
    else if (g.type === 'gable') { o += line(x0, ym, x1, ym); }
    o += '<text x="' + (W / 2) + '" y="' + (ym + (g.type === 'flat' ? 4 : 18)) + '" text-anchor="middle" fill="#fff" font-size="12" font-weight="700">' + (g.type === 'flat' ? 'Flat roof' : esc(r.pitch)) + '</text>';
    var dim = function (ax, ay, bx, by, label, rot) {
      var mx = (ax + bx) / 2, my = (ay + by) / 2;
      return '<line x1="' + ax + '" y1="' + ay + '" x2="' + bx + '" y2="' + by + '" stroke="#5b6475" stroke-width="1"/>' +
        '<text class="dim" x="' + mx + '" y="' + my + '" text-anchor="middle" dy="' + (rot ? 0 : -4) + '"' + (rot ? ' transform="rotate(' + rot + ' ' + mx + ' ' + my + ')"' : '') + '>' + label + '</text>';
    };
    var Lf = Math.round(g.L * FT) + ' ft', Sf = Math.round(g.S * FT) + ' ft';
    o += dim(x0, y0 - 12, x1, y0 - 12, Lf) + dim(x0, y1 + 22, x1, y1 + 22, Lf) + dim(x0 - 14, y0, x0 - 14, y1, Sf, -90) + dim(x1 + 14, y0, x1 + 14, y1, Sf, 90);
    return o + '<text class="dim" x="10" y="' + (H - 8) + '">Illustration from measured size &middot; ' + g.faces + ' roof faces &middot; long side ' + (g.longIsEW ? 'east-west' : 'north-south') + '</text></svg>';
  }

  /* 3D roof diagram (isometric) of the same roof on four walls. */
  function iso(r) {
    var g = geometry(r); if (!g) return '<p class="htr-rr-note">No roof outline available.</p>';
    var L = g.L, S = g.S, wall = 3, rise = g.type === 'flat' ? 0.3 : (S / 2) * g.pitch / 12, c = Math.cos(Math.PI / 6), sn = Math.sin(Math.PI / 6);
    var P = function (x, y, z) { return [(x - y) * c, (x + y) * sn - z]; };
    var top = wall + rise, inset = S / 2, faces = [];
    var poly = function (pts, fill, depth) { faces.push({ pts: pts, fill: fill, d: depth }); };
    // walls (only the two that face the viewer are visible: y=S front and x=L right)
    poly([[0, S, 0], [L, S, 0], [L, S, wall], [0, S, wall]], '#e7e2d8', L / 2 + S);
    poly([[L, 0, 0], [L, S, 0], [L, S, wall], [L, 0, wall]], '#d4cec2', L + S / 2);
    if (g.type === 'flat') {
      poly([[0, 0, top], [L, 0, top], [L, S, top], [0, S, top]], '#7d8691', 0);
      poly([[0, S, wall], [L, S, wall], [L, S, top], [0, S, top]], '#5d6670', L / 2 + S + 0.1);
      poly([[L, 0, wall], [L, S, wall], [L, S, top], [L, 0, top]], '#4f5862', L + S / 2 + 0.1);
    } else if (g.type === 'gable') {
      poly([[0, 0, wall], [L, 0, wall], [L, S / 2, top], [0, S / 2, top]], '#5f6873', L / 2 + S / 4);       // back slope
      poly([[0, S, wall], [L, S, wall], [L, S / 2, top], [0, S / 2, top]], 'url(#htrShingle)', L / 2 + 3 * S / 4); // front slope
      poly([[L, 0, wall], [L, S, wall], [L, S / 2, top]], '#cfc8bb', L + S / 2 + 0.2);                      // gable end wall
    } else {
      var a = Math.min(inset, L / 2);
      poly([[0, 0, wall], [L, 0, wall], [L - a, S / 2, top], [a, S / 2, top]], '#5f6873', L / 2 + S / 4);
      poly([[0, 0, wall], [a, S / 2, top], [0, S, wall]], '#56606b', S / 2);
      poly([[0, S, wall], [L, S, wall], [L - a, S / 2, top], [a, S / 2, top]], 'url(#htrShingle)', L / 2 + 3 * S / 4);
      poly([[L, 0, wall], [L, S, wall], [L - a, S / 2, top]], '#4f5862', L + S / 2);
    }
    faces.sort(function (p, q) { return p.d - q.d; });
    var pts2 = []; faces.forEach(function (f) { f.p2 = f.pts.map(function (v) { var q = P(v[0], v[1], v[2]); pts2.push(q); return q; }); });
    var minX = Math.min.apply(null, pts2.map(function (q) { return q[0]; })), maxX = Math.max.apply(null, pts2.map(function (q) { return q[0]; }));
    var minY = Math.min.apply(null, pts2.map(function (q) { return q[1]; })), maxY = Math.max.apply(null, pts2.map(function (q) { return q[1]; }));
    var W = 380, H = 270, s = fit(maxX - minX, maxY - minY, W, H - 20, 24), ox = (W - (maxX - minX) * s) / 2, oy = (H - 20 - (maxY - minY) * s) / 2;
    var o = '<svg class="htr-rr-plan" viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="3D roof diagram illustration">' + DEFS;
    faces.forEach(function (f) { o += '<polygon points="' + f.p2.map(function (q) { return (ox + (q[0] - minX) * s).toFixed(1) + ',' + (oy + (q[1] - minY) * s).toFixed(1); }).join(' ') + '" fill="' + f.fill + '" stroke="#2b3440" stroke-width="1" stroke-linejoin="round"/>'; });
    return o + '<text class="dim" x="10" y="' + (H - 8) + '">Illustration &middot; ' + (g.type === 'flat' ? 'flat roof' : g.type + ' roof, ' + esc(r.pitch) + ' pitch') + ' &middot; exact roof lines confirmed at inspection</text></svg>';
  }

  window.HTRRoofDiagram = { plan: plan, iso: iso, geometry: geometry };
  // Printable report: draw into placeholders when the page has them.
  document.addEventListener('DOMContentLoaded', function () {
    var data = document.getElementById('htr-rr-diagram-data'); if (!data) return;
    try { var r = JSON.parse(data.textContent); var a = document.getElementById('htr-rr-iso'), b = document.getElementById('htr-rr-planbox'); if (a) a.innerHTML = iso(r); if (b) b.innerHTML = plan(r); } catch (e) { /* leave the table */ }
  });
})();
