/**
 * php-snmptraps passkeys (WebAuthn) - used by the login page and the Security page.
 * Plain JS (the login page has no jQuery). Binary values are exchanged with the server as base64url.
 *
 *   .passkey-supported     shown when the browser supports passkeys
 *   .passkey-unsupported   shown when it does not
 *   .passkey-login         button: sign in (data-url = login/passkey/)
 *   .passkey-register-form form in the "Add a passkey" modal (data-options, data-register)
 */
(function () {
    'use strict';

    var supported = !!(window.PublicKeyCredential && navigator.credentials && window.isSecureContext);

    function b64urlToBuf(s) {
        s = s.replace(/-/g, '+').replace(/_/g, '/');
        while (s.length % 4) { s += '='; }
        var bin = atob(s), buf = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) { buf[i] = bin.charCodeAt(i); }
        return buf.buffer;
    }
    function bufToB64url(buf) {
        if (!buf) { return null; }
        var bytes = new Uint8Array(buf), bin = '';
        for (var i = 0; i < bytes.length; i++) { bin += String.fromCharCode(bytes[i]); }
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    function request(url, body) {
        var opts = { credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf(), 'Accept': 'application/json' } };
        if (body !== undefined) {
            opts.method = 'POST';
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(url, opts).then(function (r) {
            return r.text().then(function (t) {
                var data;
                try { data = JSON.parse(t); } catch (e) { throw new Error('Unexpected server response (HTTP ' + r.status + ')'); }
                if (data && data.error) { throw new Error(data.error); }
                return data;
            });
        });
    }
    /* readable messages for the usual browser errors */
    function message(err) {
        if (err && err.name === 'NotAllowedError') { return 'The passkey request was cancelled or timed out.'; }
        if (err && err.name === 'InvalidStateError') { return 'This passkey is already registered for your account.'; }
        if (err && err.name === 'SecurityError') { return 'The browser refused the request for this site (host name / HTTPS).'; }
        return (err && err.message) || String(err);
    }
    function show(el, text) {
        if (!el) { return; }
        el.textContent = text;
        el.classList.toggle('d-none', !text);
    }

    function decodeCreate(o) {
        var pk = o.publicKey;
        pk.challenge = b64urlToBuf(pk.challenge);
        pk.user.id = b64urlToBuf(pk.user.id);
        (pk.excludeCredentials || []).forEach(function (c) { c.id = b64urlToBuf(c.id); });
        return o;
    }
    function decodeGet(o) {
        var pk = o.publicKey;
        pk.challenge = b64urlToBuf(pk.challenge);
        (pk.allowCredentials || []).forEach(function (c) { c.id = b64urlToBuf(c.id); });
        return o;
    }

    /* ---- sign in ---- */
    function login(btn) {
        var box = btn.closest('.card-body') || document;
        var err = box.querySelector('.passkey-error');
        show(err, '');
        btn.disabled = true;
        request(btn.getAttribute('data-url'))
            .then(function (opts) { return navigator.credentials.get(decodeGet(opts)); })
            .then(function (cred) {
                return request(btn.getAttribute('data-url'), {
                    id: bufToB64url(cred.rawId),
                    response: {
                        clientDataJSON: bufToB64url(cred.response.clientDataJSON),
                        authenticatorData: bufToB64url(cred.response.authenticatorData),
                        signature: bufToB64url(cred.response.signature),
                        userHandle: bufToB64url(cred.response.userHandle)
                    }
                });
            })
            .then(function (res) { window.location.href = res.redirect || './'; })
            .catch(function (e) { show(err, message(e)); btn.disabled = false; });
    }

    /* ---- register (Security page modal) ---- */
    function register(form) {
        var btn = form.querySelector('button[type="submit"]');
        var err = form.querySelector('.passkey-error');
        var name = form.querySelector('[name="name"]').value.trim();
        show(err, '');
        if (!name) { show(err, 'Please enter a name.'); return; }
        btn.disabled = true;
        request(form.getAttribute('data-options'))
            .then(function (opts) { return navigator.credentials.create(decodeCreate(opts)); })
            .then(function (cred) {
                return request(form.getAttribute('data-register'), {
                    name: name,
                    credential: {
                        id: bufToB64url(cred.rawId),
                        response: {
                            clientDataJSON: bufToB64url(cred.response.clientDataJSON),
                            attestationObject: bufToB64url(cred.response.attestationObject)
                        }
                    }
                });
            })
            .then(function () {
                form.querySelector('.passkey-ok').classList.remove('d-none');
                setTimeout(function () { window.location.reload(); }, 1000);
            })
            .catch(function (e) { show(err, message(e)); btn.disabled = false; });
    }

    function init(root) {
        Array.prototype.forEach.call(root.querySelectorAll('.passkey-supported'), function (el) { el.classList.toggle('d-none', !supported); });
        Array.prototype.forEach.call(root.querySelectorAll('.passkey-unsupported'), function (el) { el.classList.toggle('d-none', supported); });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.passkey-login');
        if (btn) { e.preventDefault(); login(btn); }
    });
    document.addEventListener('submit', function (e) {
        var form = e.target.closest && e.target.closest('.passkey-register-form');
        if (form) { e.preventDefault(); if (supported) { register(form); } }
    });
    /* app.js calls it for modal content loaded later */
    window.passkeyInit = init;
    init(document);
})();
