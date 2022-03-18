<?php

namespace App\Console\Commands;

use App\Helper\CmsHelper;
use App\Helper\CmsModelHelper;
use App\Helper\CmsTableHelper;
use App\Models\CmsModel;
use App\Models\CmsModelColumn;
use App\Models\CmsModelColumnChanges;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CmsUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration and update model for CMS';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $lastMigration = DB::table('migrations')->orderBy('id', 'desc')->first();
        if (!preg_match("/cms_([0-9]+)_.*/", $lastMigration->migration)) {
            $this->error('No CMS migrations found. Please run "php artisan cms:init" first.');
            return 1;
        }
        $migrationOrder = (int)preg_replace("/cms_([0-9]+)_.*/", '$1', $lastMigration->migration);

        $modelsWithChanges = CmsModel::whereHas('changes')->with('changes', 'columns')->get();

        foreach ($modelsWithChanges as $model) {
            $tableName = Str::snake($model->table_name);

            $migrationFileName = 'update_cms_' . $tableName . '_table';

            $migrationOrder++;
            $newMigrationFile = 'database/cms_migrations/cms_' . $migrationOrder . '_' . $migrationFileName . '.php';

            $columnChanges = $this->getColumnChanges($model->changes);

            $imports = "";

            $upPayload = "";
            $downPayload = "";

            foreach ($columnChanges['addColumns'] as $columnChange) {
                $code = CmsTableHelper::columnToMigrationCode($columnChange);
                if ($code) {
                    if ($code['imports'])
                        $imports .= $code['imports'];
                    if ($code['up'])
                        $upPayload .= $code['up'];
                    if ($code['down'])
                        $downPayload .= $code['down'];
                }
            }

            foreach ($columnChanges['changeColumns'] as $columnChange) {
                $code = CmsTableHelper::columnToMigrationCode($columnChange['new'], true);
                if ($code) {
                    if ($code['imports'])
                        $imports .= $code['imports'];
                    if ($code['up'])
                        $upPayload .= $code['up'];
                }
                $code = CmsTableHelper::columnToMigrationCode($columnChange['old'], true);
                if ($code) {
                    if ($code['imports'])
                        $imports .= $code['imports'];
                    if ($code['up'])
                        $downPayload .= $code['up'];
                }
            }

            foreach ($columnChanges['dropColumns'] as $columnChange) {
                $code = CmsTableHelper::columnToMigrationCode($columnChange);
                if ($code) {
                    if ($code['imports'])
                        $imports .= $code['imports'];
                    if ($code['down'])
                        $upPayload .= $code['down'];
                    if ($code['up'])
                        $downPayload .= $code['up'];
                }
            }

            $imports = trim($imports, "\t\n");
            $upPayload = trim($upPayload, "\t\n");
            $downPayload = trim($downPayload, "\t\n");

            $fullCode = <<<END
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            $imports

            return new class extends Migration
            {
                /**
                 * Run the migrations.
                 *
                 * @return void
                 */
                public function up()
                {
                    Schema::table('$tableName', function (Blueprint \$table) {
                        $upPayload
                    });
                }

                /**
                 * Reverse the migrations.
                 *
                 * @return void
                 */
                public function down()
                {
                    Schema::table('$tableName', function (Blueprint \$table) {
                        $downPayload
                    });
                }
            };
            END;

            file_put_contents($newMigrationFile, $fullCode);

            $model->changes()->delete();
        };

        // Delete the old models
        $oldModels = glob('app/Models/CMS/Base/*.php');
        foreach ($oldModels as $oldModel) {
            unlink($oldModel);
        }
        
        // Create a new migration file
        foreach (CmsModel::with('columns')->get() as $model) {
            $this->call('make:model', [
                'name' => 'App\\Models\\CMS\\Base\\' . Str::studly($model->name),
                '--quiet' => true,
            ]);

            if (!file_exists(base_path('app/Models/CMS/' . Str::studly($model->name) . '.php'))) {
                $this->call('make:model', [
                    'name' => 'App\\Models\\CMS\\' . Str::studly($model->name),
                ]);

                CmsModelHelper::extendBaseModel($model->table_name);
            }

            CmsModelHelper::addModelData($model->table_name, $model->columns);
        };

        $this->call('migrate');
        return 0;
    }

    private function getColumnChanges($changes)
    {
        $outputChange = [
            'addColumns' => [],
            'changeColumns' => [],
            'dropColumns' => [],
        ];
        foreach ($changes as $change) {
            if ($change->old_data && $change->new_data) {
                $outputChange['changeColumns'][] = [
                    'old' => $change->old_data,
                    'new' => $change->new_data
                ];
            } else if ($change->old_data) {
                $outputChange['dropColumns'][] = $change->old_data;
            } else if ($change->new_data) {
                $outputChange['addColumns'][] = $change->new_data;
            }
        }
        return $outputChange;
    }
}
