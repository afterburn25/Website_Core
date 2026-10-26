(function () {
  'use strict';
  var marker = document.querySelector('[data-core-analytics]');
  if (!marker || navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl === true) return;
  var token = marker.getAttribute('data-token');
  var endpoint = marker.getAttribute('data-endpoint');
  if (!token || !endpoint) return;
  var active = 0, last = Date.now(), visible = !document.hidden, timer;
  function accrue() {
    var now = Date.now();
    if (visible) active += Math.min(20, Math.max(0, Math.round((now - last) / 1000)));
    last = now;
  }
  function payload() { accrue(); return JSON.stringify({ token: token, active_seconds: active }); }
  function send(final) {
    if (!active) return;
    var body = payload();
    if (final && navigator.sendBeacon) { navigator.sendBeacon(endpoint, new Blob([body], {type:'application/json'})); return; }
    fetch(endpoint, {method:'POST',credentials:'same-origin',keepalive:!!final,headers:{'Content-Type':'application/json'},body:body}).catch(function(){});
  }
  document.addEventListener('visibilitychange', function () { accrue(); visible = !document.hidden; if (!visible) send(false); });
  window.addEventListener('pagehide', function () { send(true); });
  window.addEventListener('pageshow', function () { last = Date.now(); visible = !document.hidden; });
  timer = window.setInterval(function () { if (visible) send(false); }, 15000);
  window.addEventListener('unload', function () { window.clearInterval(timer); });
}());
