-- settings before the all-India rebuild
UPDATE site_settings SET svalue='{\"enabled\":false,\"fee\":0,\"freeAbove\":0}' WHERE skey='codConfig';
UPDATE site_settings SET svalue='{\"freeThreshold\":1000,\"flatRate\":99}' WHERE skey='shippingConfig';
