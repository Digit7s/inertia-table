<?php

namespace Tests\Fixtures;

use Digit7s\InertiaTable\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CallbackRelationshipTable extends RelationshipTable
{
    public function __construct(?Request $request = null, array $columns = [])
    {
        parent::__construct($request, $columns);
        $this->rowLink(fn (User $user) => $user->profile?->bio);
    }

    public function query(): Builder
    {
        return User::query()->with('profile');
    }

    public function actions(): array
    {
        return [Action::make('company', 'Company')->url(fn (User $user) => $user->company?->name)];
    }
}
