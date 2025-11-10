
# Laravel Eloquent Searchable

[//]: # (![Packagist Version]&#40;https://img.shields.io/packagist/v/your-vendor/eloquent-searchable&#41;  )
[//]: # (![Packagist Downloads]&#40;https://img.shields.io/packagist/dt/your-vendor/eloquent-searchable&#41;  )
[//]: # (![License]&#40;https://img.shields.io/github/license/your-vendor/eloquent-searchable&#41;)

**Laravel Eloquent Searchable** is a lightweight package that provides dynamic and flexible search functionality for your Eloquent models. Simply define searchable fields and perform quick searches across your data.

## Features

- **Dynamic Searchable Fields**: Define custom searchable fields in each model.
- **Seamless Integration**: Fully compatible with Laravel's query builder and Eloquent.
- **Customizable**: Easily extend or override default behavior for specific use cases.

---

## Installation

Install the package via Composer:

```bash
composer require eaitfakir/eloquent-searchable
```

---

## Usage

### Important: Fields resolution and validation
All scopes that rely on fields will resolve the list of fields as follows:
- If you pass a non-empty `fields` array, it will be used.
- Otherwise, the model's `$searchable` property will be used.

If the resolved list is empty (no `fields` passed AND `$searchable` is not defined or empty), an `InvalidArgumentException` will be thrown with a helpful message:

> No searchable fields provided. Define a non-empty $searchable property on the model or pass a non-empty fields array to the scope.

This applies to: `search`, `exactMatch`, `keywordSearch`, `searchAcross` (for the base model fields), and `fuzzySearch`.

Additionally, `rankedSearch` validates that the `$weights` map is non-empty and will throw an `InvalidArgumentException` if it is empty.

### Adding Searchable Fields

1. Include the `Searchable` trait in your model.
2. Define the `$searchable` property in your model to specify the fields you want to search.

Example:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Eaitfakir\EloquentSearchable\Traits\Searchable;

class User extends Model
{
    use Searchable;

    protected $searchable = ['name', 'email', 'address'];
}
```

### Performing a Search

You can now perform a search using the `search` scope:

```php
// Use model's default $searchable fields
$users = User::search('John')->get();

// Or specify custom fields for this query only
$users = User::search('John', insensitive: false, fields: ['name', 'email'])->get();
```

This will search for the term `'John'` in the fields specified in the `$searchable` property by default. You can also provide custom fields per-call.


You can use the `exactMatch` scope to search for an exact term:

```php
// Default: use model's $searchable fields
$users = User::exactMatch('John')->get();

// Or specify fields explicitly for this call
$users = User::exactMatch('John', ['name', 'email'])->get();
```

This will search for the exact term `'John'` in either the model's `$searchable` fields or the provided fields.


You can use the `keywordSearch` scope to search for multiple keywords:

```php
// Defaults to case-sensitive search on MySQL (collation dependent) and uses ILIKE on Postgres when insensitive = true
$users = User::keywordSearch('John Doe')->get();

// Case-insensitive keyword search and custom fields
$users = User::keywordSearch('John Doe', insensitive: true, fields: ['name', 'email'])->get();
```

This will search for the keywords `'John'` and `'Doe'` across the specified fields. Set `insensitive: true` for case-insensitive matching.


To perform a search for a term in the relations provided, you can use the `searchAcross` scope:

```php
// Basic: search on model + relations using their specified fields
$users = User::searchAcross('John', [
    'posts' => ['title', 'content'],
])->get();

// Advanced: enable keyword mode, case-insensitive, and override base model fields
$users = User::searchAcross(
    term: 'John Doe',
    relations: ['posts' => ['title', 'content']],
    search_by_keywords: true,
    insensitive: true,
    fields: ['name', 'email']
)->get();
```

This searches for the term in the model's fields and within the provided relations. When `search_by_keywords` is true, the term is split into keywords; `insensitive: true` enables case-insensitive matching. You can also override the base model fields using the `fields` parameter.

To perform a search for a term in the searchable fields using fuzzy matching, you can use the `fuzzySearch` scope:

```php
// Basic fuzzy search using model's $searchable fields
$users = User::fuzzySearch('Johb')->get();

// Specify custom fields and maximum distance (default maxDistance = 5)
$users = User::fuzzySearch('Johb', ['name', 'email'], 3)->get();
```

How it works across databases:
- PostgreSQL: Uses the fuzzystrmatch extension's `levenshtein` function when available (recommended: enable with `CREATE EXTENSION IF NOT EXISTS fuzzystrmatch;`). Falls back to `ILIKE` if not available.
- MySQL/MariaDB: Falls back to a combination of case-insensitive `LIKE` and `SOUNDEX` for phonetic matching.

To perform a relevance-ranked search on specified fields, you can use the `rankedSearch` scope:

```php
$users = User::rankedSearch('John', ['name' => 2, 'email' => 1])->get();
```

This will perform a weighted search for the term `'John'` in the fields specified (`name` with weight 2 and `email` with weight 1 in this example) and sort results by a computed relevance score. This works on both MySQL and PostgreSQL.

Case-insensitive option:
- `search($term, $insensitive = false)` and `keywordSearch($term, $insensitive = false)` support case-insensitive search. On PostgreSQL this uses `ILIKE`; on MySQL it emulates with `LOWER(column) LIKE LOWER(?)`.

[//]: # (## Advanced Configuration)

[//]: # (If you want to define default searchable fields globally, you can publish the package's configuration file:)

[//]: # (```bash)
[//]: # (php artisan vendor:publish --tag=eloquent-searchable-config)
[//]: # (```)

[//]: # (This will create a `config/eloquent-searchable.php` file. Update the default fields as needed:)

[//]: # (```php)

[//]: # (return [)

[//]: # (    'default_searchable_fields' => ['name', 'email'])

[//]: # (];)

[//]: # (```)

[//]: # (---)

[//]: # (## Testing)

[//]: # ()
[//]: # (To ensure your package works as expected, run tests using PHPUnit:)

[//]: # ()
[//]: # (```bash)

[//]: # (composer test)

[//]: # (```)

[//]: # ()
[//]: # (Here’s an example test case for the search functionality:)

[//]: # ()
[//]: # (```php)

[//]: # (public function testUserSearch&#40;&#41;)

[//]: # ({)

[//]: # (    User::create&#40;['name' => 'Jane Doe', 'email' => 'jane@example.com']&#41;;)

[//]: # (    $results = User::search&#40;'Jane'&#41;->get&#40;&#41;;)

[//]: # ()
[//]: # (    $this->assertCount&#40;1, $results&#41;;)

[//]: # (})
[//]: # (```)

[//]: # (---)

[//]: # (--- ## Versioning)

[//]: # ()
[//]: # (--- This package adheres to [Semantic Versioning]&#40;https://semver.org/&#41;. For details about changes in each release, refer to the [Releases Page]&#40;https://github.com/your-vendor/eloquent-searchable/releases&#41;.)

[//]: # ()
[//]: # (---)

## Contributing

Contributions are welcome! To contribute:

1. Fork this repository.
2. Create a new branch: `git checkout -b feature/feature-name`.
3. Make your changes and commit: `git commit -m "Add new feature"`.
4. Push the changes: `git push origin feature/feature-name`.
5. Submit a pull request.

Please ensure all tests pass before submitting your PR.

---

## License

This package is licensed under the [MIT License](LICENSE). You’re free to use, modify, and distribute it as per the terms of the license.

---

## Credits

Developed and maintained by [EL MEHDI AIT FAKIR](https://www.linkedin.com/in/el-mehdi-ait-fakir/).

[//]: # (If you find this package helpful, feel free to [sponsor]&#40;https://github.com/sponsors/your-vendor&#41; or contribute to its development.)
