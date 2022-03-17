<?php

namespace App\Helper;

use App\Models\CmsModelColumn;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CmsTableHelper
{

    public static function addTableColumn($order, $tableName, Collection $columns)
    {
        // $migrationFile = array_filter(glob('database/cms_migrations/*.php'), function ($file) use ($tableName) {
        //     return str_contains($file, 'create_cms_' . Str::snake($tableName) . '_table');
        // });
        // $migrationFile = array_pop($migrationFile);
        // if (!$migrationFile) {
        //     return;
        // }

        $migrationFile = 'database/cms_migrations/' . $order . '_create_cms_' . Str::snake($tableName) . '_table.php';

        // Read the migration file
        $migrationFileContents = file_get_contents($migrationFile);
        $imports = '';
        $payload = "";

        // Add the columns
        foreach ($columns as $column) {
            $payloadDataType = '';
            switch ($column->data_type) {
                case CmsModelColumn::$RELATION:
                    if ($column->relation['relation'] === 'morph') {
                        $payloadDataType = 'morphs';
                    } else {
                        $payloadDataType = 'foreignIdFor';
                    }
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
                if ($column->relation['relation'] === 'morph') {
                    $tempColumnName = "'{$column->relation['model']}'";
                } else {
                    $tempColumnName = Str::studly($column->relation['model']) . '::class';
                    $suffix .= '->constrained(\'' . Str::plural($column->relation['model']) . '\')';
                    $imports .= 'use App\Models\CMS\\' . Str::studly($column->relation['model']) . ';' . "\n";
                }
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
                $suffix .= "->default({$column->data_type_params->default})";
            }
            if ($column->data_type_params['comment'] ?? false) {
                $suffix .= "->comment({$column->data_type_params->comment})";
            }
            $payload .= "\t\t\t\$table->{$payloadDataType}({$tempColumnName}){$suffix};\n";
        }

        // Find the line where the table columns are defined
        $migrationFileContents = str_replace("\$table->id();\n", "\$table->id();\n" . $payload, $migrationFileContents);

        // Add the imports
        $migrationFileContents = str_replace('use Illuminate\Database\Schema\Blueprint;', $imports . "use Illuminate\Database\Schema\Blueprint;\n", $migrationFileContents);

        // Write the migration file
        file_put_contents($migrationFile, $migrationFileContents);
    }

    public static function addTablePivotColumn($order, $tableName, $relation1, $relation2)
    {
        $pluralRelation1 =  Str::plural($relation1);
        $studlySingularRelation1 =  Str::studly(Str::singular($relation1));
        $snakeSingularRelation1 =  Str::snake(Str::singular($relation1));

        $pluralRelation2 =  Str::plural($relation2);
        $studlySingularRelation2 =  Str::studly(Str::singular($relation2));
        $snakeSingularRelation2 =  Str::snake(Str::singular($relation2));

        $migrationFile = 'database/cms_migrations/' . $order . '_create_cms_' . Str::snake($tableName) . '_table.php';

        // Read the migration file
        $migrationFileContents = file_get_contents($migrationFile);
        $imports = <<<END
        use App\Models\CMS\\$studlySingularRelation1;
        use App\Models\CMS\\$studlySingularRelation2;

        END;

        $payload = <<<END
                    \$table->foreignIdFor($studlySingularRelation1::class)->constrained('$pluralRelation1');
                    \$table->foreignIdFor($studlySingularRelation2::class)->constrained('$pluralRelation2');

        END;

        // Find the line where the table columns are defined
        $migrationFileContents = str_replace("            \$table->id();\n", $payload, $migrationFileContents);
        $migrationFileContents = str_replace(
            "\$table->timestamps();\n",
            "\$table->primary(['{$snakeSingularRelation1}_id', '{$snakeSingularRelation2}_id']);\n",
            $migrationFileContents
        );

        // Add the imports
        $migrationFileContents = str_replace('use Illuminate\Database\Schema\Blueprint;', $imports . "use Illuminate\Database\Schema\Blueprint;\n", $migrationFileContents);

        // Write the migration file
        file_put_contents($migrationFile, $migrationFileContents);
    }
}
