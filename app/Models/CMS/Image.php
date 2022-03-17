<?php

namespace App\Models\CMS;

use App\Models\CMS\ArticleCategory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = [
		'path',
	];

    public function articleCategories()
    {
        return $this->belongsToMany(ArticleCategory::class, 'article_categories_images');
    }
}
