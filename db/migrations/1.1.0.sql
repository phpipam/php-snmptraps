-- php-snmptraps 1.1.0 migration
--
-- Two-factor authentication (TOTP) and passkeys (WebAuthn):
--   users.totp_secret      base32 TOTP secret, NULL = 2FA not set up
--   users.totp_last_step   last accepted TOTP time step (a code can be used only once)
--   users.passwordless     1 = password login disabled, the user signs in with a passkey only
--   users.webauthn_handle  random WebAuthn user handle (hex), created with the first passkey
--   passkeys               registered passkeys (credential id, public key, signature counter)
--     mysql -u <user> -p <db> < db/migrations/1.1.0.sql
-- or simply: php bin/migrate.php

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `totp_secret` varchar(64) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `totp_last_step` int(10) unsigned DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `passwordless` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `webauthn_handle` char(32) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `passkeys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `credential_id` varbinary(1023) NOT NULL,
  `public_key` text NOT NULL,
  `sign_count` int(10) unsigned NOT NULL DEFAULT 0,
  `aaguid` char(36) DEFAULT NULL,
  `synced` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(64) NOT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credential_id` (`credential_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
