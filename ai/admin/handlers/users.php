<?php
/**
 * handlers/users.php — _users_action POST handler.
 * Returns [$flash, $flashType].
 */
function _handleUsers(): array
{
    $action       = Util::post('_users_action');
    $targetUserId = (int) Util::post('user_id');
    $flash        = '';
    $flashType    = 'success';

    if ($action === 'create_user') {
        $username        = Util::post('username');
        $displayName     = Util::post('display_name');
        $password        = Util::post('password');
        $passwordConfirm = Util::post('password_confirm');
        $role            = Util::post('role', 'member');
        $roleKeys        = array_column(Users::getRoles(), 'role_key');

        if ($username === '' || $displayName === '' || $password === '') {
            return ['Username, display name, and password are required.', 'error'];
        }
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            return ['Username may only contain letters, numbers, dot, underscore, and dash.', 'error'];
        }
        if (Users::getByUsername($username)) {
            return ['That username already exists.', 'error'];
        }
        if (!in_array($role, $roleKeys, true)) {
            return ['Invalid role selected.', 'error'];
        }
        if (strlen($password) < 8) {
            return ['Password must be at least 8 characters.', 'error'];
        }
        if ($password !== $passwordConfirm) {
            return ['Password confirmation does not match.', 'error'];
        }

        Users::create($username, $displayName, $password, $role);
        return ['User created.', 'success'];
    }

    if ($action === 'change_role') {
        $role = Util::post('role');
        $user = Users::getById($targetUserId);

        if (!$user) {
            return ['User not found.', 'error'];
        }
        if ($targetUserId === Auth::id()) {
            return ['Change your own role from the database or another admin account.', 'error'];
        }
        if ($user['role_key'] === 'admin' && $role !== 'admin' && Users::countByRole('admin') <= 1) {
            return ['You cannot demote the last admin.', 'error'];
        }

        Users::setRole($targetUserId, $role);
        return ['Role updated.', 'success'];
    }

    if ($action === 'reset_password') {
        $user            = Users::getById($targetUserId);
        $password        = Util::post('new_password');
        $passwordConfirm = Util::post('new_password_confirm');

        if (!$user) {
            return ['User not found.', 'error'];
        }
        if (strlen($password) < 8) {
            return ['New password must be at least 8 characters.', 'error'];
        }
        if ($password !== $passwordConfirm) {
            return ['New password confirmation does not match.', 'error'];
        }

        Users::setPassword($targetUserId, $password);
        return ['Password reset.', 'success'];
    }

    if ($action === 'delete_user') {
        $user = Users::getById($targetUserId);

        if (!$user) {
            return ['User not found.', 'error'];
        }
        if ($targetUserId === Auth::id()) {
            return ['You cannot delete your own account.', 'error'];
        }
        if ($user['role_key'] === 'admin' && Users::countByRole('admin') <= 1) {
            return ['You cannot delete the last admin.', 'error'];
        }

        Users::delete($targetUserId);
        return ['User deleted.', 'success'];
    }

    return [$flash, $flashType];
}
