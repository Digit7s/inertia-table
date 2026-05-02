<?php

declare(strict_types=1);

namespace Digit7s\InertiaTable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeTableCommand extends Command
{
    protected $signature = 'make:inertia-table {name} {model?}';

    protected $description = 'Create a new Inertia table class';

    public function handle()
    {
        $name = $this->argument('name');
        $model = $this->argument('model');

        // Auto-suffix Table if missing
        if (! str_ends_with($name, 'Table')) {
            $name .= 'Table';
        }

        // Auto-derive Model if missing
        if (! $model) {
            $model = str_replace('Table', '', $name);
        }

        $path = app_path("Tables/{$name}.php");

        if (File::exists($path)) {
            $this->error("Table {$name} already exists!");

            return;
        }

        File::ensureDirectoryExists(app_path('Tables'));

        $stub = $this->getStub($name, $model);

        File::put($path, $stub);

        $this->info("Table {$name} created successfully at app/Tables/{$name}.php");
    }

    protected function getStub($name, $model): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\\{$model};
use Digit7s\InertiaTable\InertiaTable;
use Digit7s\InertiaTable\Column;
use Illuminate\Database\Eloquent\Builder;

class {$name} extends InertiaTable
{
    protected ?string \$model = {$model}::class;

    public function query(): Builder
    {
        return {$model}::query();
    }

    public function columns(): array
    {
        return [
            Column::make('id')->sortable(),
            Column::make('created_at', 'Joined')->sortable(),
        ];
    }
}
PHP;
    }
}
