<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTranslation extends Model
{
    protected $fillable = ['category_id', 'locale', 'name'];

    /**
     * Get the category that owns this translation.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
