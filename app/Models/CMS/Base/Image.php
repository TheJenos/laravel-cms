<?php

namespace App\Models\CMS\Base;

use App\Models\CMS\ArticleCategory;
use App\Models\CMS\Comment;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = [
		'path',
		'new_path',
	];

    public function articleCategories()
    {
        return $this->belongsToMany(ArticleCategory::class, 'article_categories_images');
    }

    public function comment()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
