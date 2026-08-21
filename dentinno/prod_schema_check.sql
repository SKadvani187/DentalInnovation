-- =============================================================================
-- PRODUCTION SCHEMA DRIFT CHECK  (read-only -- changes no data)
--
-- Reports any TABLE or COLUMN the application expects (present in the known-good
-- reference schema) but MISSING on this server. Run BEFORE importing the catalogue
-- so you don't hit errors like the missing hsn_code column.
--
-- Usage:   mysql -u USER -p PROD_DB < prod_schema_check.sql
-- Uses DATABASE() so it checks whichever DB you connect to (no hardcoded name).
--
-- Generated from the reference schema: 49 tables, 552 columns.
-- If BOTH result sets are empty, production matches the reference -- safe to import.
-- =============================================================================

-- ---- 1) MISSING TABLES ------------------------------------------------------
SELECT '*** MISSING TABLE ***' AS issue, expected.tbl AS name
FROM (
  SELECT 'activity_log' AS tbl UNION ALL
  SELECT 'admin_audit_log' UNION ALL
  SELECT 'admin_login_attempts' UNION ALL
  SELECT 'admin_users' UNION ALL
  SELECT 'bulk_quotes' UNION ALL
  SELECT 'categories' UNION ALL
  SELECT 'combos' UNION ALL
  SELECT 'contact_messages' UNION ALL
  SELECT 'coupons' UNION ALL
  SELECT 'coupon_redemptions' UNION ALL
  SELECT 'courses' UNION ALL
  SELECT 'course_enrollments' UNION ALL
  SELECT 'course_lessons' UNION ALL
  SELECT 'course_modules' UNION ALL
  SELECT 'customers' UNION ALL
  SELECT 'delivery_pincodes' UNION ALL
  SELECT 'events' UNION ALL
  SELECT 'event_registrations' UNION ALL
  SELECT 'inventory_movements' UNION ALL
  SELECT 'notifications' UNION ALL
  SELECT 'notification_reads' UNION ALL
  SELECT 'offers' UNION ALL
  SELECT 'offer_items' UNION ALL
  SELECT 'orders' UNION ALL
  SELECT 'order_items' UNION ALL
  SELECT 'order_mail_log' UNION ALL
  SELECT 'order_status_history' UNION ALL
  SELECT 'otp_codes' UNION ALL
  SELECT 'page_registry' UNION ALL
  SELECT 'payments' UNION ALL
  SELECT 'products' UNION ALL
  SELECT 'product_faqs' UNION ALL
  SELECT 'product_fbt' UNION ALL
  SELECT 'product_gifts' UNION ALL
  SELECT 'product_questions' UNION ALL
  SELECT 'product_reviews' UNION ALL
  SELECT 'product_shipping' UNION ALL
  SELECT 'rbac_meta' UNION ALL
  SELECT 'refund_requests' UNION ALL
  SELECT 'roles' UNION ALL
  SELECT 'role_permissions' UNION ALL
  SELECT 'schema_migrations' UNION ALL
  SELECT 'shipping_methods' UNION ALL
  SELECT 'shipping_rules' UNION ALL
  SELECT 'shipping_zones' UNION ALL
  SELECT 'site_settings' UNION ALL
  SELECT 'testimonials' UNION ALL
  SELECT 'whatsapp_logs' UNION ALL
  SELECT 'wishlists'
) AS expected
LEFT JOIN information_schema.TABLES it
  ON it.TABLE_SCHEMA = DATABASE() AND it.TABLE_NAME = expected.tbl
WHERE it.TABLE_NAME IS NULL
ORDER BY expected.tbl;

-- ---- 2) MISSING COLUMNS (only for tables that exist) ------------------------
SELECT '*** MISSING COLUMN ***' AS issue, expected.tbl AS table_name, expected.col AS column_name
FROM (
  SELECT 'activity_log' AS tbl, 'id' AS col UNION ALL
  SELECT 'activity_log', 'actor_id' UNION ALL
  SELECT 'activity_log', 'actor_name' UNION ALL
  SELECT 'activity_log', 'action' UNION ALL
  SELECT 'activity_log', 'entity_type' UNION ALL
  SELECT 'activity_log', 'entity_id' UNION ALL
  SELECT 'activity_log', 'summary' UNION ALL
  SELECT 'activity_log', 'created_at' UNION ALL
  SELECT 'admin_audit_log', 'id' UNION ALL
  SELECT 'admin_audit_log', 'actor_id' UNION ALL
  SELECT 'admin_audit_log', 'actor_name' UNION ALL
  SELECT 'admin_audit_log', 'action' UNION ALL
  SELECT 'admin_audit_log', 'target_id' UNION ALL
  SELECT 'admin_audit_log', 'target_email' UNION ALL
  SELECT 'admin_audit_log', 'details' UNION ALL
  SELECT 'admin_audit_log', 'created_at' UNION ALL
  SELECT 'admin_login_attempts', 'ip' UNION ALL
  SELECT 'admin_login_attempts', 'attempts' UNION ALL
  SELECT 'admin_login_attempts', 'locked_until' UNION ALL
  SELECT 'admin_login_attempts', 'updated_at' UNION ALL
  SELECT 'admin_users', 'id' UNION ALL
  SELECT 'admin_users', 'name' UNION ALL
  SELECT 'admin_users', 'email' UNION ALL
  SELECT 'admin_users', 'password' UNION ALL
  SELECT 'admin_users', 'role' UNION ALL
  SELECT 'admin_users', 'role_id' UNION ALL
  SELECT 'admin_users', 'permissions' UNION ALL
  SELECT 'admin_users', 'avatar' UNION ALL
  SELECT 'admin_users', 'is_active' UNION ALL
  SELECT 'admin_users', 'last_login' UNION ALL
  SELECT 'admin_users', 'created_at' UNION ALL
  SELECT 'admin_users', 'updated_at' UNION ALL
  SELECT 'bulk_quotes', 'id' UNION ALL
  SELECT 'bulk_quotes', 'name' UNION ALL
  SELECT 'bulk_quotes', 'phone' UNION ALL
  SELECT 'bulk_quotes', 'email' UNION ALL
  SELECT 'bulk_quotes', 'pincode' UNION ALL
  SELECT 'bulk_quotes', 'address' UNION ALL
  SELECT 'bulk_quotes', 'product_slug' UNION ALL
  SELECT 'bulk_quotes', 'product_name' UNION ALL
  SELECT 'bulk_quotes', 'quantity' UNION ALL
  SELECT 'bulk_quotes', 'expected_price' UNION ALL
  SELECT 'bulk_quotes', 'status' UNION ALL
  SELECT 'bulk_quotes', 'is_read' UNION ALL
  SELECT 'bulk_quotes', 'is_deleted' UNION ALL
  SELECT 'bulk_quotes', 'created_at' UNION ALL
  SELECT 'categories', 'id' UNION ALL
  SELECT 'categories', 'name' UNION ALL
  SELECT 'categories', 'slug' UNION ALL
  SELECT 'categories', 'meta_title' UNION ALL
  SELECT 'categories', 'meta_description' UNION ALL
  SELECT 'categories', 'description' UNION ALL
  SELECT 'categories', 'parent_id' UNION ALL
  SELECT 'categories', 'image' UNION ALL
  SELECT 'categories', 'is_active' UNION ALL
  SELECT 'categories', 'sort_order' UNION ALL
  SELECT 'categories', 'created_at' UNION ALL
  SELECT 'combos', 'id' UNION ALL
  SELECT 'combos', 'slug' UNION ALL
  SELECT 'combos', 'name' UNION ALL
  SELECT 'combos', 'description' UNION ALL
  SELECT 'combos', 'meta_title' UNION ALL
  SELECT 'combos', 'meta_description' UNION ALL
  SELECT 'combos', 'mrp' UNION ALL
  SELECT 'combos', 'price' UNION ALL
  SELECT 'combos', 'discount_percent' UNION ALL
  SELECT 'combos', 'image' UNION ALL
  SELECT 'combos', 'images' UNION ALL
  SELECT 'combos', 'items' UNION ALL
  SELECT 'combos', 'in_stock' UNION ALL
  SELECT 'combos', 'stock' UNION ALL
  SELECT 'combos', 'is_active' UNION ALL
  SELECT 'combos', 'is_deleted' UNION ALL
  SELECT 'combos', 'sort_order' UNION ALL
  SELECT 'combos', 'created_at' UNION ALL
  SELECT 'combos', 'updated_at' UNION ALL
  SELECT 'contact_messages', 'id' UNION ALL
  SELECT 'contact_messages', 'name' UNION ALL
  SELECT 'contact_messages', 'phone' UNION ALL
  SELECT 'contact_messages', 'email' UNION ALL
  SELECT 'contact_messages', 'department' UNION ALL
  SELECT 'contact_messages', 'message' UNION ALL
  SELECT 'contact_messages', 'is_read' UNION ALL
  SELECT 'contact_messages', 'is_deleted' UNION ALL
  SELECT 'contact_messages', 'created_at' UNION ALL
  SELECT 'coupons', 'id' UNION ALL
  SELECT 'coupons', 'code' UNION ALL
  SELECT 'coupons', 'type' UNION ALL
  SELECT 'coupons', 'value' UNION ALL
  SELECT 'coupons', 'min_order' UNION ALL
  SELECT 'coupons', 'max_discount' UNION ALL
  SELECT 'coupons', 'uses_limit' UNION ALL
  SELECT 'coupons', 'uses_count' UNION ALL
  SELECT 'coupons', 'is_active' UNION ALL
  SELECT 'coupons', 'start_date' UNION ALL
  SELECT 'coupons', 'is_deleted' UNION ALL
  SELECT 'coupons', 'expires_at' UNION ALL
  SELECT 'coupons', 'created_at' UNION ALL
  SELECT 'coupons', 'per_user_limit' UNION ALL
  SELECT 'coupon_redemptions', 'id' UNION ALL
  SELECT 'coupon_redemptions', 'coupon_id' UNION ALL
  SELECT 'coupon_redemptions', 'customer_id' UNION ALL
  SELECT 'coupon_redemptions', 'order_id' UNION ALL
  SELECT 'coupon_redemptions', 'created_at' UNION ALL
  SELECT 'courses', 'id' UNION ALL
  SELECT 'courses', 'title' UNION ALL
  SELECT 'courses', 'slug' UNION ALL
  SELECT 'courses', 'description' UNION ALL
  SELECT 'courses', 'full_description' UNION ALL
  SELECT 'courses', 'course_type' UNION ALL
  SELECT 'courses', 'category' UNION ALL
  SELECT 'courses', 'level' UNION ALL
  SELECT 'courses', 'status' UNION ALL
  SELECT 'courses', 'is_deleted' UNION ALL
  SELECT 'courses', 'duration_hours' UNION ALL
  SELECT 'courses', 'total_lessons' UNION ALL
  SELECT 'courses', 'price' UNION ALL
  SELECT 'courses', 'discount_price' UNION ALL
  SELECT 'courses', 'is_free' UNION ALL
  SELECT 'courses', 'thumbnail' UNION ALL
  SELECT 'courses', 'preview_video' UNION ALL
  SELECT 'courses', 'instructor_name' UNION ALL
  SELECT 'courses', 'instructor_bio' UNION ALL
  SELECT 'courses', 'instructor_avatar' UNION ALL
  SELECT 'courses', 'certificate_offered' UNION ALL
  SELECT 'courses', 'max_students' UNION ALL
  SELECT 'courses', 'enrolled_count' UNION ALL
  SELECT 'courses', 'rating' UNION ALL
  SELECT 'courses', 'rating_count' UNION ALL
  SELECT 'courses', 'tags' UNION ALL
  SELECT 'courses', 'requirements' UNION ALL
  SELECT 'courses', 'outcomes' UNION ALL
  SELECT 'courses', 'created_at' UNION ALL
  SELECT 'courses', 'updated_at' UNION ALL
  SELECT 'course_enrollments', 'id' UNION ALL
  SELECT 'course_enrollments', 'course_id' UNION ALL
  SELECT 'course_enrollments', 'customer_id' UNION ALL
  SELECT 'course_enrollments', 'student_name' UNION ALL
  SELECT 'course_enrollments', 'student_email' UNION ALL
  SELECT 'course_enrollments', 'student_phone' UNION ALL
  SELECT 'course_enrollments', 'payment_status' UNION ALL
  SELECT 'course_enrollments', 'payment_amount' UNION ALL
  SELECT 'course_enrollments', 'enrollment_date' UNION ALL
  SELECT 'course_enrollments', 'completion_date' UNION ALL
  SELECT 'course_enrollments', 'progress_percent' UNION ALL
  SELECT 'course_enrollments', 'certificate_issued' UNION ALL
  SELECT 'course_lessons', 'id' UNION ALL
  SELECT 'course_lessons', 'module_id' UNION ALL
  SELECT 'course_lessons', 'course_id' UNION ALL
  SELECT 'course_lessons', 'title' UNION ALL
  SELECT 'course_lessons', 'lesson_type' UNION ALL
  SELECT 'course_lessons', 'content' UNION ALL
  SELECT 'course_lessons', 'video_url' UNION ALL
  SELECT 'course_lessons', 'duration_minutes' UNION ALL
  SELECT 'course_lessons', 'is_preview' UNION ALL
  SELECT 'course_lessons', 'sort_order' UNION ALL
  SELECT 'course_lessons', 'is_active' UNION ALL
  SELECT 'course_lessons', 'created_at' UNION ALL
  SELECT 'course_modules', 'id' UNION ALL
  SELECT 'course_modules', 'course_id' UNION ALL
  SELECT 'course_modules', 'title' UNION ALL
  SELECT 'course_modules', 'description' UNION ALL
  SELECT 'course_modules', 'sort_order' UNION ALL
  SELECT 'course_modules', 'is_active' UNION ALL
  SELECT 'course_modules', 'created_at' UNION ALL
  SELECT 'customers', 'id' UNION ALL
  SELECT 'customers', 'name' UNION ALL
  SELECT 'customers', 'email' UNION ALL
  SELECT 'customers', 'phone' UNION ALL
  SELECT 'customers', 'city' UNION ALL
  SELECT 'customers', 'state' UNION ALL
  SELECT 'customers', 'address' UNION ALL
  SELECT 'customers', 'pincode' UNION ALL
  SELECT 'customers', 'clinic_name' UNION ALL
  SELECT 'customers', 'customer_type' UNION ALL
  SELECT 'customers', 'total_orders' UNION ALL
  SELECT 'customers', 'total_spent' UNION ALL
  SELECT 'customers', 'notes' UNION ALL
  SELECT 'customers', 'is_active' UNION ALL
  SELECT 'customers', 'is_deleted' UNION ALL
  SELECT 'customers', 'created_at' UNION ALL
  SELECT 'customers', 'updated_at' UNION ALL
  SELECT 'customers', 'api_token' UNION ALL
  SELECT 'customers', 'addresses' UNION ALL
  SELECT 'customers', 'cart' UNION ALL
  SELECT 'delivery_pincodes', 'id' UNION ALL
  SELECT 'delivery_pincodes', 'pincode_prefix' UNION ALL
  SELECT 'delivery_pincodes', 'label' UNION ALL
  SELECT 'delivery_pincodes', 'delivery_days' UNION ALL
  SELECT 'delivery_pincodes', 'cod_available' UNION ALL
  SELECT 'delivery_pincodes', 'is_active' UNION ALL
  SELECT 'delivery_pincodes', 'sort_order' UNION ALL
  SELECT 'delivery_pincodes', 'created_at' UNION ALL
  SELECT 'events', 'id' UNION ALL
  SELECT 'events', 'title' UNION ALL
  SELECT 'events', 'slug' UNION ALL
  SELECT 'events', 'description' UNION ALL
  SELECT 'events', 'event_type' UNION ALL
  SELECT 'events', 'status' UNION ALL
  SELECT 'events', 'is_deleted' UNION ALL
  SELECT 'events', 'start_date' UNION ALL
  SELECT 'events', 'end_date' UNION ALL
  SELECT 'events', 'venue' UNION ALL
  SELECT 'events', 'city' UNION ALL
  SELECT 'events', 'state' UNION ALL
  SELECT 'events', 'is_online' UNION ALL
  SELECT 'events', 'online_link' UNION ALL
  SELECT 'events', 'max_attendees' UNION ALL
  SELECT 'events', 'registration_fee' UNION ALL
  SELECT 'events', 'is_free' UNION ALL
  SELECT 'events', 'banner_image' UNION ALL
  SELECT 'events', 'tags' UNION ALL
  SELECT 'events', 'organizer' UNION ALL
  SELECT 'events', 'contact_email' UNION ALL
  SELECT 'events', 'contact_phone' UNION ALL
  SELECT 'events', 'created_at' UNION ALL
  SELECT 'events', 'updated_at' UNION ALL
  SELECT 'event_registrations', 'id' UNION ALL
  SELECT 'event_registrations', 'event_id' UNION ALL
  SELECT 'event_registrations', 'customer_id' UNION ALL
  SELECT 'event_registrations', 'name' UNION ALL
  SELECT 'event_registrations', 'email' UNION ALL
  SELECT 'event_registrations', 'phone' UNION ALL
  SELECT 'event_registrations', 'clinic_name' UNION ALL
  SELECT 'event_registrations', 'payment_status' UNION ALL
  SELECT 'event_registrations', 'payment_amount' UNION ALL
  SELECT 'event_registrations', 'registration_code' UNION ALL
  SELECT 'event_registrations', 'attended' UNION ALL
  SELECT 'event_registrations', 'notes' UNION ALL
  SELECT 'event_registrations', 'created_at' UNION ALL
  SELECT 'inventory_movements', 'id' UNION ALL
  SELECT 'inventory_movements', 'product_id' UNION ALL
  SELECT 'inventory_movements', 'delta' UNION ALL
  SELECT 'inventory_movements', 'type' UNION ALL
  SELECT 'inventory_movements', 'reason' UNION ALL
  SELECT 'inventory_movements', 'reference' UNION ALL
  SELECT 'inventory_movements', 'balance_after' UNION ALL
  SELECT 'inventory_movements', 'admin_id' UNION ALL
  SELECT 'inventory_movements', 'created_at' UNION ALL
  SELECT 'notifications', 'id' UNION ALL
  SELECT 'notifications', 'title' UNION ALL
  SELECT 'notifications', 'message' UNION ALL
  SELECT 'notifications', 'type' UNION ALL
  SELECT 'notifications', 'is_read' UNION ALL
  SELECT 'notifications', 'link' UNION ALL
  SELECT 'notifications', 'created_at' UNION ALL
  SELECT 'notification_reads', 'notification_id' UNION ALL
  SELECT 'notification_reads', 'admin_id' UNION ALL
  SELECT 'notification_reads', 'read_at' UNION ALL
  SELECT 'offers', 'id' UNION ALL
  SELECT 'offers', 'slug' UNION ALL
  SELECT 'offers', 'product_id' UNION ALL
  SELECT 'offers', 'title' UNION ALL
  SELECT 'offers', 'subtitle' UNION ALL
  SELECT 'offers', 'theme' UNION ALL
  SELECT 'offers', 'accent' UNION ALL
  SELECT 'offers', 'gradient' UNION ALL
  SELECT 'offers', 'cta' UNION ALL
  SELECT 'offers', 'main_product' UNION ALL
  SELECT 'offers', 'free_items' UNION ALL
  SELECT 'offers', 'special_price' UNION ALL
  SELECT 'offers', 'total_mrp' UNION ALL
  SELECT 'offers', 'you_save' UNION ALL
  SELECT 'offers', 'save_extra' UNION ALL
  SELECT 'offers', 'valid_till' UNION ALL
  SELECT 'offers', 'is_active' UNION ALL
  SELECT 'offers', 'is_deleted' UNION ALL
  SELECT 'offers', 'sort_order' UNION ALL
  SELECT 'offers', 'social_mode' UNION ALL
  SELECT 'offers', 'social_count' UNION ALL
  SELECT 'offers', 'is_top_deal' UNION ALL
  SELECT 'offers', 'created_at' UNION ALL
  SELECT 'offers', 'updated_at' UNION ALL
  SELECT 'offer_items', 'id' UNION ALL
  SELECT 'offer_items', 'offer_id' UNION ALL
  SELECT 'offer_items', 'product_id' UNION ALL
  SELECT 'offer_items', 'name' UNION ALL
  SELECT 'offer_items', 'variant' UNION ALL
  SELECT 'offer_items', 'image' UNION ALL
  SELECT 'offer_items', 'mrp' UNION ALL
  SELECT 'offer_items', 'qty' UNION ALL
  SELECT 'offer_items', 'sort_order' UNION ALL
  SELECT 'offer_items', 'created_at' UNION ALL
  SELECT 'orders', 'id' UNION ALL
  SELECT 'orders', 'order_number' UNION ALL
  SELECT 'orders', 'customer_id' UNION ALL
  SELECT 'orders', 'status' UNION ALL
  SELECT 'orders', 'payment_status' UNION ALL
  SELECT 'orders', 'payment_method' UNION ALL
  SELECT 'orders', 'subtotal' UNION ALL
  SELECT 'orders', 'discount' UNION ALL
  SELECT 'orders', 'shipping_charge' UNION ALL
  SELECT 'orders', 'tax' UNION ALL
  SELECT 'orders', 'total' UNION ALL
  SELECT 'orders', 'coupon_id' UNION ALL
  SELECT 'orders', 'effects_reversed' UNION ALL
  SELECT 'orders', 'shipping_address' UNION ALL
  SELECT 'orders', 'notes' UNION ALL
  SELECT 'orders', 'tracking_number' UNION ALL
  SELECT 'orders', 'courier_name' UNION ALL
  SELECT 'orders', 'shipped_at' UNION ALL
  SELECT 'orders', 'delivered_at' UNION ALL
  SELECT 'orders', 'created_at' UNION ALL
  SELECT 'orders', 'updated_at' UNION ALL
  SELECT 'order_items', 'id' UNION ALL
  SELECT 'order_items', 'order_id' UNION ALL
  SELECT 'order_items', 'product_id' UNION ALL
  SELECT 'order_items', 'product_name' UNION ALL
  SELECT 'order_items', 'quantity' UNION ALL
  SELECT 'order_items', 'price' UNION ALL
  SELECT 'order_items', 'total' UNION ALL
  SELECT 'order_items', 'product_slug' UNION ALL
  SELECT 'order_items', 'variant' UNION ALL
  SELECT 'order_items', 'line_type' UNION ALL
  SELECT 'order_items', 'offer_id' UNION ALL
  SELECT 'order_items', 'hsn_code' UNION ALL
  SELECT 'order_mail_log', 'id' UNION ALL
  SELECT 'order_mail_log', 'order_id' UNION ALL
  SELECT 'order_mail_log', 'order_number' UNION ALL
  SELECT 'order_mail_log', 'mail_type' UNION ALL
  SELECT 'order_mail_log', 'recipient' UNION ALL
  SELECT 'order_mail_log', 'subject' UNION ALL
  SELECT 'order_mail_log', 'status' UNION ALL
  SELECT 'order_mail_log', 'error' UNION ALL
  SELECT 'order_mail_log', 'attempts' UNION ALL
  SELECT 'order_mail_log', 'created_at' UNION ALL
  SELECT 'order_mail_log', 'updated_at' UNION ALL
  SELECT 'order_mail_log', 'sent_at' UNION ALL
  SELECT 'order_status_history', 'id' UNION ALL
  SELECT 'order_status_history', 'order_id' UNION ALL
  SELECT 'order_status_history', 'status' UNION ALL
  SELECT 'order_status_history', 'note' UNION ALL
  SELECT 'order_status_history', 'changed_by' UNION ALL
  SELECT 'order_status_history', 'created_at' UNION ALL
  SELECT 'otp_codes', 'id' UNION ALL
  SELECT 'otp_codes', 'identifier' UNION ALL
  SELECT 'otp_codes', 'channel' UNION ALL
  SELECT 'otp_codes', 'otp_hash' UNION ALL
  SELECT 'otp_codes', 'expires_at' UNION ALL
  SELECT 'otp_codes', 'attempts' UNION ALL
  SELECT 'otp_codes', 'verified' UNION ALL
  SELECT 'otp_codes', 'blocked_until' UNION ALL
  SELECT 'otp_codes', 'last_sent_at' UNION ALL
  SELECT 'otp_codes', 'created_at' UNION ALL
  SELECT 'otp_codes', 'updated_at' UNION ALL
  SELECT 'page_registry', 'id' UNION ALL
  SELECT 'page_registry', 'page_key' UNION ALL
  SELECT 'page_registry', 'label' UNION ALL
  SELECT 'page_registry', 'url' UNION ALL
  SELECT 'page_registry', 'icon' UNION ALL
  SELECT 'page_registry', 'nav_group' UNION ALL
  SELECT 'page_registry', 'group_order' UNION ALL
  SELECT 'page_registry', 'sort_order' UNION ALL
  SELECT 'page_registry', 'supports_view' UNION ALL
  SELECT 'page_registry', 'supports_create' UNION ALL
  SELECT 'page_registry', 'supports_edit' UNION ALL
  SELECT 'page_registry', 'supports_delete' UNION ALL
  SELECT 'page_registry', 'is_super_only' UNION ALL
  SELECT 'page_registry', 'show_in_nav' UNION ALL
  SELECT 'page_registry', 'is_active' UNION ALL
  SELECT 'page_registry', 'is_system' UNION ALL
  SELECT 'page_registry', 'description' UNION ALL
  SELECT 'page_registry', 'created_at' UNION ALL
  SELECT 'payments', 'id' UNION ALL
  SELECT 'payments', 'order_id' UNION ALL
  SELECT 'payments', 'amount' UNION ALL
  SELECT 'payments', 'method' UNION ALL
  SELECT 'payments', 'transaction_id' UNION ALL
  SELECT 'payments', 'status' UNION ALL
  SELECT 'payments', 'payment_date' UNION ALL
  SELECT 'payments', 'notes' UNION ALL
  SELECT 'payments', 'created_at' UNION ALL
  SELECT 'products', 'id' UNION ALL
  SELECT 'products', 'name' UNION ALL
  SELECT 'products', 'slug' UNION ALL
  SELECT 'products', 'meta_title' UNION ALL
  SELECT 'products', 'meta_description' UNION ALL
  SELECT 'products', 'sku' UNION ALL
  SELECT 'products', 'category_id' UNION ALL
  SELECT 'products', 'description' UNION ALL
  SELECT 'products', 'features' UNION ALL
  SELECT 'products', 'full_description' UNION ALL
  SELECT 'products', 'packing_info' UNION ALL
  SELECT 'products', 'key_specifications' UNION ALL
  SELECT 'products', 'directions_for_use' UNION ALL
  SELECT 'products', 'additional_information' UNION ALL
  SELECT 'products', 'warranty_info' UNION ALL
  SELECT 'products', 'key_features' UNION ALL
  SELECT 'products', 'warranty_no' UNION ALL
  SELECT 'products', 'direction_of_use' UNION ALL
  SELECT 'products', 'weight_kg' UNION ALL
  SELECT 'products', 'short_description' UNION ALL
  SELECT 'products', 'price' UNION ALL
  SELECT 'products', 'cost_price' UNION ALL
  SELECT 'products', 'discount_price' UNION ALL
  SELECT 'products', 'discount_percent' UNION ALL
  SELECT 'products', 'stock' UNION ALL
  SELECT 'products', 'min_stock_alert' UNION ALL
  SELECT 'products', 'images' UNION ALL
  SELECT 'products', 'hover_image' UNION ALL
  SELECT 'products', 'variants' UNION ALL
  SELECT 'products', 'specifications' UNION ALL
  SELECT 'products', 'is_active' UNION ALL
  SELECT 'products', 'is_deleted' UNION ALL
  SELECT 'products', 'is_featured' UNION ALL
  SELECT 'products', 'is_new' UNION ALL
  SELECT 'products', 'total_sales' UNION ALL
  SELECT 'products', 'created_at' UNION ALL
  SELECT 'products', 'updated_at' UNION ALL
  SELECT 'products', 'shipping_class' UNION ALL
  SELECT 'products', 'shipping_method_id' UNION ALL
  SELECT 'products', 'catalogue_url' UNION ALL
  SELECT 'products', 'hsn_code' UNION ALL
  SELECT 'product_faqs', 'id' UNION ALL
  SELECT 'product_faqs', 'product_id' UNION ALL
  SELECT 'product_faqs', 'question' UNION ALL
  SELECT 'product_faqs', 'answer' UNION ALL
  SELECT 'product_faqs', 'sort_order' UNION ALL
  SELECT 'product_faqs', 'is_active' UNION ALL
  SELECT 'product_faqs', 'created_at' UNION ALL
  SELECT 'product_fbt', 'id' UNION ALL
  SELECT 'product_fbt', 'product_id' UNION ALL
  SELECT 'product_fbt', 'fbt_product_id' UNION ALL
  SELECT 'product_fbt', 'sort_order' UNION ALL
  SELECT 'product_fbt', 'created_at' UNION ALL
  SELECT 'product_gifts', 'id' UNION ALL
  SELECT 'product_gifts', 'product_id' UNION ALL
  SELECT 'product_gifts', 'gift_product_id' UNION ALL
  SELECT 'product_gifts', 'sort_order' UNION ALL
  SELECT 'product_gifts', 'created_at' UNION ALL
  SELECT 'product_questions', 'id' UNION ALL
  SELECT 'product_questions', 'product_id' UNION ALL
  SELECT 'product_questions', 'customer_id' UNION ALL
  SELECT 'product_questions', 'asker_name' UNION ALL
  SELECT 'product_questions', 'asker_email' UNION ALL
  SELECT 'product_questions', 'question' UNION ALL
  SELECT 'product_questions', 'answer' UNION ALL
  SELECT 'product_questions', 'is_answered' UNION ALL
  SELECT 'product_questions', 'is_approved' UNION ALL
  SELECT 'product_questions', 'is_deleted' UNION ALL
  SELECT 'product_questions', 'helpful_up' UNION ALL
  SELECT 'product_questions', 'helpful_down' UNION ALL
  SELECT 'product_questions', 'created_at' UNION ALL
  SELECT 'product_questions', 'answered_at' UNION ALL
  SELECT 'product_reviews', 'id' UNION ALL
  SELECT 'product_reviews', 'product_id' UNION ALL
  SELECT 'product_reviews', 'customer_id' UNION ALL
  SELECT 'product_reviews', 'reviewer_name' UNION ALL
  SELECT 'product_reviews', 'reviewer_email' UNION ALL
  SELECT 'product_reviews', 'rating' UNION ALL
  SELECT 'product_reviews', 'title' UNION ALL
  SELECT 'product_reviews', 'review' UNION ALL
  SELECT 'product_reviews', 'images' UNION ALL
  SELECT 'product_reviews', 'is_verified' UNION ALL
  SELECT 'product_reviews', 'is_approved' UNION ALL
  SELECT 'product_reviews', 'is_deleted' UNION ALL
  SELECT 'product_reviews', 'helpful_count' UNION ALL
  SELECT 'product_reviews', 'created_at' UNION ALL
  SELECT 'product_shipping', 'id' UNION ALL
  SELECT 'product_shipping', 'product_id' UNION ALL
  SELECT 'product_shipping', 'shipping_class' UNION ALL
  SELECT 'product_shipping', 'weight_kg' UNION ALL
  SELECT 'product_shipping', 'length_cm' UNION ALL
  SELECT 'product_shipping', 'width_cm' UNION ALL
  SELECT 'product_shipping', 'height_cm' UNION ALL
  SELECT 'product_shipping', 'override_cost' UNION ALL
  SELECT 'product_shipping', 'is_free_shipping' UNION ALL
  SELECT 'product_shipping', 'created_at' UNION ALL
  SELECT 'rbac_meta', 'id' UNION ALL
  SELECT 'rbac_meta', 'perm_version' UNION ALL
  SELECT 'refund_requests', 'id' UNION ALL
  SELECT 'refund_requests', 'order_id' UNION ALL
  SELECT 'refund_requests', 'customer_id' UNION ALL
  SELECT 'refund_requests', 'reason' UNION ALL
  SELECT 'refund_requests', 'status' UNION ALL
  SELECT 'refund_requests', 'refund_amount' UNION ALL
  SELECT 'refund_requests', 'razorpay_refund_id' UNION ALL
  SELECT 'refund_requests', 'admin_note' UNION ALL
  SELECT 'refund_requests', 'actioned_by' UNION ALL
  SELECT 'refund_requests', 'requested_at' UNION ALL
  SELECT 'refund_requests', 'actioned_at' UNION ALL
  SELECT 'refund_requests', 'completed_at' UNION ALL
  SELECT 'roles', 'id' UNION ALL
  SELECT 'roles', 'name' UNION ALL
  SELECT 'roles', 'slug' UNION ALL
  SELECT 'roles', 'description' UNION ALL
  SELECT 'roles', 'is_super' UNION ALL
  SELECT 'roles', 'is_system' UNION ALL
  SELECT 'roles', 'is_active' UNION ALL
  SELECT 'roles', 'created_at' UNION ALL
  SELECT 'role_permissions', 'id' UNION ALL
  SELECT 'role_permissions', 'role_id' UNION ALL
  SELECT 'role_permissions', 'page_id' UNION ALL
  SELECT 'role_permissions', 'can_view' UNION ALL
  SELECT 'role_permissions', 'can_create' UNION ALL
  SELECT 'role_permissions', 'can_edit' UNION ALL
  SELECT 'role_permissions', 'can_delete' UNION ALL
  SELECT 'schema_migrations', 'filename' UNION ALL
  SELECT 'schema_migrations', 'applied_at' UNION ALL
  SELECT 'shipping_methods', 'id' UNION ALL
  SELECT 'shipping_methods', 'name' UNION ALL
  SELECT 'shipping_methods', 'description' UNION ALL
  SELECT 'shipping_methods', 'type' UNION ALL
  SELECT 'shipping_methods', 'base_cost' UNION ALL
  SELECT 'shipping_methods', 'is_active' UNION ALL
  SELECT 'shipping_methods', 'sort_order' UNION ALL
  SELECT 'shipping_methods', 'created_at' UNION ALL
  SELECT 'shipping_methods', 'updated_at' UNION ALL
  SELECT 'shipping_rules', 'id' UNION ALL
  SELECT 'shipping_rules', 'method_id' UNION ALL
  SELECT 'shipping_rules', 'zone_id' UNION ALL
  SELECT 'shipping_rules', 'rule_type' UNION ALL
  SELECT 'shipping_rules', 'min_value' UNION ALL
  SELECT 'shipping_rules', 'max_value' UNION ALL
  SELECT 'shipping_rules', 'cost' UNION ALL
  SELECT 'shipping_rules', 'is_free' UNION ALL
  SELECT 'shipping_rules', 'is_active' UNION ALL
  SELECT 'shipping_rules', 'created_at' UNION ALL
  SELECT 'shipping_rules', 'product_class' UNION ALL
  SELECT 'shipping_zones', 'id' UNION ALL
  SELECT 'shipping_zones', 'name' UNION ALL
  SELECT 'shipping_zones', 'states' UNION ALL
  SELECT 'shipping_zones', 'pincodes' UNION ALL
  SELECT 'shipping_zones', 'is_active' UNION ALL
  SELECT 'shipping_zones', 'created_at' UNION ALL
  SELECT 'site_settings', 'skey' UNION ALL
  SELECT 'site_settings', 'svalue' UNION ALL
  SELECT 'site_settings', 'updated_at' UNION ALL
  SELECT 'testimonials', 'id' UNION ALL
  SELECT 'testimonials', 'slug' UNION ALL
  SELECT 'testimonials', 'name' UNION ALL
  SELECT 'testimonials', 'avatar' UNION ALL
  SELECT 'testimonials', 'product_image' UNION ALL
  SELECT 'testimonials', 'product_name' UNION ALL
  SELECT 'testimonials', 'text' UNION ALL
  SELECT 'testimonials', 'rating' UNION ALL
  SELECT 'testimonials', 'is_active' UNION ALL
  SELECT 'testimonials', 'sort_order' UNION ALL
  SELECT 'testimonials', 'created_at' UNION ALL
  SELECT 'whatsapp_logs', 'id' UNION ALL
  SELECT 'whatsapp_logs', 'event' UNION ALL
  SELECT 'whatsapp_logs', 'recipient' UNION ALL
  SELECT 'whatsapp_logs', 'template' UNION ALL
  SELECT 'whatsapp_logs', 'order_id' UNION ALL
  SELECT 'whatsapp_logs', 'wa_message_id' UNION ALL
  SELECT 'whatsapp_logs', 'status' UNION ALL
  SELECT 'whatsapp_logs', 'error' UNION ALL
  SELECT 'whatsapp_logs', 'created_at' UNION ALL
  SELECT 'wishlists', 'id' UNION ALL
  SELECT 'wishlists', 'customer_id' UNION ALL
  SELECT 'wishlists', 'product_id' UNION ALL
  SELECT 'wishlists', 'created_at'
) AS expected
JOIN information_schema.TABLES it
  ON it.TABLE_SCHEMA = DATABASE() AND it.TABLE_NAME = expected.tbl
LEFT JOIN information_schema.COLUMNS ic
  ON ic.TABLE_SCHEMA = DATABASE() AND ic.TABLE_NAME = expected.tbl AND ic.COLUMN_NAME = expected.col
WHERE ic.COLUMN_NAME IS NULL
ORDER BY expected.tbl, expected.col;
