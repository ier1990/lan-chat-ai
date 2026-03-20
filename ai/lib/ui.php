<?php
/**
 * UI — small rendering helpers used across view templates.
 */
class UI
{
    public static function roomIcon(string $type): string
    {
        return match ($type) {
            'channel' => '#',
            'log'     => '⊙',
            'dm'      => '@',
            'group'   => '⊕',
            default   => '#',
        };
    }

    public static function senderBadge(string $type): string
    {
        return match ($type) {
            'persona' => '<span class="badge badge-ai">AI</span>',
            'webhook' => '<span class="badge badge-hook">↓</span>',
            'system'  => '<span class="badge badge-sys">SYS</span>',
            default   => '',
        };
    }

    public static function activeClass(bool $condition, string $cls = 'active'): string
    {
        return $condition ? " $cls" : '';
    }

    public static function activeRoomClass(int $roomId, int $current): string
    {
        return self::activeClass($roomId === $current);
    }

    public static function flash(string $type, string $message): string
    {
        return '<div class="flash flash-' . Util::e($type) . '">'
             . Util::e($message)
             . '</div>';
    }

    /** Render a <select> dropdown from an associative array. */
    public static function select(string $name, array $options, string $selected = '', string $attrs = ''): string
    {
        $html = "<select name=\"" . Util::e($name) . "\" {$attrs}>";
        foreach ($options as $val => $label) {
            $sel   = (string) $val === $selected ? ' selected' : '';
            $html .= '<option value="' . Util::e((string) $val) . '"' . $sel . '>'
                   . Util::e((string) $label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /** Avatar circle with first initial. */
    public static function avatar(string $name, string $type = 'user'): string
    {
        $initial = Util::e(mb_strtoupper(mb_substr($name, 0, 1)));
        $cls     = 'avatar avatar-' . Util::e($type);
        return "<span class=\"{$cls}\">{$initial}</span>";
    }
}
