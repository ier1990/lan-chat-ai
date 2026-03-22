<?php
/**
 * handlers/settings.php — _save_settings POST handler.
 * Returns [$flash, $flashType].
 */
function _handleSettings(): array
{
    $saved = 0;
    foreach ($_POST['settings'] ?? [] as $key => $value) {
        if (Settings::set((string) $key, (string) $value)) {
            $saved++;
        }
    }
    Settings::flush();
    return ["Saved {$saved} setting(s).", 'success'];
}
