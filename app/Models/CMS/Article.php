<?php

namespace App\Models\CMS;

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

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
