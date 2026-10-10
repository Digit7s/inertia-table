<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function getDisplayBioAttribute(): ?string
    {
        return $this->bio === null ? null : strtoupper($this->bio);
    }
}
