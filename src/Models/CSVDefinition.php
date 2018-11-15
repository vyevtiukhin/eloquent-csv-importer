<?php

namespace LangleyFoxall\EloquentCSVImporter\Models;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use LangleyFoxall\EloquentCSVImporter\CSVParser;
use LangleyFoxall\EloquentCSVImporter\Exceptions\UnknownCSVMappableColumnException;
use League\Csv\Exception;

/**
 * Class CSVDefinition
 * @package App\Models
 */
class CSVDefinition extends Model
{
    protected $table = 'csv_definitions';
    /**
     * @var array
     */
    protected $casts = [
        'mappings' => 'array',
    ];

    /**
     * @var array
     */
    protected $dates = [
        'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * Find or return existing instance of the mappable class
     * @return mixed
     */
    protected function getMappable($searchAttributes = null)
    {
        if ($searchAttributes) {
            return $this->getMappableForQuery($searchAttributes);
        }
        return $this->getMappableInstance();
    }

    protected function getMappableInstance() {
        return new $this->mappable_type;
    }

    protected function getMappableForQuery($searchAttributes)
    {
        $r = new \ReflectionClass($this->mappable_type);
        $instance =  $r->newInstanceWithoutConstructor();

        //Attempt to find model for the search attributes
        return $instance->firstOrNew($searchAttributes);
    }

    /**
     * Get the mappings as an array
     * @return mixed
     */
    protected function getMappings()
    {
        return json_decode($this->mappings, true);
    }

    /**
     * Get a valid model mapping for a csv key
     * @param $from
     * @return mixed
     * @throws UnknownCSVMappableColumnException
     */
    protected function getValidToProperty($from)
    {
        $mappings = $this->getMappings();
        if (!array_key_exists($from, $mappings)) {
            throw new UnknownCSVMappableColumnException('Unknown column mapping: '.$from);
        }

        $toKey = $mappings[$from];

        if (!$this->getMappable()::getCSVMappableColumns()->contains($toKey)) {
            throw new UnknownCSVMappableColumnException('Unknown model mapping: '.$toKey);
        }

        return $toKey;
    }

    /**
     * Get a model instance
     * @param $parsedRow
     * @param $updateWithColumns
     * @return mixed
     * @throws UnknownCSVMappableColumnException
     */
    protected function getModel($parsedRow, $updateWithColumns)
    {
        if (!empty($updateWithColumns)) {
            $queryParams = [];
            foreach($updateWithColumns as $column) {
                $CSVValue = $parsedRow[$column];
                $toKey = $this->getValidToProperty($column);
                $queryParams[$toKey] = $CSVValue;
            }
            return $this->getMappable($queryParams);
        }
        return $this->getMappable();
    }

    /**
     * Map either a file or a CSV string to an array of models
     * @param File||String $data
     * @param array $updateWithColumns
     * @return Collection
     * @throws UnknownCSVMappableColumnException
     * @throws Exception
     */
    protected function instantiateModels($data, $updateWithColumns)
    {
        $csvParser = new CSVParser($data);
        $parserIterator = $csvParser->getIterator();
        $mappedModels = collect();

        $mappings = $this->getMappings();
        $mappingKeys = array_keys($mappings);

        foreach ($parserIterator as $parsedRow) {
            //on the row, grab the values inside of the search columns
            $model = $this->getModel($parsedRow, $updateWithColumns);
            foreach ($mappingKeys as $mapFrom) {
                $CSVValue = $parsedRow[$mapFrom];
                $toKey = $this->getValidToProperty($mapFrom);
                $model->setAttribute($toKey, $CSVValue);
            }
            $mappedModels->push($model);
        }

        return $mappedModels;
    }

    /**
     * Parse a csv file or string and return a collection of models
     * @param $data
     * @return Collection
     * @throws Exception
     * @throws UnknownCSVMappableColumnException
     */
    public function makeModels($data, $updateWithColumns)
    {
        return $this->instantiateModels($data);
    }

    /**
     * Parse a csv file or string and return a collection of models that have been saved to the Database
     * @param $data
     * @param $updateWithColumns - Columns to update row by if they match
     * @return Collection
     * @throws Exception
     * @throws UnknownCSVMappableColumnException
     * @throws \Throwable
     */
    public function createModels($data, $updateWithColumns)
    {
        $models = $this->instantiateModels($data, $updateWithColumns);
        DB::transaction(function () use ($models) {
            $models->each->save();
        });

        return $models;
    }

    /**
     * Model that the definition is associated with
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function assigned()
    {
        return $this->morphTo();
    }
}
