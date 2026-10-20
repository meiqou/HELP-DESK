/* USLS IT Helpdesk: progressive enhancements (the site works without JavaScript). */
(function () {
    'use strict';

    // Mobile navigation
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('primary-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Confirmation prompts for destructive actions: <form data-confirm="...">
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // Prevent double submission (skipped for forms or buttons marked data-no-lock)
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            var button = event.submitter || form.querySelector('button[type="submit"]');
            if (button && !form.hasAttribute('data-no-lock') && !button.hasAttribute('data-no-lock')) {
                setTimeout(function () { button.disabled = true; }, 0);
            }
        });
    });

    // A page restored with the Back button must not keep its buttons disabled
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            document.querySelectorAll('button[disabled]').forEach(function (b) { b.disabled = false; });
        }
    });

    // Show / hide password
    document.querySelectorAll('.password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-target'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });

    // Password strength meter + live policy checklist
    document.querySelectorAll('input[data-strength]').forEach(function (input) {
        var container = input.closest('.field');
        var fill = container.querySelector('.meter-fill');
        var label = container.querySelector('.meter-label');
        var min = parseInt(input.getAttribute('data-min'), 10) || 12;
        var rules = {
            length: function (v) { return v.length >= min; },
            lower: function (v) { return /[a-z]/.test(v); },
            upper: function (v) { return /[A-Z]/.test(v); },
            number: function (v) { return /\d/.test(v); },
            symbol: function (v) { return /[^a-zA-Z\d]/.test(v); }
        };
        var names = ['Too weak', 'Weak', 'Fair', 'Strong', 'Very strong'];

        input.addEventListener('input', function () {
            var v = input.value;
            var met = 0;
            Object.keys(rules).forEach(function (key) {
                var ok = rules[key](v);
                if (ok) met++;
                var item = container.querySelector('[data-rule="' + key + '"]');
                if (item) item.classList.toggle('is-met', ok);
            });
            // Same rules as the server (PasswordPolicy): every rule must pass and no
            // character may repeat four or more times. Only then does length add strength.
            var passes = met === 5 && !/(.)\1{3,}/.test(v);
            var score;
            if (!passes) {
                score = met >= 4 ? 1 : 0;                       // Too weak / Weak
            } else {
                score = v.length >= min + 4 ? 4 : (v.length >= min + 2 ? 3 : 2);   // Fair / Strong / Very strong
            }
            if (fill) {
                fill.style.width = (v.length ? (score + 1) * 20 : 0) + '%';
                fill.setAttribute('data-score', String(score));
            }
            if (label) label.textContent = v.length ? names[score] : 'Enter a password';
        });
    });

    // Matching password confirmation
    document.querySelectorAll('input[data-match]').forEach(function (input) {
        var other = document.getElementById(input.getAttribute('data-match'));
        var check = function () {
            input.setCustomValidity(input.value && other && input.value !== other.value ? 'Passwords do not match.' : '');
        };
        input.addEventListener('input', check);
        if (other) other.addEventListener('input', check);
    });

    // Client-side table / list filter: <input data-filter="#selector">
    document.querySelectorAll('input[data-filter]').forEach(function (input) {
        var rows = document.querySelectorAll(input.getAttribute('data-filter'));
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') event.preventDefault();
        });
        input.addEventListener('input', function () {
            var q = input.value.toLowerCase().trim();
            rows.forEach(function (row) {
                var text = (row.getAttribute('data-search') || row.textContent).toLowerCase();
                row.hidden = q !== '' && text.indexOf(q) === -1;
            });
        });
    });

    // Copy to clipboard: <button data-copy="#element">
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var source = document.querySelector(button.getAttribute('data-copy'));
            if (!source || !navigator.clipboard) return;
            navigator.clipboard.writeText(source.innerText.trim()).then(function () {
                var original = button.textContent;
                button.textContent = 'Copied';
                setTimeout(function () { button.textContent = original; }, 1500);
            });
        });
    });

    // Numeric one-time-code inputs
    document.querySelectorAll('input[data-otp]').forEach(function (input) {
        input.addEventListener('input', function () {
            if (!input.getAttribute('data-allow-recovery')) {
                input.value = input.value.replace(/\D/g, '').slice(0, 6);
            }
        });
    });
})();

// Sidebar (app shell) on small screens
(function () {
    'use strict';
    var toggle = document.querySelector('[data-sidebar-toggle]');
    if (!toggle) return;
    var sidebar = document.getElementById('sidebar');
    var setOpen = function (open) {
        var wasOpen = document.body.classList.contains('sidebar-open');
        document.body.classList.toggle('sidebar-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && sidebar) {
            var first = sidebar.querySelector('a, button');
            if (first) setTimeout(function () { first.focus(); }, 50);   // move keyboard focus into the menu
        } else if (wasOpen) {
            toggle.focus();
        }
    };
    toggle.addEventListener('click', function () { setOpen(!document.body.classList.contains('sidebar-open')); });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) setOpen(false);
    });
})();
