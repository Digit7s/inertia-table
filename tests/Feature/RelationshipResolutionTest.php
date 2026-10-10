<?php

use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\Columns\TagsColumn;
use Digit7s\InertiaTable\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\CallbackRelationshipTable;
use Tests\Fixtures\Company;
use Tests\Fixtures\Division;
use Tests\Fixtures\Image;
use Tests\Fixtures\RelationshipExportTable;
use Tests\Fixtures\RelationshipImageTable;
use Tests\Fixtures\RelationshipTable;
use Tests\Fixtures\Role;
use Tests\Fixtures\Tag;
use Tests\Fixtures\User;

function relationshipUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'A User',
        'email' => uniqid('user-', true).'@example.com',
        'is_admin' => false,
    ], $attributes));
}

test('resolves belongs-to, has-one and nested singular columns and omits auto-loaded relations from raw rows', function () {
    $owner = relationshipUser(['name' => 'Company Owner']);
    $company = Company::create(['name' => 'Acme', 'owner_id' => $owner->id]);
    $user = relationshipUser(['company_id' => $company->id]);
    $profile = $user->profile()->create(['bio' => 'A private bio']);

    $table = new RelationshipTable(request(), [
        TextColumn::make('company.name'),
        TextColumn::make('profile.bio'),
        TextColumn::make('profile.display_bio'),
        Column::make('profile.is_public'),
        TextColumn::make('company.owner.name'),
        Column::make('name'),
    ]);
    $table->paginated(false);

    $row = collect($table->toInertia()['data'])->firstWhere('id', $user->id);

    expect($row['_column_values'])->toMatchArray([
        'company.name' => 'Acme',
        'profile.bio' => 'A private bio',
        'profile.display_bio' => 'A PRIVATE BIO',
        'profile.is_public' => false,
        'company.owner.name' => 'Company Owner',
    ])
        ->and($row['name'])->toBe('A User')
        ->and($row['id'])->toBe($user->id)
        ->and($row)->not->toHaveKey('company')
        ->and($row)->not->toHaveKey('profile')
        ->and($profile->bio)->toBe('A private bio');
});

test('supports non-standard relationship foreign and owner keys in selected queries', function () {
    $division = Division::create(['key' => 'north-division', 'title' => 'Northern Division']);
    $user = relationshipUser(['division_key' => $division->key]);
    $table = new class(request(), [TextColumn::make('division.title')]) extends RelationshipTable
    {
        public function query(): Builder
        {
            return User::query()->select('name');
        }
    };
    $table->paginated(false);
    $row = $table->toInertia()['data'][0];

    expect($row['id'])->toBe($user->id)
        ->and($row['_column_values']['division.title'])->toBe('Northern Division')
        ->and($row)->not->toHaveKey('division_key');
});

test('resolves has-many and belongs-to-many relationship attributes as ordered scalar lists', function () {
    $user = relationshipUser();
    $user->posts()->createMany([['title' => 'First'], ['title' => 'Second']]);
    $roles = [Role::create(['name' => 'Admin']), Role::create(['name' => 'Editor'])];
    $user->roles()->attach(array_map(fn (Role $role) => $role->id, $roles));

    $table = new RelationshipTable(request(), [TextColumn::make('posts.title'), TagsColumn::make('roles.name')]);
    $table->paginated(false);
    $row = $table->toInertia()['data'][0];

    expect($row['_column_values']['posts.title'])->toBe(['First', 'Second'])
        ->and($row['_column_values']['roles.name'])->toBe(['Admin', 'Editor']);
});

test('resolves nested collection relationships and flattens their scalar values', function () {
    $user = relationshipUser();
    $first = $user->posts()->create(['title' => 'Post one']);
    $second = $user->posts()->create(['title' => 'Post two']);
    $first->comments()->createMany([['body' => 'Comment A'], ['body' => 'Comment B']]);
    $second->comments()->create(['body' => 'Comment C']);

    $table = new RelationshipTable(request(), [TextColumn::make('posts.comments.body')]);
    $table->paginated(false);

    expect($table->toInertia()['data'][0]['_column_values']['posts.comments.body'])
        ->toBe(['Comment A', 'Comment B', 'Comment C']);
});

test('resolves morph one, morph many, morph to and morph to many relationships', function () {
    $user = relationshipUser();
    $user->avatar()->create(['title' => 'Avatar']);
    $user->images()->create(['title' => 'Gallery one']);
    $user->images()->create(['title' => 'Gallery two']);
    $user->comments()->create(['body' => 'A polymorphic comment']);
    $user->tags()->attach(Tag::create(['name' => 'Featured'])->id);

    $table = new RelationshipTable(request(), [
        TextColumn::make('avatar.title'),
        TextColumn::make('images.title'),
        TextColumn::make('comments.body'),
        TagsColumn::make('tags.name'),
    ]);
    $table->paginated(false);
    $row = $table->toInertia()['data'][0];

    Image::create([
        'imageable_id' => $user->id,
        'imageable_type' => User::class,
        'title' => 'Morph target',
    ]);
    $company = Company::create(['name' => 'Morph Company']);
    Image::create([
        'imageable_id' => $company->id,
        'imageable_type' => Company::class,
        'title' => 'Second morph target',
    ]);
    $morphToTable = new RelationshipImageTable(request());
    $morphToTable->paginated(false);
    $morphRow = collect($morphToTable->toInertia()['data'])->firstWhere('title', 'Morph target');

    expect($row['_column_values'])->toMatchArray([
        'avatar.title' => 'Avatar',
        'images.title' => ['Avatar', 'Gallery one', 'Gallery two'],
        'comments.body' => ['A polymorphic comment'],
        'tags.name' => ['Featured'],
    ])
        ->and($morphRow['_column_values']['imageable.name'])->toBe('A User')
        ->and(collect($morphToTable->toInertia()['data'])->firstWhere('title', 'Second morph target')['_column_values']['imageable.name'])
        ->toBe('Morph Company');
});

test('missing singular relations resolve to null and empty collection relations resolve to empty lists', function () {
    relationshipUser();
    $table = new RelationshipTable(request(), [
        TextColumn::make('company.name'),
        TextColumn::make('profile.bio'),
        TagsColumn::make('roles.name'),
        TextColumn::make('posts.title'),
    ]);
    $table->paginated(false);
    $row = $table->toInertia()['data'][0];

    expect($row['_column_values'])->toMatchArray([
        'company.name' => null,
        'profile.bio' => null,
        'roles.name' => [],
        'posts.title' => [],
    ]);
});

test('explicit eager loads and their constraints remain intact', function () {
    $kept = Company::create(['name' => 'Keep']);
    $omitted = Company::create(['name' => 'Omit']);
    $keptUser = relationshipUser(['company_id' => $kept->id]);
    $omittedUser = relationshipUser(['company_id' => $omitted->id]);

    $table = new class(request(), [TextColumn::make('company.name')]) extends RelationshipTable
    {
        public function query(): Builder
        {
            return User::query()->with(['company' => fn ($query) => $query->where('name', 'Keep')]);
        }
    };
    $table->paginated(false);
    $rows = $table->toInertia()['data'];

    $rowsById = collect($rows)->keyBy('id');

    expect(collect($rows)->map(fn (array $row) => $row['_column_values']['company.name'])->all())
        ->toContain('Keep', null)
        ->and($rowsById[$keptUser->id]['company']['name'])->toBe('Keep')
        ->and($rowsById[$omittedUser->id]['company'])->toBeNull();
});

test('automatically adds relationship matching keys to selective queries without exposing injected keys', function () {
    $company = Company::create(['name' => 'Keyed Company']);
    $user = relationshipUser(['company_id' => $company->id]);
    $table = new class(request(), [TextColumn::make('company.name')]) extends RelationshipTable
    {
        public function query(): Builder
        {
            return User::query()->select('name');
        }
    };
    $table->paginated(false);
    $row = $table->toInertia()['data'][0];

    expect($row['id'])->toBe($user->id)
        ->and($row['_column_values']['company.name'])->toBe('Keyed Company')
        ->and($row)->not->toHaveKey('company_id');
});

test('row links and row actions share explicitly eager-loaded relationships', function () {
    $company = Company::create(['name' => 'Callback Company']);
    $user = relationshipUser(['company_id' => $company->id]);
    $user->profile()->create(['bio' => 'Profile bio']);

    $table = new CallbackRelationshipTable(request(), [TextColumn::make('company.name')]);
    $table->paginated(false);
    $row = $table->toInertia()['data'][0];

    expect($row['_row_link'])->toBe('Profile bio')
        ->and($row['_actions'][0]['url'])->toBe('Callback Company')
        ->and($row['_column_values']['company.name'])->toBe('Callback Company');
});

test('query counts stay constant for relationship columns while relationship callback baselines grow linearly', function () {
    $company = Company::create(['name' => 'Shared Company']);
    $optimizedQueryCounts = [];
    $callbackBaselineCounts = [];
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    foreach ([10, 100, 1000] as $size) {
        DB::table('users')->delete();
        $timestamp = now();
        $rows = [];
        for ($index = 0; $index < $size; $index++) {
            $rows[] = [
                'name' => "User {$index}",
                'email' => "user-{$index}@example.com",
                'is_admin' => false,
                'company_id' => $company->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        $queries = [];

        $legacyTable = new class(request(), [TextColumn::make('name')]) extends RelationshipTable
        {
            public function __construct($request, array $columns)
            {
                parent::__construct($request, $columns);
                $this->rowLink(function (User $user) {
                    $user->company?->name;
                    $user->profile?->bio;
                    $user->roles->pluck('name');
                    $user->posts->pluck('title');
                    $user->comments->pluck('body');

                    return $user->name;
                });
            }

            public function query(): Builder
            {
                return User::query();
            }
        };
        $legacyTable->paginated(false);
        $legacyData = $legacyTable->toInertia();
        $callbackBaselineCounts[$size] = count($queries);
        expect($legacyData['data'])->toHaveCount($size);

        $queries = [];
        $table = new CallbackRelationshipTable(request(), [
            TextColumn::make('company.name'),
            TextColumn::make('profile.bio'),
            TagsColumn::make('roles.name'),
            TextColumn::make('posts.title'),
            TextColumn::make('comments.body'),
        ]);
        $table->paginated(false);
        $data = $table->toInertia();
        $optimizedQueryCounts[$size] = count($queries);
        expect($data['data'])->toHaveCount($size);
    }

    expect($callbackBaselineCounts)->toBe([10 => 51, 100 => 501, 1000 => 5001])
        ->and($optimizedQueryCounts)->toBe([10 => 6, 100 => 6, 1000 => 6]);
});

test('relationship-only callbacks still require explicit eager loading', function () {
    $company = Company::create(['name' => 'Callback Company']);
    foreach (range(1, 10) as $index) {
        relationshipUser(['company_id' => $company->id]);
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $table = new class(request()) extends RelationshipTable
    {
        public function __construct($request)
        {
            parent::__construct($request, [TextColumn::make('name')]);
            $this->rowLink(fn (User $user) => $user->company?->name);
        }

        public function query(): Builder
        {
            return User::query();
        }
    };
    $table->paginated(false);
    $table->toInertia();

    expect(count($queries))->toBe(11);
});

test('relationship resolution does not depend on production-wide lazy loading prevention', function () {
    $company = Company::create(['name' => 'Lazy Guard Company']);
    relationshipUser(['company_id' => $company->id]);
    relationshipUser(['company_id' => $company->id]);
    Model::preventLazyLoading();

    try {
        $table = new RelationshipTable(request(), [TextColumn::make('company.name')]);
        $table->paginated(false);
        expect($table->toInertia()['data'][0]['_column_values']['company.name'])->toBe('Lazy Guard Company');
    } finally {
        Model::preventLazyLoading(false);
    }
});

test('invalid dotted paths remain unresolved and do not issue relationship queries', function () {
    relationshipUser();
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $table = new RelationshipTable(request(), [TextColumn::make('missing.name')]);
    $table->paginated(false);

    expect($table->toInertia()['data'][0]['_column_values']['missing.name'])->toBeNull()
        ->and(count($queries))->toBe(1);
});

test('permanently hidden relationship columns are not auto-loaded or added to the row payload', function () {
    $company = Company::create(['name' => 'Hidden Relation']);
    relationshipUser(['company_id' => $company->id]);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $table = new RelationshipTable(request(), [TextColumn::make('company.name')->visible(false)]);
    $table->paginated(false);
    $row = $table->toInertia()['data'][0];

    expect($row)->not->toHaveKey('_column_values')
        ->and(count($queries))->toBe(1);
});

test('relationship CSV export writes singular and collection values with bounded chunk queries', function () {
    $company = Company::create(['name' => 'CSV Company']);
    $admin = Role::create(['name' => 'Admin']);
    $editor = Role::create(['name' => 'Editor']);
    $timestamp = now();
    $users = [];
    for ($index = 0; $index < 1000; $index++) {
        $users[] = [
            'name' => "Export {$index}",
            'email' => "export-{$index}@example.com",
            'is_admin' => false,
            'company_id' => $company->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
    foreach (array_chunk($users, 500) as $chunk) {
        DB::table('users')->insert($chunk);
    }
    $userIds = DB::table('users')->pluck('id');
    foreach ($userIds->chunk(500) as $ids) {
        $pivots = [];
        foreach ($ids as $id) {
            $pivots[] = ['user_id' => $id, 'role_id' => $admin->id];
            $pivots[] = ['user_id' => $id, 'role_id' => $editor->id];
        }
        DB::table('role_user')->insert($pivots);
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $response = $this->get(route('inertia-table.export', [
        'table_class' => encrypt(RelationshipExportTable::class),
        'export_name' => 'csv',
    ]));
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toContain('"CSV Company","Admin, Editor"')
        ->and(count($queries))->toBe(4); // Exact 1,000-row boundary issues a final empty chunk query.

    $nextUser = relationshipUser(['company_id' => $company->id, 'name' => 'Export 1000']);
    $nextUser->roles()->attach([$admin->id, $editor->id]);
    $queries = [];
    $nextResponse = $this->get(route('inertia-table.export', [
        'table_class' => encrypt(RelationshipExportTable::class),
        'export_name' => 'csv',
    ]));
    ob_start();
    $nextResponse->sendContent();
    $nextContent = ob_get_clean();

    expect($nextContent)->toContain('"CSV Company","Admin, Editor"')
        ->and(count($queries))->toBe(6); // Two populated chunks, each with batched relation loads.
});

test('existing flat row payload and Phase 1 bulk action tests remain available', function () {
    $user = relationshipUser(['name' => 'Flat User']);
    $table = new RelationshipTable(request(), [Column::make('name')]);
    $table->paginated(false);
    $row = $table->toInertia()['data'][0];

    expect($row['id'])->toBe($user->id)
        ->and($row['name'])->toBe('Flat User')
        ->and($row)->not->toHaveKey('_column_values');
});
