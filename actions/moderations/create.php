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

/**
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Creates a moderation agreement instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;


$submissionid = required_param('submissionid', PARAM_INT);
$feedbackid = required_param('feedbackid', PARAM_INT);
$moderatorid = optional_param('moderatorid', $USER->id, PARAM_INT);
$stage_identifier = optional_param('stage_identifier', '', PARAM_ALPHANUMEXT);

$params = array(
    'submissionid' => $submissionid,
    'feedbackid' => $feedbackid,
    'moderatorid' => $moderatorid,
    'stage_identifier' => $stage_identifier,
);
$controller = new mod_coursework\controllers\moderations_controller($params);
$controller->create_moderation();