<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'table_name',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
    ];

    public function columns()
    {
        return $this->hasMany(CmsModelColumn::class);
    }
}
