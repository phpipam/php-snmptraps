/**
 * php-snmptraps UI (Tabler / Bootstrap 5, jQuery, tippy.js)
 */

/* send CSRF token with every XHR */
$.ajaxSetup({
    cache: false,
    headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') || '' }
});

/* ---- cookies ---- */
function createCookie(name, value, days) {
    var expires = '';
    if (typeof days !== 'undefined') {
        var d = new Date();
        d.setTime(d.getTime() + days * 86400000);
        expires = '; expires=' + d.toUTCString();
    }
    document.cookie = name + '=' + value + expires + '; path=/; SameSite=Lax';
}
function readCookie(name) {
    var parts = document.cookie.split(';');
    for (var i = 0; i < parts.length; i++) {
        var c = parts[i].trim();
        if (c.indexOf(name + '=') === 0) { return c.substring(name.length + 1); }
    }
    return null;
}

/* ---- Tabler input masks (time fields) in dynamically loaded HTML; datepickers use Tabler's data API ---- */
function initInputs(root) {
    var t = window.tabler;
    if (!t || !t.InputMask) { return; }
    Array.prototype.forEach.call(root.querySelectorAll('[data-mask]'), function (el) { t.InputMask.getOrCreateInstance(el); });
}

/* ---- tooltips: tippy-title holds escaped HTML ---- */
function initTooltips(ctx) {
    var root = ctx || document;
    var pick = function (sel) {
        var els = Array.prototype.slice.call(root.querySelectorAll(sel + ':not([data-tippy-init])'));
        els.forEach(function (el) { el.setAttribute('data-tippy-init', '1'); });
        return els;
    };
    var content = function (ref) { return ref.getAttribute('tippy-title'); };
    tippy(pick('[data-toggle="tooltip"]'), {
        duration: 0, arrow: false, followCursor: true, allowHTML: true, offset: [0, 10], content: content
    });
    tippy(pick('[data-toggle="tooltip-nofollow"]'), {
        duration: 0, arrow: false, allowHTML: true, offset: [0, 4], placement: 'bottom', content: content
    });
}

function showSpinner() { $('div.loading').show(); }
function hideSpinner() { $('div.loading').fadeOut('fast'); }

$(function () {

    initTooltips();
    initInputs(document);

    /* ---- auto reload ---- */
    var timerPaused = readCookie('stop-refresh') === '1';
    var modalOpen = false;
    var $bar = $('.progress-bar-reload');
    if ($bar.length) {
        var total = parseInt($bar.data('seconds'), 10);
        var left = total;
        setInterval(function () {
            if (timerPaused || modalOpen) { return; }
            left--;
            if (left <= 0) { window.location.reload(); return; }
            var pct = Math.round(left / total * 100);
            $bar.css('width', pct + '%')
                .toggleClass('bg-red', pct < 20).toggleClass('bg-orange', pct >= 20 && pct < 40);
            $('.badge-timer').text(left + 's');
        }, 1000);
    }
    $('.toggle-timer').on('click', function () {
        timerPaused = !timerPaused;
        createCookie('stop-refresh', timerPaused ? '1' : '0', timerPaused ? 365 : -1);
        $(this).find('i').toggleClass('ti-player-pause', !timerPaused).toggleClass('ti-player-play', timerPaused);
        $('.badge-timer, .progress-reload').toggle(!timerPaused);
        return false;
    });

    /* ---- light / dark mode (default dark; cookie read server-side so the page renders without a flash) ---- */
    $('.toggle-theme').on('click', function () {
        var light = document.documentElement.getAttribute('data-bs-theme') !== 'light';
        document.documentElement.setAttribute('data-bs-theme', light ? 'light' : 'dark');
        createCookie('theme', 'light', light ? 365 : -1);
        $(this).find('i').toggleClass('ti-sun', !light).toggleClass('ti-moon', light);
        return false;
    });

    /* ---- modals: links with .load-modal load their href into #modal1 ---- */
    var modalEl = document.getElementById('modal1');
    // Tabler >= 1.x exports Bootstrap as tabler.bootstrap (older builds set window.bootstrap)
    var bs = window.bootstrap || (window.tabler && window.tabler.bootstrap);
    var modal = modalEl && bs ? bs.Modal.getOrCreateInstance(modalEl) : null;
    var modalRedirect = null;
    var placeholder = '<div class="modal-body"><div class="placeholder-glow"><span class="placeholder col-9 mb-2"></span>'
        + '<span class="placeholder col-7 mb-2"></span><span class="placeholder col-10"></span></div></div>';

    $(document).on('click', '.load-modal', function () {
        if (!modal) { return true; }
        // optional data-redirect: where to go after a successful submit (default: reload)
        modalRedirect = $(this).data('redirect') || null;
        var $content = $('#modal1 .modal-content').html(placeholder);
        $.get($(this).attr('href'))
            .done(function (html) { $content.html(html); initTooltips($content[0]); initInputs($content[0]); })
            .fail(function (xhr) { $content.html('<div class="modal-body">' + (xhr.responseText || '<div class="alert alert-danger">Request failed</div>') + '</div>'); });
        modal.show();
        return false;
    });
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function () { modalOpen = true; });
        modalEl.addEventListener('hidden.bs.modal', function () { modalOpen = false; });
    }

    /* submit the #modal-form to data-action; reload on success */
    $(document).on('click', '.modal-execute', function () {
        var $btn = $(this).prop('disabled', true);
        var $content = $btn.closest('.modal-content');
        var $result = $content.find('.modal-result');
        showSpinner();
        $.post($btn.data('action'), $content.find('#modal-form').serialize())
            .done(function (data) {
                $result.html(data).fadeIn('fast');
                if (data.indexOf('alert-danger') === -1 && data.indexOf('alert-warning') === -1) {
                    setTimeout(function () {
                        if (modalRedirect) { window.location.href = modalRedirect; } else { window.location.reload(); }
                    }, 1200);
                } else {
                    $btn.prop('disabled', false);
                    hideSpinner();
                }
            })
            .fail(function (xhr) {
                $result.html(xhr.responseText || '<div class="alert alert-danger">Request failed</div>').fadeIn('fast');
                $btn.prop('disabled', false);
                hideSpinner();
            });
        return false;
    });

    /* ---- client side filters ---- */
    $('.host-filter').on('input', function () {
        var q = this.value.toLowerCase();
        $('.host-item').each(function () { $(this).toggle($(this).data('host').toString().indexOf(q) !== -1); });
    });
    $('.table-filter').on('input', function () {
        var q = this.value.toLowerCase();
        $($(this).data('target')).find('tbody tr').each(function () { $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1); });
    });
    $(document).on('input', '.checkbox-filter', function () {
        var q = this.value.toLowerCase();
        $(this).next().find('label').each(function () { $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1); });
    });

    /* ---- live view ---- */
    var $live = $('table.table-live');
    if ($live.length) {
        var livePaused = false;
        var interval = parseInt($live.data('live-interval'), 10) * 1000;
        var poll = function () {
            if (livePaused || modalOpen) { return; }
            var $tbody = $live.find('tbody');
            var id = $tbody.find('tr[data-id]').first().attr('data-id') || 0;
            $.get($live.data('live-url'), { id: id }).done(function (html) {
                $('.live-last').text(new Date().toLocaleTimeString());
                if (html !== 'False') {
                    var $new = $(html);
                    // a repeat replaces the older row of the same chain
                    $new.filter('tr[data-fp]').each(function () {
                        $tbody.find('tr[data-fp="' + $(this).attr('data-fp') + '"]').remove();
                    });
                    $tbody.prepend($new);
                    $tbody.find('tr').slice(500).remove();
                    initTooltips($tbody[0]);
                }
            });
        };
        setInterval(poll, interval);
        $('.live-toggle').on('click', function () {
            livePaused = !livePaused;
            $(this).find('i').toggleClass('ti-player-pause', !livePaused).toggleClass('ti-player-play', livePaused);
            return false;
        });
    }
});
