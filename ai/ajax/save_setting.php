<?php
/**
 * ajax/save_setting.php — Update one or more settings values.
 *
 * POST body (JSON or form): settings = { key: value, ... }
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireLogin();

if (!Util::isPost()) {
    Util::jsonResponse(['error' => 'POST required'], 405);
}
Util::requireCsrf();

$raw      = file_get_contents('php://input');
$data     = json_decode($raw, true) ?: $_POST;
$settings = $data['settings'] ?? [];

if (!is_array($settings) || empty($settings)) {
    Util::jsonResponse(['error' => 'No settings provided'], 400);
}

$saved  = [];
$denied = [];

foreach ($settings as $key => $value) {
    $key = (string) $key;
    if (!Permissions::canEditSetting($key)) {
        $denied[] = $key;
        continue;
    }
    if (Settings::set($key, $value)) {
        $saved[] = $key;
    }
}

Settings::flush();

Util::jsonResponse([
    'ok'     => true,
    'saved'  => $saved,
    'denied' => $denied,
]);
