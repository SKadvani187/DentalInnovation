-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: dentinno_crm
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `shipping_methods`
--

DROP TABLE IF EXISTS `shipping_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipping_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('flat','free','product','weight','price','flexible','quantity') DEFAULT 'flat',
  `base_cost` decimal(10,2) DEFAULT 0.00,
  `min_weight_kg` decimal(10,3) DEFAULT NULL,
  `max_weight_kg` decimal(10,3) DEFAULT NULL,
  `min_order_value` decimal(12,2) DEFAULT NULL,
  `max_order_value` decimal(12,2) DEFAULT NULL,
  `delivery_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipping_methods`
--

LOCK TABLES `shipping_methods` WRITE;
/*!40000 ALTER TABLE `shipping_methods` DISABLE KEYS */;
INSERT INTO `shipping_methods` VALUES (4,'Heavy Equipment Freight','Flat freight — assigned per-product to heavy machines','flat',600.00,NULL,NULL,NULL,NULL,NULL,0,4,'2026-06-11 05:15:53','2026-06-11 05:15:53'),(5,'Weight-Based Freight','Charged by package weight — assigned per-product','weight',0.00,NULL,NULL,NULL,NULL,NULL,0,5,'2026-06-11 05:15:53','2026-06-11 05:15:53'),(6,'Product-Class Handling','Surcharge/free by product shipping class','product',0.00,NULL,NULL,NULL,NULL,NULL,0,6,'2026-06-11 05:15:53','2026-06-11 05:15:53'),(7,'Bulk Order Free Shipping','Free shipping when ordering 10+ units','flat',0.00,NULL,NULL,NULL,NULL,NULL,0,7,'2026-06-11 05:15:53','2026-09-05 06:50:57');
/*!40000 ALTER TABLE `shipping_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipping_zones`
--

DROP TABLE IF EXISTS `shipping_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipping_zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `states` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`states`)),
  `pincodes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pincodes`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipping_zones`
--

LOCK TABLES `shipping_zones` WRITE;
/*!40000 ALTER TABLE `shipping_zones` DISABLE KEYS */;
INSERT INTO `shipping_zones` VALUES (8,'Gujarat','[\"Gujarat\"]','[\"36\",\"37\",\"38\",\"39\"]',1,'2026-09-05 06:40:40'),(9,'Andhra Pradesh','[\"Andhra Pradesh\"]','[\"50\",\"51\",\"52\",\"53\"]',1,'2026-09-06 16:31:08'),(10,'Assam','[\"Assam\"]','[\"78\"]',1,'2026-09-06 16:43:15'),(11,'Bihar','[\"Bihar\"]','[\"80\",\"81\",\"82\",\"83\",\"84\",\"85\"]',1,'2026-09-06 16:48:13'),(12,'Chattisagrh','[\"Chattisgarh\"]','[\"49\"]',1,'2026-09-06 17:02:24'),(13,'Delhi','[\"Delhi\"]','[\"11\"]',1,'2026-09-06 17:04:49'),(14,'Haryana','[\"Haryana\"]','[\"12\",\"13\"]',1,'2026-09-06 17:27:45'),(15,'Himachal Pradesh','[\"Himachal Pradesh\"]','[\"17\"]',1,'2026-09-06 17:30:17'),(16,'Jammu & Kashmir','[\"Jammu & Kashmir\"]','[\"18\",\"19\"]',1,'2026-09-06 17:34:11'),(17,'Jharkhand','[\"Jharkhand\"]','[\"81\",\"82\",\"83\"]',1,'2026-09-06 17:38:36'),(18,'Karnataka','[\"Karnataka\"]','[\"56\",\"57\",\"58\",\"59\"]',1,'2026-09-06 17:50:31'),(19,'Kerala','[\"Kerala\"]','[\"67\",\"68\",\"69\"]',1,'2026-09-06 17:53:55'),(20,'Madhya Pradesh','[\"Madhya Pradesh\"]','[\"45\",\"46\",\"47\",\"48\"]',1,'2026-09-06 18:01:52'),(21,'Maharashtra','[\"Maharashtra\"]','[\"40\",\"41\",\"42\",\"43\",\"44\"]',1,'2026-09-06 18:04:30'),(22,'North East','[\"North East\"]','[\"79\"]',1,'2026-09-06 18:15:57'),(23,'Odisha','[\"Odisha\"]','[\"74\",\"75\",\"76\",\"77\"]',1,'2026-09-06 18:26:27'),(24,'Punjab','[\"Punjab\"]','[\"14\",\"15\",\"16\"]',1,'2026-09-06 18:35:59'),(25,'Rajasthan','[\"Rajasthan\"]','[\"30\",\"31\",\"32\",\"33\",\"34\"]',1,'2026-09-06 18:40:31'),(26,'Tamilnadu','[\"Tamilnadu\"]','[\"60\",\"61\",\"62\",\"63\",\"64\"]',1,'2026-09-06 18:43:23'),(27,'Telangana','[\"Telangana\"]','[\"50\"]',1,'2026-09-06 18:46:05'),(28,'Uttar  Pradesh','[\"Uttar  Pradesh\"]','[\"20\",\"21\",\"22\",\"23\",\"24\",\"25\",\"26\",\"27\",\"28\"]',1,'2026-09-06 18:48:51');
/*!40000 ALTER TABLE `shipping_zones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipping_rules`
--

DROP TABLE IF EXISTS `shipping_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipping_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `method_id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `rule_type` enum('weight','price','quantity','product') NOT NULL,
  `min_value` decimal(12,2) DEFAULT 0.00,
  `max_value` decimal(12,2) DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL,
  `is_free` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `product_class` enum('standard','bulky','fragile','express_only','free') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `method_id` (`method_id`),
  KEY `zone_id` (`zone_id`),
  CONSTRAINT `shipping_rules_ibfk_1` FOREIGN KEY (`method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipping_rules_ibfk_2` FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipping_rules`
--

LOCK TABLES `shipping_rules` WRITE;
/*!40000 ALTER TABLE `shipping_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipping_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_pincodes`
--

DROP TABLE IF EXISTS `delivery_pincodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_pincodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pincode_prefix` varchar(6) NOT NULL,
  `label` varchar(120) DEFAULT NULL,
  `delivery_days` int(11) NOT NULL DEFAULT 5,
  `cod_available` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_prefix` (`pincode_prefix`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_pincodes`
--

LOCK TABLES `delivery_pincodes` WRITE;
/*!40000 ALTER TABLE `delivery_pincodes` DISABLE KEYS */;
INSERT INTO `delivery_pincodes` VALUES (15,'37','Gujarat',1,0,1,0,'2026-09-06 16:14:16'),(16,'36','Gujarat',1,1,1,0,'2026-09-06 16:27:17'),(17,'38','Gujarat',1,0,1,0,'2026-09-06 16:27:31'),(18,'39','Gujarat',1,0,1,0,'2026-09-06 16:27:49'),(19,'50','Telangana',7,0,1,0,'2026-09-06 16:31:52'),(20,'51','Andhra Pradesh',7,0,1,0,'2026-09-06 16:32:09'),(21,'52','Andhra Pradesh',7,0,1,0,'2026-09-06 16:32:31'),(22,'53','Andhra Pradesh',7,0,1,0,'2026-09-06 16:33:44'),(23,'78','Assam',7,0,1,0,'2026-09-06 16:43:41'),(24,'80','Bihar',7,0,1,0,'2026-09-06 16:48:33'),(25,'81','Bihar',7,0,1,0,'2026-09-06 16:48:47'),(26,'82','Bihar',7,0,1,0,'2026-09-06 16:49:08'),(27,'83','Bihar',7,0,1,0,'2026-09-06 16:49:23'),(28,'84','Bihar',7,0,1,0,'2026-09-06 16:49:42'),(29,'85','Bihar',7,0,1,0,'2026-09-06 16:50:01'),(30,'49','Chattisgarh',7,0,1,0,'2026-09-06 17:02:55'),(31,'11','Delhi',3,0,1,0,'2026-09-06 17:05:05'),(32,'12','Haryana',7,0,1,0,'2026-09-06 17:28:42'),(33,'13','Haryana',7,0,1,0,'2026-09-06 17:29:03'),(34,'17','Himachal Pradesh',7,0,1,0,'2026-09-06 17:30:45'),(35,'18','Jammu & Kashmir',7,0,1,0,'2026-09-06 17:34:37'),(36,'19','Jammu & Kashmir',7,0,1,0,'2026-09-06 17:34:55'),(37,'56','Karnataka',5,0,1,0,'2026-09-06 17:51:15'),(38,'57','Karnataka',5,0,1,0,'2026-09-06 17:51:32'),(39,'58','Karnataka',5,0,1,0,'2026-09-06 17:51:47'),(40,'59','Karnataka',5,0,1,0,'2026-09-06 17:52:01'),(41,'67','Kerala',7,0,1,0,'2026-09-06 17:54:50'),(42,'68','Kerala',7,0,1,0,'2026-09-06 17:55:29'),(43,'69','Kerala',7,0,1,0,'2026-09-06 17:55:52'),(44,'45','Madhya Pradesh',5,0,1,0,'2026-09-06 18:02:05'),(45,'46','Madhya Pradesh',5,0,1,0,'2026-09-06 18:02:23'),(46,'47','Madhya Pradesh',5,0,1,0,'2026-09-06 18:02:35'),(47,'48','Madhya Pradesh',5,0,1,0,'2026-09-06 18:02:50'),(48,'40','Maharashtra',3,0,1,0,'2026-09-06 18:05:00'),(49,'41','Maharashtra',3,0,1,0,'2026-09-06 18:05:33'),(50,'42','Maharashtra',3,0,1,0,'2026-09-06 18:05:48'),(51,'43','Maharashtra',3,0,1,0,'2026-09-06 18:06:09'),(52,'44','Maharashtra',3,0,1,0,'2026-09-06 18:06:22'),(53,'79','North East',7,0,1,0,'2026-09-06 18:16:09'),(54,'74','Odisha',7,0,1,0,'2026-09-06 18:26:47'),(55,'75','Odisha',7,0,1,0,'2026-09-06 18:27:02'),(56,'76','Odisha',7,0,1,0,'2026-09-06 18:27:16'),(57,'77','Odisha',7,0,1,0,'2026-09-06 18:27:33'),(58,'14','Punjab',5,0,1,0,'2026-09-06 18:36:12'),(59,'15','Punjab',5,0,1,0,'2026-09-06 18:36:30'),(60,'16','Punjab',5,0,1,0,'2026-09-06 18:36:57'),(61,'30','Rajasthan',3,0,1,0,'2026-09-06 18:40:51'),(62,'31','Rajasthan',3,0,1,0,'2026-09-06 18:41:04'),(63,'32','Rajasthan',3,0,1,0,'2026-09-06 18:41:20'),(64,'33','Rajasthan',3,0,1,0,'2026-09-06 18:41:33'),(65,'34','Rajasthan',3,0,1,0,'2026-09-06 18:41:48'),(66,'60','Tamilnadu',5,0,1,0,'2026-09-06 18:43:42'),(67,'61','Tamilnadu',5,0,1,0,'2026-09-06 18:43:56'),(68,'62','Tamilnadu',5,0,1,0,'2026-09-06 18:44:18'),(69,'63','Tamilnadu',5,0,1,0,'2026-09-06 18:44:36'),(70,'64','Tamilnadu',5,0,1,0,'2026-09-06 18:44:55'),(72,'20','Uttar  Pradesh',7,0,1,0,'2026-09-06 18:49:04'),(73,'21','Uttar  Pradesh',7,0,1,0,'2026-09-06 18:49:19'),(74,'22','Uttar  Pradesh',7,0,1,0,'2026-09-06 18:49:34'),(75,'23','Uttar  Pradesh',7,0,1,0,'2026-09-06 18:49:48'),(76,'24','Uttar  Pradesh',7,0,1,0,'2026-09-06 18:50:01'),(77,'25','Uttar  Pradesh',7,0,1,0,'2026-09-06 18:50:14'),(78,'26','Uttar  Pradesh',7,0,1,0,'2026-09-06 18:50:28'),(79,'27','Uttar  Pradesh',7,0,1,0,'2026-09-06 18:50:41'),(80,'28','Uttar  Pradesh',7,0,1,0,'2026-09-06 18:50:54');
/*!40000 ALTER TABLE `delivery_pincodes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-09 16:27:34
