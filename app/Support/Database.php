<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Database helper utilities.
 *
 * Provides consistent transaction handling and database operations.
 */
final class Database
{
    /**
     * Execute a callback within a database transaction with proper error handling.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  positive-int  $attempts  Number of retry attempts for deadlocks
     *
     * @throws Throwable
     *
     * @return T
     */
    public static function transaction(Closure $callback, int $attempts = 3): mixed
    {
        /** @var positive-int $attempts */
        return DB::transaction($callback, $attempts);
    }

    /**
     * Execute a callback within a transaction, returning a result wrapper.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     *
     * @return array{success: bool, data: T|null, error: string|null}
     */
    public static function safeTransaction(Closure $callback): array
    {
        try {
            $result = DB::transaction($callback);

            return [
                'success' => true,
                'data' => $result,
                'error' => null,
            ];
        } catch (Throwable $e) {
            logger()->error('Transaction failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute multiple operations atomically.
     *
     * @param  array<Closure>  $operations
     *
     * @return array{success: bool, results: array<mixed>, error: string|null}
     */
    public static function atomicOperations(array $operations): array
    {
        try {
            $results = DB::transaction(function () use ($operations) {
                $results = [];

                foreach ($operations as $key => $operation) {
                    $results[$key] = $operation();
                }

                return $results;
            });

            return [
                'success' => true,
                'results' => $results,
                'error' => null,
            ];
        } catch (Throwable $e) {
            logger()->error('Atomic operations failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'results' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lock a table row for update within a callback.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @template TResult
     *
     * @param  class-string<TModel>  $model
     * @param  Closure(TModel): TResult  $callback
     *
     * @return TResult
     */
    public static function lockForUpdate(string $model, int|string $id, Closure $callback): mixed
    {
        return DB::transaction(function () use ($model, $id, $callback) {
            /** @var TModel $instance */
            $instance = $model::query()
                ->lockForUpdate()
                ->findOrFail($id);

            return $callback($instance);
        });
    }

    /**
     * Check if we're currently inside a transaction.
     */
    public static function inTransaction(): bool
    {
        return DB::transactionLevel() > 0;
    }

    /**
     * Get the current transaction nesting level.
     */
    public static function transactionLevel(): int
    {
        return DB::transactionLevel();
    }
}
