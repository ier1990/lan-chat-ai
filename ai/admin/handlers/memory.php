<?php
/**
 * handlers/memory.php — _memory_action POST handler.
 * Returns [$flash, $flashType].
 */
function _handleMemory(): array
{
    $action = Util::post('_memory_action');
    $memId  = (int) Util::post('mem_id');

    if ($action === 'save') {
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '') {
            return ['Content is required.', 'error'];
        }
        Memory::save([
            'id'        => $memId,
            'title'     => Util::post('title'),
            'content'   => $content,
            'tags'      => Util::post('tags'),
            'scope'     => Util::post('scope', 'private'),
            'is_secret' => Util::post('is_secret', '0') === '1' ? 1 : 0,
        ], (int) Auth::id());
        return ['Memory saved.', 'success'];
    }

    if ($action === 'delete') {
        if (Memory::delete($memId, (int) Auth::id(), true)) {
            return ['Memory deleted.', 'success'];
        }
        return ['Memory not found.', 'error'];
    }

    return ['', 'success'];
}
