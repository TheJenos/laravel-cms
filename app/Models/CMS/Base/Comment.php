<?php

namespace App\Models\CMS\Base;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = [
		'title',
		'content',
		'commentable_id',
	];

    public function commentable()
    {
        return $this->morphTo();
    }
}
