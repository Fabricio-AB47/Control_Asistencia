// Lightweight menu toggle for all modules
(function () {
  function initMenu(nav) {
    if (!nav) return;
    // Avoid duplicate init
    if (nav.dataset.menuReady === '1') return;
    nav.dataset.menuReady = '1';

    // Inject real button if not present
    var btn = nav.querySelector('.menu__btn');
    if (!btn) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'menu__btn';
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-label', 'Abrir menú');
      btn.innerText = 'Menú ☰';
      nav.insertBefore(btn, nav.firstChild);
    }

    function openMenu() {
      nav.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
    }
    function closeMenu() {
      nav.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
    }
    function toggleMenu() {
      if (nav.classList.contains('is-open')) closeMenu();
      else openMenu();
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      toggleMenu();
    });

    // Open when cursor is exactly over the button
    btn.addEventListener('mouseenter', function () {
      openMenu();
    });
    // Close when leaving the whole menu area
    nav.addEventListener('mouseleave', function () {
      closeMenu();
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
      if (!nav.contains(e.target)) closeMenu();
    });

    // Close on Escape
    nav.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });

    // Close after clicking a link
    nav.addEventListener('click', function (e) {
      var a = e.target.closest('a');
      if (a) closeMenu();
    });
  }

  function initAll() {
    var navs = document.querySelectorAll('.contenedor-menu, .header__menu');
    navs.forEach(initMenu);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
