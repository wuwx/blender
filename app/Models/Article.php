<?php

namespace app\Models;

use App\Foundation\Models\Base\ModuleModel;
use App\Foundation\Models\Traits\HasSlug;
use App\Models\Enums\SpecialArticle;
use Cache;
use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Article extends ModuleModel
{
    use HasSlug;

    protected $with = ['media'];

    public $mediaLibraryCollections = ['images', 'downloads'];
    public $translatable = ['name', 'text', 'url'];

    public function registerMediaConversions()
    {
        parent::registerMediaConversions();

        $this->addMediaConversion('thumb')
            ->setWidth(368)
            ->setHeight(232)
            ->performOnCollections('images');
    }

    public static function findByTechnicalName(string $technicalName): Article
    {
        return Cache::rememberForever(
            "article.findByTechnicalName.{$technicalName}",
            function () use ($technicalName): Article {
                $article = static::where('technical_name', $technicalName)->first();

                if ($article === null) {
                    throw new Exception("Article `{$technicalName}` not found");
                }

                return $article;
            }
        );
    }

    public static function getWithTechnicalNameLike(string $technicalName): Collection
    {
        return Cache::rememberForever(
            "article.getWithTechnicalNameLike.{$technicalName}",
            function () use ($technicalName): Collection {
                return static::where('technical_name', 'like', "{$technicalName}.%")
                    ->orderBy('order_column')
                    ->get();
            }
        );
    }

    public function isDeletable(): bool
    {
        return !(bool) $this->technical_name;
    }


    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order_column');
    }

    public function hasChildren(): bool
    {
        return count($this->children);
    }

    public function parentArticle(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hasParentArticle(): bool
    {
        return !is_null($this->parentArticle);
    }

    public function getFullUrlAttribute(): string
    {
        $localeSegment = '';

        if (locales()->count() > 1) {
            $localeSegment = "/" . locale();
        }

        if ($this->technical_name === SpecialArticle::HOME) {
            return $localeSegment;
        }

        $parentUrl = $this->hasParentArticle() ? $this->parentArticle->url . '/' : '';

        return "{$localeSegment}/{$parentUrl}{$this->url}";
    }
}
