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
-- Table structure for table `Files`
--

DROP TABLE IF EXISTS `Files`;
CREATE TABLE `Files` (
  `id` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `UserID` bigint(20) unsigned zerofill NOT NULL,
  `name` varchar(255) NOT NULL,
  `mime` varchar(255) NOT NULL,
  `@contents` longblob,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_UserID_name` (`UserID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `Logs`
--

DROP TABLE IF EXISTS `Logs`;
CREATE TABLE `Logs` (
  `ID` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `type` enum('Access','Information','Notice','Warning','Exception','Error','Debug') NOT NULL DEFAULT 'Information',
  `subject` char(255) NOT NULL DEFAULT '',
  `action` char(78) NOT NULL DEFAULT '',
  `@contents` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='This table should not have update actions performed upon.';

--
-- Table structure for table `Nodes`
--

DROP TABLE IF EXISTS `Nodes`;
CREATE TABLE `Nodes` (
  `ID` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `@collection` varchar(255) NOT NULL,
  `@contents` longtext NOT NULL,
  `timestamp` timestamp,
  PRIMARY KEY (`ID`),
  FULLTEXT KEY `content` (`@contents`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Data nodes';

--
-- Table structure for table `Processes`
--

DROP TABLE IF EXISTS `Processes`;
CREATE TABLE `Processes` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `command` longtext NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'system',
  `weight` tinyint(1) unsigned NOT NULL DEFAULT '100' COMMENT 'Process Prority',
  `capacity` tinyint(1) unsigned NOT NULL DEFAULT '5' COMMENT 'Process Reserved Capacity',
  `pid` int(11) DEFAULT NULL,
  `start_time` datetime NOT NULL COMMENT 'Scheduled Start Time',
  `@contents` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `ProcessSchedules`
--

DROP TABLE IF EXISTS `ProcessSchedules`;
CREATE TABLE `ProcessSchedules` (
  `name` varchar(255) NOT NULL DEFAULT '',
  `schedule` varchar(255) NOT NULL DEFAULT '* * * * *' COMMENT 'Cron expression',
  `command` longtext NOT NULL,
  `@contents` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `NodeRelations`
--

DROP TABLE IF EXISTS `NodeRelations`;
CREATE TABLE `NodeRelations` (
  `@collection` varchar(255) NOT NULL,
  `Subject` varchar(40) NOT NULL,
  `Object` varchar(40) NOT NULL,
  PRIMARY KEY (`@collection`,`Subject`,`Object`),
  KEY `key_collection_object` (`@collection`,`Object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `Sessions`
--

DROP TABLE IF EXISTS `Sessions`;
CREATE TABLE `Sessions` (
  `UserID` bigint(20) unsigned zerofill NOT NULL,
  `sid` varchar(40) NOT NULL,
  `token` varchar(40),
  `timestamp` timestamp,
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `sid` (`sid`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `Translations`
--

CREATE TABLE `Translations` (
  `identifier` varchar(32) NOT NULL DEFAULT '' COMMENT 'MD5 hash of target string',
  `key` varchar(255) NOT NULL DEFAULT 'default' COMMENT 'Version key of the same identifier',
  `value` text NOT NULL COMMENT 'Translation content',
  `locale` varchar(255) NOT NULL DEFAULT '' COMMENT 'Locale of target translation',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`identifier`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `Users`
--

DROP TABLE IF EXISTS `Users`;
CREATE TABLE `Users` (
  `ID` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `username` char(255) NOT NULL,
  `password` char(119) NOT NULL,
  `status` smallint(6) NOT NULL DEFAULT 2,
  `@contents` longtext NOT NULL,
  `timestamp` timestamp,
  PRIMARY KEY (`ID`),
  KEY `idx_credentials` (`username`,`password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
