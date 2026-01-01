<?php

namespace App\Models\Mongo;

/**
 * Mongo collection: users
 * Expected fields (example):
 * - _id (ObjectId)
 * - coins (int)  // this module increments this field
 */
class MongoUser extends MongoBaseModel
{
    protected $collection = 'users';
    protected $guarded = [];
}
