-- MySQL dump 10.13  Distrib 5.7.43, for Linux (x86_64)
--
-- Host: localhost    Database: gd
-- ------------------------------------------------------
-- Server version	5.7.43-log

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
-- Table structure for table `ticket_history`
--

DROP TABLE IF EXISTS `ticket_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ticket_history_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_history`
--

LOCK TABLES `ticket_history` WRITE;
/*!40000 ALTER TABLE `ticket_history` DISABLE KEYS */;
INSERT INTO `ticket_history` VALUES (1,4,2,'completed','工单已被标记为完成','2024-11-21 05:39:34'),(2,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 05:39:40'),(3,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 05:57:19'),(4,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 06:11:08'),(5,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 06:17:05'),(6,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 06:26:00'),(7,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 06:26:15'),(8,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 06:26:31'),(9,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 08:43:50'),(10,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 09:11:25'),(11,1,2,'released','工单已被员工退回至待接收状态','2024-11-21 09:28:04'),(12,2,3,'completed','工单已被标记为完成','2024-11-22 01:24:46'),(13,6,2,'released','工单已被员工退回至待接收状态','2024-11-22 01:47:29'),(14,6,2,'released','工单已被员工退回至待接收状态','2024-11-22 01:47:56'),(15,6,3,'released','工单已被员工退回至待接收状态','2024-11-22 01:48:12'),(16,5,2,'released','工单已被员工退回至待接收状态','2024-11-22 02:11:34'),(17,6,2,'released','工单已被员工退回至待接收状态','2024-11-22 02:16:46'),(18,1,2,'released','工单已被员工退回至待接收状态','2024-11-22 02:21:06'),(19,5,2,'released','工单已被员工退回至待接收状态','2024-11-22 02:53:48');
/*!40000 ALTER TABLE `ticket_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_projects`
--

DROP TABLE IF EXISTS `ticket_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sales_person` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `project_date` date NOT NULL,
  `location` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `ticket_projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_projects`
--

LOCK TABLES `ticket_projects` WRITE;
/*!40000 ALTER TABLE `ticket_projects` DISABLE KEYS */;
INSERT INTO `ticket_projects` VALUES (1,'测试','新项目','测试','类型A',1000,'2024-11-21','测试区域','测试',1,'2024-11-21 03:04:16','2024-11-22 00:52:52'),(2,'南京','新项目','测试','类型A',200,'2024-11-21','测试区域','测试',1,'2024-11-21 04:21:52','2024-11-21 04:21:52'),(3,'1','新项目','1','类型A',1,'2024-11-21','1','1',1,'2024-11-21 04:56:22','2024-11-21 04:56:22'),(4,'南京','新项目','测试','类型B',1000,'2024-11-16','测试区域','1111',1,'2024-11-21 04:56:57','2024-11-21 04:56:57'),(5,'测试','新项目','测网速','类型A',100,'2024-11-08','11','',1,'2024-11-21 05:00:32','2024-11-21 05:00:32'),(6,'11','科拓','11','进出口',11,'2024-11-22','测试区域','111',1,'2024-11-22 01:17:30','2024-11-22 01:17:30'),(8,'11','科拓','测试','进出口',100,'2024-11-22','测试区域','11',1,'2024-11-22 01:20:52','2024-11-22 01:59:46'),(9,'111','科拓','1','进出口',111,'2024-11-23','测试区域','1',1,'2024-11-22 01:30:06','2024-11-22 01:30:06');
/*!40000 ALTER TABLE `ticket_projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','in_progress','completed','closed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `created_by` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_open` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `created_by` (`created_by`),
  KEY `assigned_to` (`assigned_to`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `ticket_projects` (`id`),
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES (1,1,'测试工单 - 测试','这是一个测试的测试工单','pending','medium',1,NULL,NULL,'2024-11-22 02:16:44',NULL,'2024-11-21 04:39:19','2024-11-22 02:51:10',0),(2,2,'测试工单 - 南京','这是一个南京的测试工单','completed','medium',1,3,3,NULL,'2024-11-21 17:24:46','2024-11-21 04:39:19','2024-11-22 01:24:46',0),(4,5,'测试','','completed','medium',1,2,2,NULL,'2024-11-20 21:39:34','2024-11-21 05:00:32','2024-11-21 05:39:34',0),(5,8,'11',NULL,'pending','medium',1,NULL,NULL,'2024-11-22 02:16:43',NULL,'2024-11-22 01:20:52','2024-11-22 02:53:48',1),(6,9,'111',NULL,'in_progress','medium',1,2,2,'2024-11-22 02:53:43',NULL,'2024-11-22 01:30:06','2024-11-22 02:53:43',1);
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','employee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'employee',
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$/JoumdNzIgnHt6kro6hMp.uUp.tQVs.8S80mXF5ZoHopm5dKA6rFy','admin@example.com','admin','approved','2024-11-21 02:33:33','2024-11-21 06:04:10'),(2,'张三','$2y$10$WF9u2K3IGUJBm/ySLNiTzuh39FmjLxJlNkfJbaRQfowcfWxHeA5s2','vip.weizhen@gmail.com','employee','approved','2024-11-21 02:45:03','2024-11-21 05:58:33'),(3,'123123','$2y$10$EfJq/K3VBZx36Fwc8e3fpuS6qRDG2bpccB7fYF8xDZJJ8LPI3Vvou','123123@123.com','employee','approved','2024-11-21 05:22:34','2024-11-21 05:22:43'),(4,'456','$2y$10$bJoSVMKKbBeyUfriXXy6UOGWWWyCQOgRFE69Fl.PdxnZIk7yT1j2S','456@456.com','employee','pending','2024-11-22 02:20:04','2024-11-22 02:20:04');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-11-22 11:02:46
