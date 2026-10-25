<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait SimulatesDatabaseWriteFailure
{
    protected function withDatabaseWritesDisabled(callable $callback): mixed
    {
        DB::statement('PRAGMA query_only = ON');

        try {
            return $callback();
        } finally {
            DB::statement('PRAGMA query_only = OFF');
        }
    }
}
