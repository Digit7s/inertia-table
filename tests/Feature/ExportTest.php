<?php

use Digit7s\InertiaTable\Exports\CsvExport;
use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Http\Request;
use Tests\Fixtures\User;
use Tests\Fixtures\UsersTable;
use Illuminate\Support\Facades\Route;

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
    $table = new class($request) extends UsersTable {
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
    $tableClass = get_class(new class extends UsersTable {
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
    $tableClass = get_class(new class extends UsersTable {
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
        'table_class' => encrypt(\stdClass::class),
        'export_name' => 'csv',
    ]));

    $response->assertStatus(403);
});

test('export route returns 404 for invalid export name', function () {
    $tableClass = get_class(new class extends UsersTable {
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
