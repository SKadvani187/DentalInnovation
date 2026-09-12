-- settings backup
UPDATE site_settings SET svalue='{\"enabled\":false,\"fee\":0,\"freeAbove\":0}' WHERE skey='codConfig';
UPDATE site_settings SET svalue='{\"freeThreshold\":1000,\"flatRate\":99}' WHERE skey='shippingConfig';
UPDATE products SET shipping_method_id=14, shipping_class='standard' WHERE id=215;
UPDATE products SET shipping_method_id=13, shipping_class='standard' WHERE id=399;
