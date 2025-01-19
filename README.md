
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
$users = User::search('John')->get();
```

This will search for the term `'John'` in the fields specified in the `$searchable` property (`name`, `email`, and `address` in this example).


You can use the `searchExact` scope to search for an exact term:

```php
$users = User::searchExact('John')->get();
```

This will search for the exact term `'John'` in the fields specified in the `$searchable` property (`name`, `email`, and `address` in this example).


You can use the `searchByKeywords` scope to search for multiple keywords:

```php
$users = User::searchByKeywords('John Doe')->get();
```

This will search for the keywords `'John'` and `'Doe'` in the fields specified in the `$searchable` property (`name`, `email`, and `address` in this example).


To perform a case-insensitive search, you can use the `searchByKeywords` or `search` scope with the `$insensitive` parameter set to `true`:

```php
$users = User::searchByKeywords('John Doe', true)->get();
```

This will perform a case-insensitive search for the keywords `'John'` and `'Doe'` in the fields specified in the `$searchable` property (`name`, `email`, and `address` in this example).


To perform a search for a term in the relations provided, you can use the `searchWithRelations` scope:

```php
$users = User::searchWithRelations('John', ['posts' => ['title', 'content']])->get();
```

This will search for the term `'John'` in the user fields (`name`, `email`, and `address` in this example) and search for the term `'John'` in the `posts` relation (`title` and `content` in this example).

To perform a search for a term in the searchable fields using fuzzy matching, you can use the `fuzzySearch` scope:

```php
$users = User::fuzzySearch('Johb')->get();
```

This will perform a fuzzy search for the term `'Johb'` in the fields specified in the `$searchable` property (`name`, `email`, and `address` in this example).

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
