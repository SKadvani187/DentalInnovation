-- Dentinno shipping configuration - all India
-- Generated 2026-09-11 08:05 from the working local setup.
--
-- WHAT THIS DOES
--   Replaces the whole shipping configuration: 5 regions, 3 delivery services,
--   39 price rules and 71 delivery areas. Existing shipping methods, zones, rules
--   and pincode rows are REMOVED first, so the result is exactly this file.
--
-- BEFORE RUNNING ON A LIVE SITE
--   1. Take a backup:
--        mysqldump -u USER -p DBNAME shipping_methods shipping_zones shipping_rules \
--          delivery_pincodes > shipping_backup.sql
--   2. Make sure the pending schema migrations have been applied first, or this will
--      fail on the delivery_days / availability-limit columns.
--
-- Rules are deleted before methods on purpose: shipping_rules.zone_id is ON DELETE
-- SET NULL, so removing a zone first would leave its rules alive with no zone, quietly
-- turning a regional price into a nationwide one.

START TRANSACTION;

DELETE FROM shipping_rules;
DELETE FROM shipping_methods;
DELETE FROM shipping_zones;
DELETE FROM delivery_pincodes;
UPDATE products SET shipping_method_id = NULL WHERE shipping_method_id IS NOT NULL;

-- ── Regions ──────────────────────────────────────────────────────────────────
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (29,'Z1 Gujarat','[]','[\"36\",\"37\",\"38\",\"39\"]',1);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (30,'Z2 West & Central','[]','[\"30\",\"31\",\"32\",\"33\",\"34\",\"40\",\"41\",\"42\",\"43\",\"44\",\"45\",\"46\",\"47\",\"48\",\"49\"]',1);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (31,'Z3 North & South','[]','[\"11\",\"12\",\"13\",\"14\",\"15\",\"16\",\"20\",\"21\",\"22\",\"23\",\"24\",\"25\",\"26\",\"27\",\"28\",\"50\",\"51\",\"52\",\"53\",\"56\",\"57\",\"58\",\"59\",\"60\",\"61\",\"62\",\"63\",\"64\",\"67\",\"68\",\"69\"]',1);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (32,'Z4 East','[]','[\"17\",\"70\",\"71\",\"72\",\"73\",\"74\",\"75\",\"76\",\"77\",\"80\",\"81\",\"82\",\"83\",\"84\",\"85\"]',1);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (33,'Z5 Remote','[]','[\"18\",\"19\",\"78\",\"79\",\"744\",\"682\"]',1);

-- ── Delivery services ────────────────────────────────────────────────────────
INSERT INTO shipping_methods (id,name,description,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (17,'Free Delivery','Free on orders above Rs.2,500','price','0.00',NULL,'5.000',NULL,NULL,NULL,1,0);
INSERT INTO shipping_methods (id,name,description,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (15,'Standard Delivery','Delivered by our regular courier service','weight','0.00',NULL,NULL,NULL,NULL,NULL,1,1);
INSERT INTO shipping_methods (id,name,description,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (16,'Express Delivery','Priority dispatch - arrives next day','weight','0.00',NULL,'5.000',NULL,NULL,1,1,2);

-- ── Price rules ──────────────────────────────────────────────────────────────
--   Free Delivery|All India
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (17,NULL,'price','2500.00',NULL,'0.00',1,NULL,1);
--   Standard Delivery|Z1 Gujarat
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,29,'weight','0.00','0.50','49.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,29,'weight','0.50','1.00','69.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,29,'weight','1.00','2.00','99.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,29,'weight','2.00','5.00','179.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,29,'weight','5.00','10.00','299.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,29,'weight','10.00',NULL,'449.00',0,NULL,1);
--   Standard Delivery|Z2 West & Central
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,30,'weight','0.00','0.50','69.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,30,'weight','0.50','1.00','99.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,30,'weight','1.00','2.00','149.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,30,'weight','2.00','5.00','269.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,30,'weight','5.00','10.00','449.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,30,'weight','10.00',NULL,'699.00',0,NULL,1);
--   Standard Delivery|Z3 North & South
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,31,'weight','0.00','0.50','89.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,31,'weight','0.50','1.00','129.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,31,'weight','1.00','2.00','199.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,31,'weight','2.00','5.00','359.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,31,'weight','5.00','10.00','599.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,31,'weight','10.00',NULL,'899.00',0,NULL,1);
--   Standard Delivery|Z4 East
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,32,'weight','0.00','0.50','99.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,32,'weight','0.50','1.00','149.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,32,'weight','1.00','2.00','229.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,32,'weight','2.00','5.00','409.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,32,'weight','5.00','10.00','699.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,32,'weight','10.00',NULL,'1049.00',0,NULL,1);
--   Standard Delivery|Z5 Remote
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,33,'weight','0.00','0.50','149.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,33,'weight','0.50','1.00','219.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,33,'weight','1.00','2.00','349.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,33,'weight','2.00','5.00','649.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,33,'weight','5.00','10.00','1099.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (15,33,'weight','10.00',NULL,'1649.00',0,NULL,1);
--   Express Delivery|Z1 Gujarat
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (16,29,'weight','0.00','0.50','89.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (16,29,'weight','0.50','1.00','129.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (16,29,'weight','1.00','2.00','179.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (16,29,'weight','2.00','5.00','319.00',0,NULL,1);
--   Express Delivery|Z2 West & Central
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (16,30,'weight','0.00','0.50','129.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (16,30,'weight','0.50','1.00','179.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (16,30,'weight','1.00','2.00','269.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (16,30,'weight','2.00','5.00','479.00',0,NULL,1);

-- ── Delivery areas (pincode, days, cash on delivery) ──────────────────────────
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('36','Gujarat',2,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('37','Gujarat',2,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('38','Gujarat',2,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('39','Gujarat & Daman',2,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('30','Rajasthan',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('31','Rajasthan',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('32','Rajasthan',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('33','Rajasthan',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('34','Rajasthan',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('40','Maharashtra & Goa',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('41','Maharashtra',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('42','Maharashtra',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('43','Maharashtra',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('44','Maharashtra',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('45','Madhya Pradesh',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('46','Madhya Pradesh',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('47','Madhya Pradesh',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('48','Madhya Pradesh',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('49','Chhattisgarh',3,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('11','Delhi NCR',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('12','Haryana',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('13','Haryana',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('14','Punjab',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('15','Punjab',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('16','Punjab & Chandigarh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('20','Uttar Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('21','Uttar Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('22','Uttar Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('23','Uttar Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('24','UP & Uttarakhand',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('25','Uttar Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('26','UP & Uttarakhand',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('27','Uttar Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('28','Uttar Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('50','Telangana',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('51','Andhra Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('52','Andhra Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('53','Andhra Pradesh',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('56','Karnataka',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('57','Karnataka',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('58','Karnataka',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('59','Karnataka',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('60','Tamil Nadu & Puducherry',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('61','Tamil Nadu',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('62','Tamil Nadu',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('63','Tamil Nadu',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('64','Tamil Nadu',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('67','Kerala',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('68','Kerala',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('69','Kerala',5,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('17','Himachal Pradesh',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('70','West Bengal',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('71','West Bengal',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('72','West Bengal',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('73','West Bengal & Sikkim',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('74','West Bengal',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('75','Odisha',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('76','Odisha',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('77','Odisha',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('80','Bihar',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('81','Bihar & Jharkhand',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('82','Bihar & Jharkhand',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('83','Bihar & Jharkhand',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('84','Bihar',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('85','Bihar',6,1,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('18','Jammu & Kashmir',9,0,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('19','J&K and Ladakh',9,0,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('682','Lakshadweep',9,0,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('744','Andaman & Nicobar',9,0,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('78','Assam',9,0,1,0);
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES ('79','North East States',9,0,1,0);

-- ── Settings ─────────────────────────────────────────────────────────────────
INSERT INTO site_settings (skey,svalue) VALUES ('codConfig','{\"available\":true,\"enabled\":true,\"fee\":50,\"freeAbove\":2500}')
  ON DUPLICATE KEY UPDATE svalue=VALUES(svalue);
INSERT INTO site_settings (skey,svalue) VALUES ('shippingConfig','{\"freeThreshold\":0,\"flatRate\":199}')
  ON DUPLICATE KEY UPDATE svalue=VALUES(svalue);

COMMIT;

