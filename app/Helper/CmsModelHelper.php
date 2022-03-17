<?php

namespace App\Helper;

use App\Models\CmsModelColumn;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class CmsModelHelper
{

    public static function extendBaseModel($tableName)
    {
        $studlyModel = Str::studly(Str::singular($tableName));
        $modelFile = 'app/Models/CMS/' . Str::studly(Str::singular($tableName)) . '.php';
        $modelFileContents = file_get_contents($modelFile);

        // Find the line where the table columns are defined
        $modelFileContents = str_replace("extends Model\n", "extends Base$studlyModel\n", $modelFileContents);

        // Add the imports
        $modelFileContents = str_replace("use Illuminate\Database\Eloquent\Model;\n", "use App\Models\CMS\Base\\$studlyModel as Base$studlyModel;\n", $modelFileContents);

        // Write the migration file
        file_put_contents($modelFile, $modelFileContents);
    }


    public static function addModelData($tableName, Collection $columns)
    {
        $snakeMainModel = Str::singular($tableName);
        $modelFile = 'app/Models/CMS/Base/' . Str::studly(Str::singular($tableName)) . '.php';

        // Read the model file
        $modelFileContents = file_get_contents($modelFile);
        $imports = '';
        $payload = "";

        $fillablePayload = "protected \$fillable = [\n";
        $relationsPayload = "\n";

        foreach ($columns as $column) {
            if ($column->data_type == CmsModelColumn::$RELATION) {
                $studlyModel = Str::studly($column->relation['model']);
                $snakeModel = Str::snake($column->relation['model']);
                $snakePluralModel = Str::snake(Str::plural($column->relation['model']));

                if ($column->relation['relation'] !== 'manyToMany') {
                    $fillablePayload .= "\t\t'{$snakeModel}_id',\n";
                }

                $extra = "";

                $relationParam = "{$studlyModel}::class";

                switch ($column->relation['relation']) {
                    case 'morph':
                        $relation = 'morphTo';
                        $singular = true;
                        $relationParam = '';
                        $hasImport = false;
                        break;
                    case 'oneToMany':
                        $relation = 'belongsTo';
                        $singular = true;
                        $hasImport = true;
                        break;
                    case 'manyToMany':
                        $relation = 'belongsToMany';
                        $singular = false;
                        $extra = ", '{$tableName}_{$snakePluralModel}'";
                        $hasImport = true;
                        break;
                }

                if ($hasImport) {
                    $imports .= "use App\Models\CMS\\$studlyModel;\n";
                }

                $relegationMethodsName = Str::camel($singular ? Str::singular($column->relation['model']) : Str::plural($column->relation['model']));

                $relationsPayload .= <<<END

                    public function {$relegationMethodsName}()
                    {
                        return \$this->{$relation}({$relationParam}{$extra});
                    }

                END;
            } else {
                $fillablePayload .= "\t\t'{$column->column}',\n";
            }
        }

        $fillablePayload .= "\t];\n";

        // foreign relation methods
        $foreignColumns = CmsModelColumn::where('relation->model', Str::singular($tableName))
            ->orWhereJsonContains('relation->models', Str::singular($tableName))
            ->with('model')
            ->get();

        foreach ($foreignColumns as $column) {
            $foreignModel = Str::studly($column->model->name);
            $snakePluralModel = Str::snake(Str::plural($foreignModel));

            $imports .= "use App\Models\CMS\\$foreignModel;\n";

            $extra = "";
            switch ($column->relation['relation']) {
                case 'morph':
                    if (($column->relation['relations'][$snakeMainModel] ?? null) == 'oneToMany') {
                        $relation = 'morphMany';
                    } else {
                        $relation = 'morphOne';
                    }
                    $singular = true;
                    $extra = ", '{$column->relation['model']}'";
                    break;
                case 'oneToOne':
                    $relation = 'hasOne';
                    $singular = true;
                    break;
                case 'oneToMany':
                    $relation = 'hasMany';
                    $singular = false;
                    break;
                case 'manyToMany':
                    $relation = 'belongsToMany';
                    $singular = false;
                    $extra = ", '{$snakePluralModel}_{$tableName}'";
                    break;
            }

            $relegationMethodsName = Str::camel($singular ? Str::singular($foreignModel) : Str::plural($foreignModel));

            $relationsPayload .= <<<END

                public function {$relegationMethodsName}()
                {
                    return \$this->{$relation}({$foreignModel}::class{$extra});
                }

            END;
        }

        $payload .= $fillablePayload . "\n";
        $payload .= trim($relationsPayload, "\n");

        // Find the line where the table columns are defined
        $modelFileContents = str_replace("use HasFactory;\n", trim($payload) . "\n", $modelFileContents);

        // Add the imports
        $modelFileContents = str_replace("use Illuminate\Database\Eloquent\Factories\HasFactory;\n", trim($imports, '\n'), $modelFileContents);

        // Write the migration file
        file_put_contents($modelFile, $modelFileContents);
    }
}
