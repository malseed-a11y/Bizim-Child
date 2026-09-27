/* Odometer trust counters (animate once on scroll into view). */
(function () {
  function ready(fn) {
    if (document.readyState !== "loading") { fn(); }
    else { document.addEventListener("DOMContentLoaded", fn); }
  }
  function digits(s) { var m = (s || "").match(/\d+/g); return m ? parseInt(m.join(""), 10) : 0; }
  function init() {
    if (typeof Odometer === "undefined") { return; }
    var els = Array.prototype.slice.call(document.querySelectorAll(".bizim-count"));
    if (!els.length) { return; }
    els.forEach(function (el) {
      var raw = (el.getAttribute("data-count") || el.textContent || "").trim();
      el.innerHTML = "";
      var od = new Odometer({ el: el, value: 0, format: "(.ddd)", duration: 2200, theme: "default" });
      el._od = od;
      el._target = digits(raw);
    });
    if ("IntersectionObserver" in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            var el = e.target;
            if (!el._ran) { el._ran = true; el._od.update(el._target); }
            io.unobserve(el);
          }
        });
      }, { threshold: 0.25 });
      els.forEach(function (el) { io.observe(el); });
    } else {
      els.forEach(function (el) { el._od.update(el._target); });
    }
  }
  ready(init);
})();
