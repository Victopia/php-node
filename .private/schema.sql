/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table Configurations
# ------------------------------------------------------------

DROP TABLE IF EXISTS `Configurations`;

CREATE TABLE `Configurations` (
  `@key` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `@contents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`@key`),
  FULLTEXT KEY `@contents` (`@contents`)
) ENGINE=MyISAM;



# Dump of table Logs
# ------------------------------------------------------------

DROP TABLE IF EXISTS `Logs`;

CREATE TABLE `Logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('Debug','Info','Notice','Warning','Error','Critical','Alert','Emergency') NOT NULL DEFAULT 'Info',
  `subject` char(255) NOT NULL,
  `action` char(78) NOT NULL,
  `@contents` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table should not have update actions performed upon.';



# Dump of table NodeRelations
# ------------------------------------------------------------

DROP TABLE IF EXISTS `NodeRelations`;

CREATE TABLE `NodeRelations` (
  `@collection` varchar(255) NOT NULL,
  `parent` varchar(40) NOT NULL,
  `child` varchar(40) NOT NULL,
  PRIMARY KEY (`@collection`,`parent`,`child`),
  KEY `key_collection_object` (`@collection`,`child`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;



# Dump of table Nodes
# ------------------------------------------------------------

DROP TABLE IF EXISTS `Nodes`;

CREATE TABLE `Nodes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `@collection` varchar(255) NOT NULL,
  `@contents` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `content` (`@contents`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data nodes';



# Dump of table Processes
# ------------------------------------------------------------

DROP TABLE IF EXISTS `Processes`;

CREATE TABLE `Processes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `command` longtext NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'system',
  `weight` tinyint(1) unsigned NOT NULL DEFAULT '100' COMMENT 'Process Prority',
  `capacity` tinyint(1) unsigned NOT NULL DEFAULT '5' COMMENT 'Process Reserved Capacity',
  `pid` int(11) DEFAULT NULL,
  `start_time` datetime NOT NULL COMMENT 'Scheduled Start Time',
  `@contents` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table Sessions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `Sessions`;

CREATE TABLE `Sessions` (
  `sid` binary(16) NOT NULL COMMENT 'UUID',
  `username` varchar(255) NOT NULL,
  `token` varchar(40) DEFAULT NULL,
  `fingerprint` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`sid`)
) ENGINE=MEMORY DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;



# Dump of table Translations
# ------------------------------------------------------------

DROP TABLE IF EXISTS `Translations`;

CREATE TABLE `Translations` (
  `identifier` varchar(32) NOT NULL COMMENT 'MD5 hash of target string',
  `key` varchar(255) NOT NULL DEFAULT 'default' COMMENT 'Version key of the same identifier',
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Translation content',
  `locale` varchar(255) NOT NULL COMMENT 'Locale of target translation',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`identifier`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;



# Dump of table User
# ------------------------------------------------------------

DROP TABLE IF EXISTS `User`;

CREATE TABLE `User` (
  `uuid` binary(16) NOT NULL,
  `username` char(255) NOT NULL,
  `password` char(119) NOT NULL,
  `@contents` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `username` (`username`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
