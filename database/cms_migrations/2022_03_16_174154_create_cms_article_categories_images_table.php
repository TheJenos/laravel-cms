<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\CMS\ArticleCategory;
use App\Models\CMS\Image;
use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article_categories_images', function (Blueprint $table) {
            $table->foreignIdFor(ArticleCategory::class)->constrained('article_categories');
			$table->foreignIdFor(Image::class)->constrained('images');
            $table->primary(['article_category_id', 'image_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article_categories_images');
    }
};
