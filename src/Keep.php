<?php

namespace Laragear\Capstone;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;
use function is_array;
use function is_string;
use function value;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
class Keep
{
    /**
     * Create a new Keep instance.
     *
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TModel>): void)|string[]  $filter
     * @param  array{0: mixed, 1: bool}|array{}  $condition
     * @internal
     */
    public function __construct(
        protected Model $model,
        protected int $amount = 5,
        protected ?DateTimeInterface $time = null,
        protected Closure|array $filter = [],
        protected mixed $forceDelete = false,
        protected string $sortColumn = '',
        protected string $sort = 'desc',
        protected string $afterColumn = '',
        protected DateTimeInterface|string|null $after = null,
        protected array $condition = [true, true],
    )
    {
        $this->sortColumn = $this->model->getKeyName();
    }

    /**
     * Sets the columns or the query to group the models in the table.
     *
     * @param (\Closure(\Illuminate\Database\Eloquent\Builder<TModel>): void)|string[]|string ...$columns
     * @return $this
     */
    public function same(Closure|array|string $columns): static
    {
        $this->filter = is_string($columns) ? [$columns] : $columns;

        return $this;
    }



    /**
     * Sets the limit for the group of models in the table.
     *
     * @return $this
     */
    public function by(int $amount): static
    {
        if (0 > $this->amount = $amount) {
            throw new InvalidArgumentException('Amount must be greater than 0.');
        }

        return $this;
    }

    /**
     * Disables the amount limit for the group of models in the table.
     *
     * @return $this
     */
    public function all(): static
    {
        return $this->by(0);
    }

    /**
     * Sets the limit for the group based on a relative time.
     *
     * @return $this
     */
    public function after(DateTimeInterface|string $after, string $column = ''): static
    {
        if (($this->after = Carbon::parse($after))->isFuture()) {
            throw new InvalidArgumentException('The time cannot be set into the future.');
        }

        $this->afterColumn = $column ?: $this->model->getCreatedAtColumn();

        return $this;
    }

    /**
     * Order the models to keep from the oldest (or greater) to the latest (or lesser).
     *
     * @return $this
     */
    public function oldest(string $column = ''): static
    {
        $this->sortColumn = $column ?: $this->sortColumn;
        $this->sort = 'asc';

        return $this;
    }

    /**
     * Order the models to keep from the latest (or lesser) to the oldest (or greater).
     *
     * @return $this
     */
    public function latest(string $column = ''): static
    {
        $this->sortColumn = $column ?: $this->sortColumn;
        $this->sort = 'desc';

        return $this;
    }

    /**
     * Deletes models outside the limit using
     *
     * @param  (\Closure(TModel):mixed)|mixed  $force
     * @return $this
     */
    public function forceDelete(mixed $force = true): static
    {
        $this->forceDelete = $force;

        return $this;
    }

    /**
     * Run the deletion logic only when the condition evaluates to true.
     *
     * @return $this
     */
    public function deleteWhen(mixed $condition): static
    {
        $this->condition = [$condition, false];

        return $this;
    }

    /**
     * Run the deletion logic only when the condition evaluates to false.
     * @return $this
     */
    public function deleteUnless(mixed $condition): static
    {
        $this->condition = [$condition, true];

        return $this;
    }

    /**
     * Delete the records that are out-of-bounds.
     */
    public function performDeletion(): void
    {
        if (!$this->filter) {
            throw new RuntimeException('There are no columns set to keep the models in the table.');
        }

        if ($this->shouldNotDelete()) {
            return;
        }

        $query = $this->createDeletableQuery();

        $query = $query->clone()
            ->whereKeyNot(
                $query
                    ->orderBy($this->sortColumn, $this->sort)
                    ->when($this->amount)->limit($this->amount)
                    ->when($this->afterColumn)->where($this->afterColumn, '>', $this->after)
                    ->select($this->model->getQualifiedKeyName()),
            );

        if (value($this->forceDelete, $this->model)) {
            $query->forceDelete();
        } else {
            $query->delete();
        }
    }

    /**
     * Check if the condition should stop the delete operation.
     */
    protected function shouldNotDelete(): bool
    {
        return $this->condition[1] !== (bool) value($this->condition[0]);
    }

    /**
     * Create the query that will be executed to delete the records.
     */
    protected function createDeletableQuery(): Builder
    {
        $query = $this->model->newQuery();

        if (is_array($this->filter)) {
            foreach ($this->filter as $column) {
                $query->where($column, $this->model->getAttributes()[$column] ?? null);
            }
        } else {
            ($this->filter)($query); // @phpstan-ignore-line
        }

        return $query;
    }
}
