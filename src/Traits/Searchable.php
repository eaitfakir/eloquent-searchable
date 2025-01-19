<?php

namespace Eaitfakir\EloquentSearchable\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * Scope a query that searches for a term in the searchable fields.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $term
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $term, bool $insensitive = false): Builder
    {
        return $query->where(function (Builder $query) use ($insensitive, $term) {
            foreach ($this->getSearchableFields() as $field) {
                $operator = $insensitive ? 'ilike' : 'like';
                $query->orWhere($field, $operator, "%{$term}%");
            }
        });
    }

    /**
     * Scope a query that searches for an exact term in the searchable fields.
     *
     * This method is similar to the `search` scope, but it does not use LIKE
     * operator. Instead, it searches for an exact value.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $term
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchExact($query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            foreach ($this->getSearchableFields() as $field) {
                $query->orWhere($field, $term);
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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $term
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchByKeywords(Builder $query, string $term, bool $insensitive = false): Builder
    {
        $keywords = explode(' ', $term);
        return $query->where(function (Builder $query) use ($insensitive, $keywords) {
            foreach ($this->getSearchableFields() as $field) {
                foreach ($keywords as $keyword) {
                    $operator = $insensitive ? 'ilike' : 'like';
                    $query->orWhere($field, $operator, "%{$keyword}%");
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
     * @param  string  $term
     * @param  array  $relations  Associative array of relations and fields to search
     * @param  bool  $search_by_keywords  Whether to split the search term into keywords,
     *                                    default is `false`
     * @param  bool  $insensitive  Whether to perform a case-insensitive search,
     *                             default is `false`
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchWithRelations($query, string $term, array $relations, bool $search_by_keywords = false, bool $insensitive = false): Builder
    {
        if ($search_by_keywords) {
            $query = $this->scopeSearchByKeywords($query, $term, $insensitive);
        }else{
            $query = $this->scopeSearch($query, $term, $insensitive);
        }

        foreach ($relations as $relation => $fields) {
            $query->orWhereHas($relation, function (Builder $query) use ($fields, $insensitive, $term) {
                foreach ($fields as $field) {
                    $operator = $insensitive ? 'ilike' : 'like';
                    $query->orWhere($field, $operator, "%{$term}%");
                }
            });
        }

        return $query;
    }

    /**
     * Scope a query that performs a fuzzy search on the specified fields.
     *
     * This method uses the Levenshtein distance algorithm to perform a fuzzy search
     * on the provided fields. If no fields are specified, it defaults to using the
     * model's searchable fields. The search will include fields where the Levenshtein
     * distance between the term and the field value is less than 3.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $term The search term to perform the fuzzy matching on.
     * @param array $fields The fields to search within, defaults to the model's searchable fields.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFuzzySearch($query, string $term, array $fields): Builder
    {
        $fields = $fields ?: $this->getSearchableFields();
        return $query->where(function (Builder $query) use ($term, $fields) {
            foreach ($fields as $field) {
                $query->orWhereRaw("LEVENSHEIN(?, {$field}) < ?", [$term, 3]);
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
}
