<?php

namespace App\Models\Mongo;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * Base Mongo Eloquent model for mongodb/laravel-mongodb.
 *
 * IMPORTANT:
 * - This MUST use DocumentModel trait to route queries to MongoDB driver.
 * - Without DocumentModel, Eloquent will behave like SQL (PDO) and you get
 *   "Call to a member function prepare() on null".
 */
abstract class MongoBaseModel extends EloquentModel
{
    use DocumentModel;

    protected $connection = 'mongodb';

    
    // Mongo collections don't use incremental ints by default
    public $incrementing = false;

    // _id is stored as ObjectId; package handles it
    protected $keyType = 'string';

    protected $guarded = [];
}
