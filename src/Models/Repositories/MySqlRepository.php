<?php

namespace Nanvaie\DatabaseRepository\Models\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nanvaie\DatabaseRepository\Models\Entity\Entity;
use Nanvaie\DatabaseRepository\Models\Enums\GriewFilterOperator;
use Nanvaie\DatabaseRepository\Models\Factories\IFactory;

abstract class MySqlRepository
{
    private null|ConnectionInterface $alternativeDbConnection;

    protected string $primaryKey = 'id';

    protected string $table = '';

    protected bool $softDelete = false;

    private bool $withTrashed = false;

    protected IFactory $factory;

    public function __construct()
    {
        $this->alternativeDbConnection = null;
    }

    /**
     * Notice: this function cannot be used in async jobs because the connection is not serializable!
     */
    public function changeDatabaseConnection($connection)
    {
        $this->alternativeDbConnection = $connection;
    }

    public function newQuery(): Builder
    {
        if (is_null($this->alternativeDbConnection)) {
            $query = app('db')->table($this->table);
        } else {
            $query = $this->alternativeDbConnection->table($this->table);
        }

//        if ($this->softDelete) {
//            if (!$this->withTrashed) {
//                $query = $query->whereNull('deleted_at');
//            } else {
//                $this->withTrashed = false;
//            }
//        }

        return $query;
    }

//    public function withTrashed(): MySqlRepository
//    {
//        $this->withTrashed = true;
//
//        return $this;
//    }

    public function getAllForGridView(null|int &$total, int $offset = 0, int $count = 0, array $orders = [], array $filters = []): Collection
    {
        $query = $this->newQuery();

        $result = $this->processGridViewQuery($query, $total, $offset, $count, $orders, $filters)->get();

        return $this->factory->makeCollectionOfEntities($result);
    }

    public function raw($str)
    {
        if (is_null($this->alternativeDbConnection)) {
            return app('db')->raw($str);
        }
        return $this->alternativeDbConnection->raw($str);
    }

    public function exists($columnValue, $columnName = null)
    {
        if (is_null($columnName)) {
            $columnName = $this->primaryKey;
        }
        return $this->newQuery()->where($columnName, $columnValue)->exists();
    }

    /**
     * this is for validation purpose look at AppServiceProvider
     */
    public function valueExists($attribute, $value, $ignoredPrimaryKey = null)
    {
        $query = $this->newQuery();

        if ($this->softDelete) {
            $query->whereNull('deleted_at');
        }

        $query->where($attribute, $value);

        if (!is_null($ignoredPrimaryKey)) {
            $query->where($this->primaryKey, '<>', $ignoredPrimaryKey);
        }

        return $query->exists();
    }

     public function updateOrCreate(Entity $model)
    {
        if ($this->exists($model->getPrimaryKey())) {
            $this->update($model);
        } else {
            $this->create($model);
        }
    }

    /**
     * @param Entity $model
     */
    public function createIfNotExists(Entity $model)
    {
        if (!$this->exists($model->getPrimaryKey())) {
            $this->create($model);
        }
    }

    /**
     * It returns maximum row Id
     * @return int|mixed
     */
    public function getMaxId()
    {
        $entity = $this->newQuery()->orderByDesc($this->primaryKey)->first();
        if ($entity) {
            return $entity->id;
        } else {
            return 0;
        }
    }

    protected function processGridViewQuery(Builder $query, null|int &$total, int $offset = 0, int $count = 0, array $orders = [], array $filters = []): Builder
    {
        if ($orders) {
            $query = $this->processOrder($query, $orders);
        }

        if ($filters) {
            $query = $this->processFilter($query, $filters);
        }

        $total = $query->count();

        if ($count) {
            $query->offset($offset);
            $query->limit($count);
        }

        return $query;
    }

    /**
     * @param Builder $query
     * @param array $orders
     * @return Builder
     */
    protected function processOrder(Builder $query, array $orders): Builder
    {
        foreach ($orders as $order) {
            $query->orderBy($order->name, $order->type);
        }
        return $query;
    }

    /**
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    protected function processFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $filter) {
            switch (strtolower(Str::snake($filter->operator))) {
                case GriewFilterOperator::IS_NULL:
                    $query->whereNull($filter->name);
                    break;
                case GriewFilterOperator::IS_NOT_NULL:
                    $query->whereNotNull($filter->name);
                    break;
                case GriewFilterOperator::IS_EQUAL_TO:
                    if (is_string($filter->operand1) && Str::contains($filter->operand1, '|')) {
                        // create in functionality with equal string
                        $arr = array_filter(explode('|', $filter->operand1));
                        $query->whereIn($filter->name, $arr);
                    } else {
                        $query->where($filter->name, '=', $filter->operand1);
                    }
                    break;
                case GriewFilterOperator::IS_NOT_EQUAL_TO:
                    if (is_string($filter->operand1) && Str::contains($filter->operand1, '|')) {
                        // create in functionality with equal string
                        $arr = array_filter(explode('|', $filter->operand1));
                        $query->whereNotIn($filter->name, $arr);
                    } else {
                        $query->where($filter->name, '<>', $filter->operand1);
                    }
                    break;
                case GriewFilterOperator::START_WITH:
                    $query->where($filter->name, 'LIKE', $filter->operand1 . '%');
                    break;
                case GriewFilterOperator::DOES_NOT_CONTAINS:
                    $query->where($filter->name, 'NOT LIKE', '%' . $filter->operand1 . '%');
                    break;
                case GriewFilterOperator::CONTAINS:
                    $query->where($filter->name, 'LIKE', '%' . $filter->operand1 . '%');
                    break;
                case GriewFilterOperator::ENDS_WITH:
                    $query->where($filter->name, 'LIKE', '%' . $filter->operand1);
                    break;
                case GriewFilterOperator::IN:
                    $query->whereIn($filter->name, $filter->operand1);
                    break;
                case GriewFilterOperator::NOT_IN:
                    $query->whereNotIn($filter->name, $filter->operand1);
                    break;
                case GriewFilterOperator::BETWEEN:
                    $query->whereBetween($filter->name, array($filter->operand1, $filter->operand2));
                    break;
                case GriewFilterOperator::IS_AFTER_THAN_OR_EQUAL_TO:
                case GriewFilterOperator::IS_GREATER_THAN_OR_EQUAL_TO:
                    $query->where($filter->name, '>=', $filter->operand1);
                    break;
                case GriewFilterOperator::IS_AFTER_THAN:
                case GriewFilterOperator::IS_GREATER_THAN:
                    $query->where($filter->name, '>', $filter->operand1);
                    break;
                case GriewFilterOperator::IS_LESS_THAN_OR_EQUAL_TO:
                case GriewFilterOperator::IS_BEFORE_THAN_OR_EQUAL_TO:
                    $query->where($filter->name, '<=', $filter->operand1);
                    break;
                case GriewFilterOperator::IS_LESS_THAN:
                case GriewFilterOperator::IS_BEFORE_THAN:
                    $query->where($filter->name, '<', $filter->operand1);
                    break;
            }
        }

        return $query;
    }
}
