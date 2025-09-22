<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_Translator {
    private static $preferredLocale = '';

    public static function set_preferred_locale($locale) {
        self::$preferredLocale = is_string($locale) ? trim($locale) : '';
    }

    public static function get_name(array $item) {
        if (empty($item['translations']) || !is_array($item['translations'])) {
            return isset($item['name']) ? (string) $item['name'] : '';
        }

        // Intentar encontrar traducción exacta según locale
        if (self::$preferredLocale !== '') {
            foreach ($item['translations'] as $translation) {
                if (!is_array($translation) || empty($translation['name'])) { continue; }
                $tloc = self::find_locale_in_translation($translation);
                if ($tloc !== '' && strcasecmp($tloc, self::$preferredLocale) === 0) {
                    return (string) $translation['name'];
                }
            }
        }

        // Fallback: primera traducción válida
        foreach ($item['translations'] as $translation) {
            if (is_array($translation) && !empty($translation['name'])) {
                return (string) $translation['name'];
            }
        }

        return isset($item['name']) ? (string) $item['name'] : '';
    }

    public static function get_description(array $item) {
        if (empty($item['translations']) || !is_array($item['translations'])) {
            return isset($item['description']) ? (string) $item['description'] : '';
        }

        if (self::$preferredLocale !== '') {
            foreach ($item['translations'] as $translation) {
                if (!is_array($translation)) { continue; }
                $tloc = self::find_locale_in_translation($translation);
                if ($tloc !== '' && strcasecmp($tloc, self::$preferredLocale) === 0) {
                    if (!empty($translation['description'])) {
                        return (string) $translation['description'];
                    }
                    if (!empty($translation['text'])) {
                        return (string) $translation['text'];
                    }
                }
            }
        }

        // fallback
        foreach ($item['translations'] as $translation) {
            if (!is_array($translation)) continue;
            if (!empty($translation['description'])) {
                return (string) $translation['description'];
            }
            if (!empty($translation['text'])) {
                return (string) $translation['text'];
            }
        }

        return isset($item['description']) ? (string) $item['description'] : '';
    }

    public static function get_all_descriptions(array $item) {
        if (empty($item['translations']) || !is_array($item['translations'])) {
            return [];
        }

        $descriptions = [];
        foreach ($item['translations'] as $translation) {
            if (!is_array($translation)) continue;

            $text = '';
            if (!empty($translation['description'])) {
                $text = (string) $translation['description'];
            } elseif (!empty($translation['text'])) {
                $text = (string) $translation['text'];
            }

            $label = '';
            if (!empty($translation['language']) && is_array($translation['language']) && !empty($translation['language']['name'])) {
                $label = (string) $translation['language']['name'];
            }

            $locale = self::find_locale_in_translation($translation);
            if (self::$preferredLocale !== '' && $locale !== '' && strcasecmp($locale, self::$preferredLocale) !== 0) {
                continue; // filtra por locale
            }

            $descriptions[] = [
                'name'        => $text,
                'description' => $label,
                'locale'      => $locale,
            ];
        }

        return $descriptions;
    }

    public static function get_locale_for_name(array $item) {
        if (empty($item['translations']) || !is_array($item['translations'])) {
            return '';
        }
        if (self::$preferredLocale !== '') {
            foreach ($item['translations'] as $translation) {
                if (!is_array($translation) || empty($translation['name'])) continue;
                $loc = self::find_locale_in_translation($translation);
                if ($loc !== '' && strcasecmp($loc, self::$preferredLocale) === 0) {
                    return $loc;
                }
            }
        }
        foreach ($item['translations'] as $translation) {
            if (is_array($translation) && !empty($translation['name'])) {
                return self::find_locale_in_translation($translation);
            }
        }
        return '';
    }

    public static function guess_locale(array $item) {
        if (empty($item['translations']) || !is_array($item['translations'])) {
            return '';
        }
        foreach ($item['translations'] as $translation) {
            $loc = self::find_locale_in_translation($translation);
            if ($loc !== '') return $loc;
        }
        return '';
    }

    private static function find_locale_in_translation($translation) {
        if (!is_array($translation)) {
            return '';
        }
        if (!empty($translation['locale']) && is_string($translation['locale'])) {
            return trim($translation['locale']);
        }
        if (!empty($translation['language'])) {
            $lang = $translation['language'];
            if (is_array($lang)) {
                $candidates = [
                    $lang['locale'] ?? '',
                    $lang['code'] ?? '',
                    $lang['ref'] ?? '',
                    $lang['name'] ?? '',
                ];
                foreach ($candidates as $cand) {
                    $cand = is_string($cand) ? trim($cand) : '';
                    if ($cand !== '') return $cand;
                }
            } elseif (is_string($lang)) {
                $lang = trim($lang);
                if ($lang !== '') return $lang;
            }
        }
        return '';
    }
}

