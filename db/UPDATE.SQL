/* @version 0.1.10 */

-- add reloadpage to users
ALTER TABLE `users` ADD `reload_page` INT(32)  NULL  DEFAULT '900';
-- add operator user
ALTER TABLE `users` CHANGE `role` `role` SET('user','operator','administrator') NOT NULL  DEFAULT 'user';
-- add hostnames to filter permitted hosts
ALTER TABLE `users` ADD `hostnames` text COLLATE utf8_bin NOT NULL;


/* @version 0.2.1 */
ALTER TABLE `traps` ADD INDEX `severity` (`severity`);
ALTER TABLE `traps` ADD INDEX `hostname` (`hostname`);


/* @version 0.3.0 */
ALTER TABLE `users` ADD `dash_layout` VARCHAR(128)  NULL  DEFAULT '6:30;6:30;12:15;12:15';

/* @version 1.0.0 - see db/migrations/1.0.0.sql */

RENAME TABLE `maintaneance` TO `maintenance`;
DROP TABLE IF EXISTS `settings`;

ALTER TABLE `maintenance`
  MODIFY `hostname` varchar(128) NOT NULL DEFAULT '',
  ADD KEY `hostname` (`hostname`,`start`,`stop`);

UPDATE `exceptions` SET `hostname` = 'all' WHERE `hostname` IS NULL OR `hostname` = '';
ALTER TABLE `exceptions`
  MODIFY `hostname` varchar(128) NOT NULL DEFAULT 'all';

ALTER TABLE `severity_definitions`
  MODIFY `severity` enum('emergency','alert','critical','error','warning','notice','informational','debug') NOT NULL;

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

ALTER TABLE `traps`
  MODIFY `ip` varchar(45) DEFAULT NULL,
  ADD KEY `date` (`date`),
  ADD KEY `oid` (`oid`(64)),
  ADD KEY `message` (`message`(64)),
  ALGORITHM=INPLACE, LOCK=NONE;

/* @version 1.0.1 - see db/migrations/1.0.1.sql */
ALTER TABLE `users`
  ALTER `dash_layout` SET DEFAULT '6:20;6:20;12:15;12:15';
UPDATE `users` SET `dash_layout` = REGEXP_REPLACE(`dash_layout`, ':([2-9][1-9]|[3-9]0|[0-9]{3,})(;|$)', ':20\\2');

/* @version 1.0.2 - see db/migrations/1.0.2.sql (traps.repeats + host_oid_date index) */
ALTER TABLE `traps`
  ADD COLUMN IF NOT EXISTS `repeats` int(10) unsigned NOT NULL DEFAULT 0,
  ALGORITHM=INSTANT;

-- lookup index for the repeat check; replaces the hostname index (its leftmost prefix)
ALTER TABLE `traps`
  ADD KEY IF NOT EXISTS `host_oid_date` (`hostname`,`oid`,`date`),
  DROP KEY IF EXISTS `hostname`,
  ALGORITHM=INPLACE, LOCK=NONE;

-- optional one-time backfill of the counter for stored traps (600 s = default 10 min window)
UPDATE `traps` t
JOIN (
  SELECT `id`, ROW_NUMBER() OVER (PARTITION BY `hostname`, `oid`, `h`, `chain` ORDER BY `date`, `id`) - 1 AS `rep`
  FROM (
    SELECT `id`, `hostname`, `oid`, `date`, `h`,
           SUM(`brk`) OVER (PARTITION BY `hostname`, `oid`, `h` ORDER BY `date`, `id`) AS `chain`
    FROM (
      SELECT `id`, `hostname`, `oid`, `date`, MD5(IFNULL(`content`, '')) AS `h`,
             IF(TIMESTAMPDIFF(SECOND,
                  LAG(`date`) OVER (PARTITION BY `hostname`, `oid`, MD5(IFNULL(`content`, '')) ORDER BY `date`, `id`),
                  `date`) < 600, 0, 1) AS `brk`
      FROM `traps`
    ) a
  ) b
) r ON r.`id` = t.`id`
SET t.`repeats` = r.`rep`
WHERE r.`rep` > 0;

/* @version 1.0.3 - see db/migrations/1.0.3.sql, then run: php bin/rebuild-repeats.php */
ALTER TABLE `traps`
  ADD COLUMN IF NOT EXISTS `fingerprint` char(32) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `first_date` timestamp NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `superseded` tinyint(1) NOT NULL DEFAULT 0,
  ALGORITHM=INSTANT;

-- repeat lookup by fingerprint; host_oid_date (1.0.2) is replaced by the plain hostname index
ALTER TABLE `traps`
  ADD KEY IF NOT EXISTS `fingerprint` (`fingerprint`,`date`),
  ADD KEY IF NOT EXISTS `hostname` (`hostname`),
  DROP KEY IF EXISTS `host_oid_date`,
  ALGORITHM=INPLACE, LOCK=NONE;
