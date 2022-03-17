<?php

namespace App\Console\Commands;

use App\Helper\CmsHelper;
use App\Helper\CmsModelHelper;
use App\Helper\CmsTableHelper;
use App\Models\CmsModel;
use App\Models\CmsModelColumn;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CmsInitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration and model for CMS';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $migrationCount = DB::table('migrations')->count();
        $baseMigrations = glob('database/migrations/*.php');
        $stepsToRollback = $migrationCount - count($baseMigrations);
        $migrationCount = count($baseMigrations);
        
        // Rollback the old migrations
        if ($stepsToRollback >= 1) {
            $this->call('migrate:rollback', [
                '--step' => $stepsToRollback,
            ]);
        }
        $oldMigrations = glob('database/cms_migrations/*.php');
        foreach ($oldMigrations as $oldMigration) {
            unlink($oldMigration);
        }
        $this->newLine();
        
        // Delete the old models
        $oldModels = glob('app/Models/CMS/Base/*.php');
        foreach ($oldModels as $oldModel) {
            unlink($oldModel);
        }
        
        $migrationOrder = 0;

        // Create a new migration file
        foreach (CmsModel::with('columns')->get() as $model) {
            $this->info("Creating migration for {$model->name}");
            $this->call('make:migration', [
                'name' => 'create_cms_' . Str::snake($model->table_name) . '_table',
                '--create' => Str::snake($model->table_name),
                '--path' => 'database/cms_migrations',
            ]);

            $migrationOrder++;
            $migrationFile = array_filter(glob('database/cms_migrations/*.php'), function ($file) use ($model) {
                return str_contains($file, 'create_cms_' . Str::snake($model->table_name) . '_table');
            });
            $migrationFile = array_pop($migrationFile);
            rename($migrationFile, 'database/cms_migrations/' . $migrationOrder . '_create_cms_' . Str::snake($model->table_name) . '_table.php');

            $this->newLine(1);

            $this->info("Add columns for {$model->name}");
            CmsTableHelper::addTableColumn($migrationOrder, $model->table_name, $model->columns);
            $this->newLine(1);

            $mappingTable = $model->columns->filter(fn ($column) => $column->relation && $column->relation['relation'] === 'manyToMany')->values();
            if ($mappingTable->isNotEmpty()) {
                $this->info("Add pivot table for {$model->name}");
                foreach ($mappingTable as $column) {
                    $migrationFileName = 'create_cms_' . Str::snake($model->table_name) . '_' . Str::snake(Str::plural($column->relation['model'])) . '_table';

                    $this->call('make:migration', [
                        'name' => $migrationFileName,
                        '--create' => Str::snake($model->table_name) . '_' . Str::snake(Str::plural($column->relation['model'])),
                        '--path' => 'database/cms_migrations',
                    ]);
                    $this->newLine(1);

                    $migrationOrder++;
                    $migrationFile = array_filter(glob('database/cms_migrations/*.php'), function ($file) use ($migrationFileName) {
                        return str_contains($file, $migrationFileName);
                    });
                    $migrationFile = array_pop($migrationFile);
                    rename($migrationFile, 'database/cms_migrations/' . $migrationOrder . '_' . $migrationFileName . '.php');

                    $this->info("Add pivot columns for {$model->name}");
                    CmsTableHelper::addTablePivotColumn(
                        $migrationOrder,
                        Str::snake($model->table_name) . '_' . Str::snake(Str::plural($column->relation['model'])),
                        Str::snake($model->table_name),
                        Str::snake(Str::plural($column->relation['model']))
                    );
                }

                $this->newLine(1);
            }

            $this->info("Creating base model for {$model->name}");
            $this->call('make:model', [
                'name' => 'App\\Models\\CMS\\Base\\' . Str::studly($model->name),
            ]);
            $this->newLine(1);

            if (!file_exists(base_path('app/Models/CMS/' . Str::studly($model->name) . '.php'))) {
                $this->info("Creating model for {$model->name}");
                $this->call('make:model', [
                    'name' => 'App\\Models\\CMS\\' . Str::studly($model->name),
                ]);
                $this->newLine(1);

                $this->info("extend base model for {$model->name}");
                CmsModelHelper::extendBaseModel($model->table_name);
                $this->newLine(1);
            }

            $this->info("Add base model data for {$model->name}");
            CmsModelHelper::addModelData($model->table_name, $model->columns);
            $this->newLine(1);
        }

        $this->call('migrate');
        return 0;
    }
}
