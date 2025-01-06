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
    public function scopeSearch($query, $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            foreach ($this->getSearchableFields() as $field) {
                $query->orWhere($field, 'like', "%{$term}%");
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
    public function scopeSearchExact($query, $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            foreach ($this->getSearchableFields() as $field) {
                $query->orWhere($field, $term);
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
