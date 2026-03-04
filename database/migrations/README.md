# Suitescape API - Database Migrations

## Migration Organization (March 2, 2026)

The migrations have been consolidated for fresh deployment. Column additions and modifications that were in separate migration files have been merged into the original table creation migrations.

## Consolidated Changes

### 1. `videos` table (`2023_09_15_131348_create_videos_table.php`)
- Added: `moderated_by` (foreignUuid, nullable) - References users table

### 2. `listings` table (`2023_09_18_172809_create_listings_table.php`)
- Added: `latitude` (string, 50, nullable)
- Added: `longitude` (string, 50, nullable)

### 3. `bookings` table (`2023_11_04_012800_create_bookings_table.php`)
- Updated: `status` enum now includes `pending_payment` for GCash/GrabPay payments
- Full enum values: `to_pay`, `pending_payment`, `upcoming`, `ongoing`, `cancelled`, `completed`, `to_rate`

### 4. `invoices` table (`2023_11_04_062042_create_invoices_table.php`)
- Added: `payment_id` (string, 255, nullable) - For PayMongo payment tracking
- Updated: `payment_status` enum values: `pending`, `paid`, `fully_refunded`, `partially_refunded`

### 5. `sections` table (`2024_05_08_020006_create_sections_table.php`)
- Added: `thumbnail` (string, nullable)

## New Tables Added

### `package_inquiries` table (`2026_03_02_100000_create_package_inquiries_table.php`)
- For travel deal package inquiry system
- Fields: id, package_id, user_id, name, email, phone, message, status, admin_notes, read_at, replied_at

### `booking_cancellations` table (`2025_08_07_181850_create_booking_cancellations_table.php`)
- For tracking booking cancellation details

### `webhooks` table (`2025_08_09_132520_create_webhooks_table.php`)
- For PayMongo webhook event logging

## Backup Location

Old modification migrations have been moved to:
```
database/migrations/_old_migrations/
```

These include:
- `2025_02_21_064727_add_moderated_by_to_videos_table.php`
- `2025_05_08_044554_update_payment_status_column_in_invoices_table.php`
- `2025_08_04_185548_add_payment_id_to_invoices_table.php`
- `2025_10_19_235819_add_latitude_longitude_to_listings_table.php`
- `2026_02_22_191915_add_thumbnail_to_sections_table.php`
- `2026_02_28_070230_add_pending_payment_status_to_bookings_table.php`
- `2026_03_02_130036_create_package_inquiries_table.php` (duplicate)

## Fresh Deployment

For a fresh deployment, simply run:
```bash
php artisan migrate:fresh --seed
```

## Existing Database Migration

If migrating an existing database, you may need to use the old migration files from `_old_migrations/` folder to apply incremental changes.

## Migration Order

The migrations are ordered chronologically and will execute in the correct order based on their timestamps.
