<?php
namespace App\Helper;

use App\Models\CmsModelColumn;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class CmsModelHelper {

    public static function addModelData($tableName, Collection $columns)
    {
        $modelFile = 'app/Models/CMS/' . Str::studly(Str::singular($tableName)) . '.php';

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
                $imports .= "use App\Models\CMS\\$studlyModel;\n";

                $extra = "";

                switch ($column->relation['relation']) {
                    case 'oneToMany':
                        $relation = 'belongsTo';
                        $singular = true;
                        break;
                    case 'manyToMany':
                        $relation = 'belongsToMany';
                        $singular = false;
                        $extra = ", '{$tableName}_{$snakePluralModel}'";
                        break;
                    default:
                        $relation = 'belongsTo';
                        $singular = true;
                }

                $relegationMethodsName = Str::camel($singular ? Str::singular($column->relation['model']) : Str::plural($column->relation['model']));

                $relationsPayload .= <<<END

                    public function {$relegationMethodsName}()
                    {
                        return \$this->{$relation}({$studlyModel}::class{$extra});
                    }

                END;
            } else {
                $fillablePayload .= "\t\t'{$column->column}',\n";
            }
        }

        $fillablePayload .= "\t];\n";

        // foreign relation methods
        $foreignColumns = CmsModelColumn::where('relation->model', Str::singular($tableName))->with('model')->get();
        foreach ($foreignColumns as $column) {
            $foreignModel = Str::studly($column->model->name);
            $snakePluralModel = Str::snake(Str::plural($foreignModel));

            $imports .= "use App\Models\CMS\\$foreignModel;\n";

            $extra = "";
            switch ($column->relation['relation']) {
                case 'oneToMany':
                    $relation = 'hasMany';
                    $singular = false;
                    break;
                case 'manyToMany':
                    $relation = 'belongsToMany';
                    $singular = false;
                    $extra = ", '{$snakePluralModel}_{$tableName}'";
                    break;
                default:
                    $relation = 'hasOne';
                    $singular = true;
            }

            $relegationMethodsName = Str::camel($singular ? Str::singular($foreignModel) : Str::plural($foreignModel));

            $relationsPayload .= <<<END

                public function {$relegationMethodsName}()
                {
                    return \$this->{$relation}({$foreignModel}::class{$extra});
                }

            END;
        }

        $payload .= $fillablePayload."\n";
        $payload .= trim($relationsPayload,"\n");

        // Find the line where the table columns are defined
        $modelFileContents = str_replace("use HasFactory;\n", trim($payload) . "\n", $modelFileContents);

        // Add the imports
        $modelFileContents = str_replace("use Illuminate\Database\Eloquent\Factories\HasFactory;\n", trim($imports,'\n'), $modelFileContents);

        // Write the migration file
        file_put_contents($modelFile, $modelFileContents);
    }
}