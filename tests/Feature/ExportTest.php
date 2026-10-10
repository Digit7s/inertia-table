<?php

use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\Exports\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Company;
use Tests\Fixtures\Image;
use Tests\Fixtures\RelationshipImageTable;
use Tests\Fixtures\RelationshipTable;
use Tests\Fixtures\Role;
use Tests\Fixtures\Tag;
use Tests\Fixtures\User;
use Tests\Fixtures\UsersTable;

beforeEach(function () {
    User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'is_admin' => true]);
    User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'is_admin' => false]);
    User::create(['name' => 'Charlie', 'email' => 'charlie@example.com', 'is_admin' => false]);
});

test('export is disabled by default', function () {
    $request = Request::create('/');
    $table = new UsersTable($request);
    $data = $table->toInertia();

    expect($data['meta'])->not->toHaveKey('exports');
});

test('export metadata is included when enabled', function () {
    $request = Request::create('/');

    // Create an anonymous class extending UsersTable to add exports
    $table = new class($request) extends UsersTable
    {
        public function exports(): array
        {
            return [
                CsvExport::make('users.csv')->name('csv')->label('Export CSV'),
            ];
        }
    };

    $data = $table->toInertia();

    expect($data['meta'])->toHaveKey('exports');
    expect($data['meta']['exports'])->toHaveCount(1);
    expect($data['meta']['exports'][0]['name'])->toBe('csv');
    expect($data['meta']['exports'][0]['label'])->toBe('Export CSV');
    expect($data['meta']['exports'][0]['url'])->not->toBeNull();
});

test('export route downloads csv', function () {
    // We need to define a route for the test to hit if the package hasn't booted fully in this context
    // The testbench setup should have booted it, but let's test it using a direct request

    // Create an anonymous class extending UsersTable to add exports
    $tableClass = get_class(new class extends UsersTable
    {
        public function exports(): array
        {
            return [
                CsvExport::make('users.csv')->name('csv')->label('Export CSV'),
            ];
        }
    });

    $response = $this->get(route('inertia-table.export', [
        'table_class' => encrypt($tableClass),
        'export_name' => 'csv',
    ]));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename=users.csv');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Alice,alice@example.com');
    expect($content)->toContain('Bob,bob@example.com');
});

test('export route respects search and sort', function () {
    $tableClass = get_class(new class extends UsersTable
    {
        public function exports(): array
        {
            return [
                CsvExport::make('users.csv')->name('csv'),
            ];
        }
    });

    $response = $this->get(route('inertia-table.export', [
        'table_class' => encrypt($tableClass),
        'export_name' => 'csv',
        'search' => 'Alice',
    ]));

    $response->assertStatus(200);

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Alice');
    expect($content)->not->toContain('Bob');
});

test('export route returns 403 for invalid table', function () {
    $response = $this->get(route('inertia-table.export', [
        'table_class' => encrypt(stdClass::class),
        'export_name' => 'csv',
    ]));

    $response->assertStatus(403);
});

test('export route returns 404 for invalid export name', function () {
    $tableClass = get_class(new class extends UsersTable
    {
        public function exports(): array
        {
            return [
                CsvExport::make('users.csv')->name('csv'),
            ];
        }
    });

    $response = $this->get(route('inertia-table.export', [
        'table_class' => encrypt($tableClass),
        'export_name' => 'invalid_csv',
    ]));

    $response->assertStatus(404);
});

function readExportCsv(string $content): array
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $content));
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

function sendExport(string $tableClass, array $parameters = []): array
{
    $response = test()->get(route('inertia-table.export', array_merge([
        'table_class' => encrypt($tableClass),
        'export_name' => 'csv',
    ], $parameters)));
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    return [$response, $content];
}

test('generated CSV neutralizes formula prefixes and retains ordinary multiline CSV values', function () {
    DB::table('users')->delete();
    $attacks = [
        '=1+1',
        '+SUM(1,1)',
        '-HYPERLINK("https://evil.test")',
        '@SUM(1,1)',
        " \t=1+1",
        "\r=1+1",
        "\n@SUM(1,1)",
        "\t+2",
        "\u{00A0}+1+1",
        "\u{200B}@SUM(1,1)",
        "\u{FEFF}=1+1",
        '-12.5',
        '+2e3',
        'ordinary text',
        "line one\nline two, with \"quotes\"",
    ];

    foreach ($attacks as $index => $name) {
        User::create([
            'name' => $name,
            'email' => "formula-{$index}@example.com",
            'is_admin' => false,
        ]);
    }

    $tableClass = get_class(new class(request()) extends RelationshipTable
    {
        public function columns(): array
        {
            return [Column::make('name')];
        }

        public function exports(): array
        {
            return [CsvExport::make('safe.csv')->name('csv')];
        }
    });

    [$response, $content] = sendExport($tableClass);
    $rows = readExportCsv($content);
    $actual = array_column(array_slice($rows, 1), 0);

    expect($response->getStatusCode())->toBe(200)
        ->and($actual)->toBe([
            "'=1+1",
            "'+SUM(1,1)",
            "'-HYPERLINK(\"https://evil.test\")",
            "'@SUM(1,1)",
            "' \t=1+1",
            "'\r=1+1",
            "'\n@SUM(1,1)",
            "'\t+2",
            "'\u{00A0}+1+1",
            "'\u{200B}@SUM(1,1)",
            "'\u{FEFF}=1+1",
            '-12.5',
            '+2e3',
            'ordinary text',
            "line one\nline two, with \"quotes\"",
        ]);
});

test('export applies configured column allowlists, visibility, tenant query scopes, and authorization', function () {
    DB::table('users')->delete();
    User::create(['name' => 'Tenant A', 'email' => 'a@tenant.test', 'is_admin' => true]);
    User::create(['name' => 'Tenant B', 'email' => 'b@other.test', 'is_admin' => true]);

    $tableClass = get_class(new class(request()) extends RelationshipTable
    {
        public function columns(): array
        {
            return [
                Column::make('name'),
                Column::make('email')->exportable(false),
                Column::make('is_admin')->visible(false),
            ];
        }

        public function query(): Builder
        {
            return User::query()->where('email', 'like', '%@tenant.test');
        }

        public function exports(): array
        {
            return [CsvExport::make('tenant.csv')->name('csv')->authorizeUsing(fn () => true)];
        }
    });
    [$allowedResponse, $allowedContent] = sendExport($tableClass, [
        'columns' => 'email,is_admin',
        'export_name' => 'csv',
    ]);
    $rows = readExportCsv($allowedContent);

    expect($allowedResponse->getStatusCode())->toBe(200)
        ->and($rows[0])->toBe(['Name'])
        ->and($rows)->toHaveCount(2)
        ->and($allowedContent)->toContain('Tenant A')
        ->and($allowedContent)->not->toContain('Tenant B', 'a@tenant.test', 'b@other.test');

    $deniedClass = get_class(new class(request()) extends RelationshipTable
    {
        public function columns(): array
        {
            return [Column::make('name')];
        }

        public function exports(): array
        {
            return [CsvExport::make('denied.csv')->name('csv')->authorizeUsing(fn () => false)];
        }
    });
    [$deniedResponse] = sendExport($deniedClass);
    expect($deniedResponse->getStatusCode())->toBe(403);
});

test('export state uses configured search filters and sorting without accepting arbitrary filter keys', function () {
    DB::table('users')->delete();
    User::create(['name' => 'Zulu', 'email' => 'z@example.com', 'is_admin' => false]);
    User::create(['name' => 'Alpha', 'email' => 'a@example.com', 'is_admin' => false]);
    User::create(['name' => 'Other', 'email' => 'o@example.com', 'is_admin' => true]);

    $tableClass = get_class(new class(request()) extends UsersTable
    {
        public function columns(): array
        {
            return [Column::make('name')->searchable()->sortable()];
        }

        public function exports(): array
        {
            return [CsvExport::make('sorted.csv')->name('csv')];
        }
    });
    [$response, $content] = sendExport($tableClass, [
        'search' => 'L',
        'filter' => ['is_admin' => '0'],
        'sort' => 'name',
        'dir' => 'asc',
    ]);
    $rows = readExportCsv($content);

    expect($response->getStatusCode())->toBe(200)
        ->and(array_column(array_slice($rows, 1), 0))->toBe(['Alpha', 'Zulu']);

    $response = $this->get(route('inertia-table.export', [
        'table_class' => encrypt($tableClass),
        'export_name' => 'csv',
        'filter' => ['email' => 'secret'],
    ]));
    $response->assertSessionHasErrors('filter.email');
});

test('export row set is complete and duplicate-free across multiple stable chunks', function () {
    DB::table('users')->delete();
    $timestamp = now();
    for ($chunk = 0; $chunk < 5; $chunk++) {
        $rows = [];
        for ($index = 0; $index < 500; $index++) {
            $number = $chunk * 500 + $index;
            $rows[] = [
                'name' => sprintf('Row %05d', $number),
                'email' => "row-{$number}@example.com",
                'is_admin' => false,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
        DB::table('users')->insert($rows);
    }

    $tableClass = get_class(new class(request()) extends UsersTable
    {
        public function columns(): array
        {
            return [Column::make('id')->sortable()];
        }

        public function exports(): array
        {
            return [CsvExport::make('ids.csv')->name('csv')];
        }
    });
    [$response, $content] = sendExport($tableClass, ['sort' => 'id', 'dir' => 'asc']);
    $ids = array_map('intval', array_column(array_slice(readExportCsv($content), 1), 0));

    expect($response->getStatusCode())->toBe(200)
        ->and($ids)->toHaveCount(2500)
        ->and(count(array_unique($ids)))->toBe(2500)
        ->and($ids)->toBe(range(min($ids), max($ids)));
});

test('CSV preserves typed numbers dates booleans nulls and safely formats collection cells', function () {
    DB::table('users')->delete();
    $user = User::create([
        'name' => 'Safe row',
        'email' => 'safe-row@example.com',
        'is_admin' => false,
    ]);
    $user->roles()->attach([
        Role::create(['name' => '=HYPERLINK("https://evil.test")'])->id,
        Role::create(['name' => 'Editor'])->id,
    ]);

    $tableClass = get_class(new class(request()) extends RelationshipTable
    {
        public function columns(): array
        {
            return [
                Column::make('id'),
                Column::make('is_admin'),
                Column::make('created_at'),
                Column::make('company.name'),
                Column::make('roles.name'),
            ];
        }

        public function exports(): array
        {
            return [CsvExport::make('typed.csv')->name('csv')];
        }
    });
    [$response, $content] = sendExport($tableClass);
    $rows = readExportCsv($content);
    $expectedDate = $user->fresh()->created_at->format('Y-m-d\\TH:i:s.uP');

    expect($response->getStatusCode())->toBe(200)
        ->and($rows[1])->toBe([
            (string) $user->getKey(),
            '0',
            $expectedDate,
            '',
            "'=HYPERLINK(\"https://evil.test\"), Editor",
        ]);
});

test('CSV uses the shared resolver for nested singular and collection relationship paths', function () {
    DB::table('users')->delete();
    $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'is_admin' => false]);
    $company = Company::create(['name' => 'Acme', 'owner_id' => $owner->id]);
    $user = User::create([
        'name' => 'Relationship export',
        'email' => 'relationship-export@example.test',
        'is_admin' => false,
        'company_id' => $company->id,
    ]);
    $user->profile()->create(['bio' => 'profile value']);
    $post = $user->posts()->create(['title' => 'post value']);
    $post->comments()->createMany([['body' => 'comment one'], ['body' => 'comment two']]);
    $user->roles()->attach(Role::create(['name' => 'Editor'])->id);
    $user->avatar()->create(['title' => 'avatar value']);
    $user->images()->create(['title' => 'image value']);
    $user->tags()->attach(Tag::create(['name' => 'tag value'])->id);

    $tableClass = get_class(new class(request()) extends RelationshipTable
    {
        public function columns(): array
        {
            return [
                Column::make('company.name'),
                Column::make('company.owner.name'),
                Column::make('profile.display_bio'),
                Column::make('posts.title'),
                Column::make('posts.comments.body'),
                Column::make('roles.name'),
                Column::make('avatar.title'),
                Column::make('images.title'),
                Column::make('tags.name'),
            ];
        }

        public function query(): Builder
        {
            return User::query()->where('email', 'relationship-export@example.test');
        }

        public function exports(): array
        {
            return [CsvExport::make('relationships.csv')->name('csv')];
        }
    });
    [$response, $content] = sendExport($tableClass);
    $rows = readExportCsv($content);

    expect($response->getStatusCode())->toBe(200)
        ->and($rows[1])->toBe([
            'Acme',
            'Owner',
            'PROFILE VALUE',
            'post value',
            'comment one, comment two',
            'Editor',
            'avatar value',
            'avatar value, image value',
            'tag value',
        ]);
});

test('CSV export retains soft-delete scopes on related models', function () {
    DB::table('users')->delete();
    $company = Company::create(['name' => 'Deleted company']);
    $user = User::create([
        'name' => 'Soft delete export',
        'email' => 'soft-delete-export@example.test',
        'is_admin' => false,
        'company_id' => $company->id,
    ]);
    $company->delete();

    $tableClass = get_class(new class(request()) extends RelationshipTable
    {
        public function columns(): array
        {
            return [Column::make('company.name')];
        }

        public function query(): Builder
        {
            return User::query()->where('email', 'soft-delete-export@example.test');
        }

        public function exports(): array
        {
            return [CsvExport::make('soft-delete.csv')->name('csv')];
        }
    });
    [$response, $content] = sendExport($tableClass);
    $rows = readExportCsv($content);

    expect($response->getStatusCode())->toBe(200)
        ->and($rows)->toBe([['Company.name'], [null]]);
});

test('CSV export resolves morph-to targets for each concrete related model type', function () {
    $user = User::create(['name' => 'Morph User', 'email' => 'morph-user@example.test', 'is_admin' => false]);
    $company = Company::create(['name' => 'Morph Company']);
    $user->images()->create(['title' => 'User image']);
    Image::create([
        'imageable_id' => $company->id,
        'imageable_type' => Company::class,
        'title' => 'Company image',
    ]);

    $tableClass = get_class(new class(request()) extends RelationshipImageTable
    {
        public function exports(): array
        {
            return [CsvExport::make('morph.csv')->name('csv')];
        }
    });
    [$response, $content] = sendExport($tableClass);
    $rows = readExportCsv($content);
    $values = array_column(array_slice($rows, 1), 0);
    sort($values);

    expect($response->getStatusCode())->toBe(200)
        ->and($values)->toBe(['Morph Company', 'Morph User']);
});
