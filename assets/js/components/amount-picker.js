/* Hero amount picker: choose an amount, then open the donation modal with it. */
(function () {
  function ready(fn) {
    if (document.readyState !== "loading") { fn(); }
    else { document.addEventListener("DOMContentLoaded", fn); }
  }

  function selectedAmount(picker) {
    var custom = picker.querySelector(".bizim-hd__input");
    if (custom && custom.value !== "" && parseFloat(custom.value) > 0) {
      return String(Math.floor(parseFloat(custom.value)));
    }
    var active = picker.querySelector(".bizim-hd__level.is-selected");
    return active ? active.getAttribute("data-amount") : "";
  }

  ready(function () {
    // Amount selection
    document.addEventListener("click", function (e) {
      var lvl = e.target.closest(".bizim-hd__level");
      if (!lvl) { return; }
      var picker = lvl.closest(".bizim-hd");
      picker.querySelectorAll(".bizim-hd__level").forEach(function (b) {
        b.classList.remove("is-selected");
        b.setAttribute("aria-pressed", "false");
      });
      lvl.classList.add("is-selected");
      lvl.setAttribute("aria-pressed", "true");
      var custom = picker.querySelector(".bizim-hd__input");
      if (custom) { custom.value = ""; }
    });

    // Typing a custom amount clears the preset selection
    document.addEventListener("input", function (e) {
      var input = e.target.closest(".bizim-hd__input");
      if (!input) { return; }
      var picker = input.closest(".bizim-hd");
      if (input.value !== "") {
        picker.querySelectorAll(".bizim-hd__level").forEach(function (b) {
          b.classList.remove("is-selected");
          b.setAttribute("aria-pressed", "false");
        });
      }
    });

    // Capture phase: run BEFORE the modal script so the URL carries the amount.
    document.addEventListener("click", function (e) {
      var btn = e.target.closest(".bizim-hd__submit");
      if (!btn) { return; }
      var picker = btn.closest(".bizim-hd");
      var modal = document.getElementById(picker.getAttribute("data-modal"));
      if (!modal) { return; }
      var frame = modal.querySelector("iframe[data-src]");
      if (!frame) { return; }

      var amount = selectedAmount(picker);
      if (!amount) { return; }

      var base = frame.getAttribute("data-src").split("&bizim-amount=")[0];
      var url = base + "&bizim-amount=" + encodeURIComponent(amount);

      // Only reload the form when the amount actually changed.
      if (modal.__bizimAmount !== amount) {
        modal.__bizimAmount = amount;
        frame.setAttribute("data-src", url);
        frame.removeAttribute("src");     // modal script re-applies it on open
      }
    }, true);
  });
})();