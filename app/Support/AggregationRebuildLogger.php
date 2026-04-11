<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class AggregationRebuildLogger
{
    protected static function logger()
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/aggregation-rebuild.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);
    }

    public static function debug(string $message, array $context = []): void
    {
        static::logger()->debug($message, static::normalizeContext($context));
    }

    public static function info(string $message, array $context = []): void
    {
        static::logger()->info($message, static::normalizeContext($context));
    }

    public static function warning(string $message, array $context = []): void
    {
        static::logger()->warning($message, static::normalizeContext($context));
    }

    public static function error(string $message, array $context = []): void
    {
        static::logger()->error($message, static::normalizeContext($context));
    }

    public static function critical(string $message, array $context = []): void
    {
        static::logger()->critical($message, static::normalizeContext($context));
    }

    protected static function normalizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $context[$key . '_message'] = $value->getMessage();
                $context[$key . '_file'] = $value->getFile();
                $context[$key . '_line'] = $value->getLine();
                $context[$key . '_trace'] = $value->getTraceAsString();
                unset($context[$key]);
            }
        }

        return $context;
    }
}