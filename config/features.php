<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Architecture Feature Flags
    |--------------------------------------------------------------------------
    |
    | These flags control the gradual migration to the new database architecture.
    | Start with all false, enable progressively during migration.
    |
    */

    // Use new three-tier database architecture
    'use_new_database_architecture' => env('USE_NEW_DB_ARCHITECTURE', false),

    // Write to both old and new databases during transition
    'write_to_both_databases' => env('WRITE_TO_BOTH_DBS', false),

    // Use aggregation tables for reports (faster queries)
    'use_aggregation_tables' => env('USE_AGGREGATION_TABLES', false),

    /*
    |--------------------------------------------------------------------------
    | Data Retention Settings
    |--------------------------------------------------------------------------
    */

    // How many days to keep in operational (hot) database
    'operational_retention_days' => env('OPERATIONAL_RETENTION_DAYS', 90),

    // Enable compression for archive tables
    'archive_compression_enabled' => env('ARCHIVE_COMPRESSION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Aggregation Settings
    |--------------------------------------------------------------------------
    */

    'enable_daily_aggregations' => env('ENABLE_DAILY_AGGREGATIONS', true),
    'enable_weekly_aggregations' => env('ENABLE_WEEKLY_AGGREGATIONS', true),
    'enable_monthly_aggregations' => env('ENABLE_MONTHLY_AGGREGATIONS', true),

];