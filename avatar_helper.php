<?php
include_once __DIR__ . '/auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// Cache-busting token for the default avatar.
// The old default-avatar.png was an empty (0-byte) file, so browsers may have
// cached a broken image. Appending the file's modified time to the URL makes
// the browser fetch the new one instead of the cached broken copy.
if (!function_exists('avatar_url')) {
    function avatar_url($file, $base = '../uploads/') {
        $file = $file !== '' ? $file : 'default-avatar.png';
        $path = __DIR__ . '/uploads/' . $file;
        $v    = @filemtime($path);
        return $base . rawurlencode($file) . ($v ? '?v=' . $v : '');
    }
}
?>
