<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

use Illuminate\Support\Facades\DB;

class SearchArabicNamesUsingRegexp
{
    protected static bool $sqliteFunctionIsCreated = false;

    public static function setSqliteFunction(): void
    {
        if (! static::$sqliteFunctionIsCreated && DB::connection()->getName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('regexp',
                function ($pattern, $data, $delimiter = '~', $modifiers = 'isuS') {
                    if (isset($pattern, $data) === true) {
                        return preg_match(sprintf('%1$s%2$s%1$s%3$s', $delimiter, $pattern, $modifiers), $data) > 0;
                    }

                    return null;
                }
            );
            static::$sqliteFunctionIsCreated = true;
        }
    }

    public static function SanitizeName(?string $value): string
    {
        return str($value)
            ->replaceMatches('!\s+!', ' ')
            ->trim()
            ->replace(['~', '*', '(', ')', '+', '=', '^', '%', '$', '/', '\\', '[', ']', '{', '}', '<', '>'], '')
            ->toString();
    }

    public static function convertNameToRegexp(?string $text = null): string
    {
        static::setSqliteFunction();
        $text = static::SanitizeName($text);
        $replace = [
            'أ',
            'ا',
            'إ',
            'آ',
            'ي',
            'ى',
            'ه',
            'ة',
        ];
        $with = ['(أ|ا|آ|إ)',
            '(أ|ا|آ|إ)',
            '(أ|ا|آ|إ)',
            '(أ|ا|آ|إ)',
            '(ي|ى)',
            '(ي|ى)',
            '(ه|ة)',
            '(ه|ة)',
        ];
        $new = array_combine($replace, $with);
        $result = '';
        $len = mb_strlen($text, 'utf-8');
        for ($i = 0; $i < $len; $i++) {
            $current = mb_substr($text, $i, 1, 'utf-8');
            $result .= $new[$current] ?? $current;
        }

        return $result;
    }
}
