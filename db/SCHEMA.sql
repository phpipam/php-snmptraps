# Dump of table exceptions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `exceptions`;

CREATE TABLE `exceptions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `oid` varchar(128) NOT NULL DEFAULT '',
  `hostname` varchar(32) DEFAULT NULL,
  `content` varchar(128) DEFAULT NULL,
  `comment` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `oid` (`oid`,`content`,`hostname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


# Dump of table maintaneance
# ------------------------------------------------------------

DROP TABLE IF EXISTS `maintaneance`;

CREATE TABLE `maintaneance` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `hostname` varchar(64) NOT NULL DEFAULT '',
  `start` datetime NOT NULL,
  `stop` datetime NOT NULL,
  `comment` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table severity_definitions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `severity_definitions`;

CREATE TABLE `severity_definitions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `oid` varchar(128) NOT NULL DEFAULT '',
  `severity` set('emergency','alert','critical','error','warning','notice','informational','debug') NOT NULL DEFAULT '',
  `content` varchar(128) DEFAULT '',
  `comment` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `oid` (`oid`,`content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `severity_definitions` WRITE;
/*!40000 ALTER TABLE `severity_definitions` DISABLE KEYS */;

INSERT INTO `severity_definitions` (`id`, `oid`, `severity`, `content`, `comment`)
VALUES
	(1,'CISCO-IPSEC-FLOW-MONITOR-MIB::cipSecTunnelStop','notice',NULL,'IPSec tunnel stopped'),
	(2,'CISCO-IPSEC-FLOW-MONITOR-MIB::cipSecTunnelStart','notice',NULL,'IPSec tunnel started'),
	(3,'CISCO-IPSEC-FLOW-MONITOR-MIB::cikeTunnelStart','notice',NULL,'IKE tunnel started'),
	(4,'CISCO-IPSEC-FLOW-MONITOR-MIB::cikeTunnelStop','notice',NULL,'IKE tunnel stopped'),
	(5,'IF-MIB::linkUp','warning',NULL,'Link goes up'),
	(6,'IF-MIB::linkDown','warning',NULL,'Link goes down'),
	(7,'OSPF-TRAP-MIB::ospfTraps','critical',NULL,'All OSPF traps'),
	(8,'SNMPv2-MIB::warmStart','emergency',NULL,NULL),
	(9,'SNMPv2-MIB::coldStart','emergency',NULL,NULL),
	(10,'BGP4-MIB::bgp.0','critical',NULL,'BGP traps'),
	(11,'BRIDGE-MIB::topologyChange','critical',NULL,'STP topology change'),
	(12,'CISCO-CONFIG-MAN-MIB::ciscoConfigManEvent','debug',NULL,'Config change'),
	(14,'CISCOTRAP-MIB::tcpConnectionClose','notice',NULL,NULL),
	(15,'SNMPv2-MIB::authenticationFailure','error',NULL,'Authentication failure event'),
	(16,'CISCO-SMI::ciscoMgmt','error','ifName',NULL),
	(17,'DISMAN-EVENT-MIB::mteTriggerFired','critical',NULL,'Trigger fired'),
	(18,'CISCO-IF-EXTENSION-MIB::cieLinkDown','alert',NULL,'Link went down'),
	(19,'CISCO-IF-EXTENSION-MIB::cieLinkUp','alert',NULL,'Link came up'),
	(22,'JUNIPER-JS-SMI::jnxJsChassisCluster','critical',NULL,NULL),
	(23,'OSPF-TRAP-MIB::ospfNbrStateChange','alert',NULL,NULL),
	(24,'OSPF-TRAP-MIB::ospfIfAuthFailure','alert',NULL,'OSPF authentication failure'),
	(25,'BRIDGE-MIB::newRoot','critical',NULL,NULL),
	(26,'ENTITY-MIB::entConfigChange','informational',NULL,'config change'),
	(27,'BGP4-MIB::bgpEstablished','warning',NULL,NULL),
	(32,'BGP4-MIB::bgpBackwardTransition','alert','bgpBackwardTransition',NULL),
	(33,'JUNIPER-CFGMGMT-MIB::jnxCmCfgChange','informational','jnxCmCfgChange','Juniper config changed'),
	(35,'JUNIPER-MIB::jnxFruPowerOn','emergency','',NULL),
	(36,'JUNIPER-MIB::jnxFruInsertion','warning','jnxFruInsertion',NULL),
	(37,'JUNIPER-MIB::jnxFruPowerOff','error','jnxFruPowerOff',NULL),
	(38,'JUNIPER-MIB::jnxFruOnline','warning','jnxFruOnline',NULL),
	(39,'IPV6-MIB::ipv6IfStateChange','warning','','IPv6 state change'),
	(40,'CISCO-VTP-MIB::vtpVlanCreated','warning','',NULL),
	(41,'JUNIPER-CHASSIS-CLUSTER-MIB::jnxJsChassisClusterNotifications.4','warning','',NULL),
	(42,'JUNIPER-SP-MIB::jnxSpSvcSetCpuOk','warning','jnxSpSvcSetCpuOk','CPU returned to normal'),
	(43,'JUNIPER-CHASSIS-CLUSTER-MIB::jnxJsChassisClusterSwitchover','emergency','jnxJsChassisClusterSwitchover','Chasis switchover occured'),
	(44,'JUNIPER-SP-MIB::jnxSpSvcSetCpuExceeded','emergency','jnxSpSvcSetCpuExceeded','CPU threshold exceeded'),
	(45,'JUNIPER-CHASSIS-CLUSTER-MIB::jnxJsChassisClusterNotifications.5','emergency','jnxJsChassisClusterNotifications.5','Cluster state change'),
	(46,'JUNIPER-DOM-MIB::jnxDomAlarmSet','warning','jnxDomAlarmSet','Digital optical monitoring alarm set'),
	(47,'JUNIPER-DOM-MIB::jnxDomAlarmCleared','notice','jnxDomAlarmCleared','Digital optical monitoring alarm cleared'),
	(49,'BGP4-MIB::bgpEstablished','emergency','bgpEstablished','This alert will trigger when BGP session goes to established'),
	(54,'MPLS-L3VPN-STD-MIB::mplsL3VpnVrfUp','alert','','VRF came up'),
	(55,'CISCO-PAE-MIB::cpaeAuthFailVlanNotif','debug','cpaeAuthFailVlanNotif','Auth failed'),
	(56,'CISCO-PAE-MIB::cpaeGuestVlanNotif','debug','cpaeGuestVlanNotif',NULL),
	(57,'CISCO-VTP-MIB::vlanTrunkPortDynamicStatusChange','notice','vlanTrunkPortDynamicStatusChange',NULL),
	(58,'BRIDGE-MIB::topologyChange','warning','topologyChange',NULL),
	(59,'DISMAN-EVENT-MIB::mteTriggerFired','emergency','mteTriggerFired',NULL);

/*!40000 ALTER TABLE `severity_definitions` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table traps
# ------------------------------------------------------------

DROP TABLE IF EXISTS `traps`;

CREATE TABLE `traps` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `hostname` varchar(32) DEFAULT NULL,
  `ip` varchar(15) DEFAULT NULL,
  `oid` varchar(128) DEFAULT NULL,
  `date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `message` varchar(256) DEFAULT NULL,
  `severity` varchar(32) DEFAULT 'unknown',
  `content` text,
  `raw` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(25) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `real_name` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `auth_method` set('local','ad', 'krb') COLLATE utf8_bin NOT NULL DEFAULT 'local',
  `password` char(128) COLLATE utf8_bin DEFAULT NULL,
  `role` set('user','operator','administrator') CHARACTER SET utf8 NOT NULL DEFAULT 'user',
  `email` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `tel` varchar(15) COLLATE utf8_bin DEFAULT NULL,
  `notification_types` varchar(128) COLLATE utf8_bin DEFAULT 'mail',
  `notification_severities` varchar(128) COLLATE utf8_bin DEFAULT 'emergency;alert;critical;unknown',
  `quiet_time_start` time DEFAULT '00:00:00',
  `quiet_time_stop` time DEFAULT '00:00:00',
  `last_login` timestamp NULL DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  `reload_page` INT(32)  NULL  DEFAULT '900',
  `hostnames` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`),
  KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;

INSERT INTO `users` (`id`, `username`, `real_name`, `auth_method`, `password`, `role`, `email`, `tel`, `notification_types`, `notification_severities`, `quiet_time_start`, `quiet_time_stop`, `last_login`, `last_activity`, `hostnames`)
VALUES
  (1,'Admin','Admin user',X'6C6F63616C',X'243624726F756E64733D333030302447546749466E354973506559553254332448775446756E736F566864424930684C3250507871546641444E576F76716358547130624F4A4C58705330426865714A58692E4830592E7A4532363454304A444E2F50426E46597067677044504F6A4F63434B2E7731','administrator','ni@sploh.si',X'',X'6D61696C',X'656D657267656E63793B616C6572743B637269746963616C3B756E6B6E6F776E','00:00:00','00:00:00',NULL,NULL,'');

/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
