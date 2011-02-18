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
 * Cambridge My Courses Block
 *
 * @package    block_cam_mycourses
 * @author     Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once('lib.php');

$id    = required_param('id', PARAM_INT);    // Course Module ID, or

require_login();
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));

$config = get_config('block_cam_mycourses');
$display = "display_".$id;
$enrolled = "enrol_".$id;
$cascade = "cascade_".$id;

if (!empty($config->$display) && isset($config->$enrolled) && isset($config->$cascade)) {
    echo  get_mycourse_category_content($id, $config->$cascade, $config->$enrolled, $config->$display);

}