<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

/**
 * The strings the Vue pages show, read from `lang/{locale}/ui/*.php` (one file per area, e.g.
 * `ui/common.php`) and `messages.php`. English fills any gap so a missing Spanish key never shows a raw key.
 */
class UiMessages
{
    /**
     * @return array<string, mixed>
     */
    public static function for(string $locale): array
    {
        $fallback = config('app.fallback_locale', 'en');
        $messages = static::load($fallback);

        if ($locale !== $fallback) {
            $messages = array_replace_recursive($messages, static::load($locale));
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(string $locale): array
    {
        $messages = ['ui' => []];
        $directory = lang_path($locale.'/ui');

        foreach (File::isDirectory($directory) ? File::files($directory) : [] as $file) {
            $area = $file->getFilenameWithoutExtension();
            $messages['ui'][$area] = require $file->getPathname();
        }

        $messages['messages'] = Lang::get('messages', [], $locale);

        return $messages;
    }
}
