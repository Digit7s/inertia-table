<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
