/* @version 0.1.10 */

-- add reloadpage to users
ALTER TABLE `users` ADD `reload_page` INT(32)  NULL  DEFAULT '900';
-- add operator user
ALTER TABLE `users` CHANGE `role` `role` SET('user','operator','administrator') NOT NULL  DEFAULT 'user';
-- add hostnames to filter permitted hosts
ALTER TABLE `users` ADD `hostnames` text COLLATE utf8_bin NOT NULL;

