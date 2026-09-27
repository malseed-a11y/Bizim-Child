/* Runs INSIDE the GiveWP form iframe: apply ?bizim-amount= to the real form.
   Fails safe - if anything is missing the donor simply picks the amount themselves. */
(function () {
  var target = new URLSearchParams(window.location.search).get("bizim-amount");
  if (!target) { return; }
  var wanted = parseFloat(target);
  if (!(wanted > 0)) { return; }

  function numberFrom(text) {
    var cleaned = String(text).replace(/[^0-9.,]/g, "").replace(/,/g, "");
    return parseFloat(cleaned);
  }

  function apply() {
    var hidden = document.querySelector('input[name="amount"]');
    if (!hidden) { return false; }
    if (parseFloat(hidden.value) === wanted) { return true; }

    // 1) Prefer an exact preset level so the form highlights it.
    var levels = document.querySelectorAll(".givewp-fields-amount__level");
    for (var i = 0; i < levels.length; i++) {
      if (numberFrom(levels[i].textContent) === wanted) {
        levels[i].click();
        return parseFloat(document.querySelector('input[name="amount"]').value) === wanted;
      }
    }

    // 2) Otherwise treat it as a custom amount (React-safe value set).
    var custom = document.querySelector(".givewp-fields-amount__input-custom");
    if (!custom) { return false; }
    var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value").set;
    setter.call(custom, String(wanted));
    custom.dispatchEvent(new Event("input", { bubbles: true }));
    custom.dispatchEvent(new Event("change", { bubbles: true }));
    return parseFloat(document.querySelector('input[name="amount"]').value) === wanted;
  }

  var tries = 0;
  var timer = setInterval(function () {
    tries++;
    if (apply() || tries > 100) { clearInterval(timer); }   // ~15s guard
  }, 150);
})();