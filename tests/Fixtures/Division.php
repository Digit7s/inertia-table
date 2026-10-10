<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
