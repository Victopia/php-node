SET NAMES utf8;

--
-- Table structure for table `Configuration`
--

DROP TABLE IF EXISTS `Configuration`;

CREATE TABLE `Configuration` (
  `@key` varchar(255) NOT NULL DEFAULT '',
  `@contents` longtext NOT NULL,
  PRIMARY KEY (`@key`),
  FULLTEXT KEY `@contents` (`@contents`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `File`
--

DROP TABLE IF EXISTS `File`;

CREATE TABLE `File` (
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
-- Table structure for table `Log`
--

DROP TABLE IF EXISTS `Log`;

CREATE TABLE `Log` (
  `ID` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `type` enum('Access','Information','Notice','Warning','Exception','Error','Debug') NOT NULL DEFAULT 'Information',
  `subject` char(255) NOT NULL DEFAULT '',
  `action` char(78) NOT NULL DEFAULT '',
  `@contents` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `Log_INDEX` (`subject`,`action`),
  FULLTEXT KEY `Log_FULLTEXT` (`@contents`)
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
-- Table structure for table `ProcessQueue`
--

DROP TABLE IF EXISTS `ProcessQueue`;

CREATE TABLE `ProcessQueue` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `path` text NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `pid` int
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `Relation`
--

DROP TABLE IF EXISTS `Relation`;

CREATE TABLE `Relation` (
  `@collection` varchar(255) NOT NULL,
  `Subject` varchar(40) NOT NULL,
  `Object` varchar(40) NOT NULL,
  PRIMARY KEY (`@collection`,`Subject`,`Object`),
  KEY `key_collection_object` (`@collection`,`Object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `Session`
--

DROP TABLE IF EXISTS `Session`;

CREATE TABLE `Session` (
  `UserID` bigint(20) unsigned zerofill NOT NULL,
  `sid` varchar(40) NOT NULL,
  `token` varchar(40),
  `timestamp` timestamp,
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `sid` (`sid`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `User
--

DROP TABLE IF EXISTS `User`;

CREATE TABLE `User` (
  `ID` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `username` char(255) NOT NULL,
  `password` char(119) NOT NULL,
  `status` smallint(6) NOT NULL DEFAULT 2,
  `@contents` longtext NOT NULL,
  `timestamp` timestamp,
  PRIMARY KEY (`ID`),
  KEY `idx_credentials` (`username`,`password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Table structure for table `UserData`
--

DROP TABLE IF EXISTS `UserData`;

CREATE TABLE `UserData` (
  `UserID` bigint(20) unsigned zerofill NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` varchar(16383) NOT NULL DEFAULT '',
  PRIMARY KEY (`UserID`,`name`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dump completed on 2012-11-23 13:59:28