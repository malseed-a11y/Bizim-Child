/* Donation modal: open/close (trigger, overlay, close button, Esc). */
(function () {
  function ready(fn) {
    if (document.readyState !== "loading") { fn(); }
    else { document.addEventListener("DOMContentLoaded", fn); }
  }
  function closeModal(m) {
    if (!m) { return; }
    m.classList.remove("is-open");
    m.setAttribute("aria-hidden", "true");
    document.body.classList.remove("bizim-modal-open");
    document.documentElement.style.removeProperty("--bizim-scrollbar");
  }
  ready(function () {
    document.addEventListener("click", function (e) {
      var opener = e.target.closest("[data-bizim-modal]");
      if (opener) {
        e.preventDefault();
        var m = document.getElementById(opener.getAttribute("data-bizim-modal"));
        if (!m) { return; }
        // Locking body scroll hides the scrollbar, which widens the page and
        // knocks sliders (Splide) out of alignment. Reserve the same width.
        var scrollbar = window.innerWidth - document.documentElement.clientWidth;
        document.documentElement.style.setProperty("--bizim-scrollbar", scrollbar + "px");

        m.classList.add("is-open");
        m.setAttribute("aria-hidden", "false");
        document.body.classList.add("bizim-modal-open");

        // Load the donation form the first time this modal is opened.
        var frame = m.querySelector("iframe[data-src]");
        if (frame && !frame.getAttribute("src")) {
          frame.setAttribute("src", frame.getAttribute("data-src"));
        }

        window.dispatchEvent(new Event("resize"));
        setTimeout(function () {
          var iframe = m.querySelector("iframe");
          if (iframe) { iframe.style.height = "550px"; iframe.style.minHeight = "480px"; }
          window.dispatchEvent(new Event("resize"));
        }, 150);
        return;
      }
      if (e.target.closest("[data-bizim-close]")) {
        closeModal(e.target.closest(".bizim-modal"));
      }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") { closeModal(document.querySelector(".bizim-modal.is-open")); }
    });
  });
})();