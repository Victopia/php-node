-- MySQL dump 10.13  Distrib 5.5.21, for osx10.6 (i386)
--
-- Host: localhost    Database: cometolist
-- ------------------------------------------------------
-- Server version	5.5.21

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Configuration`
--

DROP TABLE IF EXISTS `Configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Configuration` (
  `@key` varchar(255) NOT NULL DEFAULT '',
  `@contents` longtext NOT NULL,
  PRIMARY KEY (`@key`),
  FULLTEXT KEY `@contents` (`@contents`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `File`
--

DROP TABLE IF EXISTS `File`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `File` (
  `id` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `UserID` bigint(20) unsigned zerofill NOT NULL,
  `name` varchar(255) NOT NULL,
  `mime` varchar(255) NOT NULL,
  `@contents` longblob,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_UserID_name` (`UserID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Log`
--

DROP TABLE IF EXISTS `Log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Log` (
  `ID` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `type` enum('Access','Information','Notice','Warning','Exception','Error','Debug') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Information',
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `action` varchar(78) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `@contents` longtext COLLATE utf8_unicode_ci NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `Log_INDEX` (`subject`,`action`),
  FULLTEXT KEY `Log_FULLTEXT` (`@contents`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='This table should not have update actions performed upon.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Message`
--

DROP TABLE IF EXISTS `Message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Message` (
  `APP_ID` bigint(20) unsigned NOT NULL DEFAULT '0',
  `FNC_ID` bigint(20) unsigned NOT NULL,
  `MSG_ID` bigint(20) unsigned NOT NULL DEFAULT '0',
  `contents` longtext NOT NULL,
  `locale` varchar(20) NOT NULL,
  PRIMARY KEY (`APP_ID`,`FNC_ID`,`MSG_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Nodes`
--

DROP TABLE IF EXISTS `Nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Nodes` (
  `ID` bigint(20) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `@collection` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `@contents` longtext COLLATE utf8_unicode_ci NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  FULLTEXT KEY `content` (`@contents`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Data nodes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Processes`
--

DROP TABLE IF EXISTS `Processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Processes` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `path` text NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Relation`
--

DROP TABLE IF EXISTS `Relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Relation` (
  `@collection` varchar(255) NOT NULL,
  `Subject` varchar(40) NOT NULL,
  `Object` varchar(40) NOT NULL,
  PRIMARY KEY (`@collection`,`Subject`,`Object`),
  KEY `key_collection_object` (`@collection`,`Object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Session`
--

DROP TABLE IF EXISTS `Session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Session` (
  `UserID` bigint(20) unsigned zerofill NOT NULL,
  `sid` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `token` varchar(40) COLLATE utf8_unicode_ci DEFAULT '',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `sid` (`sid`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `UserData`
--

DROP TABLE IF EXISTS `UserData`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `UserData` (
  `UserID` bigint(20) unsigned zerofill NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` varchar(16383) NOT NULL DEFAULT '',
  PRIMARY KEY (`UserID`,`name`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Dump completed on 2012-11-23 13:59:28