<?php

namespace LangleyFoxall\EloquentCSVImporter\Traits;

use Illuminate\Support\Collection;
use Schema;

trait CSVMappable
{
    /**
     * Get possible columns that can be mapped to a csv
     * @return Collection
     */
    public static function getCSVMappableColumns()
    {
        $table = with(new static)->getTable();

        return new Collection(Schema::getColumnListing($table));
    }
}
