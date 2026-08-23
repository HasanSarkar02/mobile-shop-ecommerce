<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseLockRetry
{
    private const MYSQL_DEADLOCK = 1213;

    private const MYSQL_LOCK_WAIT_TIMEOUT = 1205;

    private const MYSQL_SERIALIZATION_FAILURE = 40001;

    private const MYSQL_TABLE_DEF_CHANGED = 1412;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  int  $attempts  maximum number of transaction attempts (default 3)
     * @return T
     */
    public static function run(callable $callback, int $attempts = 3): mixed
    {
        $attempt = 1;

        while (true) {
            try {
                return DB::transaction($callback);
            } catch (Throwable $e) {
                $concurrencyError = self::isConcurrencyError($e);

                if (! $concurrencyError || $attempt >= $attempts) {
                    if ($concurrencyError) {
                        self::resetTransactionLevel();
                    }

                    throw $e;
                }

                $attempt++;

                // A deadlock victim may have been rolled back by the server
                // while the connection still believes a transaction is open.
                // Reset the connection to a clean state before retrying.
                self::resetTransactionLevel();

                // Small bounded backoff between attempts.
                usleep(50_000 * $attempt);
            }
        }
    }

    private static function isConcurrencyError(Throwable $e): bool
    {

        $codes = [self::MYSQL_DEADLOCK, self::MYSQL_LOCK_WAIT_TIMEOUT, self::MYSQL_SERIALIZATION_FAILURE, self::MYSQL_TABLE_DEF_CHANGED];

        $candidate = $e;

        while ($candidate !== null) {
            if ($candidate instanceof QueryException && is_array($candidate->errorInfo)) {
                $code = (int) ($candidate->errorInfo[1] ?? 0);

                if (in_array($code, $codes, true)) {
                    return true;
                }
            }

            if ($candidate instanceof \PDOException) {
                $info = $candidate->errorInfo;
                $code = is_array($info) && isset($info[1]) ? (int) $info[1] : (int) $candidate->getCode();

                if (in_array($code, $codes, true)) {
                    return true;
                }
            }

            $candidate = $candidate->getPrevious();
        }

        return false;
    }

    private static function resetTransactionLevel(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}
