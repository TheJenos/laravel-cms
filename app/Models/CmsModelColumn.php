<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsModelColumn extends Model
{
    public static int $RELATION = 0;
    public static int $STRING = 1;
    public static int $INTEGER = 2;
    public static int $TEXT = 3;

    use HasFactory;

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

    public function model()
    {
        return $this->belongsTo(CmsModel::class,'cms_model_id');
    }
}
