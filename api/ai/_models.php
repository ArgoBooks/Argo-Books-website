<?php
/**
 * Model selection for the AI completions proxy (api/ai/completions.php).
 */

const AI_SUPPORTED_MODELS = ['gemini-3.5-flash', 'gemini-3.1-flash-lite', 'gemini-2.5-pro'];

// USD per million tokens, input then output, for the cost estimate on the admin stats page; thinking tokens bill as output.
const AI_MODEL_PRICES = [
    'gemini-3.1-flash-lite' => [0.25, 1.50],
    'gemini-3.5-flash' => [1.50, 9.00],
    'gemini-2.5-pro' => [1.25, 10.00],
];

// Installed desktop builds pin a model id that Google has since retired (e.g.
// gemini-2.5-flash); empty requests also need a default. Both are remapped to a
// current model so existing installs keep working without a forced app update.
const AI_RETIRED_MODELS = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-2.0-flash-001'];

/**
 * The Gemini model a request runs on, or null when the requested model is not supported.
 *
 * Vision requests (receipt scans include an image) get the accuracy tier; text-only calls get
 * the cheaper general model. Only a license-verified request may pick its model. The free path
 * is authenticated by a self-asserted X-Device-Id, and every app build sends a retired id
 * there anyway, so it always gets the default.
 */
function ai_resolve_model(string $requested, bool $hasImage, bool $isLicensed): ?string
{
    if ($requested !== '' && !in_array($requested, AI_RETIRED_MODELS, true)) {
        if (!in_array($requested, AI_SUPPORTED_MODELS, true)) {
            return null;
        }
        if ($isLicensed) {
            return $requested;
        }
    }

    $default = $hasImage
        ? ($_ENV['GEMINI_MODEL_EXTRACTION'] ?? 'gemini-3.5-flash')
        : ($_ENV['GEMINI_MODEL'] ?? 'gemini-3.1-flash-lite');

    return in_array($default, AI_SUPPORTED_MODELS, true) ? $default : null;
}
