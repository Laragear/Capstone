# Capstone
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/{project}.svg)](https://packagist.org/packages/laragear/{project})
[![Latest stable test run](https://github.com/Laragear/Capstone/actions/workflows/php.yml/badge.svg)](https://github.com/Laragear/Capstone/actions/workflows/php.yml)
[![Codecov Coverage](https://codecov.io/gh/Laragear/Capstone/graph/badge.svg?token=C9Cc6XOxXE)](https://codecov.io/gh/Laragear/Capstone)
[![Maintainability](https://qlty.sh/badges/42c707d7-ce2e-4726-a1be-29ea151de711/maintainability.svg)](https://qlty.sh/gh/Laragear/projects/Capstone)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_Capstone&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_Capstone)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/12.x/octane#introduction)

Limit the number of model records by amount or date.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laragear\Capstone\HasCap;
use Laragear\Capstone\Keep;

class Draft extends Model
{
    use HasCap;
    
    /**
     * Limits the model records in the table. 
     */
    public function keep(Keep $keep)
    {
        $keep->same('post_id')->by(10);
    }
}
```

## Become a sponsor

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable.

## Requirements

* PHP 8.2
* Laravel 11 or later

## Installation

You can install the package via Composer. 

```bash
composer require laragear/capstone
```

## How this works?

This library can limit how many models can be persisted in a table. The only requisite is a primary key, and that these models share a particular column value.

For example, imagine a `Post` model having `Drafts`. To limit the amount of drafts created, this library does it automatically using the `Laragear\Capstone\HasCap` trait. By default, it uses a limit of 5. It only requires a column to group the drafts to keep.

## Set up

Find the Eloquent Model you want to limit, add the `Laragear\Capstone\HasCap` trait with the `keep()` method.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laragear\Capstone\HasCap;
use Laragear\Capstone\Keep;

class Draft extends Model
{
    use HasCap;
    
    public function keep(Keep $keep)
    {
       //
    }
}
```

The next step is to configure which column to group the models to keep, otherwise the whole table would be affected. The `keep()` method receives a `Laragear\Capstone\Keep` instance you can use to point out the column (or columns) to group the models.

For example, we can use the `post_id` to group the drafts for a particular post with the `same()` method and the name of the column, which will exclude drafts with different `post_id` from the operation. 

```php
use Laragear\Capstone\Keep;

public function keep(Keep $keep)
{
    $keep->same('post_id');
}
``` 

You may also set more than one column to match more records in the database.

```php
use Laragear\Capstone\Keep;

public function keep(Keep $keep)
{
    $keep->same('post_id', 'user_id');
}
``` 

Alternatively, you may use a callback that modifies the query to group the records to limit. The callback should receive the `Illuminate\Database\Eloquent\Builder` instance.

```php
use Laragear\Capstone\Keep;
use Illuminate\Contracts\Database\Eloquent\Builder

public function keep(Keep $keep)
{
    $keep->same(function (Builder $query) {
        $query
            ->where('post_id', $this->post_id)
            ->whereRelation('post.user', 'is_vip', false)
    });
}
```

> [!IMPORTANT]
> 
> The query is using only to group the records to keep and delete, not to [apply limits](#amount-limit).

### Amount limit

By default, the records are limited by 5. Each time a new record is inserted into the database, the oldest is deleted. You may change the amount using the `by()` method.

```php
use Laragear\Capstone\Keep;

public function keep(Keep $keep)
{
    $keep->same('post_id')->by(10);
}
```

You also disable the limit by setting it to `0` (zero) or using `all()`.

```php
$keep->same('post_id')->all();
```

> [!TIP]
>
> You can combine both `by()` and [`after()`](#time-limit) to limit records by amount and time, respectively.

### Time limit

You can also limit the records to keep by a relative date in the past using the `after()` method, which accepts a `DateTimeInterface` like a `Carbon` instance or a string to be parsed through [`strtotime`](https://www.php.net/manual/en/function.strtotime.php).

```php
use Laragear\Capstone\Keep;

public function keep(Keep $keep)
{
    $keep->same('post_id')->after('-14 days');
}
```

By default, it uses the model "created at" column to pick which records should be kept. You may change the column name using a second parameter.

```php
$keep->same('post_id')->after(now()->subMonth(), 'updated_at');
```

> [!TIP]
> 
> You can combine both [`by()`](#amount-limit) and `after()` to limit records by amount and time, respectively.

### Ordering

When the models to keep are queried to exclude them from the deletion, these are ordered by their primary key in descending order. If your models have unordered primary keys, like UUID v4, or strings, you may change the query order by their creation timestamp using `latest()` and `oldest()`.

```php
use Laragear\Capstone\Keep;

public function keep(Keep $keep)
{
    $keep->same('post_id')->oldest();
}
```

Both methods also accepts the column to sort the records if these don't have a creation timestamp, or you need to sort them using another column.

```php
$cap->same('post_id')->latest('saved_at');
```

### Force delete

Records that are not kept will be removed using the `delete()` method. If your model uses [soft deletes](https://laravel.com/docs/12.x/eloquent#soft-deleting), you may remove it completely from the databases using `forceDeleting()` method.

```php
use Laragear\Capstone\Keep;

public function keep(Keep $keep)
{
    $keep->same('post_id')->forceDelete();
}
```

You may also use the `forceDelete()` with a callback that receives the model and returns the result of the condition.

```php
$keep->same('post_id')->forceDelete(fn ($draft) => $draft->author->isNotVip());
```

### Cancelling deletion

If you require to disable deleting out-of-bounds records, then you can use `deleteWhen()` and `deleteUnless()` with a condition or callback that evaluates to _truthy_ or _falsy_, respectively, as long it's not `null`.

```php
use Laragear\Capstone\Keep;

public function keep(Keep $keep)
{
    $keep->same('post_id')->deleteWhen($this->isVip());
}
```

## Laravel Octane compatibility

- There are no singletons using a stale app instance.
- There are no singletons using a stale config instance.
- There are no singletons using a stale request instance.
- There are no static properties written during a request.

There should be no problems using this package with Laravel Octane.

## Security

If you discover any security related issues, issue a [Security Advisor](https://github.com/Laragear/Capstone/security/advisories/new)

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2025 Laravel LLC.
