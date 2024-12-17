<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Laragear\Capstone\HasCap;
use Laragear\Capstone\Keep;
use Orchestra\Testbench\TestCase;

class HasCapTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app
            ->make(SchemaBuilder::class)
            ->create('test_models', static function (Blueprint $table): void {
                $table->id();
                $table->integer('group')->default(1);
                $table->string('title');
                $table->timestamp('starts_at')->useCurrent();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });
    }

    public function test_performs_deletion_on_creation(): void
    {
        $this->app->extend(Keep::class, function () {
            return $this->mock(Keep::class, function ($mock) {
                $mock->expects('same')->with('group_id')->once();
                $mock->expects('performDeletion')->once();
            });
        });

        $model = TestModelWithHasCap::create(['title' => 'test']);

        $model->update(['title' => 'new test']);
    }
}

/**
 * @method self create(array $attributes = [])
 */
class TestModelWithHasCap extends Model
{
    use HasCap;

    protected $table = 'test_models';

    protected $fillable = ['title', 'starts_at', 'ends_at'];

    public function keep(Keep $keep): void
    {
        $keep->same('group_id');
    }
}
