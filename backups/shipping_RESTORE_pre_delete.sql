-- Full shipping configuration as it stood at 16:07 on 2026-09-09,
-- BEFORE the 9 methods were deleted at 16:27. Restores methods, their rules, and zones.
-- Original ids are preserved so product overrides keep pointing at the right method.
SET FOREIGN_KEY_CHECKS=0;

-- METHODS
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (11,'Weight-Based Shipping','weight','150.00',NULL,NULL,NULL,NULL,5,1,0) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (14,'Express Delivery Ahmedabad','flat','80.00',NULL,NULL,NULL,NULL,1,1,0) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (8,'Standard Delivery','flat','99.00',NULL,NULL,NULL,NULL,NULL,0,0) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (9,'Express Delivery','flat','299.00',NULL,NULL,NULL,NULL,5,0,0) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (10,'Free Shipping','price','0.00',NULL,NULL,NULL,NULL,NULL,0,0) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (12,'Product-Specific','product','0.00',NULL,NULL,NULL,NULL,NULL,0,0) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (1,'Standard Delivery','price','0.00',NULL,NULL,NULL,NULL,NULL,0,1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (2,'Local Gujarat Delivery','price','0.00',NULL,NULL,NULL,NULL,NULL,0,2) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (3,'Metro Express Zone','price','0.00',NULL,NULL,NULL,NULL,NULL,0,3) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (4,'Heavy Equipment Freight','flat','600.00',NULL,NULL,NULL,NULL,NULL,0,4) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (5,'Weight-Based Freight','weight','0.00',NULL,NULL,NULL,NULL,NULL,0,5) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (6,'Product-Class Handling','product','0.00',NULL,NULL,NULL,NULL,NULL,0,6) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_methods (id,name,type,base_cost,min_weight_kg,max_weight_kg,min_order_value,max_order_value,delivery_days,is_active,sort_order) VALUES (7,'Bulk Order Free Shipping','flat','0.00',NULL,NULL,NULL,NULL,NULL,0,7) ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ZONES
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (8,'Gujarat','["Gujarat"]','["36","37","38","39"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (9,'Andhra Pradesh','["Andhra Pradesh"]','["50","51","52","53"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (10,'Assam','["Assam"]','["78"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (11,'Bihar','["Bihar"]','["80","81","82","83","84","85"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (12,'Chattisagrh','["Chattisgarh"]','["49"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (13,'Delhi','["Delhi"]','["11"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (14,'Haryana','["Haryana"]','["12","13"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (15,'Himachal Pradesh','["Himachal Pradesh"]','["17"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (16,'Jammu & Kashmir','["Jammu & Kashmir"]','["18","19"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (17,'Jharkhand','["Jharkhand"]','["81","82","83"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (18,'Karnataka','["Karnataka"]','["56","57","58","59"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (19,'Kerala','["Kerala"]','["67","68","69"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (20,'Madhya Pradesh','["Madhya Pradesh"]','["45","46","47","48"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (21,'Maharashtra','["Maharashtra"]','["40","41","42","43","44"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (22,'North East','["North East"]','["79"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (23,'Odisha','["Odisha"]','["74","75","76","77"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (24,'Punjab','["Punjab"]','["14","15","16"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (25,'Rajasthan','["Rajasthan"]','["30","31","32","33","34"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (26,'Tamilnadu','["Tamilnadu"]','["60","61","62","63","64"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (27,'Telangana','["Telangana"]','["50"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shipping_zones (id,name,states,pincodes,is_active) VALUES (28,'Uttar  Pradesh','["Uttar  Pradesh"]','["20","21","22","23","24","25","26","27","28"]',1) ON DUPLICATE KEY UPDATE name=VALUES(name);

-- RULES
INSERT INTO shipping_rules (id,method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (30,11,NULL,'weight','0.00','0.99','150.00',0,NULL,1) ON DUPLICATE KEY UPDATE cost=VALUES(cost);
INSERT INTO shipping_rules (id,method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (31,11,NULL,'weight','1.00','1.99','300.00',0,NULL,1) ON DUPLICATE KEY UPDATE cost=VALUES(cost);
INSERT INTO shipping_rules (id,method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (32,11,NULL,'weight','4.99','7.00','700.00',0,NULL,1) ON DUPLICATE KEY UPDATE cost=VALUES(cost);
INSERT INTO shipping_rules (id,method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (33,11,NULL,'weight','7.01','8.99','1000.00',0,NULL,1) ON DUPLICATE KEY UPDATE cost=VALUES(cost);
INSERT INTO shipping_rules (id,method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (34,1,NULL,'weight','2.00','4.98','500.00',0,NULL,1) ON DUPLICATE KEY UPDATE cost=VALUES(cost);
INSERT INTO shipping_rules (id,method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (35,1,NULL,'weight','9.00','9.99','1200.00',0,NULL,1) ON DUPLICATE KEY UPDATE cost=VALUES(cost);
INSERT INTO shipping_rules (id,method_id,zone_id,rule_type,min_value,max_value,cost,is_free,product_class,is_active) VALUES (36,1,NULL,'weight','10.00','20.00','1800.00',0,NULL,1) ON DUPLICATE KEY UPDATE cost=VALUES(cost);

SET FOREIGN_KEY_CHECKS=1;
