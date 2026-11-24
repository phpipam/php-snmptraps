-- php-snmptraps 1.0.0 migration (from 0.3.x)
--
-- Run once against the snmptraps database:
--   mysql -u <user> -p <db> < db/migrations/1.0.0.sql
-- The traps ALTER is online (no write lock), new traps keep arriving while indexes build.

-- fix table name typo, drop unused table
RENAME TABLE `maintaneance` TO `maintenance`;
DROP TABLE IF EXISTS `settings`;

ALTER TABLE `maintenance`
  MODIFY `hostname` varchar(128) NOT NULL DEFAULT '',
  ADD KEY `hostname` (`hostname`,`start`,`stop`);

-- hostnames can be up to 128 chars (was 32)
UPDATE `exceptions` SET `hostname` = 'all' WHERE `hostname` IS NULL OR `hostname` = '';
ALTER TABLE `exceptions`
  MODIFY `hostname` varchar(128) NOT NULL DEFAULT 'all';

-- a definition has exactly one severity
ALTER TABLE `severity_definitions`
  MODIFY `severity` enum('emergency','alert','critical','error','warning','notice','informational','debug') NOT NULL;

-- id as primary key, unique username, wider columns, password_hash() support, drop unused column
UPDATE `users` SET `hostnames` = 'all' WHERE `hostnames` = '';
ALTER TABLE `users`
  DROP PRIMARY KEY,
  DROP KEY `id`,
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  MODIFY `username` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `auth_method` enum('local','ad') NOT NULL DEFAULT 'local',
  MODIFY `password` varchar(255) DEFAULT NULL,
  MODIFY `role` enum('user','operator','administrator') NOT NULL DEFAULT 'user',
  MODIFY `email` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  MODIFY `tel` varchar(32) DEFAULT NULL,
  MODIFY `reload_page` int(11) DEFAULT 900,
  MODIFY `hostnames` text NOT NULL DEFAULT 'all',
  DROP COLUMN `last_activity`;
UPDATE `users` SET `email` = NULL WHERE `email` = '';

-- IPv6 source addresses, indexes for date search, OID and message lookups
ALTER TABLE `traps`
  MODIFY `ip` varchar(45) DEFAULT NULL,
  ADD KEY `date` (`date`),
  ADD KEY `oid` (`oid`(64)),
  ADD KEY `message` (`message`(64)),
  ALGORITHM=INPLACE, LOCK=NONE;
