<?php

namespace App\Services\Import\Processors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BaseTableProcessor - Abstract base for all table processors
 * 
 * Supports multiple import strategies:
 * - UPSERT: Insert or update based on unique keys (default)
 * - REPLACE: Delete existing records for date, then insert (source of truth)
 * - INSERT: Just insert new records (no conflict handling)
 * 
 * Supports importing to both operational and archive databases
 */
abstract class BaseTableProcessor
{
    /**
     * Import strategy constants
     */
    const STRATEGY_UPSERT = 'upsert';
    const STRATEGY_REPLACE = 'replace';
    const STRATEGY_INSERT = 'insert';

    /**
     * Get the base table name (without _hot or _archive suffix)
     */
    abstract protected function getTableName(): string;

    /**
     * Get unique key columns for upsert (only used if strategy is UPSERT)
     */
    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date'];
    }

    /**
     * Get fillable columns for this table
     */
    abstract protected function getFillableColumns(): array;

    /**
     * Get import strategy for this table
     * Override in child classes to change strategy
     */
    protected function getImportStrategy(): string
    {
        return self::STRATEGY_UPSERT;
    }

    /**
     * Get database connection to use (operational or analytics)
     * Override in child classes to import to archive
     */
    protected function getDatabaseConnection(): string
    {
        return 'operational';
    }

    /**
     * Get table suffix (_hot or _archive)
     */
    protected function getTableSuffix(): string
    {
        return $this->getDatabaseConnection() === 'operational' ? '_hot' : '_archive';
    }

    /**
     * Transform raw CSV data before import
     * Override this in child classes for custom transformations
     */
    protected function transformData(array $row): array
    {
        return $row;
    }

    /**
     * Validate data before import
     * Override this in child classes for custom validation
     */
    protected function validate(array $row): bool
    {
        return true;
    }

    /**
     * Process and import data for this table
     * 
     * @param array $data Array of rows to import
     * @param string $businessDate Business date for this import
     * @return int Number of rows imported
     */
    public function process(array $data, string $businessDate): int
    {
        if (empty($data)) {
            Log::warning("No data to import for " . $this->getTableName());
            return 0;
        }

        $tableName = $this->getTableName() . $this->getTableSuffix();
        $connection = $this->getDatabaseConnection();
        $strategy = $this->getImportStrategy();

        Log::info("Processing table with {$strategy} strategy", [
            'table' => $tableName,
            'connection' => $connection,
            'rows' => count($data),
            'date' => $businessDate
        ]);

        // Transform and validate data
        $transformed = $this->transformAndValidate($data, $businessDate);

        if (empty($transformed)) {
            Log::warning("No valid data after transformation", ['table' => $tableName]);
            return 0;
        }

        // Execute import based on strategy
        $count = $this->executeImport($transformed, $businessDate, $tableName, $connection, $strategy);

        Log::info("Import completed", [
            'table' => $tableName,
            'rows_imported' => $count,
            'strategy' => $strategy
        ]);

        return $count;
    }

    /**
     * Transform and validate all rows
     */
    protected function transformAndValidate(array $data, string $businessDate): array
    {
        $transformed = [];
        $fillable = $this->getFillableColumns();

        foreach ($data as $index => $row) {
            // Add business_date if not present
            if (!isset($row['business_date'])) {
                $row['business_date'] = $businessDate;
            }

            // Transform data
            $row = $this->transformData($row);

            // Validate
            if (!$this->validate($row)) {
                Log::warning("Validation failed for row", [
                    'table' => $this->getTableName(),
                    'row_index' => $index,
                    'data' => $row
                ]);
                continue;
            }

            // Only keep fillable columns
            $filtered = array_intersect_key($row, array_flip($fillable));

            $transformed[] = $filtered;
        }

        return $transformed;
    }

    /**
     * Execute import based on strategy
     */
    protected function executeImport(
        array $data, 
        string $businessDate, 
        string $tableName, 
        string $connection, 
        string $strategy
    ): int {
        DB::connection($connection)->transaction(function() use ($data, $businessDate, $tableName, $connection, $strategy) {
            if ($strategy === self::STRATEGY_REPLACE) {
                // Delete existing data for this business date
                $this->deleteExistingData($businessDate, $tableName, $connection);
            }

            if ($strategy === self::STRATEGY_UPSERT) {
                // Upsert data
                DB::connection($connection)
                    ->table($tableName)
                    ->upsert($data, $this->getUniqueKeys(), array_keys($data[0]));
            } else {
                // Insert data (for both REPLACE and INSERT strategies)
                DB::connection($connection)
                    ->table($tableName)
                    ->insert($data);
            }
        });

        return count($data);
    }

    /**
     * Delete existing data for business date
     * Override to customize deletion logic (e.g., by store + date)
     */
    protected function deleteExistingData(string $businessDate, string $tableName, string $connection): void
    {
        $deleted = DB::connection($connection)
            ->table($tableName)
            ->where('business_date', $businessDate)
            ->delete();

        Log::info("Deleted existing data", [
            'table' => $tableName,
            'date' => $businessDate,
            'rows_deleted' => $deleted
        ]);
    }

    /**
     * Helper: Parse datetime string to MySQL format
     */
    protected function parseDateTime(?string $datetime): ?string
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($datetime)->toDateTimeString();
        } catch (\Exception $e) {
            Log::warning("Failed to parse datetime", [
                'value' => $datetime,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Helper: Parse date string to MySQL format
     */
    protected function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->toDateString();
        } catch (\Exception $e) {
            Log::warning("Failed to parse date", [
                'value' => $date,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Helper: Convert to numeric or null
     */
    protected function toNumeric($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? $value : null;
    }

    /**
     * Helper: Convert to boolean
     */
    protected function toBoolean($value): ?bool
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
