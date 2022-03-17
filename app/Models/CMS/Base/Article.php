<?php

namespace App\Models\CMS\Base;

use App\Models\CMS\ArticleCategory;
use App\Models\CMS\Comment;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
		'article_category_id',
		'title',
		'content',
	];

    public function articleCategory()
    {
        return $this->belongsTo(ArticleCategory::class);
    }

    public function comment()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
