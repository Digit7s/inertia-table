<?php

use Digit7s\InertiaTable\Filter;
use Illuminate\Database\Eloquent\Builder;
use Tests\Fixtures\User;
use Tests\Fixtures\UsersTable;

function bulkActionUrlFor(string $tableClass, array $payload): array
{
    return array_merge([
        'table_class' => encrypt($tableClass),
        'action_key' => 'delete',
    ], $payload);
}

test('select all matching uses search from the frontend post body', function () {
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'is_admin' => false]);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'is_admin' => false]);

    $this->post(route('inertia-table.bulk-action').'?search=Bob', bulkActionUrlFor(UsersTable::class, [
        'ids' => [],
        'selectAllMatching' => true,
        'search' => 'Alice',
        'filters' => '{}',
    ]))->assertRedirect();

    expect(User::find($alice->id))->toBeNull()
        ->and(User::find($bob->id))->not->toBeNull();
});

test('select all matching applies multiple filters from the frontend payload', function () {
    $matching = User::create(['name' => 'Alice Admin', 'email' => 'alice-admin@example.com', 'is_admin' => true]);
    $otherName = User::create(['name' => 'Bob Admin', 'email' => 'bob-admin@example.com', 'is_admin' => true]);
    $otherStatus = User::create(['name' => 'Alice Guest', 'email' => 'alice-guest@example.com', 'is_admin' => false]);
    $table = new class extends UsersTable
    {
        public function filters(): array
        {
            return [
                Filter::make('name'),
                Filter::make('is_admin')->type('boolean'),
            ];
        }
    };

    $this->post(route('inertia-table.bulk-action'), bulkActionUrlFor($table::class, [
        'ids' => [],
        'selectAllMatching' => true,
        'filters' => json_encode(['name' => 'Alice', 'is_admin' => '1']),
    ]))->assertRedirect();

    expect(User::find($matching->id))->toBeNull()
        ->and(User::find($otherName->id))->not->toBeNull()
        ->and(User::find($otherStatus->id))->not->toBeNull();
});

test('select all matching combines search and filters', function () {
    $matching = User::create(['name' => 'Alice Admin', 'email' => 'alice-admin@example.com', 'is_admin' => true]);
    $wrongSearch = User::create(['name' => 'Bob Admin', 'email' => 'bob-admin@example.com', 'is_admin' => true]);
    $wrongFilter = User::create(['name' => 'Alice Guest', 'email' => 'alice-guest@example.com', 'is_admin' => false]);

    $this->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, [
        'ids' => [],
        'selectAllMatching' => true,
        'search' => 'Alice',
        'filters' => json_encode(['is_admin' => '1']),
    ]))->assertRedirect();

    expect(User::find($matching->id))->toBeNull()
        ->and(User::find($wrongSearch->id))->not->toBeNull()
        ->and(User::find($wrongFilter->id))->not->toBeNull();
});

test('explicit selection accepts only unique in-scope ids', function () {
    $john = User::create(['name' => 'John', 'email' => 'john@example.com', 'is_admin' => false]);
    $jane = User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'is_admin' => false]);

    $this->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, [
        'ids' => [$jane->id, $jane->id],
    ]))->assertRedirect();

    expect(User::find($jane->id))->toBeNull()
        ->and(User::find($john->id))->not->toBeNull();
});

test('explicit selection rejects ids outside the table query scope', function () {
    $inScope = User::create(['name' => 'Admin', 'email' => 'admin-scope@example.com', 'is_admin' => true]);
    $outOfScope = User::create(['name' => 'Guest', 'email' => 'guest-scope@example.com', 'is_admin' => false]);
    $table = new class extends UsersTable
    {
        public function query(): Builder
        {
            return User::query()->where('is_admin', true);
        }
    };

    $this->post(route('inertia-table.bulk-action'), bulkActionUrlFor($table::class, [
        'ids' => [$outOfScope->id],
    ]))->assertUnprocessable();

    expect(User::find($inScope->id))->not->toBeNull()
        ->and(User::find($outOfScope->id))->not->toBeNull();
});

test('select all matching preserves the table base query scopes', function () {
    $inScope = User::create(['name' => 'Scoped Admin', 'email' => 'scoped-admin@example.com', 'is_admin' => true]);
    $outOfScope = User::create(['name' => 'Scoped Guest', 'email' => 'scoped-guest@example.com', 'is_admin' => false]);
    $table = new class extends UsersTable
    {
        public function query(): Builder
        {
            return User::query()->where('is_admin', true);
        }
    };

    $this->post(route('inertia-table.bulk-action'), bulkActionUrlFor($table::class, [
        'ids' => [],
        'selectAllMatching' => true,
        'filters' => '{}',
    ]))->assertRedirect();

    expect(User::find($inScope->id))->toBeNull()
        ->and(User::find($outOfScope->id))->not->toBeNull();
});

test('select all matching limits are based on the query rather than client counts', function () {
    $existingAdmin = User::create(['name' => 'Existing Admin', 'email' => 'existing-admin@example.com', 'is_admin' => true]);
    $newAdmin = User::create(['name' => 'Another Admin', 'email' => 'another-admin@example.com', 'is_admin' => true]);
    $table = new class extends UsersTable
    {
        public function query(): Builder
        {
            return User::query()->where('is_admin', true);
        }

        public function maxBulkActionRecords(): int
        {
            return 1;
        }
    };

    $this->withHeader('Accept', 'application/json')
        ->post(route('inertia-table.bulk-action'), bulkActionUrlFor($table::class, [
            'ids' => [],
            'selectAllMatching' => true,
            'filters' => '{}',
            'total' => 1,
        ]))->assertUnprocessable();

    expect(User::find($existingAdmin->id))->not->toBeNull()
        ->and(User::find($newAdmin->id))->not->toBeNull();
});

test('select all matching rejects malformed search and filter state', function () {
    $user = User::create(['name' => 'Still Here', 'email' => 'still-here@example.com', 'is_admin' => false]);

    $payload = [
        'ids' => [],
        'selectAllMatching' => true,
        'search' => ['invalid'],
        'filters' => '{}',
    ];
    $this->withHeader('Accept', 'application/json')
        ->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, $payload))
        ->assertUnprocessable();

    $payload['search'] = 'Still';
    $payload['filters'] = '{invalid';
    $this->withHeader('Accept', 'application/json')
        ->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, $payload))
        ->assertUnprocessable();

    $payload['filters'] = json_encode(['unknown_filter' => 'value']);
    $this->withHeader('Accept', 'application/json')
        ->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, $payload))
        ->assertUnprocessable();

    $payload['filters'] = json_encode(['is_admin' => 'not-a-boolean']);
    $this->withHeader('Accept', 'application/json')
        ->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, $payload))
        ->assertUnprocessable();

    expect(User::find($user->id))->not->toBeNull();
});

test('bulk action rejects malformed explicit ids and oversized id payloads', function () {
    $user = User::create(['name' => 'Still Here', 'email' => 'still-here@example.com', 'is_admin' => false]);

    $this->withHeader('Accept', 'application/json')
        ->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, [
            'ids' => [['nested' => 'value']],
        ]))->assertUnprocessable();

    $this->withHeader('Accept', 'application/json')
        ->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, [
            'ids' => array_fill(0, 1001, $user->id),
        ]))->assertUnprocessable();

    expect(User::find($user->id))->not->toBeNull();
});

test('bulk action rejects unknown actions', function () {
    $user = User::create(['name' => 'Still Here', 'email' => 'still-here@example.com', 'is_admin' => false]);

    $this->post(route('inertia-table.bulk-action'), array_merge(
        bulkActionUrlFor(UsersTable::class, ['ids' => [$user->id]]),
        ['action_key' => 'not-registered'],
    ))->assertNotFound();

    expect(User::find($user->id))->not->toBeNull();
});

test('empty explicit selection is rejected without executing the callback', function () {
    $user = User::create(['name' => 'Still Here', 'email' => 'still-here@example.com', 'is_admin' => false]);

    $this->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, [
        'ids' => [],
        'selectAllMatching' => false,
    ]))->assertUnprocessable();

    expect(User::find($user->id))->not->toBeNull();
});

test('explicit ids cannot be mixed with select all matching', function () {
    $user = User::create(['name' => 'Still Here', 'email' => 'still-here@example.com', 'is_admin' => false]);

    $this->withHeader('Accept', 'application/json')
        ->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, [
            'ids' => [$user->id],
            'selectAllMatching' => true,
            'search' => 'Still',
            'filters' => '{}',
        ]))->assertUnprocessable();

    expect(User::find($user->id))->not->toBeNull();
});

test('an empty matching result does not invoke the action', function () {
    $user = User::create(['name' => 'Still Here', 'email' => 'still-here@example.com', 'is_admin' => false]);

    $this->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, [
        'ids' => [],
        'selectAllMatching' => true,
        'search' => 'no matching record',
        'filters' => '{}',
    ]))->assertRedirect();

    expect(User::find($user->id))->not->toBeNull();
});

test('the existing explicit id request shape remains compatible', function () {
    $user = User::create(['name' => 'Selected', 'email' => 'selected@example.com', 'is_admin' => false]);

    $this->post(route('inertia-table.bulk-action'), bulkActionUrlFor(UsersTable::class, [
        'ids' => [$user->id],
    ]))->assertRedirect();

    expect(User::find($user->id))->toBeNull();
});
