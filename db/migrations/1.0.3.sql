-- php-snmptraps 1.0.3 migration
--
-- Repeated traps are compared by a fingerprint (hostname, OID and content with timestamp
-- values such as clogHistTimestamp blanked) and collapsed in the UI: only the latest trap
-- of a repeat chain is listed (older ones get superseded = 1), first_date is the date of
-- the first trap of the chain.
--   mysql -u <user> -p <db> < db/migrations/1.0.3.sql
--   php bin/rebuild-repeats.php        (fills the new columns for stored traps)
-- or simply: php bin/migrate.php (applies pending migrations and runs the rebuild)

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
