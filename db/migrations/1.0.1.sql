-- php-snmptraps 1.0.1 migration
--
-- Dashboard widgets show at most 20 traps per severity group.
--   mysql -u <user> -p <db> < db/migrations/1.0.1.sql

ALTER TABLE `users`
  ALTER `dash_layout` SET DEFAULT '6:20;6:20;12:15;12:15';
UPDATE `users` SET `dash_layout` = REGEXP_REPLACE(`dash_layout`, ':([2-9][1-9]|[3-9]0|[0-9]{3,})(;|$)', ':20\\2');
