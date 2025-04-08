<?php

namespace Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laragear\Capstone\Keep;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function now;

class KeepTest extends TestCase
{
    /**
     * @var \Mockery\MockInterface&\Illuminate\Database\Eloquent\Model
     */
    protected MockInterface $model;

    /**
     * @var \Mockery\MockInterface&\Illuminate\Database\Eloquent\Builder
     */
    protected MockInterface $builder;

    /**
     * @var \Mockery\MockInterface&\Illuminate\Database\Eloquent\Builder
     */
    protected MockInterface $builderClone;

    protected function setUp(): void
    {
        $this->builderClone = Mockery::mock(Builder::class);

        $this->builder = Mockery::mock(Builder::class);
        $this->builder->expects('clone')->zeroOrMoreTimes()->andReturn($this->builderClone);

        $this->model = Mockery::mock(Model::class);
        $this->model->expects('newQuery')->zeroOrMoreTimes()->andReturn($this->builder);

        $this->builderClone->expects('whereKeyNot')->zeroOrMoreTimes()->with($this->builder)->andReturnSelf();
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    protected function keep(): Keep
    {
        return new Keep($this->model);
    }

    public function test_throws_when_no_filter_set(): void
    {
        $this->builderClone->expects('delete')->never();
        $this->model->expects('getKeyName')->andReturn('id');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There are no columns set to keep the models in the table.');

        $this->keep()->performDeletion();
    }

    public function test_limits_by_five_by_default(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->performDeletion();
    }

    public function test_limits_by_zero(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->twice()->andReturn('id');
        $this->model->expects('getAttributes')->twice()->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->twice()->andReturn('model.id');

        $this->builder->expects('where')->twice()->with('model_id', null);

        $this->builder->expects('orderBy')->twice()->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->twice()->with(0)->andReturnSelf();
        $this->builder->expects('limit')->twice()->with(0)->andReturnSelf();
        $this->builder->expects('when')->twice()->with('')->andReturnSelf();
        $this->builder->expects('where')->twice()->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->twice()->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete')->twice();

        $this->keep()->same('model_id')->by(0)->performDeletion();
        $this->keep()->same('model_id')->all()->performDeletion();
    }

    public function test_limits_throws_exception_if_amount_below_zero(): void
    {
        $this->builderClone->expects('delete')->never();
        $this->model->expects('getKeyName')->andReturn('id');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than 0.');

        $this->keep()->same('model_id')->by(-1)->performDeletion();
    }

    public function test_same_with_string(): void
    {
        $this->expectNotToPerformAssertions();

        $limit = 5;

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['model_id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', 1);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with($limit)->andReturnSelf();
        $this->builder->expects('limit')->with($limit)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->by($limit)->performDeletion();
    }

    public function test_same_with_string_array(): void
    {
        $this->expectNotToPerformAssertions();

        $limit = 5;

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->twice()->andReturn(['foo_id' => 1, 'bar_id' => 2]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('foo_id', 1);
        $this->builder->expects('where')->with('bar_id', 2);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with($limit)->andReturnSelf();
        $this->builder->expects('limit')->with($limit)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same(['foo_id', 'bar_id'])->by($limit)->performDeletion();
    }

    public function test_same_with_closure(): void
    {
        $this->expectNotToPerformAssertions();

        $limit = 5;

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->never();
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('foo', 'bar');

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with($limit)->andReturnSelf();
        $this->builder->expects('limit')->with($limit)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same(fn($query) => $query->where('foo', 'bar'))->by($limit)->performDeletion();
    }

    public function test_by(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with(10)->andReturnSelf();
        $this->builder->expects('limit')->with(10)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->by(10)->performDeletion();
    }

    public function test_after_with_datetime(): void
    {
        $limit = 5;
        $now = now();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['model_id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');
        $this->model->expects('getCreatedAtColumn')->andReturn('created_at');

        $this->builder->expects('where')->with('model_id', 1);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with($limit)->andReturnSelf();
        $this->builder->expects('limit')->with($limit)->andReturnSelf();
        $this->builder->expects('when')->with('created_at')->andReturnSelf();
        $this->builder->expects('where')->withArgs(function ($column, $operator, Carbon $value) use ($now): bool {
            static::assertSame('created_at', $column);
            static::assertSame('>', $operator);
            static::assertEquals($value, $now);

            return true;
        })->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->after($now)->performDeletion();
    }

    public function test_after_with_string(): void
    {
        $limit = 5;
        $now = '2024-01-01 00:00:00';

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['model_id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');
        $this->model->expects('getCreatedAtColumn')->andReturn('created_at');

        $this->builder->expects('where')->with('model_id', 1);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with($limit)->andReturnSelf();
        $this->builder->expects('limit')->with($limit)->andReturnSelf();
        $this->builder->expects('when')->with('created_at')->andReturnSelf();
        $this->builder->expects('where')->withArgs(function ($column, $operator, Carbon $value) use ($now): bool {
            static::assertSame('created_at', $column);
            static::assertSame('>', $operator);
            static::assertSame($value->toDateTimeString(), $now);

            return true;
        })->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->after($now)->performDeletion();
    }

    public function test_after_with_column_name(): void
    {
        $this->expectNotToPerformAssertions();

        $limit = 5;
        $now = now();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['model_id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');
        $this->model->expects('getCreatedAtColumn')->never();

        $this->builder->expects('where')->with('model_id', 1);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with($limit)->andReturnSelf();
        $this->builder->expects('limit')->with($limit)->andReturnSelf();
        $this->builder->expects('when')->with('test_at')->andReturnSelf();
        $this->builder->expects('where')->with('test_at', '>', Mockery::type(Carbon::class))->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->after($now, 'test_at')->performDeletion();
    }

    public function test_after_throws_exception_if_future(): void
    {
        $this->builderClone->expects('delete')->never();
        $this->model->expects('getKeyName')->andReturn('id');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The time cannot be set into the future.');

        $this->keep()->same('model_id')->after(now()->addSecond())->performDeletion();
    }

    public function test_latest_with_default_primary_key(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->latest()->performDeletion();
    }

    public function test_latest_with_custom_column(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('test_at', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->latest('test_at')->performDeletion();
    }

    public function test_oldest_with_default_primary_key(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('id', 'asc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->oldest()->performDeletion();
    }

    public function test_oldest_with_custom_column(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('test_at', 'asc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->oldest('test_at')->performDeletion();
    }

    public function test_force_delete(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('forceDelete');

        $this->keep()->same('model_id')->forceDelete()->performDeletion();
    }

    public function test_force_delete_with_condition_truthy(): void
    {
        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');
        $this->model->expects('getKey')->andReturn('test_key');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('forceDelete');

        $this->keep()->same('model_id')->forceDelete(function ($model) {
            static::assertSame($this->model, $model);

            return $model->getKey();
        })->performDeletion();
    }

    public function test_force_delete_with_condition_falsy(): void
    {
        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');
        $this->model->expects('getKey')->andReturnNull();

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->forceDelete(function ($model) {
            static::assertSame($this->model, $model);

            return $model->getKey();
        })->performDeletion();
    }

    public function test_delete_when_truthy_deletes(): void
    {
        $this->expectNotToPerformAssertions();

        $this->builderClone->expects('delete')->never();
        $this->model->expects('getKeyName')->andReturn('id');

        $this->keep()->same('model_id')->deleteWhen(fn() => 1)->performDeletion();
    }

    public function test_delete_when_falsy_does_not_delete(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->deleteWhen(fn() => 0)->performDeletion();
    }

    public function test_delete_unless_truthy_does_not_delete(): void
    {
        $this->expectNotToPerformAssertions();

        $this->builderClone->expects('delete')->never();
        $this->model->expects('getKeyName')->andReturn('id');

        $this->keep()->same('model_id')->deleteUnless(fn() => 0)->performDeletion();
    }

    public function test_delete_unless_falsy_deletes(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->expects('getKeyName')->andReturn('id');
        $this->model->expects('getAttributes')->andReturn(['id' => 1]);
        $this->model->expects('getQualifiedKeyName')->andReturn('model.id');

        $this->builder->expects('where')->with('model_id', null);

        $this->builder->expects('orderBy')->with('id', 'desc')->andReturnSelf();
        $this->builder->expects('when')->with(5)->andReturnSelf();
        $this->builder->expects('limit')->with(5)->andReturnSelf();
        $this->builder->expects('when')->with('')->andReturnSelf();
        $this->builder->expects('where')->with('', '>', null)->andReturnSelf();
        $this->builder->expects('select')->with('model.id')->andReturnSelf();

        $this->builderClone->expects('delete');

        $this->keep()->same('model_id')->deleteUnless(fn() => 1)->performDeletion();
    }
}
