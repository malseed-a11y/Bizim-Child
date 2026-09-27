(function () {
  function ready(fn) {
    if (document.readyState !== "loading") {
      fn();
    } else {
      document.addEventListener("DOMContentLoaded", fn);
    }
  }

  function closeModal(m) {
    if (m) {
      m.classList.remove("is-open");
      m.setAttribute("aria-hidden", "true");
      document.body.classList.remove("bizim-modal-open");
    }
  }

  ready(function () {
    document.addEventListener("click", function (e) {
      var opener = e.target.closest("[data-bizim-modal]");
      if (opener) {
        e.preventDefault();
        var modalId = opener.getAttribute("data-bizim-modal");
        var m = document.getElementById(modalId);
        if (m) {
          m.classList.add("is-open");
          m.setAttribute("aria-hidden", "false");
          document.body.classList.add("bizim-modal-open");

          // Trigger window resize event to force GiveWP embed to adjust width/height
          window.dispatchEvent(new Event("resize"));

          // Set height on GiveWP iframe or shadow container after short delay
          setTimeout(function () {
            var iframe = m.querySelector("iframe");
            if (iframe) {
              iframe.style.height = "550px";
              iframe.style.minHeight = "480px";
            }
            window.dispatchEvent(new Event("resize"));
          }, 150);
        } else {
          console.error("Modal element not found for ID:", modalId);
        }
        return;
      }

      if (e.target.closest("[data-bizim-close]")) {
        closeModal(e.target.closest(".bizim-modal"));
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        closeModal(document.querySelector(".bizim-modal.is-open"));
      }
    });
  });
})();