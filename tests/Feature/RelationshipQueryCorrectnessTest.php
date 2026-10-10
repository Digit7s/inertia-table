<?php

use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\Company;
use Tests\Fixtures\RelationshipImageTable;
use Tests\Fixtures\RelationshipStateTable;
use Tests\Fixtures\RelationshipTable;
use Tests\Fixtures\Role;
use Tests\Fixtures\User;

function queryAuditUser(string $name, ?int $companyId = null): User
{
    return User::create([
        'name' => $name,
        'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@example.com',
        'is_admin' => false,
        'company_id' => $companyId,
    ]);
}

function relationshipQueryTable(array $columns, array $filters = [], ?Builder $query = null, ?Request $request = null): RelationshipTable
{
    return new class($request ?? request(), $columns, $filters, $query) extends RelationshipTable
    {
        public function __construct($request, array $columns, private array $auditFilters, private ?Builder $auditQuery)
        {
            parent::__construct($request, $columns);
        }

        public function filters(): array
        {
            return $this->auditFilters;
        }

        public function query(): Builder
        {
            return $this->auditQuery ?? User::query();
        }
    };
}

test('belongs-to and nested singular relationships sort in either direction with stable ties', function () {
    $ownerA = queryAuditUser('Owner A');
    $ownerB = queryAuditUser('Owner B');
    $companyA = Company::create(['name' => 'Alpha', 'owner_id' => $ownerA->id]);
    $companyB = Company::create(['name' => 'Beta', 'owner_id' => $ownerB->id]);
    $user1 = queryAuditUser('User 1', $companyB->id);
    $user2 = queryAuditUser('User 2', $companyA->id);
    $user3 = queryAuditUser('User 3', $companyA->id);

    $ascRequest = Request::create('/?sort=company.name&dir=asc', 'GET');
    $userQuery = User::query()->whereIn('name', ['User 1', 'User 2', 'User 3']);
    $asc = relationshipQueryTable([Column::make('company.name')->sortable()], query: $userQuery, request: $ascRequest);
    $asc->rowLink(fn (User $user) => collect(array_keys($user->getAttributes()))
        ->contains(fn (string $key) => str_starts_with($key, '__it_sort_')));
    $asc->paginated(false);
    $ascendingRows = $asc->toInertia()['data'];
    expect(collect($ascendingRows)->pluck('id')->all())
        ->toBe([$user2->id, $user3->id, $user1->id]);
    expect(collect(array_keys($ascendingRows[0]))->contains(fn (string $key) => str_starts_with($key, '__it_sort_')))
        ->toBeFalse()
        ->and($ascendingRows[0]['_row_link'])->toBeFalse();

    $descRequest = Request::create('/?sort=company.name&dir=desc', 'GET');
    $desc = relationshipQueryTable(
        [Column::make('company.name')->sortable()],
        query: User::query()->whereIn('name', ['User 1', 'User 2', 'User 3']),
        request: $descRequest
    );
    $desc->paginated(false);
    expect(collect($desc->toInertia()['data'])->pluck('id')->all())
        ->toBe([$user1->id, $user2->id, $user3->id]);

    $nestedRequest = Request::create('/?sort=company.owner.name&dir=asc', 'GET');
    $nested = relationshipQueryTable(
        [Column::make('company.owner.name')->sortable()],
        query: User::query()->whereIn('name', ['User 1', 'User 2', 'User 3']),
        request: $nestedRequest
    );
    $nested->paginated(false);
    expect(collect($nested->toInertia()['data'])->pluck('id')->all())
        ->toBe([$user2->id, $user3->id, $user1->id]);
});

test('has-one and null belongs-to sorting are deterministic and nulls sort last', function () {
    $company = Company::create(['name' => 'Present']);
    $nullRelation = queryAuditUser('Null relation');
    $withRelation = queryAuditUser('Has relation', $company->id);
    $withRelation->profile()->create(['bio' => 'Has profile']);

    $nullSort = relationshipQueryTable(
        [Column::make('company.name')->sortable()],
        request: Request::create('/?sort=company.name&dir=asc', 'GET')
    );
    $nullSort->paginated(false);
    expect(collect($nullSort->toInertia()['data'])->pluck('id')->all())
        ->toBe([$withRelation->id, $nullRelation->id]);

    $hasOneSort = relationshipQueryTable(
        [Column::make('profile.bio')->sortable()],
        request: Request::create('/?sort=profile.bio&dir=desc', 'GET')
    );
    $hasOneSort->paginated(false);
    expect(collect($hasOneSort->toInertia()['data'])->pluck('id')->all())
        ->toBe([$withRelation->id, $nullRelation->id]);
});

test('morph-one singular sorting is supported with its morph type constraint', function () {
    $withAvatar = queryAuditUser('With avatar');
    $withoutAvatar = queryAuditUser('Without avatar');
    $withAvatar->avatar()->create(['title' => 'Zulu']);

    $table = relationshipQueryTable(
        [Column::make('avatar.title')->sortable()],
        query: User::query()->whereIn('id', [$withAvatar->id, $withoutAvatar->id]),
        request: Request::create('/?sort=avatar.title', 'GET')
    );
    $table->paginated(false);

    expect(collect($table->toInertia()['data'])->pluck('id')->all())
        ->toBe([$withAvatar->id, $withoutAvatar->id]);
});

test('relationship sorting and filtering preserve related soft-delete scopes', function () {
    $active = Company::create(['name' => 'Visible Company']);
    $deleted = Company::create(['name' => 'Hidden Company']);
    $visibleUser = queryAuditUser('Visible relation', $active->id);
    $deletedUser = queryAuditUser('Deleted relation', $deleted->id);
    $deleted->delete();

    $sort = relationshipQueryTable(
        [Column::make('company.name')->sortable()],
        query: User::query()->whereIn('id', [$visibleUser->id, $deletedUser->id]),
        request: Request::create('/?sort=company.name', 'GET')
    );
    $sort->paginated(false);
    expect(collect($sort->toInertia()['data'])->pluck('id')->all())
        ->toBe([$visibleUser->id, $deletedUser->id]);

    $filter = relationshipQueryTable(
        [Column::make('name')],
        [Filter::make('company.name')],
        User::query()->whereIn('id', [$visibleUser->id, $deletedUser->id]),
        Request::create('/', 'GET', ['filter' => ['company.name' => 'Hidden']])
    );
    $filter->paginated(false);
    expect($filter->toInertia()['data'])->toBe([]);
});

test('collection and morph-to sorts are rejected as validation errors', function () {
    $collection = relationshipQueryTable(
        [Column::make('posts.title')->sortable()],
        request: Request::create('/?sort=posts.title', 'GET')
    );
    expect(fn () => $collection->toInertia())->toThrow(ValidationException::class);

    $morphTo = new class(Request::create('/?sort=imageable.name', 'GET')) extends RelationshipImageTable
    {
        public function columns(): array
        {
            return [Column::make('imageable.name')->sortable()];
        }
    };
    expect(fn () => $morphTo->toInertia())->toThrow(ValidationException::class);
});

test('invalid and unregistered dotted sort keys fail without reaching SQL', function () {
    $table = relationshipQueryTable(
        [Column::make('company.name')->sortable()],
        request: Request::create('/?sort=company.secret%3Bdrop%20table%20users', 'GET')
    );

    expect(fn () => $table->toInertia())->toThrow(ValidationException::class);

    $missingField = relationshipQueryTable(
        [Column::make('company.missing_column')->sortable()],
        request: Request::create('/?sort=company.missing_column', 'GET')
    );
    expect(fn () => $missingField->toInertia())->toThrow(ValidationException::class);
});

test('belongs-to, has-many, belongs-to-many and nested relation filters use any matching related row', function () {
    $ownerMatch = queryAuditUser('Owner Match');
    $ownerOther = queryAuditUser('Owner Other');
    $matchCompany = Company::create(['name' => 'Acme', 'owner_id' => $ownerMatch->id]);
    $otherCompany = Company::create(['name' => 'Other', 'owner_id' => $ownerOther->id]);
    $belongsToMatch = queryAuditUser('Belongs To', $matchCompany->id);
    $hasManyMatch = queryAuditUser('Has Many');
    $hasManyMatch->posts()->create(['title' => 'Needle post']);
    $hasManyOther = queryAuditUser('Has Many Other');
    $hasManyOther->posts()->create(['title' => 'Other post']);
    $manyMatch = queryAuditUser('Many To Many');
    $role = Role::create(['name' => 'Reviewer']);
    $manyMatch->roles()->attach($role->id);
    queryAuditUser('Unmatched', $otherCompany->id);

    $filters = [
        Filter::make('company.name'),
        Filter::make('posts.title'),
        Filter::make('roles.name'),
        Filter::make('company.owner.name'),
    ];

    foreach ([
        ['company.name', 'Acme', [$belongsToMatch->id]],
        ['posts.title', 'Needle', [$hasManyMatch->id]],
        ['roles.name', 'Reviewer', [$manyMatch->id]],
        ['company.owner.name', 'Owner Match', [$belongsToMatch->id]],
    ] as [$key, $value, $expected]) {
        $request = Request::create('/', 'GET', ['filter' => [$key => $value]]);
        $table = relationshipQueryTable([Column::make('name')], $filters, request: $request);
        $table->paginated(false);
        expect(collect($table->toInertia()['data'])->pluck('id')->all())->toBe($expected);
    }
});

test('multiple relationship filters combine with grouped global search and preserve base query scopes', function () {
    $company = Company::create(['name' => 'Acme']);
    $match = queryAuditUser('Alice Blue', $company->id);
    $match->posts()->create(['title' => 'Priority']);
    queryAuditUser('Bob Blue', $company->id)->posts()->create(['title' => 'Priority']);
    queryAuditUser('Alice Green', $company->id)->posts()->create(['title' => 'Other']);
    $scoped = User::query()->where('is_admin', false)->where('name', 'like', 'Alice%');
    $request = Request::create('/', 'GET', [
        'search' => 'Blue',
        'filter' => ['company.name' => 'Acme', 'posts.title' => 'Priority'],
    ]);
    $table = relationshipQueryTable(
        [Column::make('name')->searchable()],
        [Filter::make('company.name'), Filter::make('posts.title')],
        $scoped,
        $request
    );
    $table->paginated(false);

    expect(collect($table->toInertia()['data'])->pluck('id')->all())->toBe([$match->id]);
});

test('relationship filtering does not duplicate parents and paginator totals stay accurate', function () {
    $company = Company::create(['name' => 'Shared']);
    $one = queryAuditUser('One', $company->id);
    $two = queryAuditUser('Two', $company->id);
    $one->posts()->createMany([['title' => 'Match A'], ['title' => 'Match B']]);
    $two->posts()->create(['title' => 'Match C']);
    $request = Request::create('/?filter%5Bposts.title%5D=Match&perPage=1', 'GET');
    $table = relationshipQueryTable(
        [Column::make('name')],
        [Filter::make('posts.title')],
        request: $request
    );
    $result = $table->toInertia();

    expect($result['meta']['total'])->toBe(2)
        ->and($result['data'])->toHaveCount(2);
});

test('text search and filters match ASCII case consistently across database drivers', function () {
    $company = Company::create(['name' => 'Acme Industries']);
    $matching = queryAuditUser('Case Search', $company->id);
    queryAuditUser('No Match', Company::create(['name' => 'Other Company'])->id);

    $filterTable = relationshipQueryTable(
        [Column::make('name')],
        [Filter::make('company.name')],
        request: Request::create('/', 'GET', ['filter' => ['company.name' => 'ACME']])
    );
    $filterTable->paginated(false);

    $searchTable = relationshipQueryTable(
        [Column::make('company.name')->searchable()],
        request: Request::create('/', 'GET', ['search' => 'ACME'])
    );
    $searchTable->paginated(false);

    expect(collect($filterTable->toInertia()['data'])->pluck('id')->all())->toBe([$matching->id])
        ->and(collect($searchTable->toInertia()['data'])->pluck('id')->all())->toBe([$matching->id]);
});

test('relationship sort and filter SQL can be explained by the active database driver', function () {
    $company = Company::create(['name' => 'Plan Company']);
    queryAuditUser('Plan User', $company->id);

    $sortTable = relationshipQueryTable(
        [Column::make('company.name')->sortable()],
        request: Request::create('/?sort=company.name&dir=asc', 'GET')
    );
    $sortQuery = $sortTable->query();
    $sortTable->applySorting($sortQuery);
    $sortSql = $sortQuery->toSql();
    $sortPlan = DB::select('EXPLAIN '.$sortSql, $sortQuery->getBindings());

    $filterTable = relationshipQueryTable(
        [Column::make('name')],
        [Filter::make('company.name')],
        request: Request::create('/', 'GET', ['filter' => ['company.name' => 'plan']])
    );
    $filterQuery = $filterTable->query();
    $filterTable->applyFiltering($filterQuery);
    $filterSql = $filterQuery->toSql();
    $filterPlan = DB::select('EXPLAIN '.$filterSql, $filterQuery->getBindings());

    if (getenv('SHOW_QUERY_PLANS') === '1') {
        fwrite(STDOUT, 'RELATIONSHIP_SORT_SQL='.$sortSql.PHP_EOL);
        fwrite(STDOUT, 'RELATIONSHIP_SORT_PLAN='.json_encode($sortPlan, JSON_UNESCAPED_SLASHES).PHP_EOL);
        fwrite(STDOUT, 'RELATIONSHIP_FILTER_SQL='.$filterSql.PHP_EOL);
        fwrite(STDOUT, 'RELATIONSHIP_FILTER_PLAN='.json_encode($filterPlan, JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    expect($sortSql)->toContain('select')
        ->and($filterSql)->toContain('exists')
        ->and($filterSql)->toContain('LOWER')
        ->and($sortPlan)->not->toBeEmpty()
        ->and($filterPlan)->not->toBeEmpty();
});

test('equal relationship sort values remain page-stable without gaps or duplicates', function () {
    $company = Company::create(['name' => 'Same sort value']);
    $users = [];
    for ($index = 0; $index < 20; $index++) {
        $users[] = queryAuditUser("Page row {$index}", $company->id);
    }

    $request = request();
    $request->query->replace(['sort' => 'company.name', 'page' => 1]);
    $firstPage = relationshipQueryTable([Column::make('company.name')->sortable()], request: $request)->toInertia();
    $request->query->replace(['sort' => 'company.name', 'page' => 2]);
    $secondPage = relationshipQueryTable([Column::make('company.name')->sortable()], request: $request)->toInertia();
    $firstIds = collect($firstPage['data'])->pluck('id')->all();
    $secondIds = collect($secondPage['data'])->pluck('id')->all();

    expect($firstPage['meta']['total'])->toBe(20)
        ->and($secondPage['meta']['total'])->toBe(20)
        ->and(count(array_intersect($firstIds, $secondIds)))->toBe(0)
        ->and(array_merge($firstIds, $secondIds))->toBe(array_map(fn (User $user) => $user->id, $users));
});

test('singular relation sorting and relation filtering add bounded queries at 10, 100 and 1000 rows', function () {
    $company = Company::create(['name' => 'Shared Company']);
    $counts = [];
    $captured = [];
    DB::listen(function ($query) use (&$captured) {
        $captured[] = $query->sql;
    });

    foreach ([10, 100, 1000] as $size) {
        DB::table('users')->delete();
        $now = now();
        foreach (array_chunk(array_map(fn ($i) => [
            'name' => "Row {$i}",
            'email' => "row-{$i}@example.com",
            'is_admin' => false,
            'company_id' => $company->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], range(1, $size)), 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }
        $captured = [];
        $request = Request::create('/?sort=company.name&filter%5Bcompany.name%5D=Shared&perPage=100', 'GET');
        $table = relationshipQueryTable(
            [Column::make('company.name')->sortable()],
            [Filter::make('company.name')],
            request: $request
        );
        $table->toInertia();
        $counts[$size] = count($captured);
    }

    if (DB::connection()->getDriverName() === 'sqlite') {
        expect($counts)->toBe([10 => 5, 100 => 5, 1000 => 5]);
    }

    expect(array_unique($counts))->toHaveCount(1)
        ->and($counts[1000])->toBeLessThanOrEqual(6)
        ->and(collect($captured)->contains(fn (string $sql) => str_contains($sql, 'nulls last') || str_contains($sql, 'order by case when')))->toBeTrue();
});

test('table rendering, bulk select-all, and CSV export share relationship search and filter scope', function () {
    $acme = Company::create(['name' => 'Acme']);
    $other = Company::create(['name' => 'Other']);
    $alice = queryAuditUser('Alice Admin', $acme->id);
    $bob = queryAuditUser('Bob Admin', $acme->id);
    $otherAlice = queryAuditUser('Alice Other', $other->id);

    $state = ['search' => 'Alice', 'filter' => ['company.name' => 'Acme']];
    $table = new RelationshipStateTable(Request::create('/', 'GET', $state));
    $table->paginated(false);
    expect(collect($table->toInertia()['data'])->pluck('id')->all())->toBe([$alice->id]);

    $this->post(route('inertia-table.bulk-action'), [
        'table_class' => encrypt(RelationshipStateTable::class),
        'action_key' => 'delete',
        'ids' => [],
        'selectAllMatching' => true,
        'search' => 'Alice',
        'filters' => json_encode(['company.name' => 'Acme']),
    ])->assertRedirect();
    expect(User::find($alice->id))->toBeNull()
        ->and(User::find($bob->id))->not->toBeNull()
        ->and(User::find($otherAlice->id))->not->toBeNull();

    $exportedAlice = queryAuditUser('Alice Export', $acme->id);
    queryAuditUser('Bob Export', $acme->id);
    queryAuditUser('Alice Other Export', $other->id);
    $response = $this->get(route('inertia-table.export', [
        'table_class' => encrypt(RelationshipStateTable::class),
        'export_name' => 'csv',
        'search' => 'Alice',
        'filters' => json_encode(['company.name' => 'Acme']),
    ]));

    expect($response->streamedContent())->toContain('Alice Export')
        ->and($response->streamedContent())->not->toContain('Bob Export')
        ->and($response->streamedContent())->not->toContain('Alice Other Export')
        ->and(User::find($exportedAlice->id))->not->toBeNull();
});
