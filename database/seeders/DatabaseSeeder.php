<?php

namespace Database\Seeders;

use App\Models\CmsModel;
use App\Models\CmsModelColumn;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $tables = [
            'image' => [
                [
                    'column' => 'path',
                    'data_type' => CmsModelColumn::$STRING,
                ],
            ],
            'article_category' => [
                [
                    'column' => 'name',
                    'data_type' => CmsModelColumn::$STRING,
                ],
                [
                    'column' => 'image',
                    'data_type' => CmsModelColumn::$RELATION,
                    'relation' => [
                        'model' => 'image',
                        'relation' => 'manyToMany',
                    ],
                ],
            ],
            'article' => [
                [
                    'column' => 'article_category',
                    'data_type' => CmsModelColumn::$RELATION,
                    'relation' => [
                        'model' => 'article_category',
                        'relation' => 'oneToMany',
                    ],
                ],
                [
                    'column' => 'title',
                    'data_type' => CmsModelColumn::$STRING,
                ],
                [
                    'column' => 'content',
                    'data_type' => CmsModelColumn::$TEXT,
                ],
            ],
            'comment' => [
                [
                    'column' => 'article',
                    'data_type' => CmsModelColumn::$RELATION,
                    'relation' => [
                        'model' => 'article',
                        'relation' => 'oneToMany',
                    ],
                ],
                [
                    'column' => 'title',
                    'data_type' => CmsModelColumn::$STRING,
                ],
                [
                    'column' => 'content',
                    'data_type' => CmsModelColumn::$TEXT,
                    'data_type_params' => [
                        'nullable' => true,
                    ]
                ],
            ],
        ];


        foreach ($tables as $key => $value) {
            $table =  CmsModel::create([
                'name' => Str::singular($key),
                'table_name' => Str::plural($key),
            ]);

            foreach ($value as $column) {
                $table->columns()->create($column);
            }
        }
    }
}
