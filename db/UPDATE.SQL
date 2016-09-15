/* @version 0.1.10 */

-- Add reloadpage to users
ALTER TABLE `users` ADD `reload_page` INT(32)  NULL  DEFAULT '900'  AFTER `last_activity`;
-- add operator user
ALTER TABLE `users` CHANGE `role` `role` SET('user','operator','administrator') NOT NULL  DEFAULT 'user';
