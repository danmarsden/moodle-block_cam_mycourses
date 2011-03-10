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
require_once('displaycategories_form.php');

require_login();
$context = get_context_instance(CONTEXT_SYSTEM);
require_capability('moodle/site:config', $context);

$strcatsettings = get_string('categorysettings', 'block_cam_mycourses');
$categoriesurl = new moodle_url('/blocks/cam_mycourses/displaycategories.php');
$settingsurl = new moodle_url('/admin/settings.php?section=blocksettingcam_mycourses');

$PAGE->set_url($categoriesurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('base');
$PAGE->set_title($strcatsettings);
$PAGE->set_heading($strcatsettings);

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add(get_string('pluginname', 'block_cam_mycourses'), $settingsurl);
$PAGE->navbar->add($strcatsettings, $categoriesurl);
echo $OUTPUT->header();

$mform = new mycourse_categories_form('');
if (data_submitted() && confirm_sesskey()) {
    make_categories_list($list, $parents);
    foreach ($list as $cid => $notused) {
        set_config('display_'.$cid, optional_param('display'.$cid, 0, PARAM_INT),'block_cam_mycourses');
        set_config('cascade_'.$cid, optional_param('cascade'.$cid, 0, PARAM_INT),'block_cam_mycourses');
        set_config('enrol_'.$cid, optional_param('enrolled'.$cid, 0, PARAM_INT),'block_cam_mycourses');
    }
    echo $OUTPUT->notification(get_string('settingssaved','block_cam_mycourses'), 'notifysuccess');
}

echo html_writer::start_tag('div', array('id'=>'category-headers-container','class'=>'header'));
echo html_writer::start_tag('div', array('class'=>'category-header-'.strtolower(get_string('category', 'block_cam_mycourses'))));
echo get_string('category', 'block_cam_mycourses');
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', array('class'=>'category-header-'.strtolower(get_string('display', 'block_cam_mycourses'))));
echo get_string('display', 'block_cam_mycourses');
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', array('class'=>'category-header-'.strtolower(get_string('cascade', 'block_cam_mycourses'))));
echo get_string('cascade', 'block_cam_mycourses');
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', array('class'=>'category-header-'.strtolower(get_string('enrolled', 'block_cam_mycourses'))));
echo get_string('enrolled', 'block_cam_mycourses');
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', array('class'=>'clear'));
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

$mform->display();

echo $OUTPUT->footer();
