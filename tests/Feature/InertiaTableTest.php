<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Tests\Fixtures\User;
use Tests\Fixtures\UsersTable;

beforeEach(function () {
    User::create(['name' => 'John Doe', 'email' => 'john@example.com', 'is_admin' => true]);
    User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'is_admin' => false]);
});

test('it can render table data for inertia', function () {
    $table = new UsersTable;
    $data = $table->toInertia();

    expect($data)->toHaveKeys(['data', 'columns', 'filters', 'query', 'meta']);
    expect($data['data'])->toHaveCount(2);
    expect($data['columns'])->toHaveCount(5);
});

test('it can filter data', function () {
    $request = new Request(['filter' => ['is_admin' => '1']]);
    $table = new UsersTable($request);
    $data = $table->toInertia();

    expect($data['data'])->toHaveCount(1);
    expect($data['data'][0]['name'])->toBe('John Doe');
});

test('it can search data', function () {
    $request = new Request(['search' => 'Jane']);
    $table = new UsersTable($request);
    $data = $table->toInertia();

    expect($data['data'])->toHaveCount(1);
    expect($data['data'][0]['name'])->toBe('Jane Doe');
});

test('it can sort data', function () {
    // Sort by name desc
    $request = new Request(['sort' => 'name', 'dir' => 'desc']);
    $table = new UsersTable($request);
    $data = $table->toInertia();

    expect($data['data'][0]['name'])->toBe('John Doe');
    expect($data['data'][1]['name'])->toBe('Jane Doe');

    // Sort by name asc
    $request = new Request(['sort' => 'name', 'dir' => 'asc']);
    $table = new UsersTable($request);
    $data = $table->toInertia();

    expect($data['data'][0]['name'])->toBe('Jane Doe');
    expect($data['data'][1]['name'])->toBe('John Doe');
});

test('it includes columns configuration', function () {
    $table = new UsersTable;
    $data = $table->toInertia();

    $nameColumn = collect($data['columns'])->firstWhere('key', 'name');

    expect($nameColumn['sortable'])->toBeTrue();
    expect($nameColumn['searchable'])->toBeTrue();
    expect($nameColumn['label'])->toBe('Name');
});

test('it includes row links if configured', function () {
    $table = new class extends UsersTable
    {
        public function query(): Builder
        {
            $this->rowLink(fn ($user) => "/users/{$user->id}");

            return parent::query();
        }
    };

    $data = $table->toInertia();
    expect($data['data'][0]['_row_link'])->not->toBeNull();
});

test('it can handle bulk actions', function () {
    $table = new UsersTable;
    $bulkActions = $table->bulkActions();

    expect($bulkActions)->toHaveCount(1);
    expect($bulkActions[0]->key)->toBe('delete');

    // Simulate bulk action call
    $bulkActions[0]->handle([1], false);

    expect(User::find(1))->toBeNull();
    expect(User::find(2))->not->toBeNull();
});

test('it can execute bulk action through controller', function () {
    $tableClass = UsersTable::class;
    $payload = [
        'table_class' => encrypt($tableClass),
        'action_key' => 'delete',
        'ids' => [2],
    ];

    $response = $this->post(route('inertia-table.bulk-action'), $payload);

    $response->assertStatus(302);
    expect(User::find(2))->toBeNull();
});
