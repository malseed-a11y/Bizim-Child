(function(){
  function ready(fn){ if(document.readyState!=="loading"){ fn(); } else { document.addEventListener("DOMContentLoaded", fn); } }
  ready(function(){
    var input = document.querySelector(".bizim-tel");
    if(!input || typeof window.intlTelInput === "undefined"){ return; }
    var iti = window.intlTelInput(input, {
      initialCountry: "tr",
      preferredCountries: ["tr","de","nl","fr","gb","us"],
      nationalMode: false,
      separateDialCode: false,
      autoPlaceholder: "aggressive",
      utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
    });
    var form = input.closest("form");
    input.addEventListener("blur", function(){
      if(input.value.trim() === ""){ input.classList.remove("bizim-valid"); input.classList.remove("wpcf7-not-valid"); return; }
      if(iti.isValidNumber()){ input.classList.add("bizim-valid"); input.classList.remove("wpcf7-not-valid"); }
      else { input.classList.remove("bizim-valid"); input.classList.add("wpcf7-not-valid"); }
    });
    input.addEventListener("input", function(){ input.classList.remove("wpcf7-not-valid"); });
    if(form){
      var fields = form.querySelectorAll(".bizim-cf7-input");
      Array.prototype.forEach.call(fields, function(f){
        if(f === input){ return; }
        f.addEventListener("blur", function(){
          var v = f.value.trim();
          if(v === ""){ f.classList.remove("bizim-valid"); return; }
          if(f.checkValidity()){ f.classList.add("bizim-valid"); } else { f.classList.remove("bizim-valid"); }
        });
      });
      form.addEventListener("submit", function(e){
        if(input.value.trim() !== ""){
          if(!iti.isValidNumber()){
            input.classList.add("wpcf7-not-valid");
            input.focus();
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
          }
          input.value = iti.getNumber();
        }
      }, true);
    }
    document.addEventListener("wpcf7mailsent", function(){
      if(!form){ return; }
      Array.prototype.forEach.call(form.querySelectorAll(".bizim-valid"), function(f){ f.classList.remove("bizim-valid"); });
    });
    document.addEventListener("wpcf7invalid", function(){
      var t = form ? form.querySelector(".wpcf7-not-valid") : null; if(t){ t.focus(); }
    });
  });
})();
