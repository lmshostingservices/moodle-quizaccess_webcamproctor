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

/**
 * Shows the enlarged image when a thumbnail in the proctoring report is clicked.
 *
 * Bootstrap opens the dialog from the button's data attributes; this module fills it.
 *
 * @module     quizaccess_webcamproctor/report
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Listen for clicks on report thumbnails.
 */
export const init = () => {
    document.addEventListener('click', (e) => {
        const button = e.target.closest('.webcamproctor-thumbnail-button');
        if (!button) {
            return;
        }
        const image = button.querySelector('img');
        const large = document.getElementById('webcamproctor-image-large');
        const title = document.getElementById('webcamproctor-image-title');
        if (image && large) {
            large.src = image.src;
            large.alt = image.alt;
        }
        if (title) {
            title.textContent = button.dataset.title || '';
        }
    });
};
