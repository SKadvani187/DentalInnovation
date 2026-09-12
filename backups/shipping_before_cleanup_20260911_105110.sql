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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipping_methods`
--

LOCK TABLES `shipping_methods` WRITE;
/*!40000 ALTER TABLE `shipping_methods` DISABLE KEYS */;
INSERT INTO `shipping_methods` VALUES (1,'Standard Delivery','National baseline — applies everywhere by order value','price',0.00,NULL,NULL,NULL,NULL,NULL,0,1,'2026-06-11 05:15:53','2026-09-05 06:50:27'),(2,'Local Gujarat Delivery','Cheaper rate for Gujarat (zone 1)','price',0.00,NULL,NULL,NULL,NULL,NULL,0,2,'2026-06-11 05:15:53','2026-09-05 06:50:39'),(3,'Metro Express Zone','Faster/cheaper free threshold for metro pincodes','price',0.00,NULL,NULL,NULL,NULL,NULL,0,3,'2026-06-11 05:15:53','2026-09-05 06:50:51'),(4,'Heavy Equipment Freight','Flat freight — assigned per-product to heavy machines','flat',600.00,NULL,NULL,NULL,NULL,NULL,0,4,'2026-06-11 05:15:53','2026-06-11 05:15:53'),(5,'Weight-Based Freight','Charged by package weight — assigned per-product','weight',0.00,NULL,NULL,NULL,NULL,NULL,0,5,'2026-06-11 05:15:53','2026-06-11 05:15:53'),(6,'Product-Class Handling','Surcharge/free by product shipping class','product',0.00,NULL,NULL,NULL,NULL,NULL,0,6,'2026-06-11 05:15:53','2026-06-11 05:15:53'),(7,'Bulk Order Free Shipping','Free shipping when ordering 10+ units','flat',0.00,NULL,NULL,NULL,NULL,NULL,0,7,'2026-06-11 05:15:53','2026-09-05 06:50:57'),(8,'Standard Delivery','Delivered by our regular courier service','flat',99.00,NULL,NULL,NULL,NULL,NULL,0,0,'2026-06-20 20:40:34','2026-09-11 04:48:02'),(9,'Express Delivery','Delivery in 1-2 business days','flat',299.00,NULL,NULL,NULL,NULL,NULL,0,0,'2026-06-20 20:40:34','2026-09-05 06:49:21'),(10,'Free Shipping','Free on orders above ₹5000','price',0.00,NULL,NULL,NULL,NULL,NULL,0,0,'2026-06-20 20:40:34','2026-09-05 06:49:26'),(11,'Standard Delivery','Delivered by our regular courier service','weight',150.00,NULL,NULL,NULL,NULL,NULL,0,0,'2026-06-20 20:40:34','2026-09-11 05:11:35'),(12,'Product-Specific','Per product shipping charge','product',0.00,NULL,NULL,NULL,NULL,NULL,0,0,'2026-06-20 20:40:34','2026-09-05 06:50:24'),(14,'Express Delivery Ahmedabad','','flat',80.00,NULL,2.000,NULL,NULL,1,0,0,'2026-09-05 07:29:14','2026-09-11 05:11:35'),(15,'Standard Delivery','Delivered by our regular courier service','weight',0.00,NULL,NULL,NULL,NULL,NULL,1,1,'2026-09-11 05:11:35','2026-09-11 05:11:35'),(16,'Express Delivery','Priority dispatch - arrives next day','weight',0.00,NULL,5.000,NULL,NULL,1,1,2,'2026-09-11 05:11:35','2026-09-11 05:11:35'),(17,'Free Delivery','Free on orders above Rs.2,500','price',0.00,NULL,NULL,NULL,NULL,NULL,1,0,'2026-09-11 05:11:35','2026-09-11 05:11:35');
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
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipping_zones`
--

LOCK TABLES `shipping_zones` WRITE;
/*!40000 ALTER TABLE `shipping_zones` DISABLE KEYS */;
INSERT INTO `shipping_zones` VALUES (8,'Gujarat','[\"Gujarat\"]','[\"36\",\"37\",\"38\",\"39\"]',0,'2026-09-05 06:40:40'),(9,'Andhra Pradesh','[\"Andhra Pradesh\"]','[\"50\",\"51\",\"52\",\"53\"]',0,'2026-09-06 16:31:08'),(10,'Assam','[\"Assam\"]','[\"78\"]',0,'2026-09-06 16:43:15'),(11,'Bihar','[\"Bihar\"]','[\"80\",\"81\",\"82\",\"83\",\"84\",\"85\"]',0,'2026-09-06 16:48:13'),(12,'Chattisagrh','[\"Chattisgarh\"]','[\"49\"]',0,'2026-09-06 17:02:24'),(13,'Delhi','[\"Delhi\"]','[\"11\"]',0,'2026-09-06 17:04:49'),(14,'Haryana','[\"Haryana\"]','[\"12\",\"13\"]',0,'2026-09-06 17:27:45'),(15,'Himachal Pradesh','[\"Himachal Pradesh\"]','[\"17\"]',0,'2026-09-06 17:30:17'),(16,'Jammu & Kashmir','[\"Jammu & Kashmir\"]','[\"18\",\"19\"]',0,'2026-09-06 17:34:11'),(17,'Jharkhand','[\"Jharkhand\"]','[\"81\",\"82\",\"83\"]',0,'2026-09-06 17:38:36'),(18,'Karnataka','[\"Karnataka\"]','[\"56\",\"57\",\"58\",\"59\"]',0,'2026-09-06 17:50:31'),(19,'Kerala','[\"Kerala\"]','[\"67\",\"68\",\"69\"]',0,'2026-09-06 17:53:55'),(20,'Madhya Pradesh','[\"Madhya Pradesh\"]','[\"45\",\"46\",\"47\",\"48\"]',0,'2026-09-06 18:01:52'),(21,'Maharashtra','[\"Maharashtra\"]','[\"40\",\"41\",\"42\",\"43\",\"44\"]',0,'2026-09-06 18:04:30'),(22,'North East','[\"North East\"]','[\"79\"]',0,'2026-09-06 18:15:57'),(23,'Odisha','[\"Odisha\"]','[\"74\",\"75\",\"76\",\"77\"]',0,'2026-09-06 18:26:27'),(24,'Punjab','[\"Punjab\"]','[\"14\",\"15\",\"16\"]',0,'2026-09-06 18:35:59'),(25,'Rajasthan','[\"Rajasthan\"]','[\"30\",\"31\",\"32\",\"33\",\"34\"]',0,'2026-09-06 18:40:31'),(26,'Tamilnadu','[\"Tamilnadu\"]','[\"60\",\"61\",\"62\",\"63\",\"64\"]',0,'2026-09-06 18:43:23'),(27,'Telangana','[\"Telangana\"]','[\"50\"]',0,'2026-09-06 18:46:05'),(28,'Uttar  Pradesh','[\"Uttar  Pradesh\"]','[\"20\",\"21\",\"22\",\"23\",\"24\",\"25\",\"26\",\"27\",\"28\"]',0,'2026-09-06 18:48:51'),(29,'Z1 Gujarat','[]','[\"36\",\"37\",\"38\",\"39\"]',1,'2026-09-11 05:11:35'),(30,'Z2 West & Central','[]','[\"30\",\"31\",\"32\",\"33\",\"34\",\"40\",\"41\",\"42\",\"43\",\"44\",\"45\",\"46\",\"47\",\"48\",\"49\"]',1,'2026-09-11 05:11:35'),(31,'Z3 North & South','[]','[\"11\",\"12\",\"13\",\"14\",\"15\",\"16\",\"20\",\"21\",\"22\",\"23\",\"24\",\"25\",\"26\",\"27\",\"28\",\"50\",\"51\",\"52\",\"53\",\"56\",\"57\",\"58\",\"59\",\"60\",\"61\",\"62\",\"63\",\"64\",\"67\",\"68\",\"69\"]',1,'2026-09-11 05:11:35'),(32,'Z4 East','[]','[\"17\",\"70\",\"71\",\"72\",\"73\",\"74\",\"75\",\"76\",\"77\",\"80\",\"81\",\"82\",\"83\",\"84\",\"85\"]',1,'2026-09-11 05:11:35'),(33,'Z5 Remote','[]','[\"18\",\"19\",\"78\",\"79\",\"744\",\"682\"]',1,'2026-09-11 05:11:35');
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
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipping_rules`
--

LOCK TABLES `shipping_rules` WRITE;
/*!40000 ALTER TABLE `shipping_rules` DISABLE KEYS */;
INSERT INTO `shipping_rules` VALUES (30,11,NULL,'weight',0.00,0.99,150.00,0,0,'2026-09-05 06:43:39',NULL),(31,11,NULL,'weight',1.00,1.99,300.00,0,0,'2026-09-05 08:13:14',NULL),(32,11,NULL,'weight',4.99,7.00,700.00,0,0,'2026-09-05 08:14:38',NULL),(34,8,NULL,'weight',2.00,4.98,500.00,0,0,'2026-09-08 17:31:11',NULL),(35,8,NULL,'weight',9.00,9.99,1200.00,0,0,'2026-09-08 17:33:14',NULL),(36,8,NULL,'weight',10.00,20.00,1800.00,0,0,'2026-09-08 17:33:45',NULL),(37,11,NULL,'weight',2.00,4.98,500.00,0,0,'2026-09-11 04:28:59',NULL),(38,11,NULL,'weight',7.01,NULL,1000.00,0,0,'2026-09-11 04:31:07',NULL),(39,15,29,'weight',0.00,0.50,49.00,0,1,'2026-09-11 05:11:35',NULL),(40,15,29,'weight',0.50,1.00,69.00,0,1,'2026-09-11 05:11:35',NULL),(41,15,29,'weight',1.00,2.00,99.00,0,1,'2026-09-11 05:11:35',NULL),(42,15,29,'weight',2.00,5.00,179.00,0,1,'2026-09-11 05:11:35',NULL),(43,15,29,'weight',5.00,10.00,299.00,0,1,'2026-09-11 05:11:35',NULL),(44,15,29,'weight',10.00,NULL,449.00,0,1,'2026-09-11 05:11:35',NULL),(45,15,30,'weight',0.00,0.50,69.00,0,1,'2026-09-11 05:11:35',NULL),(46,15,30,'weight',0.50,1.00,99.00,0,1,'2026-09-11 05:11:35',NULL),(47,15,30,'weight',1.00,2.00,149.00,0,1,'2026-09-11 05:11:35',NULL),(48,15,30,'weight',2.00,5.00,269.00,0,1,'2026-09-11 05:11:35',NULL),(49,15,30,'weight',5.00,10.00,449.00,0,1,'2026-09-11 05:11:35',NULL),(50,15,30,'weight',10.00,NULL,699.00,0,1,'2026-09-11 05:11:35',NULL),(51,15,31,'weight',0.00,0.50,89.00,0,1,'2026-09-11 05:11:35',NULL),(52,15,31,'weight',0.50,1.00,129.00,0,1,'2026-09-11 05:11:35',NULL),(53,15,31,'weight',1.00,2.00,199.00,0,1,'2026-09-11 05:11:35',NULL),(54,15,31,'weight',2.00,5.00,359.00,0,1,'2026-09-11 05:11:35',NULL),(55,15,31,'weight',5.00,10.00,599.00,0,1,'2026-09-11 05:11:35',NULL),(56,15,31,'weight',10.00,NULL,899.00,0,1,'2026-09-11 05:11:35',NULL),(57,15,32,'weight',0.00,0.50,99.00,0,1,'2026-09-11 05:11:35',NULL),(58,15,32,'weight',0.50,1.00,149.00,0,1,'2026-09-11 05:11:35',NULL),(59,15,32,'weight',1.00,2.00,229.00,0,1,'2026-09-11 05:11:35',NULL),(60,15,32,'weight',2.00,5.00,409.00,0,1,'2026-09-11 05:11:35',NULL),(61,15,32,'weight',5.00,10.00,699.00,0,1,'2026-09-11 05:11:35',NULL),(62,15,32,'weight',10.00,NULL,1049.00,0,1,'2026-09-11 05:11:35',NULL),(63,15,33,'weight',0.00,0.50,149.00,0,1,'2026-09-11 05:11:35',NULL),(64,15,33,'weight',0.50,1.00,219.00,0,1,'2026-09-11 05:11:35',NULL),(65,15,33,'weight',1.00,2.00,349.00,0,1,'2026-09-11 05:11:35',NULL),(66,15,33,'weight',2.00,5.00,649.00,0,1,'2026-09-11 05:11:35',NULL),(67,15,33,'weight',5.00,10.00,1099.00,0,1,'2026-09-11 05:11:35',NULL),(68,15,33,'weight',10.00,NULL,1649.00,0,1,'2026-09-11 05:11:35',NULL),(69,16,29,'weight',0.00,0.50,89.00,0,1,'2026-09-11 05:11:35',NULL),(70,16,29,'weight',0.50,1.00,129.00,0,1,'2026-09-11 05:11:35',NULL),(71,16,29,'weight',1.00,2.00,179.00,0,1,'2026-09-11 05:11:35',NULL),(72,16,29,'weight',2.00,5.00,319.00,0,1,'2026-09-11 05:11:35',NULL),(73,16,30,'weight',0.00,0.50,129.00,0,1,'2026-09-11 05:11:35',NULL),(74,16,30,'weight',0.50,1.00,179.00,0,1,'2026-09-11 05:11:35',NULL),(75,16,30,'weight',1.00,2.00,269.00,0,1,'2026-09-11 05:11:35',NULL),(76,16,30,'weight',2.00,5.00,479.00,0,1,'2026-09-11 05:11:35',NULL),(77,17,NULL,'price',2500.00,NULL,0.00,1,1,'2026-09-11 05:11:35',NULL);
/*!40000 ALTER TABLE `shipping_rules` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-11 10:51:10
