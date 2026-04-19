<?php

if (! defined('ABSPATH')) {
    exit;
}

class Toptour_Core_I18n
{
    /**
     * @var array<string, array<string, string>>
     */
    private static $dictionaries = array();

    /**
     * Detect current locale.
     *
     * @return string
     */
    public static function get_locale()
    {
        if (function_exists('determine_locale')) {
            $locale = determine_locale();
        } else {
            $locale = get_locale();
        }

        if (! is_string($locale) || $locale === '') {
            return 'en_US';
        }

        return $locale;
    }

    /**
     * Load dictionary for locale.
     *
     * @param string $locale Locale code.
     * @return array<string, string>
     */
    public static function load_dictionary($locale = '')
    {
        $locale = (string) $locale;
        if ($locale === '') {
            $locale = self::get_locale();
        }

        if (isset(self::$dictionaries[$locale])) {
            return self::$dictionaries[$locale];
        }

        $file_name = self::get_language_file_name($locale);
        $file_path = TOPTOUR_CORE_PATH . 'languages/' . $file_name;

        self::$dictionaries[$locale] = self::parse_dictionary_file($file_path);

        return self::$dictionaries[$locale];
    }

    /**
     * Translate key from dictionary with fallback chain.
     *
     * @param string $key Translation key.
     * @param string $fallback Optional fallback value.
     * @return string
     */
    public static function t($key, $fallback = '')
    {
        $key = trim((string) $key);
        if ($key === '') {
            return (string) $fallback;
        }

        $locale = self::get_locale();
        $dictionary = self::load_dictionary($locale);

        if (array_key_exists($key, $dictionary)) {
            return $dictionary[$key];
        }

        $default_dictionary = self::load_dictionary('en_US');
        if (array_key_exists($key, $default_dictionary)) {
            return $default_dictionary[$key];
        }

        if ($fallback !== '') {
            return (string) $fallback;
        }

        return $key;
    }

    /**
     * Resolve locale to translation file name.
     *
     * @param string $locale Locale code.
     * @return string
     */
    private static function get_language_file_name($locale)
    {
        if ($locale === 'sk_SK' || strpos($locale, 'sk_') === 0 || $locale === 'sk') {
            return 'sk.csv';
        }

        if ($locale === 'en_US' || strpos($locale, 'en_') === 0 || $locale === 'en') {
            return 'en.csv';
        }

        return 'en.csv';
    }

    /**
     * Parse key/value dictionary lines from file.
     *
     * @param string $file_path Absolute file path.
     * @return array<string, string>
     */
    private static function parse_dictionary_file($file_path)
    {
        if (! file_exists($file_path)) {
            return array();
        }

        $lines = file($file_path, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines)) {
            return array();
        }

        $dictionary = array();

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if ($line === '') {
                continue;
            }

            $separator_position = strpos($line, '=');
            if ($separator_position === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator_position));
            $value = trim(substr($line, $separator_position + 1));

            if ($key === '') {
                continue;
            }

            $dictionary[$key] = $value;
        }

        return $dictionary;
    }
}