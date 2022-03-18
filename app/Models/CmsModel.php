<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsModel extends Model
{
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

    public function changes()
    {
        return $this->hasMany(CmsModelChanges::class);
    }
}
