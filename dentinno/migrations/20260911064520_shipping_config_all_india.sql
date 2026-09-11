-- shipping_config_all_india
--
-- ⚠ DESTRUCTIVE. This REPLACES the entire shipping configuration:
--   every shipping method, zone, rule and pincode row is deleted first, then the
--   all-India setup below is inserted. Any configuration already in the target
--   database is lost. Back up before deploying:
--
--     mysqldump -u USER -p DBNAME shipping_methods shipping_zones shipping_rules \
--       delivery_pincodes > shipping_backup.sql
--
-- WHAT IT CREATES
--   5 regions covering every state and union territory
--   Standard Delivery  - weight priced, all regions
--   Express Delivery   - next day, max 5 kg, Gujarat and West/Central only
--   Free Delivery      - orders above Rs.2,500, max 5 kg
--   39 price rules, 71 delivery areas with days and cash-on-delivery
--
-- Order matters: rules are deleted BEFORE zones. shipping_rules.zone_id is
-- ON DELETE SET NULL, so dropping a zone first would leave its rules alive with the
-- region cleared - silently turning a regional price into a nationwide one.
--
-- Ids are taken from LAST_INSERT_ID() rather than hardcoded, so this works on a
-- database whose auto-increment counters differ from the one it was built on.
-- Re-running is safe: the delete-then-insert leaves exactly this configuration.

START TRANSACTION;

DELETE FROM shipping_rules;
DELETE FROM shipping_methods;
DELETE FROM shipping_zones;
DELETE FROM delivery_pincodes;
UPDATE products SET shipping_method_id = NULL WHERE shipping_method_id IS NOT NULL;

-- ── Regions ──────────────────────────────────────────────────────────────
INSERT INTO shipping_zones (name,states,pincodes,is_active) VALUES ('Z1 Gujarat','[]','[\"36\",\"37\",\"38\",\"39\"]',1);
SET @z1 := LAST_INSERT_ID();   -- Z1 Gujarat
INSERT INTO shipping_zones (name,states,pincodes,is_active) VALUES ('Z2 West & Central','[]','[\"30\",\"31\",\"32\",\"33\",\"34\",\"40\",\"41\",\"42\",\"43\",\"44\",\"45\",\"46\",\"47\",\"48\",\"49\"]',1);
SET @z2 := LAST_INSERT_ID();   -- Z2 West & Central
INSERT INTO shipping_zones (name,states,pincodes,is_active) VALUES ('Z3 North & South','[]','[\"11\",\"12\",\"13\",\"14\",\"15\",\"16\",\"20\",\"21\",\"22\",\"23\",\"24\",\"25\",\"26\",\"27\",\"28\",\"50\",\"51\",\"52\",\"53\",\"56\",\"57\",\"58\",\"59\",\"60\",\"61\",\"62\",\"63\",\"64\",\"67\",\"68\",\"69\"]',1);
SET @z3 := LAST_INSERT_ID();   -- Z3 North & South
INSERT INTO shipping_zones (name,states,pincodes,is_active) VALUES ('Z4 East','[]','[\"17\",\"70\",\"71\",\"72\",\"73\",\"74\",\"75\",\"76\",\"77\",\"80\",\"81\",\"82\",\"83\",\"84\",\"85\"]',1);
SET @z4 := LAST_INSERT_ID();   -- Z4 East
INSERT INTO shipping_zones (name,states,pincodes,is_active) VALUES ('Z5 Remote','[]','[\"18\",\"19\",\"78\",\"79\",\"744\",\"682\"]',1);
SET @z5 := LAST_INSERT_ID();   -- Z5 Remote

-- ── Delivery services ────────────────────────────────────────────────────
INSERT INTO shipping_methods (name,description,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order)
  VALUES ('Free Delivery','Free on orders above Rs.2,500','price','0.00',NULL,'5.000',NULL,NULL,NULL,1,0);
SET @m1 := LAST_INSERT_ID();   -- Free Delivery
INSERT INTO shipping_methods (name,description,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order)
  VALUES ('Standard Delivery','Delivered by our regular courier service','weight','0.00',NULL,NULL,NULL,NULL,NULL,1,1);
SET @m2 := LAST_INSERT_ID();   -- Standard Delivery
INSERT INTO shipping_methods (name,description,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order)
  VALUES ('Express Delivery','Priority dispatch - arrives next day','weight','0.00',NULL,'5.000',NULL,NULL,1,1,2);
SET @m3 := LAST_INSERT_ID();   -- Express Delivery

-- ── Price rules ──────────────────────────────────────────────────────────
--   Free Delivery - All India
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m1,NULL,'price','2500.00',NULL,'0.00',1,NULL,1);
--   Standard Delivery - Z1 Gujarat
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z1,'weight','0.00','0.50','49.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z1,'weight','0.50','1.00','69.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z1,'weight','1.00','2.00','99.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z1,'weight','2.00','5.00','179.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z1,'weight','5.00','10.00','299.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z1,'weight','10.00',NULL,'449.00',0,NULL,1);
--   Standard Delivery - Z2 West & Central
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z2,'weight','0.00','0.50','69.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z2,'weight','0.50','1.00','99.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z2,'weight','1.00','2.00','149.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z2,'weight','2.00','5.00','269.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z2,'weight','5.00','10.00','449.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z2,'weight','10.00',NULL,'699.00',0,NULL,1);
--   Standard Delivery - Z3 North & South
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z3,'weight','0.00','0.50','89.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z3,'weight','0.50','1.00','129.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z3,'weight','1.00','2.00','199.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z3,'weight','2.00','5.00','359.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z3,'weight','5.00','10.00','599.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z3,'weight','10.00',NULL,'899.00',0,NULL,1);
--   Standard Delivery - Z4 East
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z4,'weight','0.00','0.50','99.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z4,'weight','0.50','1.00','149.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z4,'weight','1.00','2.00','229.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z4,'weight','2.00','5.00','409.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z4,'weight','5.00','10.00','699.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z4,'weight','10.00',NULL,'1049.00',0,NULL,1);
--   Standard Delivery - Z5 Remote
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z5,'weight','0.00','0.50','149.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z5,'weight','0.50','1.00','219.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z5,'weight','1.00','2.00','349.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z5,'weight','2.00','5.00','649.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z5,'weight','5.00','10.00','1099.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m2,@z5,'weight','10.00',NULL,'1649.00',0,NULL,1);
--   Express Delivery - Z1 Gujarat
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m3,@z1,'weight','0.00','0.50','89.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m3,@z1,'weight','0.50','1.00','129.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m3,@z1,'weight','1.00','2.00','179.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m3,@z1,'weight','2.00','5.00','319.00',0,NULL,1);
--   Express Delivery - Z2 West & Central
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m3,@z2,'weight','0.00','0.50','129.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m3,@z2,'weight','0.50','1.00','179.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m3,@z2,'weight','1.00','2.00','269.00',0,NULL,1);
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (@m3,@z2,'weight','2.00','5.00','479.00',0,NULL,1);

-- ── Delivery areas: pincode, days, cash on delivery ───────────────────────
INSERT INTO delivery_pincodes (pincode_prefix,label,delivery_days,cod_available,is_active,sort_order) VALUES
  ('36','Gujarat',2,1,1,0),
  ('37','Gujarat',2,1,1,0),
  ('38','Gujarat',2,1,1,0),
  ('39','Gujarat & Daman',2,1,1,0),
  ('30','Rajasthan',3,1,1,0),
  ('31','Rajasthan',3,1,1,0),
  ('32','Rajasthan',3,1,1,0),
  ('33','Rajasthan',3,1,1,0),
  ('34','Rajasthan',3,1,1,0),
  ('40','Maharashtra & Goa',3,1,1,0),
  ('41','Maharashtra',3,1,1,0),
  ('42','Maharashtra',3,1,1,0),
  ('43','Maharashtra',3,1,1,0),
  ('44','Maharashtra',3,1,1,0),
  ('45','Madhya Pradesh',3,1,1,0),
  ('46','Madhya Pradesh',3,1,1,0),
  ('47','Madhya Pradesh',3,1,1,0),
  ('48','Madhya Pradesh',3,1,1,0),
  ('49','Chhattisgarh',3,1,1,0),
  ('11','Delhi NCR',5,1,1,0),
  ('12','Haryana',5,1,1,0),
  ('13','Haryana',5,1,1,0),
  ('14','Punjab',5,1,1,0),
  ('15','Punjab',5,1,1,0),
  ('16','Punjab & Chandigarh',5,1,1,0),
  ('20','Uttar Pradesh',5,1,1,0),
  ('21','Uttar Pradesh',5,1,1,0),
  ('22','Uttar Pradesh',5,1,1,0),
  ('23','Uttar Pradesh',5,1,1,0),
  ('24','UP & Uttarakhand',5,1,1,0),
  ('25','Uttar Pradesh',5,1,1,0),
  ('26','UP & Uttarakhand',5,1,1,0),
  ('27','Uttar Pradesh',5,1,1,0),
  ('28','Uttar Pradesh',5,1,1,0),
  ('50','Telangana',5,1,1,0),
  ('51','Andhra Pradesh',5,1,1,0),
  ('52','Andhra Pradesh',5,1,1,0),
  ('53','Andhra Pradesh',5,1,1,0),
  ('56','Karnataka',5,1,1,0),
  ('57','Karnataka',5,1,1,0),
  ('58','Karnataka',5,1,1,0),
  ('59','Karnataka',5,1,1,0),
  ('60','Tamil Nadu & Puducherry',5,1,1,0),
  ('61','Tamil Nadu',5,1,1,0),
  ('62','Tamil Nadu',5,1,1,0),
  ('63','Tamil Nadu',5,1,1,0),
  ('64','Tamil Nadu',5,1,1,0),
  ('67','Kerala',5,1,1,0),
  ('68','Kerala',5,1,1,0),
  ('69','Kerala',5,1,1,0),
  ('17','Himachal Pradesh',6,1,1,0),
  ('70','West Bengal',6,1,1,0),
  ('71','West Bengal',6,1,1,0),
  ('72','West Bengal',6,1,1,0),
  ('73','West Bengal & Sikkim',6,1,1,0),
  ('74','West Bengal',6,1,1,0),
  ('75','Odisha',6,1,1,0),
  ('76','Odisha',6,1,1,0),
  ('77','Odisha',6,1,1,0),
  ('80','Bihar',6,1,1,0),
  ('81','Bihar & Jharkhand',6,1,1,0),
  ('82','Bihar & Jharkhand',6,1,1,0),
  ('83','Bihar & Jharkhand',6,1,1,0),
  ('84','Bihar',6,1,1,0),
  ('85','Bihar',6,1,1,0),
  ('18','Jammu & Kashmir',9,0,1,0),
  ('19','J&K and Ladakh',9,0,1,0),
  ('682','Lakshadweep',9,0,1,0),
  ('744','Andaman & Nicobar',9,0,1,0),
  ('78','Assam',9,0,1,0),
  ('79','North East States',9,0,1,0);

-- ── Settings ─────────────────────────────────────────────────────────────
INSERT INTO site_settings (skey,svalue) VALUES ('codConfig','{\"available\":true,\"enabled\":true,\"fee\":50,\"freeAbove\":2500}')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);
INSERT INTO site_settings (skey,svalue) VALUES ('shippingConfig','{\"freeThreshold\":0,\"flatRate\":199}')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

COMMIT;

