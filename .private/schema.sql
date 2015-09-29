SET NAMES utf8;

--
-- Table structure for table `Configurations`
--

DROP TABLE IF EXISTS `Configurations`;
CREATE TABLE `Configurations` (
  `@key` varchar(255) NOT NULL DEFAULT '',
  `@contents` longtext NOT NULL,
  PRIMARY KEY (`@key`),
  FULLTEXT KEY `@contents` (`@contents`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `Logs`
--

DROP TABLE IF EXISTS `Logs`;
CREATE TABLE `Logs` (
  `id` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `type` enum('Debug','Info','Notice','Warning','Error','Critical','Alert','Emergency') NOT NULL DEFAULT 'Info',
  `subject` char(255) NOT NULL DEFAULT '',
  `action` char(78) NOT NULL DEFAULT '',
  `@contents` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='This table should not have update actions performed upon.';

--
-- Table structure for table `Nodes`
--

DROP TABLE IF EXISTS `Nodes`;
CREATE TABLE `Nodes` (
  `id` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `@collection` varchar(255) NOT NULL,
  `@contents` longtext NOT NULL,
  `timestamp` timestamp,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `content` (`@contents`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Data nodes';

--
-- Table structure for table `NodeRelations`
--

DROP TABLE IF EXISTS `NodeRelations`;
CREATE TABLE `NodeRelations` (
  `@collection` varchar(255) NOT NULL,
  `parent` varchar(40) NOT NULL,
  `child` varchar(40) NOT NULL,
  PRIMARY KEY (`@collection`,`parent`,`child`),
  KEY `key_collection_object` (`@collection`,`child`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `Processes`
--

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
  `timestamp` timestamp,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `Sessions`
--

DROP TABLE IF EXISTS `Sessions`;
CREATE TABLE `Sessions` (
  `sid` binary(16) NOT NULL COMMENT 'UUID',
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Username',
  `token` varchar(40) CHARACTER SET utf8 DEFAULT NULL,
  `fingerprint` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`sid`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `Translations`
--

DROP TABLE IF EXISTS `Translations`;
CREATE TABLE `Translations` (
  `identifier` varchar(32) NOT NULL DEFAULT '' COMMENT 'MD5 hash of target string',
  `key` varchar(255) NOT NULL DEFAULT 'default' COMMENT 'Version key of the same identifier',
  `value` text NOT NULL COMMENT 'Translation content',
  `locale` varchar(255) NOT NULL DEFAULT '' COMMENT 'Locale of target translation',
  `timestamp` timestamp,
  PRIMARY KEY (`identifier`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Note: Model tables
-- Do not use plural when naming model tables, because data model class makes
-- more sense in singular. On Earth we have no reliable way to convert from
-- singular to plural in any language, let's keep it singular before we go to
-- Mars.

--
-- Table structure for table `User`
--

DROP TABLE IF EXISTS `User`;
CREATE TABLE `User` (
  `id` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `username` char(255) NOT NULL,
  `password` char(119) NOT NULL,
  `status` smallint(6) NOT NULL DEFAULT 2,
  `@contents` longtext NOT NULL,
  `timestamp` timestamp,
  PRIMARY KEY (`id`),
  KEY `idx_credentials` (`username`,`password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
