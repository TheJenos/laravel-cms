<?php

namespace App\Models\CMS;

use App\Models\CMS\Article;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = [
		'article_id',
		'title',
		'content',
	];

	public function article()
	{
		return $this->belongsTo(Article::class);
	}
}
