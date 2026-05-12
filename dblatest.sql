-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: localhost    Database: attendance_leave_tracker
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `absences`
--

DROP TABLE IF EXISTS `absences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `absences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `leave_date` date NOT NULL,
  `leave_type` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `admin_remarks` text,
  `archived` tinyint(1) NOT NULL DEFAULT '0',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `absences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `absences`
--

LOCK TABLES `absences` WRITE;
/*!40000 ALTER TABLE `absences` DISABLE KEYS */;
/*!40000 ALTER TABLE `absences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_data`
--

DROP TABLE IF EXISTS `leave_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_name` varchar(255) NOT NULL,
  `leave_type` varchar(100) NOT NULL,
  `leave_date` date NOT NULL,
  `is_archived` tinyint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee_name` (`employee_name`),
  KEY `idx_leave_date` (`leave_date`),
  KEY `idx_leave_type` (`leave_type`),
  KEY `idx_archived` (`is_archived`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_data`
--

LOCK TABLES `leave_data` WRITE;
/*!40000 ALTER TABLE `leave_data` DISABLE KEYS */;
INSERT INTO `leave_data` VALUES (1,'Blaine Jenner A. Bilalat','Vacation Leave','2025-01-15',0,'2026-05-12 15:48:19'),(2,'Blaine Jenner A. Bilalat','Vacation Leave','2025-03-13',0,'2026-05-12 15:48:19'),(3,'Blaine Jenner A. Bilalat','Vacation Leave','2025-03-18',0,'2026-05-12 15:48:19'),(4,'Blaine Jenner A. Bilalat','Vacation Leave','2025-04-29',0,'2026-05-12 15:48:19'),(5,'Blaine Jenner A. Bilalat','Vacation Leave','2025-04-30',0,'2026-05-12 15:48:19'),(6,'Diony F. Guillen','Vacation Leave','2025-02-09',0,'2026-05-12 15:48:19'),(7,'Diony F. Guillen','Vacation Leave','2025-02-10',0,'2026-05-12 15:48:19'),(8,'Diony F. Guillen','Vacation Leave','2025-02-11',0,'2026-05-12 15:48:19'),(9,'Diony F. Guillen','Vacation Leave','2025-02-12',0,'2026-05-12 15:48:19'),(10,'Diony F. Guillen','Vacation Leave','2025-02-13',0,'2026-05-12 15:48:19'),(11,'Diony F. Guillen','Vacation Leave','2025-06-11',0,'2026-05-12 15:48:19'),(12,'Diony F. Guillen','Vacation Leave','2025-06-15',0,'2026-05-12 15:48:19'),(13,'Diony F. Guillen','Vacation Leave','2025-06-16',0,'2026-05-12 15:48:19'),(14,'Esmeralda T. Acebedo','Vacation Leave','2025-02-23',0,'2026-05-12 15:48:19'),(15,'Esmeralda T. Acebedo','Vacation Leave','2025-02-24',0,'2026-05-12 15:48:19'),(16,'LA Angelo R. Luciano','Vacation Leave','2025-03-17',0,'2026-05-12 15:48:19'),(17,'LA Angelo R. Luciano','Vacation Leave','2025-03-18',0,'2026-05-12 15:48:19'),(18,'LA Angelo R. Luciano','Vacation Leave','2025-03-19',0,'2026-05-12 15:48:19'),(19,'Gabriel N. Felias','Vacation Leave','2025-03-19',0,'2026-05-12 15:48:19'),(20,'Kathleen Gail C. Maderaje','Vacation Leave','2025-03-13',0,'2026-05-12 15:48:19'),(21,'Kathleen Gail C. Maderaje','Vacation Leave','2025-03-16',0,'2026-05-12 15:48:19'),(22,'Blaine Jenner A. Bilalat','Sick Leave','2025-03-02',0,'2026-05-12 15:48:19'),(23,'Blaine Jenner A. Bilalat','Sick Leave','2025-03-11',0,'2026-05-12 15:48:19'),(24,'Blaine Jenner A. Bilalat','Sick Leave','2025-03-12',0,'2026-05-12 15:48:19'),(25,'Diony F. Guillen','Sick Leave','2025-01-05',0,'2026-05-12 15:48:19'),(26,'Diony F. Guillen','Sick Leave','2025-03-17',0,'2026-05-12 15:48:19'),(27,'LA Angelo R. Luciano','Sick Leave','2025-01-27',0,'2026-05-12 15:48:19'),(28,'LA Angelo R. Luciano','Sick Leave','2025-02-03',0,'2026-05-12 15:48:19'),(29,'LA Angelo R. Luciano','Sick Leave','2025-02-04',0,'2026-05-12 15:48:19'),(30,'LA Angelo R. Luciano','Sick Leave','2025-02-12',0,'2026-05-12 15:48:19'),(31,'LA Angelo R. Luciano','Sick Leave','2025-02-13',0,'2026-05-12 15:48:19'),(32,'LA Angelo R. Luciano','Sick Leave','2025-03-09',0,'2026-05-12 15:48:19'),(33,'LA Angelo R. Luciano','Sick Leave','2025-03-10',0,'2026-05-12 15:48:19'),(34,'LA Angelo R. Luciano','Sick Leave','2025-03-16',0,'2026-05-12 15:48:19'),(35,'LA Angelo R. Luciano','Sick Leave','2025-03-17',0,'2026-05-12 15:48:19'),(36,'LA Angelo R. Luciano','Sick Leave','2025-03-18',0,'2026-05-12 15:48:19'),(37,'LA Angelo R. Luciano','Sick Leave','2025-03-19',0,'2026-05-12 15:48:19'),(38,'LA Angelo R. Luciano','Sick Leave','2025-04-14',0,'2026-05-12 15:48:19'),(39,'LA Angelo R. Luciano','Sick Leave','2025-04-15',0,'2026-05-12 15:48:19'),(40,'LA Angelo R. Luciano','Sick Leave','2025-04-30',0,'2026-05-12 15:48:19'),(41,'Gabriel N. Felias','Sick Leave','2025-02-05',0,'2026-05-12 15:48:19'),(42,'Gabriel N. Felias','Sick Leave','2025-02-10',0,'2026-05-12 15:48:19'),(43,'Gabriel N. Felias','Sick Leave','2025-02-25',0,'2026-05-12 15:48:19'),(44,'Gabriel N. Felias','Sick Leave','2025-03-10',0,'2026-05-12 15:48:19'),(45,'Gabriel N. Felias','Sick Leave','2025-03-12',0,'2026-05-12 15:48:19'),(46,'Gabriel N. Felias','Sick Leave','2025-04-14',0,'2026-05-12 15:48:19'),(47,'Gabriel N. Felias','Sick Leave','2025-04-20',0,'2026-05-12 15:48:19'),(48,'Kristian D. Jocson','Sick Leave','2025-01-06',0,'2026-05-12 15:48:19'),(49,'Kristian D. Jocson','Sick Leave','2025-01-12',0,'2026-05-12 15:48:19'),(50,'Kristian D. Jocson','Sick Leave','2025-01-13',0,'2026-05-12 15:48:19'),(51,'Kristian D. Jocson','Sick Leave','2025-01-21',0,'2026-05-12 15:48:19'),(52,'Kristian D. Jocson','Sick Leave','2025-01-22',0,'2026-05-12 15:48:19'),(53,'Kristian D. Jocson','Sick Leave','2025-02-21',0,'2026-05-12 15:48:19'),(54,'Kristian D. Jocson','Sick Leave','2025-02-22',0,'2026-05-12 15:48:19'),(55,'Kristian D. Jocson','Sick Leave','2025-03-23',0,'2026-05-12 15:48:19'),(56,'Kristian D. Jocson','Sick Leave','2025-03-24',0,'2026-05-12 15:48:19'),(57,'Kristian D. Jocson','Sick Leave','2025-04-14',0,'2026-05-12 15:48:19'),(58,'Kristian D. Jocson','Sick Leave','2025-04-22',0,'2026-05-12 15:48:19'),(59,'Cesar Rey Templonuevo','Sick Leave','2025-04-16',0,'2026-05-12 15:48:19'),(60,'Cesar Rey Templonuevo','Sick Leave','2025-04-28',0,'2026-05-12 15:48:19'),(61,'Cesar Rey Templonuevo','Sick Leave','2025-05-11',0,'2026-05-12 15:48:19'),(62,'Kathleen Gail C. Maderaje','Sick Leave','2025-02-09',0,'2026-05-12 15:48:19'),(63,'Kathleen Gail C. Maderaje','Sick Leave','2025-03-17',0,'2026-05-12 15:48:19'),(64,'Kathleen Gail C. Maderaje','Sick Leave','2025-03-31',0,'2026-05-12 15:48:19'),(65,'Kathleen Gail C. Maderaje','Sick Leave','2025-04-27',0,'2026-05-12 15:48:19'),(66,'Kathleen Gail C. Maderaje','Sick Leave','2025-04-28',0,'2026-05-12 15:48:19'),(67,'Kathleen Gail C. Maderaje','Sick Leave','2025-05-06',0,'2026-05-12 15:48:19'),(68,'Kathleen Gail C. Maderaje','Sick Leave','2025-05-11',0,'2026-05-12 15:48:19'),(69,'Esmeralda T. Acebedo','Sick Leave','2025-03-17',0,'2026-05-12 15:48:19'),(70,'Blaine Jenner A. Bilalat','Special Privilege Leave','2025-01-19',0,'2026-05-12 15:48:19'),(71,'Blaine Jenner A. Bilalat','Special Privilege Leave','2025-03-11',0,'2026-05-12 15:48:19'),(72,'Blaine Jenner A. Bilalat','Special Privilege Leave','2025-03-12',0,'2026-05-12 15:48:19'),(73,'Diony F. Guillen','Special Privilege Leave','2025-01-06',0,'2026-05-12 15:48:19'),(74,'Diony F. Guillen','Special Privilege Leave','2025-04-29',0,'2026-05-12 15:48:19'),(75,'Esmeralda T. Acebedo','Special Privilege Leave','2025-01-05',0,'2026-05-12 15:48:19'),(76,'Esmeralda T. Acebedo','Special Privilege Leave','2025-01-06',0,'2026-05-12 15:48:19'),(77,'Esmeralda T. Acebedo','Special Privilege Leave','2025-01-07',0,'2026-05-12 15:48:19'),(78,'LA Angelo R. Luciano','Special Privilege Leave','2025-03-05',0,'2026-05-12 15:48:19'),(79,'LA Angelo R. Luciano','Special Privilege Leave','2025-03-06',0,'2026-05-12 15:48:19'),(80,'Gabriel N. Felias','Special Privilege Leave','2025-01-05',0,'2026-05-12 15:48:19'),(81,'Gabriel N. Felias','Special Privilege Leave','2025-01-20',0,'2026-05-12 15:48:19'),(82,'Gabriel N. Felias','Special Privilege Leave','2025-02-16',0,'2026-05-12 15:48:19'),(83,'Eris D. Magpantay','Special Privilege Leave','2025-02-16',0,'2026-05-12 15:48:19'),(84,'Eris D. Magpantay','Special Privilege Leave','2025-04-29',0,'2026-05-12 15:48:19'),(85,'Kristian D. Jocson','Special Privilege Leave','2025-05-19',0,'2026-05-12 15:48:19'),(86,'Kristian D. Jocson','Special Privilege Leave','2025-05-26',0,'2026-05-12 15:48:19'),(87,'Cesar Rey Templonuevo','Special Privilege Leave','2025-03-16',0,'2026-05-12 15:48:19'),(88,'Kathleen Gail C. Maderaje','Special Privilege Leave','2025-01-12',0,'2026-05-12 15:48:19'),(89,'Kathleen Gail C. Maderaje','Special Privilege Leave','2025-01-13',0,'2026-05-12 15:48:19'),(90,'Kathleen Gail C. Maderaje','Special Privilege Leave','2025-02-04',0,'2026-05-12 15:48:19'),(91,'Blaine Jenner A. Bilalat','Wellness Leave','2025-02-16',0,'2026-05-12 15:48:19'),(92,'Blaine Jenner A. Bilalat','Wellness Leave','2025-05-04',0,'2026-05-12 15:48:19'),(93,'Diony F. Guillen','Wellness Leave','2025-06-08',0,'2026-05-12 15:48:19'),(94,'Diony F. Guillen','Wellness Leave','2025-06-09',0,'2026-05-12 15:48:19'),(95,'Diony F. Guillen','Wellness Leave','2025-06-10',0,'2026-05-12 15:48:19'),(96,'Esmeralda T. Acebedo','Wellness Leave','2025-03-09',0,'2026-05-12 15:48:19'),(97,'Esmeralda T. Acebedo','Wellness Leave','2025-03-25',0,'2026-05-12 15:48:19'),(98,'Esmeralda T. Acebedo','Wellness Leave','2025-05-22',0,'2026-05-12 15:48:19'),(99,'Esmeralda T. Acebedo','Wellness Leave','2025-05-25',0,'2026-05-12 15:48:19'),(100,'Esmeralda T. Acebedo','Wellness Leave','2025-05-26',0,'2026-05-12 15:48:19'),(101,'LA Angelo R. Luciano','Wellness Leave','2025-03-11',0,'2026-05-12 15:48:19'),(102,'LA Angelo R. Luciano','Wellness Leave','2025-03-12',0,'2026-05-12 15:48:19'),(103,'LA Angelo R. Luciano','Wellness Leave','2025-03-13',0,'2026-05-12 15:48:19'),(104,'LA Angelo R. Luciano','Wellness Leave','2025-04-13',0,'2026-05-12 15:48:19'),(105,'Eris D. Magpantay','Wellness Leave','2025-04-27',0,'2026-05-12 15:48:19'),(106,'Eris D. Magpantay','Wellness Leave','2025-04-28',0,'2026-05-12 15:48:19'),(107,'Cesar Rey Templonuevo','Wellness Leave','2025-03-30',0,'2026-05-12 15:48:19'),(108,'Cesar Rey Templonuevo','Wellness Leave','2025-03-31',0,'2026-05-12 15:48:19'),(109,'Cesar Rey Templonuevo','Wellness Leave','2025-04-30',0,'2026-05-12 15:48:19'),(110,'Kathleen Gail C. Maderaje','Wellness Leave','2025-02-16',0,'2026-05-12 15:48:19'),(111,'Kathleen Gail C. Maderaje','Wellness Leave','2025-02-25',0,'2026-05-12 15:48:19'),(112,'Kathleen Gail C. Maderaje','Wellness Leave','2025-03-09',0,'2026-05-12 15:48:19'),(113,'Kathleen Gail C. Maderaje','Wellness Leave','2025-03-19',0,'2026-05-12 15:48:19'),(114,'Kathleen Gail C. Maderaje','Wellness Leave','2025-04-22',0,'2026-05-12 15:48:19');
/*!40000 ALTER TABLE `leave_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_types`
--

LOCK TABLES `leave_types` WRITE;
/*!40000 ALTER TABLE `leave_types` DISABLE KEYS */;
INSERT INTO `leave_types` VALUES (1,'Vacation Leave',1,'2026-05-12 15:15:28'),(2,'Sick Leave',1,'2026-05-12 15:15:28'),(3,'Special Privilege Leave',1,'2026-05-12 15:15:28'),(4,'Wellness Leave',1,'2026-05-12 15:15:28');
/*!40000 ALTER TABLE `leave_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `contact_number` varchar(15) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `role` varchar(20) NOT NULL DEFAULT 'employee',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'COA','Admin','User','admin@gmail.com',NULL,'$2y$10$uelPDzDrmGgHD.pPSda6S.tgkdM2Bl33kOFuw9M.UEYFHM07MWQie','2026-05-08 13:03:10','admin'),(4,'Blaine Jenner','A.','Bilalat','blainejennerabilalat@gmail.com',NULL,'$2y$10$placeholderHashChangeMe1234567890123456789012345678901','2026-05-12 14:46:37','employee'),(5,'Diony','F.','Guillen','dionyfguillen@gmail.com',NULL,'$2y$10$placeholderHashChangeMe1234567890123456789012345678901','2026-05-12 14:46:37','employee'),(6,'Esmeralda','T.','Acebedo','esmeraldatacebedo@gmail.com',NULL,'$2y$10$placeholderHashChangeMe1234567890123456789012345678901','2026-05-12 14:46:37','employee'),(7,'LA Angelo','R.','Luciano','laangelorluciano@gmail.com',NULL,'$2y$10$placeholderHashChangeMe1234567890123456789012345678901','2026-05-12 14:46:37','employee'),(8,'Gabriel','N.','Felias','gabrielnfelias@gmail.com',NULL,'$2y$10$placeholderHashChangeMe1234567890123456789012345678901','2026-05-12 14:46:37','employee'),(9,'Eris','D.','Magpantay','erisdmagpantay@gmail.com',NULL,'$2y$10$placeholderHashChangeMe1234567890123456789012345678901','2026-05-12 14:46:37','employee'),(10,'Kristian','D.','Jocson','kristiandjocson@gmail.com',NULL,'$2y$10$placeholderHashChangeMe1234567890123456789012345678901','2026-05-12 14:46:37','employee'),(11,'Cesar Rey','','Templonuevo','cesarreytemplonuevo@gmail.com',NULL,'$2y$10$placeholderHashChangeMe1234567890123456789012345678901','2026-05-12 14:46:37','employee'),(12,'Kathleen Gail','C.','Maderaje','kathleengailcmaderaj@gmail.com',NULL,'$2y$10$placeholderHashChangeMe1234567890123456789012345678901','2026-05-12 14:46:37','employee');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'attendance_leave_tracker'
--

--
-- Dumping routines for database 'attendance_leave_tracker'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-13  0:14:12
