<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Category|null $parent
 * @property Collection<int, Category> $children
 * @property Collection<int, Product> $products
 * @property int|null $children_count
 * @property int|null $products_count
 * @property string $title
 * @property string $code
 * @property int $key
 * @property int $level
 * @property string $path
 * @property string $full_path
 */
class Category extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'key',
        'parent_key',
        'code',
        'title',
        'definition',
        'level',
        'path',
    ];

    /**
     * Maintain materialized hierarchy path and level automatically.
     */
    protected static function booted()
    {
        static::saving(function ($category) {
            if (! empty($category->parent_key)) {
                $parent = self::find($category->parent_key);
                if ($parent) {
                    $category->level = $parent->level + 1;
                    $category->path = ($parent->path ?: "/{$parent->key}/")."{$category->key}/";
                } else {
                    $category->level = 1;
                    $category->path = "/{$category->key}/";
                }
            } else {
                $category->level = 0;
                $category->path = "/{$category->key}/";
            }
        });
    }

    /**
     * Get all category keys in this subtree (including self).
     */
    public function getAllDescendantKeys(): array
    {
        $path = $this->path ?: "/{$this->key}/";

        return self::where('path', 'like', $path.'%')
            ->pluck('key')
            ->toArray();
    }

    /**
     * Parent category relationship.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_key', 'key');
    }

    /**
     * Immediate child categories.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_key', 'key')->orderBy('title');
    }

    /**
     * Recursive children tree.
     *
     * @return HasMany<Category, $this>
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * Products belonging directly to this category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_key', 'key');
    }

    /**
     * Scope to find root segments (parent_key is null).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_key');
    }

    /**
     * Scope to search categories by title or code.
     */
    public function scopeSearch($query, $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('key', 'like', "%{$term}%");
        });
    }

    /**
     * In-memory cache for ancestor categories during request lifetime.
     */
    protected static array $ancestorCache = [];

    /**
     * Get breadcrumb array from root to this category.
     */
    public function getAncestors(): array
    {
        if (empty($this->path)) {
            $ancestors = [];
            $current = $this->parent;
            while ($current) {
                array_unshift($ancestors, [
                    'key' => $current->key,
                    'code' => $current->code,
                    'title' => $current->title,
                    'level' => $current->level,
                ]);
                $current = $current->parent;
            }

            return $ancestors;
        }

        $keys = array_values(array_filter(explode('/', trim($this->path, '/'))));
        array_pop($keys); // Remove self key

        if (empty($keys)) {
            return [];
        }

        $ancestors = [];
        $missingKeys = [];
        foreach ($keys as $k) {
            $intK = (int) $k;
            if (isset(self::$ancestorCache[$intK])) {
                $ancestors[$intK] = self::$ancestorCache[$intK];
            } else {
                $missingKeys[] = $intK;
            }
        }

        if (! empty($missingKeys)) {
            $fetched = self::whereIn('key', $missingKeys)->get();
            foreach ($fetched as $cat) {
                $item = [
                    'key' => $cat->key,
                    'code' => $cat->code,
                    'title' => $cat->title,
                    'level' => $cat->level,
                ];
                self::$ancestorCache[$cat->key] = $item;
                $ancestors[$cat->key] = $item;
            }
        }

        $ordered = [];
        foreach ($keys as $k) {
            $intK = (int) $k;
            if (isset($ancestors[$intK])) {
                $ordered[] = $ancestors[$intK];
            }
        }

        return $ordered;
    }

    /**
     * Get human-readable breadcrumb path string.
     */
    public function getFullPathAttribute(): string
    {
        $ancestors = $this->getAncestors();
        $titles = array_column($ancestors, 'title');
        $titles[] = $this->title;

        return implode(' > ', $titles);
    }
}
