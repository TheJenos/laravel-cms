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
                'columns' => [
                    [
                        'column' => 'path',
                        'data_type' => CmsModelColumn::$STRING,
                    ],
                    [
                        'column' => 'delete_path',
                        'data_type' => CmsModelColumn::$STRING,
                    ],
                ]
            ],
            'article_category' => [
                'columns' => [
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
                ]
            ],
            'article' => [
                'columns' => [
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
                ]
            ],
            'comment' => [
                'columns' => [
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
                    [
                        'column' => 'commentable',
                        'data_type' => CmsModelColumn::$RELATION,
                        'relation' => [
                            'model' => 'commentable',
                            'models' => ['article', 'image'],
                            'relation' => 'morph',
                            'relations' => [
                                'article' => 'oneToMany',
                                'image' => 'oneToMany',
                            ],
                        ],
                    ],
                ]
            ],
        ];


        foreach ($tables as $key => $value) {
            /** @var CmsModel */
            $table =  CmsModel::create([
                'name' => Str::singular($key),
                'table_name' => Str::plural($key),
                'additional_data' => $value['additional_data'] ?? null,
            ]);

            foreach ($value['columns'] as $column) {
                CmsModelColumn::withoutEvents(function () use ($table, $column) {
                    $table->columns()->create($column);
                });
            }
        }

        /** @var CmsModel */
        $imageModel = CmsModel::whereName('image')->firstOrFail();

        $imageModel->columns()->create([
            'column' => 'new_path',
            'data_type' => CmsModelColumn::$STRING,
        ]);

        $imageModel->columns()->where('column','path')->first()->update([
            'data_type_params' => [
                'nullable' => true,
            ],
        ]);

        $imageModel->columns()->where('column','delete_path')->first()->delete();
    }
}
