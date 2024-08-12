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


require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $PAGE, $USER;

$id = required_param('id', PARAM_INT);

$params = array(
    'id' => $id,
);
$url = '/mod/coursework/actions/deadline_extensions/create.php';
$link = new \moodle_url($url, $params);
$PAGE->set_url($link);

$controller = new mod_coursework\controllers\deadline_extensions_controller($params);
$controller->update_deadline_extension();