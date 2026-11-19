<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Support\UiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class UiTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_spanish_and_english_ui_files_have_the_same_keys(): void
    {
        $this->assertSame($this->keys('en'), $this->keys('es'));
    }

    public function test_every_spanish_and_english_lang_file_has_the_same_keys(): void
    {
        $this->assertSame($this->allKeys('en'), $this->allKeys('es'));
    }

    public function test_ui_strings_use_the_vue_i18n_placeholder_syntax(): void
    {
        // The Vue pages fill {name}; a Laravel-style :name would reach the screen as raw text.
        $wrong = [];

        foreach (['en', 'es'] as $locale) {
            foreach (Arr::dot(UiMessages::for($locale)['ui']) as $key => $text) {
                if (is_string($text) && preg_match('/(^|\s):[a-z_]+\b/', $text) === 1) {
                    $wrong[] = "{$locale}: ui.{$key} → {$text}";
                }
            }
        }

        $this->assertSame([], $wrong);
    }

    public function test_english_and_spanish_strings_use_the_same_placeholders(): void
    {
        $placeholders = function (string $locale): array {
            $result = [];
            foreach (Arr::dot(UiMessages::for($locale)['ui']) as $key => $text) {
                if (is_string($text)) {
                    preg_match_all('/\{([a-zA-Z_]+)\}/', $text, $matches);
                    sort($matches[1]);
                    $result[$key] = $matches[1];
                }
            }

            return $result;
        };

        $this->assertSame($placeholders('en'), $placeholders('es'));
    }

    public function test_every_key_the_vue_pages_use_exists(): void
    {
        $known = array_flip(array_keys(Arr::dot(UiMessages::for('en'))));
        $missing = [];

        foreach ((new Finder)->files()->in(resource_path('js'))->name('*.vue')->name('*.js') as $file) {
            preg_match_all("/\\bt\\(\\s*'(ui\\.[a-z0-9_.]+)'/", $file->getContents(), $matches);

            foreach ($matches[1] as $key) {
                if (! isset($known[$key])) {
                    $missing[] = $file->getRelativePathname().' → '.$key;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    public function test_pages_share_the_users_language_with_english_filling_gaps(): void
    {
        $user = User::factory()->create(['locale' => 'es']);

        $this->actingAs($user)->get('/settings/trash')->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'es')
            ->where('i18n.messages.ui.trash.title', 'Papelera')
        );
    }

    /**
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $keys = [];
        foreach ((new Finder)->files()->in(lang_path($locale.'/ui'))->name('*.php') as $file) {
            foreach (array_keys(Arr::dot(require $file->getPathname())) as $key) {
                $keys[] = $file->getFilenameWithoutExtension().'.'.$key;
            }
        }
        sort($keys);

        return $keys;
    }

    /**
     * Every key of every PHP lang file of a locale, prefixed by the file's path (e.g. "ui/buckets.title").
     *
     * @return list<string>
     */
    private function allKeys(string $locale): array
    {
        $keys = [];
        foreach ((new Finder)->files()->in(lang_path($locale))->name('*.php') as $file) {
            $prefix = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -4));
            foreach (array_keys(Arr::dot(require $file->getPathname())) as $key) {
                $keys[] = $prefix.'.'.$key;
            }
        }
        sort($keys);

        return $keys;
    }
}
