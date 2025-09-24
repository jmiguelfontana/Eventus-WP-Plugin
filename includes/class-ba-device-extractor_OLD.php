<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_Extractor {

    public static function main_identifier(array $item) {
        if (empty($item['identifiers']) || !is_array($item['identifiers'])) {
            return isset($item['primaryIdentifier']) ? (string) $item['primaryIdentifier'] : '';
        }

        $fallback = '';
        foreach ($item['identifiers'] as $identifier) {
            if (!is_array($identifier)) continue;

            if (!empty($identifier['isPrimary']) && !empty($identifier['ref'])) {
                return (string) $identifier['ref'];
            }

            if ($fallback === '' && !empty($identifier['ref'])) {
                $fallback = (string) $identifier['ref'];
            }

            if (!empty($identifier['type']['name']) 
                && stripos($identifier['type']['name'], 'primary') !== false 
                && !empty($identifier['ref'])) {
                return (string) $identifier['ref'];
            }
        }

        return $fallback;
    }

    public static function manufacturer(array $item) {
        if (empty($item['manufacturer']) || !is_array($item['manufacturer'])) {
            return '';
        }

        $parts = [];
        if (!empty($item['manufacturer']['name'])) {
            $parts[] = (string) $item['manufacturer']['name'];
        }
        if (!empty($item['manufacturer']['ref'])) {
            $parts[] = '(' . (string) $item['manufacturer']['ref'] . ')';
        }

        return trim(implode(' ', $parts));
    }

    public static function identifier_by_keywords(array $item, array $keywords) {
        if (empty($item['identifiers']) || !is_array($item['identifiers'])) {
            return '';
        }

        foreach ($item['identifiers'] as $identifier) {
            if (!is_array($identifier)) continue;

            $typeName = '';
            if (!empty($identifier['type']['name'])) {
                $typeName = (string) $identifier['type']['name'];
            } elseif (!empty($identifier['type']['ref'])) {
                $typeName = (string) $identifier['type']['ref'];
            }

            foreach ($keywords as $keyword) {
                if ($typeName !== '' 
                    && stripos($typeName, $keyword) !== false 
                    && !empty($identifier['ref'])) {
                    return (string) $identifier['ref'];
                }
            }
        }

        return '';
    }

    public static function implantable(array $item) {
        if (!array_key_exists('implantable', $item)) {
            return '';
        }

        $value = $item['implantable'];
        if ($value === null || $value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
    }

    public static function nomenclature(array $item) {
        $term = null;
        if (!empty($item['nomenclaturesTerms']) && is_array($item['nomenclaturesTerms'])) {
            $term = $item['nomenclaturesTerms'][0];
            if (is_array($term) && !empty($term['nomenclatureTerm'])) {
                $term = $term['nomenclatureTerm'];
            }
        } elseif (!empty($item['nomenclatureTerm']) && is_array($item['nomenclatureTerm'])) {
            $term = $item['nomenclatureTerm'];
        }

        if (!is_array($term)) {
            return [
                'term'        => '',
                'type'        => '',
                'description' => '',
                'lang'        => '',
            ];
        }

        $data = [
            'term'        => '',
            'type'        => '',
            'description' => '',
            'lang'        => '',
        ];

        $chosen = null;
        if (!empty($term['translations']) && is_array($term['translations'])) {
            if (BA_Device_Translator::get_locale_for_name(['translations' => $term['translations']]) !== '') {
                foreach ($term['translations'] as $t) {
                    if (!is_array($t)) continue;
                    $tloc = !empty($t['locale']) ? trim((string) $t['locale']) : BA_Device_Translator::guess_locale(['translations' => [$t]]);
                    if ($tloc !== '' && strcasecmp($tloc, BA_Device_Translator::get_locale_for_name(['translations' => $term['translations']])) === 0) {
                        $chosen = $t;
                        break;
                    }
                }
            }
            if (!$chosen) {
                $cand = $term['translations'][0] ?? null;
                if (is_array($cand)) $chosen = $cand;
            }
        }

        if ($chosen) {
            if (!empty($chosen['name'])) {
                $data['term'] = (string) $chosen['name'];
            }
            if (!empty($chosen['description'])) {
                $data['description'] = (string) $chosen['description'];
            }
            if (!empty($chosen['locale'])) {
                $data['lang'] = (string) $chosen['locale'];
            } elseif (!empty($chosen['language']['code'])) {
                $data['lang'] = (string) $chosen['language']['code'];
            } elseif (!empty($chosen['language']['name'])) {
                $data['lang'] = (string) $chosen['language']['name'];
            } elseif (!empty($chosen['language'])) {
                $data['lang'] = (string) $chosen['language'];
            }
        }

        if ($data['term'] === '' && !empty($term['name'])) {
            $data['term'] = (string) $term['name'];
        }

        if ($data['description'] === '' && !empty($term['description'])) {
            $data['description'] = (string) $term['description'];
        }

        if ($data['type'] === '' && !empty($term['type']['name'])) {
            $data['type'] = (string) $term['type']['name'];
        } elseif ($data['type'] === '' && !empty($term['type']['ref'])) {
            $data['type'] = (string) $term['type']['ref'];
        }

        return $data;
    }

    public static function format_nomenclature_label(array $nomenclature) {
        $term = isset($nomenclature['term']) ? trim((string) $nomenclature['term']) : '';
        $type = isset($nomenclature['type']) ? trim((string) $nomenclature['type']) : '';

        if ($term === '' && $type === '') {
            return '';
        }

        if ($type === '') {
            return $term;
        }

        if ($term === '') {
            return $type;
        }

        return $type . ': ' . $term;
    }

    public static function format_identifier_active_value(array $identifier) {
        if (!array_key_exists('isActive', $identifier)) {
            return '';
        }

        $value = $identifier['isActive'];
        if ($value === null || $value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
    }
}
