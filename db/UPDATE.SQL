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