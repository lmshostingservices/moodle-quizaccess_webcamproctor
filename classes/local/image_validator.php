<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace quizaccess_webcamproctor\local;

/**
 * Validates webcam images sent by the browser.
 *
 * Only JPEG, PNG and WebP data URIs are accepted. The decoded bytes must be a real
 * image of that type, and no larger than the size limit.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_validator {
    /** @var int Largest decoded image accepted, in bytes. */
    const MAX_BYTES = 512000;

    /** @var int Smallest decoded image accepted, in bytes. A real webcam frame is never this small. */
    const MIN_BYTES = 1000;

    /** @var array Accepted MIME types mapped to their PHP image type constants. */
    const TYPES = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    /**
     * Check a data URI and return it in canonical form, or null when it is not an acceptable image.
     *
     * @param string $datauri The data URI sent by the browser.
     * @return string|null The canonical data URI, or null if rejected.
     */
    public static function clean(string $datauri): ?string {
        if (!preg_match('~^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/]+={0,2})$~', $datauri, $matches)) {
            return null;
        }
        $mimetype = $matches[1];
        if ((strlen($matches[2]) * 3 / 4) > self::MAX_BYTES + 3) {
            return null;
        }
        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) < self::MIN_BYTES || strlen($binary) > self::MAX_BYTES) {
            return null;
        }
        $info = @getimagesizefromstring($binary);
        if (!$info || $info[2] !== self::TYPES[$mimetype]) {
            return null;
        }
        return 'data:' . $mimetype . ';base64,' . base64_encode($binary);
    }

    /**
     * Split a stored data URI into its MIME type and binary content.
     *
     * @param string $datauri A data URI stored by this plugin.
     * @return array|null [mimetype, binary] or null if the value is not a data URI.
     */
    public static function decode(string $datauri): ?array {
        if (!preg_match('~^data:(image/[a-z+.-]+);base64,(.*)$~s', $datauri, $matches)) {
            return null;
        }
        $binary = base64_decode($matches[2], true);
        return $binary === false ? null : [$matches[1], $binary];
    }
}
