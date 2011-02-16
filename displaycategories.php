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

make_categories_list(&$list, &$parents);

if (data_submitted() && confirm_sesskey()) {
    foreach ($list as $cid => $notused) {
        set_config('display_'.$cid, optional_param('display'.$cid, 0, PARAM_INT),'block_cam_mycourses');
        set_config('cascade_'.$cid, optional_param('cascade'.$cid, 0, PARAM_INT),'block_cam_mycourses');
        set_config('enrol_'.$cid, optional_param('enrolled'.$cid, 0, PARAM_INT),'block_cam_mycourses');
    }
    echo $OUTPUT->notification(get_string('settingssaved','block_cam_mycourses'), 'notifysuccess');
}

echo '<form method="post" action="displaycategories.php">';
echo '<table class="generalbox editcourse boxaligncenter"><tr class="header">';
echo '<th class="header" scope="col">'.get_string('categories').'</th>';
echo '<th class="header" scope="col">'.get_string('display', 'block_cam_mycourses').'</th>';
echo '<th class="header" scope="col">'.get_string('cascade', 'block_cam_mycourses').'</th>';
echo '<th class="header" scope="col">'.get_string('enrolled', 'block_cam_mycourses').'</th>';
echo '</tr>';

print_category_edit(NULL, $list, $parents);
echo '<tr><td colspan="4" align="center">';
echo '<input type="hidden" name="sesskey" value="'.sesskey().'"/>';
echo '<input type="submit" value="' . get_string("savechanges") . '"\" /></td></tr>';
echo '</table>';
echo '</form>';


echo $OUTPUT->footer();


function print_category_edit($category, $displaylist, $parentslist, $depth=-1, $up=false, $down=false) {
/// Recursive function to print all the categories ready for editing

    global $CFG, $USER, $OUTPUT;

    static $str = NULL;
    static $config = NULL;

    if (is_null($config)) {
        $config = get_config('block_cam_mycourses');
    }

    if (is_null($str)) {
        $str = new stdClass;
        $str->spacer = $OUTPUT->spacer().' ';
        $str->full = get_string('full', 'block_cam_mycourses');
        $str->listing = get_string('listing', 'block_cam_mycourses');
        $str->no = get_string('no');
    }
    $options = array(1=>$str->full, 2=>$str->listing);
    if (!empty($category)) {

        if (!isset($category->context)) {
            $category->context = get_context_instance(CONTEXT_COURSECAT, $category->id);
        }

        echo '<tr><td align="left" class="name">';
        for ($i=0; $i<$depth;$i++) {
            echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        }
        echo format_string($category->name);
        echo '</td>';
        $display = "display_".$category->id;
        $display = isset($config->$display) ? $config->$display : 0;
        $enrolled = "enrol_".$category->id;
        $enrolled = isset($config->$enrolled) ? $config->$enrolled : false;
        $cascade = "cascade_".$category->id;
        $cascade = isset($config->$cascade) ? $config->$cascade : false;

        echo html_writer::tag('td', html_writer::select($options,'display'.$category->id, $display,array(0=>$str->no)));
        echo html_writer::tag('td', html_writer::checkbox('cascade'.$category->id, 1, $cascade));
        echo html_writer::tag('td', html_writer::checkbox('enrolled'.$category->id, 1, $enrolled));

        echo '</tr>';
    } else {
        $category->id = '0';
    }

    if ($categories = get_categories($category->id)) {   // Print all the children recursively
        $countcats = count($categories);
        $count = 0;
        $first = true;
        $last = false;
        foreach ($categories as $cat) {
            $count++;
            if ($count == $countcats) {
                $last = true;
            }
            $up = $first ? false : true;
            $down = $last ? false : true;
            $first = false;

            print_category_edit($cat, $displaylist, $parentslist, $depth+1, $up, $down);
        }
    }
}
