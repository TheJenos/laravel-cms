<?php

namespace App\Models\CMS;

use App\Models\CMS\Image;
use App\Models\CMS\Article;
use Illuminate\Database\Eloquent\Model;

class ArticleCategory extends Model
{
    protected $fillable = [
		'name',
	];

    public function images()
    {
        return $this->belongsToMany(Image::class, 'article_categories_images');
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }
}
