-- php-snmptraps 1.0.2 migration
--
-- Repeated traps: traps.repeats counts identical traps (hostname, OID, content) that arrived
-- each less than $repeat_window_minutes after the previous one; the same lookup skips
-- notifications within $notification_dedup_minutes (see config.dist.php).
--   mysql -u <user> -p <db> < db/migrations/1.0.2.sql
-- Apply BEFORE deploying the 1.0.2 code (the receiver writes the new column).

ALTER TABLE `traps`
  ADD COLUMN `repeats` int(10) unsigned NOT NULL DEFAULT 0,
  ALGORITHM=INSTANT;

-- lookup index for the repeat check; replaces the hostname index (its leftmost prefix)
ALTER TABLE `traps`
  ADD KEY `host_oid_date` (`hostname`,`oid`,`date`),
  DROP KEY `hostname`,
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
