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

namespace quizaccess_webcamproctor;

use quizaccess_webcamproctor\local\image_validator;

/**
 * Tests for the image validator.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \quizaccess_webcamproctor\local\image_validator
 */
final class image_validator_test extends \basic_testcase {
    public function test_accepts_real_images_only(): void {
        $img = imagecreatetruecolor(200, 150);
        for ($i = 0; $i < 3000; $i++) {
            imagesetpixel($img, rand(0, 199), rand(0, 149), imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255)));
        }
        ob_start();
        imagejpeg($img, null, 90);
        $jpeg = ob_get_clean();
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        $this->assertNotNull(image_validator::clean('data:image/jpeg;base64,' . base64_encode($jpeg)));
        $this->assertNotNull(image_validator::clean('data:image/png;base64,' . base64_encode($png)));
        // Wrong declared type.
        $this->assertNull(image_validator::clean('data:image/png;base64,' . base64_encode($jpeg)));
        // Not an image type we accept.
        $this->assertNull(image_validator::clean('data:image/svg+xml;base64,' . base64_encode(str_repeat('<svg/>', 400))));
        $this->assertNull(image_validator::clean('data:text/html;base64,' . base64_encode(str_repeat('<b>x</b>', 400))));
        // Random bytes labelled as JPEG.
        $this->assertNull(image_validator::clean('data:image/jpeg;base64,' . base64_encode(random_bytes(5000))));
        // Too large and too small.
        $this->assertNull(image_validator::clean('data:image/jpeg;base64,' . base64_encode(random_bytes(600000))));
        $this->assertNull(image_validator::clean('data:image/jpeg;base64,' . base64_encode('x')));
        $this->assertNull(image_validator::clean(''));
    }
}
