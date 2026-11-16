/* E-Kantin — interaksi UI */
(function () {
    'use strict';

    /* Navigasi mobile */
    var navToggle = document.querySelector('[data-nav-toggle]');
    if (navToggle) {
        navToggle.addEventListener('click', function () {
            var open = document.body.classList.toggle('nav-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    /* Tutup navigasi mobile setelah memilih tautan */
    document.querySelectorAll('.main-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            document.body.classList.remove('nav-open');
            if (navToggle) {
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    });

    /* Bayangan header saat halaman digulir */
    var header = document.querySelector('[data-header]');
    if (header) {
        var syncHeader = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', syncHeader, { passive: true });
        syncHeader();
    }

    /* Reset menu mobile ketika layar melebar */
    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            document.body.classList.remove('nav-open');
            if (navToggle) {
                navToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });

    /* Flash message */
    document.querySelectorAll('[data-flash]').forEach(function (flash) {
        var close = flash.querySelector('[data-flash-close]');
        if (close) {
            close.addEventListener('click', function () {
                flash.remove();
            });
        }
        window.setTimeout(function () {
            if (!flash.isConnected) {
                return;
            }
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-6px)';
            flash.style.transition = 'opacity .25s ease, transform .25s ease';
            window.setTimeout(function () {
                flash.remove();
            }, 260);
        }, 6500);
    });

    /* Tampilkan / sembunyikan password */
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var wrap = button.closest('.password-wrap');
            var input = wrap ? wrap.querySelector('input') : null;
            if (!input) {
                return;
            }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? '🙈' : '👁️';
            button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        });
    });

    /* Isi otomatis akun demo */
    document.querySelectorAll('[data-demo-email]').forEach(function (button) {
        button.addEventListener('click', function () {
            var email = document.querySelector('input[name="email"]');
            var password = document.querySelector('input[name="password"]');
            if (email) {
                email.value = button.getAttribute('data-demo-email') || '';
            }
            if (password) {
                password.value = button.getAttribute('data-demo-password') || '';
                password.focus();
            }
        });
    });

    /* Konfirmasi aksi destruktif */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || 'Yakin ingin melanjutkan?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    /* Filter otomatis saat select berubah */
    document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            var form = select.closest('form');
            if (form) {
                form.submit();
            }
        });
    });

    /* Stepper stok cepat di halaman ketersediaan */
    document.querySelectorAll('[data-stock-step]').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = button.closest('[data-stock-form]');
            var input = form ? form.querySelector('input[name="stok"]') : null;
            if (!input) {
                return;
            }
            var step = parseInt(button.getAttribute('data-stock-step'), 10) || 0;
            var min = parseInt(input.getAttribute('min') || '0', 10);
            var max = parseInt(input.getAttribute('max') || '9999', 10);
            var value = parseInt(input.value, 10) || 0;
            input.value = Math.max(min, Math.min(max, value + step));
            input.focus();
        });
    });

    /* Tampilkan pilihan kantin hanya untuk peran pengelola */
    var roleSelect = document.querySelector('[data-role-select]');
    var kantinField = document.querySelector('[data-kantin-field]');
    if (roleSelect && kantinField) {
        var syncRole = function () {
            var isKantin = roleSelect.value === 'kantin';
            kantinField.classList.toggle('is-hidden', !isKantin);
            var select = kantinField.querySelector('select');
            if (select) {
                select.required = isKantin;
            }
        };
        roleSelect.addEventListener('change', syncRole);
        syncRole();
    }

    /* Tekan Escape untuk menutup navigasi mobile */
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.classList.contains('nav-open')) {
            document.body.classList.remove('nav-open');
            if (navToggle) {
                navToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });
})();
