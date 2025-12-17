(function () {
  'use strict';

  function shuffle(arr) {
    var a = (arr || []).slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a;
  }

  function mount(instance) {
    var uid  = instance.uid;
    var root = document.getElementById(uid);
    if (!root) return;

    root.dataset.yabbyqUid = uid;

    var qData   = instance.question || {};
    var qTextEl = root.querySelector('.yabbyq-question');
    var optsEl  = root.querySelector('.yabbyq-options');
    var resEl   = root.querySelector('.yabbyq-result');
    var rWrap   = root.querySelector('.yabbyq-reward');
    var rCodeEl = root.querySelector('.yabbyq-reward-code');
    var ctaWrap = root.querySelector('.yabbyq-cta');
    var ctaLink = root.querySelector('.yabbyq-cta-link');

    if (qTextEl) qTextEl.textContent = qData.text || '';

    // render options (shuffled)
    var opts = shuffle(qData.options || []);
    opts.forEach(function (opt, idx) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'yabbyq-option';
      btn.textContent = String(opt);
      btn.setAttribute('data-opt', String(opt));

      // click
      btn.addEventListener('click', function () { checkAnswer(btn, opt); });

      // keyboard (Enter/Space)
      btn.addEventListener('keydown', function (e) {
        var k = e.key || e.code;
        if (k === 'Enter' || k === ' ' || k === 'Spacebar') {
          e.preventDefault();
          btn.click();
        }
      });

      if (optsEl) optsEl.appendChild(btn);
      if (idx === 0) btn.tabIndex = 0;
    });

    function disableAll() {
      if (!optsEl) return;
      Array.prototype.forEach.call(
        optsEl.querySelectorAll('.yabbyq-option'),
        function (b) { b.disabled = true; }
      );
    }

    function checkAnswer(btn, selected) {
      if (!resEl) return;

      var correct = (selected === qData.correct);

      if (correct) {
        resEl.textContent = '✅ Correct! Here is your reward:';
        resEl.style.display = 'block';
        
        if (rCodeEl) rCodeEl.textContent = qData.reward || '';
        if (rWrap)  rWrap.style.display  = 'block';
        if (ctaWrap) ctaWrap.style.display = 'block';

        // set CTA link if provided
        if (ctaLink && typeof instance.ctaUrl === 'string' && instance.ctaUrl.length > 0) {
          ctaLink.href = instance.ctaUrl;
          ctaLink.rel  = 'noopener noreferrer';
        }

        disableAll();
        btn.classList.add('correct');

        // focus na CTA ako postoji, inače na kod
        setTimeout(function(){
          if (ctaLink) { ctaLink.focus(); }
          else if (rCodeEl) { rCodeEl.focus && rCodeEl.focus(); }
        }, 50);

      } else {
        resEl.textContent = '❌ Incorrect! Please try again.';
        resEl.style.display = 'block';
        disableAll();
        btn.classList.add('wrong');
        
        
        setTimeout(function(){ resEl.focus && resEl.focus(); }, 50);
      }
    }

    // copy-to-clipboard na reward kod
    if (rCodeEl) {
      rCodeEl.setAttribute('title', 'Click to copy');
      rCodeEl.style.cursor = 'pointer';
      rCodeEl.addEventListener('click', function(){
        var code = rCodeEl.textContent || '';
        if (!code) return;
        try {
          navigator.clipboard.writeText(code).then(function(){
            var prev = rCodeEl.textContent;
            rCodeEl.textContent = 'Copied!';
            setTimeout(function(){ rCodeEl.textContent = prev; }, 900);
          });
        } catch(e) { /* no-op */ }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!window.YABBY_QUIZ || !Array.isArray(window.YABBY_QUIZ)) return;
    window.YABBY_QUIZ.forEach(mount);
  });
})();