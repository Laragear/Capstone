<?php

namespace Laragear\Capstone;

use function app;

trait HasCap
{
    /**
     * Boot the current trait.
     */
    protected static function bootHasCap(): void
    {
        static::created(static function (self $model): void {
            $keep = app(Keep::class, ['model' => $model]);

            $model->keep($keep);

            $keep->performDeletion();
        });
    }

    /**
     * Configure the records that should be kept and forgotten.
     */
    abstract public function keep(Keep $keep): void;
}
