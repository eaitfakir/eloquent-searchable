<?php

namespace Eaitfakir\EloquentSearchable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait Searchable
{
    /**
     * Scope a query that searches for a term in the searchable fields.
     * Works for MySQL and PostgreSQL. For case-insensitive search on MySQL,
     * it falls back to LOWER(field) LIKE LOWER(?), while on PostgreSQL it uses ILIKE.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $term
     * @param bool $insensitive Whether to perform case-insensitive search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $term, bool $insensitive = false): Builder
    {
        $fields = $this->getSearchableFields();
        return $query->where(function (Builder $q) use ($fields, $insensitive, $term) {
            foreach ($fields as $field) {
                $this->applyLikeCondition($q, $field, $term, $insensitive, 'or');
            }
        });
    }

    /**
     * Scope a query that searches for an exact term in the searchable fields.
     *
     * This method is similar to the `search` scope, but it does not use LIKE
     * operator. Instead, it searches for an exact value.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $term
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchExact($query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            foreach ($this->getSearchableFields() as $field) {
                $q->orWhere($field, $term);
            }
        });
    }

    /**
     * Scope a query that searches for multiple keywords in the searchable fields.
     *
     * This method splits the search term into individual keywords and performs
     * a search for each keyword using the LIKE operator across the defined
     * searchable fields.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $term
     * @param bool $insensitive Whether to perform case-insensitive search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchByKeywords(Builder $query, string $term, bool $insensitive = false): Builder
    {
        $keywords = array_values(array_filter(explode(' ', $term), fn ($w) => $w !== ''));
        $fields = $this->getSearchableFields();
        return $query->where(function (Builder $q) use ($insensitive, $keywords, $fields) {
            foreach ($fields as $field) {
                foreach ($keywords as $keyword) {
                    $this->applyLikeCondition($q, $field, $keyword, $insensitive, 'or');
                }
            }
        });
    }

    /**
     * Scope a query that searches for a term in the searchable fields and
     * the relations provided.
     *
     * This method is similar to the `search` scope, but it also searches for
     * the term in the relations provided. The relations and fields should be
     * provided as an associative array where the key is the relation name and
     * the value is an array of fields to search.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $term
     * @param array $relations Associative array of relations and fields to search
     * @param bool $search_by_keywords Whether to split the search term into keywords,
     *                                    default is `false`
     * @param bool $insensitive Whether to perform a case-insensitive search,
     *                             default is `false`
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchWithRelations($query, string $term, array $relations, bool $search_by_keywords = false, bool $insensitive = false): Builder
    {
        if ($search_by_keywords) {
            $query = $this->scopeSearchByKeywords($query, $term, $insensitive);
        } else {
            $query = $this->scopeSearch($query, $term, $insensitive);
        }

        foreach ($relations as $relation => $fields) {
            $query->orWhereHas($relation, function (Builder $q) use ($fields, $insensitive, $term) {
                foreach ($fields as $field) {
                    $this->applyLikeCondition($q, $field, $term, $insensitive, 'or');
                }
            });
        }

        return $query;
    }

    /**
     * Scope a query that performs a fuzzy search on the specified fields.
     *
     * It attempts to use Levenshtein distance where available:
     * - PostgreSQL: uses levenshtein(lower(field), ?) from fuzzystrmatch extension if available.
     * - MySQL: falls back to a combination of LOWER(field) LIKE and SOUNDEX matching.
     *
     * If Levenshtein is not available, it gracefully degrades to a case-insensitive LIKE.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $term The search term to perform the fuzzy matching on.
     * @param array $fields The fields to search within, defaults to the model's searchable fields.
     * @param int $maxDistance The maximum Levenshtein distance allowed (when supported).
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFuzzySearch(Builder $query, string $term, array $fields = [], int $maxDistance = 5): Builder
    {
        $fields = empty($fields) ? $this->getSearchableFields() : $fields;
        $driver = $this->getDatabaseDriver();
        $lowerTerm = mb_strtolower($term);

        return $query->where(function (Builder $q) use ($driver, $fields, $lowerTerm, $maxDistance, $term) {
            foreach ($fields as $field) {
                if ($driver === 'pgsql') {
                    if ($this->postgresHasLevenshtein()) {
                        $q->orWhereRaw('LEVENSHTEIN(LOWER(' . $this->wrap($field) . '), ?) <= ?', [$lowerTerm, $maxDistance]);
                    } else {
                        // Fallback to ILIKE when levenshtein is not available
                        $q->orWhereRaw($this->wrap($field) . ' ILIKE ?', ['%' . $term . '%']);
                    }
                } else {
                    // MySQL and others: fallback to LIKE and SOUNDEX for rough phonetic match
                    $this->applyLikeCondition($q, $field, $term, true, 'or');
                    // SOUNDEX available in MySQL; use try-best approach
                    $q->orWhereRaw('SOUNDEX(' . $this->wrap($field) . ') = SOUNDEX(?)', [$term]);
                }
            }
        });
    }

    /**
     * Scope a query that performs a weighted search on the specified fields.
     *
     * This method assigns a weight to each field that is searched. The weight is
     * used to order the results by relevance. The search will include fields where
     * the value contains the search term.
     * Works for MySQL and PostgreSQL by using CASE expressions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $term The search term to perform the weighted matching on.
     * @param array $weights The weights to assign to each field, as an associative array where the key is the field name and the value is the weight.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWeightedSearch(Builder $query, string $term, array $weights): Builder
    {
        $query->select('*');

        $insensitive = true; // Weighted search is typically case-insensitive
        $sumParts = [];

        // Escape term for safe literal embedding in LIKE pattern
        $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
        // Quote pattern using PDO to avoid SQL injection and respect connection driver quoting
        try {
            $quotedPattern = DB::connection()->getPdo()->quote($pattern);
        } catch (\Throwable $e) {
            // Fallback basic quoting
            $quotedPattern = "'" . str_replace("'", "''", $pattern) . "'";
        }

        foreach ($weights as $field => $weight) {
            $wrapped = $this->wrap($field);
            $likeExpr = $insensitive && $this->getDatabaseDriver() === 'pgsql'
                ? "$wrapped ILIKE $quotedPattern"
                : "LOWER($wrapped) LIKE LOWER($quotedPattern)";

            $caseExpr = "(CASE WHEN $likeExpr THEN $weight ELSE 0 END)";
            $alias = 'relevance_' . str_replace(['.', '"', '`'], '_', $field);
            $query->addSelect(DB::raw("{$caseExpr} AS {$this->wrapAlias($alias)}"));
            $sumParts[] = $caseExpr;
        }

        if (!empty($sumParts)) {
            $totalExpr = '(' . implode(' + ', $sumParts) . ')';
            $query->addSelect(DB::raw("{$totalExpr} AS {$this->wrapAlias('relevance_total')}"));
            $query->orderByDesc('relevance_total');
        }

        // Also restrict results to any field that matches at least by LIKE
        return $query->where(function (Builder $q) use ($term, $weights, $insensitive) {
            foreach (array_keys($weights) as $field) {
                $this->applyLikeCondition($q, $field, $term, $insensitive, 'or');
            }
        });
    }

    /**
     * Retrieve the list of fields that are searchable.
     *
     * @return array The array of searchable field names.
     */
    protected function getSearchableFields(): array
    {
        return property_exists($this, 'searchable') ? $this->searchable : [];
    }

    /**
     * Apply a portable LIKE or ILIKE condition.
     */
    protected function applyLikeCondition(Builder $query, string $field, string $term, bool $insensitive, string $boolean = 'and'): void
    {
        $driver = $this->getDatabaseDriver();
        $isOr = strtolower($boolean) === 'or';

        if ($insensitive) {
            if ($driver === 'pgsql') {
                if ($isOr) {
                    $query->orWhere($field, 'ilike', "%{$term}%");
                } else {
                    $query->where($field, 'ilike', "%{$term}%");
                }
            } else {
                // Use LOWER(field) LIKE LOWER(?) for MySQL and others
                $sql = 'LOWER(' . $this->wrap($field) . ') LIKE ?';
                $binding = mb_strtolower('%' . $term . '%');
                if ($isOr) {
                    $query->orWhereRaw($sql, [$binding]);
                } else {
                    $query->whereRaw($sql, [$binding]);
                }
            }
        } else {
            if ($isOr) {
                $query->orWhere($field, 'like', "%{$term}%");
            } else {
                $query->where($field, 'like', "%{$term}%");
            }
        }
    }

    /**
     * Get current database driver name (mysql, pgsql, sqlite, etc.).
     */
    protected function getDatabaseDriver(): string
    {
        try {
            return DB::connection()->getDriverName();
        } catch (\Throwable $e) {
            throw new \Exception('Unable to get database driver name');
        }
    }

    /**
     * Wrap an identifier (column) using the grammar for the current connection.
     */
    protected function wrap(string $identifier): string
    {
        return DB::connection()->getQueryGrammar()->wrap($identifier);

    }

    protected function wrapAlias(string $alias): string
    {
        // Aliases should not be wrapped with quotes in most cases; return as-is
        return $alias;
    }

    /**
     * Detect if PostgreSQL has the fuzzystrmatch extension with levenshtein available.
     * Caches the result per request to avoid repeated checks.
     */
    protected function postgresHasLevenshtein(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if ($this->getDatabaseDriver() !== 'pgsql') {
            return $cache = false;
        }
        try {
            // to_regprocedure returns null if the function signature is not found
            $result = DB::select("SELECT to_regprocedure('levenshtein(text,text)') IS NOT NULL AS exists");
            if (!empty($result)) {
                $row = (array) $result[0];
                $exists = (bool) array_values($row)[0];
                return $cache = $exists;
            }
            return $cache = false;
        } catch (\Throwable $e) {
            return $cache = false;
        }
    }
}
