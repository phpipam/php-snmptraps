/* @version 0.1.05 */

/* Add reloadpage to users */
ALTER TABLE `users` ADD `reload_page` INT(32)  NULL  DEFAULT '900'  AFTER `last_activity`;


/* @version 0.1.10 */