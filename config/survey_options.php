<?php
/**
 * Survey Options Configuration
 *
 * Single source of truth for the choices the desktop app offers in its two
 * surveys. Both the app-facing endpoint (api/survey-options.php) and the answer
 * receiver (api/track-app-event.php) read from here, so adding or removing a
 * choice is a single edit to config/survey-options.json with no code changes.
 *
 * The file holds two lists:
 *   - "options": where the person heard about Argo Books.
 *   - "goals":   what they came to do. Asked beside the first question once
 *                someone has started using the app, and on its own when
 *                someone closes the app without having recorded anything.
 *
 * There is intentionally NO hardcoded fallback list here. The desktop app ships
 * its own bundled default lists and uses them whenever this endpoint is
 * unreachable or returns a non-2xx, so the app is the single source of the
 * offline fallback. If the JSON cannot be read, accessors return null and callers
 * degrade accordingly (the options endpoint 500s; the receiver validates leniently).
 */

/**
 * Allowed format for a choice key, shared by JSON parsing and the receiver's
 * degraded-mode validation so both stay consistent. Lowercase letters, digits,
 * underscores and hyphens; 2-40 chars.
 */
const SURVEY_KEY_PATTERN = '/^[a-z0-9_-]{2,40}$/';

/**
 * Returns one list from config/survey-options.json as an array of
 * {key, label, freeform?} maps, or null if the file is missing or malformed or
 * the list is absent or empty. Cached per request.
 *
 * @return array<int, array{key:string,label:string,freeform?:bool}>|null
 */
function survey_choice_list(string $list) {
    static $lists = [];
    if (array_key_exists($list, $lists)) {
        return $lists[$list];
    }

    $lists[$list] = null;

    static $data = false; // false = not yet read
    if ($data === false) {
        $data = null;
        $json = @file_get_contents(__DIR__ . '/survey-options.json');
        if ($json === false) {
            // Log so a broken deploy (missing JSON) is noticeable rather than silently
            // degrading to the app's bundled defaults + lenient server validation.
            error_log('survey_options: unable to read config/survey-options.json');
        } else {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $data = $decoded;
            } else {
                error_log('survey_options: config/survey-options.json is malformed');
            }
        }
    }

    if (!is_array($data) || !isset($data[$list]) || !is_array($data[$list])) {
        if (is_array($data)) {
            error_log("survey_options: config/survey-options.json has no \"$list\" list");
        }
        return null;
    }

    $parsed = [];
    foreach ($data[$list] as $o) {
        if (!is_array($o) || empty($o['key']) || !isset($o['label'])) {
            continue;
        }
        // Normalize and validate the key so a config typo fails fast (and visibly)
        // instead of producing a key the app can never match.
        $key = strtolower(trim((string)$o['key']));
        if (!preg_match(SURVEY_KEY_PATTERN, $key)) {
            error_log('survey_options: skipping choice with invalid key: ' . json_encode($o['key']));
            continue;
        }
        $entry = ['key' => $key, 'label' => (string)$o['label']];
        if (!empty($o['freeform'])) {
            $entry['freeform'] = true;
        }
        $parsed[] = $entry;
    }

    if (count($parsed) > 0) {
        $lists[$list] = $parsed;
    } else {
        error_log("survey_options: the \"$list\" list in config/survey-options.json has no valid choices");
    }

    return $lists[$list];
}

/**
 * The keys in a list, or null if the list is unavailable.
 *
 * @return string[]|null
 */
function survey_choice_keys(string $list, bool $freeformOnly = false) {
    $choices = survey_choice_list($list);
    if ($choices === null) {
        return null;
    }
    $keys = [];
    foreach ($choices as $o) {
        if (!$freeformOnly || !empty($o['freeform'])) {
            $keys[] = $o['key'];
        }
    }
    return $keys;
}

/**
 * Key to label for a list, for the admin pages. Empty when the list is unavailable.
 *
 * @return array<string, string>
 */
function survey_choice_labels(string $list): array {
    $labels = [];
    foreach (survey_choice_list($list) ?? [] as $o) {
        $labels[$o['key']] = $o['label'];
    }
    return $labels;
}

/** Where the person heard about Argo Books. */
function get_survey_options() {
    return survey_choice_list('options');
}

/** What the person came to do. */
function get_survey_goals() {
    return survey_choice_list('goals');
}
