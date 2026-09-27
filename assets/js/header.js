/* Global header: mobile nav toggle. */
(function () {
  function ready(fn) {
    if (document.readyState !== "loading") { fn(); }
    else { document.addEventListener("DOMContentLoaded", fn); }
  }
  ready(function () {
    var tog = document.querySelector(".bizim-header-toggle");
    var nav = document.querySelector(".bizim-header-nav");
    if (!tog || !nav) { return; }
    tog.addEventListener("click", function () {
      nav.classList.toggle("is-open");
      tog.classList.toggle("is-active");
    });
    nav.addEventListener("click", function (e) {
      if (e.target.closest("a")) {
        nav.classList.remove("is-open");
        tog.classList.remove("is-active");
      }
    });
  });
})();

/* ==========================================================================
   Give Archive - Mobile Custom Scrollbar
   Works with Bricks + dynamically rendered filter elements
   ========================================================================== */
(function(){'use strict';var BAR='.bizim-give-filterbar',FILTER='.bizim-give-filter, ul.bizim-give-filter',THUMB=0.30,PAD=16;

function update(bar,filter){if(!bar||!filter)return;var maxScroll=filter.scrollWidth-filter.clientWidth,trackWidth=Math.max(0,bar.clientWidth-PAD*2),thumbWidth=trackWidth*THUMB,maxTravel=Math.max(0,trackWidth-thumbWidth);if(maxScroll<=1||maxTravel<=0){bar.style.setProperty('--scroll-x','0px');bar.classList.add('no-horizontal-scroll');return;}bar.classList.remove('no-horizontal-scroll');var pct=Math.max(0,Math.min(1,filter.scrollLeft/maxScroll));bar.style.setProperty('--scroll-x',(pct*maxTravel).toFixed(2)+'px');}

function initBar(bar){if(!bar)return;var filter=bar.querySelector(FILTER);if(!filter)return;if(filter.dataset.customScrollbarReady==='true'){update(bar,filter);return;}filter.dataset.customScrollbarReady='true';filter.addEventListener('scroll',function(){update(bar,filter);},{passive:true});window.addEventListener('resize',function(){update(bar,filter);},{passive:true});if(typeof ResizeObserver!=='undefined'){new ResizeObserver(function(){update(bar,filter);}).observe(bar);}if(typeof MutationObserver!=='undefined'){new MutationObserver(function(){requestAnimationFrame(function(){update(bar,filter);});}).observe(filter,{childList:true,subtree:true});}requestAnimationFrame(function(){update(bar,filter);});setTimeout(function(){update(bar,filter);},100);setTimeout(function(){update(bar,filter);},500);}

function initAll(){document.querySelectorAll(BAR).forEach(initBar);}

function watchDOM(){if(typeof MutationObserver==='undefined'||!document.body)return;new MutationObserver(function(){requestAnimationFrame(initAll);}).observe(document.body,{childList:true,subtree:true});}

['bricks/ajax/end','bricks/ajax/query_result/completed','bricks/ajax/query_result/displayed','bricks/ajax/nodes_added'].forEach(function(evt){document.addEventListener(evt,function(){requestAnimationFrame(initAll);});});

function start(){initAll();watchDOM();}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',start);}else{start();}
window.addEventListener('load',initAll);
})();
