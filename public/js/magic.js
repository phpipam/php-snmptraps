/**
 * php-snmptraps UI behaviour
 */

/* send CSRF token with every XHR */
$.ajaxSetup({
    cache: false,
    headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') || '' }
});

$(document).ready(function () {

    function showSpinner() { $('div.loading').show(); }
    function hideSpinner() { $('div.loading').fadeOut('fast'); }

    function initTooltips(ctx) {
        $('.tooltip2', ctx || document).tooltip({
            track: true, delay: 0, showURL: false, showBody: " - ",
            extraClass: "tooltip2", fixPNG: true, opacity: 0.95, left: 0
        });
    }
    initTooltips();
    if ($("[rel=tooltip]").length) { $("[rel=tooltip]").tooltip(); }

    $('a.disabled, button.disabled, .disabled a').click(function () { return false; });

    /* ---- page reload timer ---- */
    var isPaused = false;
    var modalPaused = false;
    setInterval(function () {
        var $bar = $('div.progress-bar');
        if (isPaused || !$bar.length || !$('div.progress-bar-reload').length) { return; }
        var reloadAt = parseInt($('div.progress-bar-reload').html(), 10);
        var state = parseInt($bar.attr('aria-valuenow'), 10) + 2;
        var pct = Math.round(state / reloadAt * 100);
        if (pct > 100) { window.location.reload(); return; }
        $bar.attr('aria-valuenow', state).html(pct + "%").attr("style", "width:" + pct + "%");
        $('.pbar_percentage').html(reloadAt - state);
    }, 2000);

    function stopTimer()   { isPaused = true;  $('.fa-toggle-timer').removeClass('fa-pause').addClass('fa-play'); }
    function resumeTimer() { isPaused = false; $('.fa-toggle-timer').removeClass('fa-play').addClass('fa-pause'); }
    $('.fa-toggle-timer').click(function () { isPaused ? resumeTimer() : stopTimer(); });

    /* ---- modals ---- */
    function openModal(id, href) {
        var $m = $(id);
        $m.find('.modal-content').html('<div class="modal-body">Loading...</div>').load(href);
        $m.modal('show');
        if (!isPaused) { modalPaused = true; }
        stopTimer();
    }
    $(document).on("click", '.load-modal', function () { openModal('#modal1', $(this).attr('href')); return false; });
    $(document).on("click", '.load-modal-big', function () { openModal('#modal2', $(this).attr('href')); return false; });
    $('#modal1, #modal2').on('hidden.bs.modal', function () {
        if (modalPaused) { modalPaused = false; resumeTimer(); }
    });

    /* submit the form inside a modal to the URL in data-action */
    $(document).on('click', '.modal-execute', function () {
        var $content = $(this).closest('.modal-content');
        var $result = $content.find('.modal-result');
        showSpinner();
        $.post($(this).data('action'), $content.find('#modal-form').serialize())
            .done(function (data) {
                $result.html(data).fadeIn('fast');
                if (data.indexOf('alert-danger') === -1 && data.indexOf('alert-warning') === -1) {
                    setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    hideSpinner();
                }
            })
            .fail(function (xhr) {
                $result.html(xhr.responseText || '<div class="alert alert-danger">Request failed</div>').fadeIn('fast');
                hideSpinner();
            });
        return false;
    });

    /* ---- full screen tables ---- */
    $(document).on("click", 'th.header-full_screen', function () {
        $(this).closest('div.container-fluid').toggleClass('full_screen full_screen_live');
        $('nav.navbar, .search-wrapper, .footer, .hosts-wrapper, .message-wrapper').toggleClass('hidden');
    });

    /* ---- bootstrap-table ---- */
    $('table.sorted')
        .attr("data-toggle", "table")
        .attr('data-pagination', 'true')
        .attr('data-page-size', '50')
        .attr('data-page-list', '[50,100,250,500,All]')
        .attr('data-search', 'true')
        .attr('data-classes', 'table-no-bordered')
        .attr('data-icon-size', 'sm')
        .attr('data-show-footer', 'false')
        .attr('data-show-columns', 'true')
        .attr('data-icons-prefix', 'fa')
        .attr('data-icons', 'icons')
        .attr('data-sortable', 'true')
        .attr('data-sort-order', 'desc')
        .attr('data-sort-name', 'date');

    $('table.sorted-ajax')
        .attr('data-side-pagination', 'server')
        .attr('data-toggle', 'table')
        .attr('data-row-style', 'rowClass')
        .attr('data-row-attributes', 'rowAttributes');

    /* re-attach tooltips after server side pages load */
    $(document).on('load-success.bs.table post-body.bs.table', 'table', function () { initTooltips(this); });
});

/* bootstrap-table callbacks (global) */
var icons = { refresh: 'fa-refresh', toggle: 'fa-list-alt', columns: 'fa-th', paginationSwitchDown: 'fa-caret-square-o-down', paginationSwitchUp: 'fa-caret-square-o-up' };

function rowClass(row) {
    return { classes: row.classes };
}

/* row.tip is escaped HTML from the server; bootstrap-table escapes it again for the attribute */
function rowAttributes(row) {
    return { title: row.tip || '', 'data-id': row.tip_id || '' };
}

function cellStyleMessage() {
    return { classes: "field-message" };
}
