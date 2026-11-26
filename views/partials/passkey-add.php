<?php
/** Modal body: name a new passkey; the button starts the WebAuthn registration (public/js/passkey.js) */
?>
<form class="passkey-register-form" data-options="ajax/passkey-options/" data-register="ajax/passkey-register/" autocomplete="off">
    <p>A passkey signs you in with your fingerprint, face, device PIN or security key - without a password and without a code.
        It is stored on this device, in your password manager or on a security key.</p>
    <div class="mb-3">
        <label class="form-label required">Name</label>
        <input type="text" name="name" class="form-control" maxlength="64" placeholder="e.g. MacBook Touch ID, iPhone, YubiKey" required autofocus>
        <div class="form-hint">Helps you recognise the passkey in the list later.</div>
    </div>
    <div class="passkey-unsupported alert alert-warning d-none">This browser does not support passkeys.</div>
    <div class="passkey-error alert alert-danger d-none"></div>
    <div class="passkey-ok alert alert-success d-none">Passkey added</div>
    <button type="submit" class="btn btn-primary w-100"><i class="ti ti-fingerprint me-2"></i>Create passkey</button>
</form>
