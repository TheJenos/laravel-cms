<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsModelColumn extends Model
{
    public static int $RELATION = 0;
    public static int $STRING = 1;
    public static int $INTEGER = 2;
    public static int $TEXT = 3;

    protected $casts = [
        'data_type_params' => 'array',
        'relation' => 'array',
    ];

    protected $fillable = [
        'column',
        'data_type',
        'data_type_params',
        'relation',
    ];

    public static function boot() {
        parent::boot();

        self::creating(function (CmsModelColumn $model) {
            $model->model->changes()->updateOrCreate([
                'column' => $model->column,
            ], [
                'old_data' => null,
                'new_data' => $model->getAttributes(),
            ]);
        });

        self::updating(function (CmsModelColumn $model) {
            $model->model->changes()->updateOrCreate([
                'column' => $model->column,
            ], [
                'old_data' => $model->getRawOriginal(),
                'new_data' => $model->getAttributes(),
            ]);
        });

        self::deleting(function (CmsModelColumn $model) {
            $model->model->changes()->updateOrCreate([
                'column' => $model->column,
            ], [
                'old_data' => $model->getRawOriginal(),
                'new_data' => null,
            ]);
        });
    }

    public function model()
    {
        return $this->belongsTo(CmsModel::class,'cms_model_id');
    }
}
