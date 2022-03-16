<?php

namespace App\Console\Commands;

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
        // Delete the old migrations
        if ($stepsToRollback > 0) {
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
        $oldModels = glob('app/Models/CMS/*.php');
        foreach ($oldModels as $oldModel) {
            unlink($oldModel);
        }

        // Create a new migration file
        foreach (CmsModel::with('columns')->get() as $model) {
            $this->info("Creating migration for {$model->name}");
            $this->call('make:migration', [
                'name' => 'create_cms_' . Str::snake($model->table_name) . '_table',
                '--create' => Str::snake($model->table_name),
                '--path' => 'database/cms_migrations',
            ]);
            $this->newLine(1);

            $this->info("Add columns for {$model->name}");
            $this->addTableColumn($model->table_name, $model->columns);
            $this->newLine(1);

            $mappingTable = $model->columns->filter(fn ($column) => $column->relation && $column->relation['relation'] === 'manyToMany')->values();
            if ($mappingTable->isNotEmpty()) {
                $this->info("Add pivot table for {$model->name}");
                foreach ($mappingTable as $column) {
                    $this->call('make:migration', [
                        'name' => 'create_cms_' . Str::snake($model->table_name) . '_' . Str::snake(Str::plural($column->relation['model'])) . '_table',
                        '--create' => Str::snake($model->table_name) . '_' . Str::snake(Str::plural($column->relation['model'])),
                        '--path' => 'database/cms_migrations',
                    ]);
                    $this->newLine(1);
                }

                $this->info("Add pivot columns for {$model->name}");
                $this->addTablePivotColumn(
                    Str::snake($model->table_name) . '_' . Str::snake(Str::plural($column->relation['model'])),
                    Str::snake($model->table_name),
                    Str::snake(Str::plural($column->relation['model']))
                );
                $this->newLine(1);
            }

            $this->info("Creating model for {$model->name}");
            $this->call('make:model', [
                'name' => 'App\\Models\\CMS\\' . Str::studly($model->name),
            ]);
            $this->newLine(1);

            $this->info("Add model data for {$model->name}");
            $this->addModelData($model->table_name, $model->columns);
            $this->newLine(1);
        }

        $this->call('migrate');
        return 0;
    }


    private function addTablePivotColumn($tableName, $relation1, $relation2)
    {

        $migrationFile = array_filter(glob('database/cms_migrations/*.php'), function ($file) use ($tableName) {
            return str_contains($file, 'create_cms_' . Str::snake($tableName) . '_table');
        });
        $migrationFile = array_pop($migrationFile);
        if (!$migrationFile) {
            return;
        }
        // Read the migration file
        $migrationFileContents = file_get_contents($migrationFile);
        $imports = '';
        $payload = "";

        $payload .= "\$table->foreignIdFor(" . Str::studly(Str::singular($relation1)) . '::class)->constrained(\'' . Str::plural($relation1) . '\');' . "\n";
        $imports .= 'use App\Models\CMS\\' . Str::studly(Str::singular($relation1)) . ';' . "\n";
        $payload .= "\t\t\t\$table->foreignIdFor(" . Str::studly(Str::singular($relation2)) . '::class)->constrained(\'' . Str::plural($relation2) . '\');' . "\n";
        $imports .= 'use App\Models\CMS\\' . Str::studly(Str::singular($relation2)) . ';' . "\n";

        // Find the line where the table columns are defined
        $migrationFileContents = str_replace("\$table->id();\n", $payload, $migrationFileContents);
        $migrationFileContents = str_replace(
            "\$table->timestamps();\n",
            "\$table->primary(['" . Str::snake(Str::singular($relation1)) . "_id', '" . Str::snake(Str::singular($relation2)) . "_id']);\n",
            $migrationFileContents
        );

        // Add the imports
        $migrationFileContents = str_replace('use Illuminate\Database\Schema\Blueprint;', $imports . "use Illuminate\Database\Schema\Blueprint;\n", $migrationFileContents);

        // Write the migration file
        file_put_contents($migrationFile, $migrationFileContents);
    }

    private function addTableColumn($tableName, Collection $columns)
    {
        $migrationFile = array_filter(glob('database/cms_migrations/*.php'), function ($file) use ($tableName) {
            return str_contains($file, 'create_cms_' . Str::snake($tableName) . '_table');
        });
        $migrationFile = array_pop($migrationFile);
        if (!$migrationFile) {
            return;
        }
        // Read the migration file
        $migrationFileContents = file_get_contents($migrationFile);
        $imports = '';
        $payload = "";

        // Add the columns
        foreach ($columns as $column) {
            $payloadDataType = '';
            switch ($column->data_type) {
                case CmsModelColumn::$RELATION:
                    $payloadDataType = 'foreignIdFor';
                    break;
                case CmsModelColumn::$STRING:
                    $payloadDataType = 'string';
                    break;
                case CmsModelColumn::$INTEGER:
                    $payloadDataType = 'integer';
                    break;
                case CmsModelColumn::$TEXT:
                    $payloadDataType = 'text';
                    break;
            }
            $suffix = '';

            if ($column->data_type === CmsModelColumn::$RELATION && $column->relation['relation'] === 'manyToMany') {
                continue;
            }

            if ($column->data_type == CmsModelColumn::$RELATION) {
                $tempColumnName = Str::studly($column->relation['model']) . '::class';
                $suffix .= '->constrained(\'' . Str::plural($column->relation['model']) . '\')';
                $imports .= 'use App\Models\CMS\\' . Str::studly($column->relation['model']) . ';' . "\n";
            } else {
                $tempColumnName = '\'' . $column->column . '\'';
            }
            if ($column->data_type_params['nullable'] ?? false) {
                $suffix .= '->nullable()';
            }
            if ($column->data_type_params['unsigned'] ?? false) {
                $suffix .= '->unsigned()';
            }
            if ($column->data_type_params['default'] ?? false) {
                $suffix .= '->default(\'' . $column->data_type_params->default . '\')';
            }
            if ($column->data_type_params['comment'] ?? false) {
                $suffix .= '->comment(\'' . $column->data_type_params->comment . '\')';
            }
            $payload .= "\t\t\t\$table->" . $payloadDataType . '(' . $tempColumnName . ')' . $suffix . ';' . "\n";
        }

        // Find the line where the table columns are defined
        $migrationFileContents = str_replace("\$table->id();\n", "\$table->id();\n" . $payload, $migrationFileContents);

        // Add the imports
        $migrationFileContents = str_replace('use Illuminate\Database\Schema\Blueprint;', $imports . "use Illuminate\Database\Schema\Blueprint;\n", $migrationFileContents);

        // Write the migration file
        file_put_contents($migrationFile, $migrationFileContents);
    }

    private function addModelData($tableName, Collection $columns)
    {
        $modelFile = __DIR__ . '/../../Models/CMS/' . Str::studly(Str::singular($tableName)) . '.php';

        // Read the model file
        $modelFileContents = file_get_contents($modelFile);
        $imports = '';
        $payload = "";

        $fillablePayload = "protected \$fillable = [\n";
        $relationsPayload = "\n";

        foreach ($columns as $column) {
            if ($column->data_type == CmsModelColumn::$RELATION) {
                if ($column->relation['relation'] !== 'manyToMany') {
                    $fillablePayload .= "\t\t'" .  Str::snake($column->relation['model']) . "_id'," . "\n";
                }
                $imports .= 'use App\Models\CMS\\' . Str::studly($column->relation['model']) . ';' . "\n";

                $extra = "";

                switch ($column->relation['relation']) {
                    case 'oneToMany':
                        $relation = 'belongsTo';
                        $singular = true;
                        break;
                    case 'manyToMany':
                        $relation = 'belongsToMany';
                        $singular = false;
                        $extra = ', \'' . $tableName . '_' . Str::snake(Str::plural($column->relation['model'])) . '\'';
                        break;
                    default:
                        $relation = 'belongsTo';
                        $singular = true;
                }

                $relegationMethodsName = Str::camel($singular ? Str::singular($column->relation['model']) : Str::plural($column->relation['model']));

                $relationsPayload .= "\tpublic function " . $relegationMethodsName . "()\n";
                $relationsPayload .= "\t{\n";
                $relationsPayload .= "\t\treturn \$this->" . $relation . "(" . Str::studly($column->relation['model']) . "::class" . $extra . ");\n";
                $relationsPayload .= "\t}\n\n";
            } else {
                $fillablePayload .= "\t\t'" . $column->column . "'," . "\n";
            }
        }

        // foreign relation methods
        $foreignColumns = CmsModelColumn::where('relation->model', Str::singular($tableName))->with('model')->get();
        foreach ($foreignColumns as $column) {
            $foreignModel = Str::studly($column->model->name);
            $imports .= 'use App\Models\CMS\\' . Str::studly($foreignModel) . ';' . "\n";

            $extra = "";

            switch ($column->relation['relation']) {
                case 'oneToMany':
                    $relation = 'hasMany';
                    $singular = false;
                    break;
                case 'manyToMany':
                    $relation = 'belongsToMany';
                    $singular = false;
                    $extra = ', \'' . Str::snake(Str::plural($foreignModel)) . '_' . $tableName . '\'';
                    break;
                default:
                    $relation = 'hasOne';
                    $singular = true;
            }

            $relegationMethodsName = Str::camel($singular ? Str::singular($foreignModel) : Str::plural($foreignModel));
            $relationsPayload .= "\tpublic function " . $relegationMethodsName . "()\n";
            $relationsPayload .= "\t{\n";
            $relationsPayload .= "\t\treturn \$this->" . $relation . "(" . Str::studly($foreignModel) . "::class" . $extra . ");\n";
            $relationsPayload .= "\t}\n\n";
        }


        $fillablePayload .= "\t];\n";

        $payload .= $fillablePayload;
        $payload .= $relationsPayload;

        // Find the line where the table columns are defined
        $modelFileContents = str_replace("use HasFactory;\n", trim($payload) . "\n", $modelFileContents);

        // Add the imports
        $modelFileContents = str_replace('use Illuminate\Database\Eloquent\Factories\HasFactory;', $imports, $modelFileContents);

        // Write the migration file
        file_put_contents($modelFile, $modelFileContents);
    }
}
