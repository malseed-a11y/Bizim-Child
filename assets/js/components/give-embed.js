/* GiveWP inline embeds: stay invisible until the form inside the iframe has really rendered.
   GiveWP sizes the iframe before the form paints, and the iframe document is white -
   revealing on height alone shows an empty white card. */
(function () {
  function ready(fn) {
    if (document.readyState !== "loading") { fn(); }
    else { document.addEventListener("DOMContentLoaded", fn); }
  }

  function formPainted(frame) {
    if (frame.offsetHeight < 60) { return false; }
    try {
      var doc = frame.contentDocument;
      if (!doc) { return false; }
      var form = doc.querySelector(".givewp-donation-form, form");
      return !!form && form.offsetHeight > 50;
    } catch (e) {
      return frame.offsetHeight > 60;   // cross-origin fallback
    }
  }

  function watch(frame) {
    var wrap = frame.closest(".brxe-shortcode");
    if (!wrap || wrap.__bizimWatched) { return; }
    wrap.__bizimWatched = true;

    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      if (formPainted(frame) || tries > 150) {   // 30s failsafe
        wrap.classList.add("is-form-ready");
        clearInterval(timer);
      }
    }, 150);
  }

  ready(function () {
    function scan() {
      var frames = document.querySelectorAll(".root-data-givewp-embed iframe");
      Array.prototype.forEach.call(frames, watch);
    }
    scan();
    if (window.MutationObserver) {
      new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
    }
  });
})();