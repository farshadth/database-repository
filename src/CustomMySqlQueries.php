<?php

namespace Nanvaie\DatabaseRepository;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait CustomMySqlQueries
{
    protected $dataTypes = [
        'bool' => 'bool',
        'boolean' => 'bool',
        'bit' => 'string',
        'int' => 'int',
        'integer' => 'int',
        'tinyint' => 'int',
        'smallint' => 'int',
        'mediumint' => 'int',
        'bigint' => 'int',
        'float' => 'float',
        'double' => 'float',
        'json' => 'string',
        'char' => 'string',
        'varchar' => 'string',
        'binary' => 'string',
        'varbinary' => 'string',
        'tinyblob' => 'string',
        'tinytext' => 'string',
        'text' => 'string',
        'blob' => 'string',
        'mediumtext' => 'string',
        'mediumblob' => 'string',
        'longtext' => 'string',
        'longblob' => 'string',
        'enum' => 'string',
        'date' => 'string',
        'time' => 'string',
        'datetime' => 'string',
        'timestamp' => 'string',
        'point' => 'string',
    ];

    /**
     * Extract all columns from a given table.
     */
    public function getAllColumnsInTable(string $tableName): Collection
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
            ->where('TABLE_NAME', $tableName)
            ->orderBy('ORDINAL_POSITION')
            ->get();
    }

    /**
     * Extract all table names.
     */
    public function getAllTableNames(): Collection
    {
        return DB::table('INFORMATION_SCHEMA.TABLES')
            ->select('TABLE_NAME')
            ->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
            ->where('TABLE_NAME', '<>', 'migrations')
            ->get();
    }

    /**
     * Extract all foreign keys from a given table. Foreign key's relations must define in MySql!
     */
    public function extractForeignKeys(string $tableName): Collection
    {
        $foreignKeys = DB::table('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
            ->where('TABLE_NAME', $tableName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->orderBy('ORDINAL_POSITION')
            ->get();

        $foreignKeys->each(function ($foreignKey) {
            $foreignKey->VARIABLE_NAME = Str::camel(str_replace('_id', '', $foreignKey->COLUMN_NAME));
            $foreignKey->ENTITY_DATA_TYPE = ucfirst(Str::camel(Str::singular($foreignKey->REFERENCED_TABLE_NAME)));
        });

        return $foreignKeys;
    }

    /**
     * Extract all indexes from a given table!
     */
    public function extractIndexes(string $tableName): Collection
    {
        $indexes = DB::table('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
            ->where('TABLE_NAME', $tableName)
            ->where('CONSTRAINT_NAME', '!=' ,'PRIMARY')
            ->whereNull('REFERENCED_TABLE_NAME')
            ->orderBy('ORDINAL_POSITION')
            ->get();

        return $indexes;
    }
}
