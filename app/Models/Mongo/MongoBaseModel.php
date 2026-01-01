<?php

namespace App\Models\Mongo;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Supports either MongoDB Laravel package (MongoDB\Laravel\Eloquent\Model)
 * or Jenssegers\Mongodb\Eloquent\Model (older).
 */
class MongoBaseModel extends EloquentModel
{
    protected $connection = 'mongodb';

    public function __construct(array $attributes = [])
    {
        // If a Mongo Eloquent base model exists, we rebind the parent class at runtime by composition isn't possible.
        // This class is a simple fallback for projects that still interact via DB::connection('mongodb') in services.
        parent::__construct($attributes);
    }
}
