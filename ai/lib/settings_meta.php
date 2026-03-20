<?php
/**
 * SettingsMeta — reads settings_meta to auto-render admin forms.
 * No new page code is needed when you add a setting key to the DB.
 */
class SettingsMeta
{
    /** All meta rows for a tab, joined with current values from settings. */
    public static function getByTab(string $tab): array
    {
        return DB::fetchAll(
            'SELECT sm.*, s.setting_value, s.is_sensitive, s.is_editable
             FROM settings_meta sm
             JOIN settings s ON s.setting_key = sm.setting_key
             WHERE sm.tab_name = ?
             ORDER BY sm.sort_order ASC',
            [$tab]
        );
    }

    /** Distinct list of tab names. */
    public static function getTabs(): array
    {
        $rows = DB::fetchAll(
            'SELECT DISTINCT tab_name FROM settings_meta ORDER BY tab_name'
        );
        return array_column($rows, 'tab_name');
    }

    /** Render an HTML form field for one meta row. Returns '' if not allowed. */
    public static function renderField(array $meta): string
    {
        if ($meta['is_sensitive'] && !Auth::isAdmin()) {
            return '';
        }
        $key      = Util::e($meta['setting_key']);
        $val      = Util::e((string) ($meta['setting_value'] ?? ''));
        $label    = Util::e($meta['label']);
        $help     = $meta['help_text']
            ? '<small class="field-help">' . Util::e($meta['help_text']) . '</small>'
            : '';
        $disabled = (!$meta['is_editable'] || ($meta['is_sensitive'] && !Auth::isAdmin()))
            ? ' disabled'
            : '';

        $input = match ($meta['input_type']) {
            'textarea' => "<textarea name=\"settings[{$key}]\" id=\"f_{$key}\"{$disabled} rows=\"3\">{$val}</textarea>",
            'select'   => self::renderSelect($meta, (string) ($meta['setting_value'] ?? ''), $disabled),
            'checkbox' => '<input type="hidden" name="settings[' . $key . ']" value="0">'
                          . '<input type="checkbox" name="settings[' . $key . ']" id="f_' . $key . '" value="1"'
                          . ($val === '1' ? ' checked' : '') . $disabled . '>',
            'password' => "<input type=\"password\" name=\"settings[{$key}]\" id=\"f_{$key}\" value=\"{$val}\"{$disabled}>",
            default    => "<input type=\"text\" name=\"settings[{$key}]\" id=\"f_{$key}\" value=\"{$val}\"{$disabled}>",
        };

        return "<div class=\"field\">"
             . "<label for=\"f_{$key}\">{$label}</label>"
             . $input
             . $help
             . "</div>\n";
    }

    private static function renderSelect(array $meta, string $current, string $disabled): string
    {
        $opts = Util::jsonDecode($meta['options_json']) ?? [];
        $key  = Util::e($meta['setting_key']);
        $html = "<select name=\"settings[{$key}]\" id=\"f_{$key}\"{$disabled}>";
        foreach ($opts as $optVal => $optLabel) {
            $sel   = (string) $optVal === $current ? ' selected' : '';
            $html .= '<option value="' . Util::e((string) $optVal) . '"' . $sel . '>'
                   . Util::e((string) $optLabel) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}
