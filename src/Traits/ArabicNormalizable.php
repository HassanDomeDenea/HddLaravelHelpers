<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

trait ArabicNormalizable
{

    protected array $arabicNormalizable = ['name'];

    public static function bootArabicNormalizable()
    {
        static::saving(function ($model) {
            foreach ($model->arabicNormalizable as $field) {
                if (isset($model->{$field})) {
                    $normalizedField = $field . '_normalized';
                    $model->{$normalizedField} = self::normalizeArabic($model->{$field});
                }
            }
        });
    }

    /**
     * Normalize Arabic text for flexible searching.
     */
    public static function normalizeArabic($text)
    {
        if (!$text) return $text;

        $text = trim($text);

        $map = [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي',
            'ئ' => 'ي',
            'ؤ' => 'و',

            // Remove harakat (tashkeel)
            'ً' => '', 'ٌ' => '', 'ٍ' => '',
            'َ' => '', 'ُ' => '', 'ِ' => '',
            'ّ' => '',

            // Remove persian Letters
            'گ' => 'ك',
            'چ' => 'ج',
            'پ' => 'ب',
            'ڤ' => 'ف',
            'ژ' => 'ز',
        ];

        return strtr($text, $map);
    }

    /**
     * Scope for flexible LIKE search.
     */
    public function scopeWhereLikeArabic($query, $column, $value)
    {
        $normalizedColumn = $column . '_normalized';
        return $query->where($normalizedColumn, 'LIKE', '%' . self::normalizeArabic($value) . '%');
    }
}
