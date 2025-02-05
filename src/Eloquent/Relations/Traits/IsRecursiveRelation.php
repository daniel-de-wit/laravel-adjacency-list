<?php

namespace Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 */
trait IsRecursiveRelation
{
    /**
     * Whether to include the parent model.
     *
     * @var bool
     */
    protected $andSelf;

    /**
     * Create a new recursive relationship instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query
     * @param TDeclaringModel $parent
     * @param string $foreignKey
     * @param string $localKey
     * @param bool $andSelf
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey, $andSelf)
    {
        $this->andSelf = $andSelf;

        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Build model dictionary.
     *
     * @param \Illuminate\Database\Eloquent\Collection<array-key, TRelatedModel> $results
     * @return array<int|string, array<int, TRelatedModel>>
     */
    protected function buildDictionary(Collection $results)
    {
        return $results->mapToDictionary(function (Model $result) {
            return [$result->getFirstPathSegment() => $result];
        })->all();
    }

    /**
     * Get the fully qualified local key name.
     *
     * @return string
     */
    public function getQualifiedLocalKeyName()
    {
        return $this->qualifyColumn($this->localKey);
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param string $method
     * @param array<int|string, mixed> $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $methods = ['update', 'increment', 'decrement', 'delete', 'forceDelete'];

        if (in_array($method, $methods)) {
            $expression = $this->query->getQuery()->from;

            $table = $this->parent->getTable();

            $this->query->getQuery()->from = $table;

            $this->query->getModel()->setTable($table);

            $keys = $this->query->getQuery()->newQuery()->from($expression)->select($this->localKey);

            return $this->query->whereIn($this->getQualifiedLocalKeyName(), $keys)->$method(...$parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Replace table hash with expression name in self-relation aggregate queries.
     *
     * @param \Illuminate\Database\Eloquent\Builder<*> $query
     * @param \Illuminate\Database\Query\Expression<*> $expression
     * @return \Illuminate\Database\Query\Expression<*>
     */
    protected function replaceTableHash(Builder $query, Expression $expression)
    {
        return new Expression(
            str_replace(
                $query->getGrammar()->wrap(
                    $this->getRelationCountHash(false)
                ),
                $query->getGrammar()->wrap(
                    $query->getModel()->getExpressionName()
                ),
                (string) $expression->getValue(
                    $query->getGrammar()
                ),
            )
        );
    }
}
