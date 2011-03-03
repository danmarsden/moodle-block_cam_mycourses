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
 * Creates some test data for testing purposes.
 *
 * @package   block_cam_mycourses
 * @author    David Drummond <david@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');

require_login();
$context = get_context_instance(CONTEXT_SYSTEM);
require_capability('moodle/site:config', $context);

$startcourse  = required_param('startcourse', PARAM_TEXT);
$endcourse    = required_param('endcourse', PARAM_TEXT);
$replacedata  = optional_param('replacedata', 0,  PARAM_BOOL);

$sql = "select id, courseid from {groups} g where courseid >= ? and courseid <= ?"; 

$groups = $DB->get_records_sql($sql, array($startcourse, $endcourse));

//print_object($groups);

$groupdetail = get_testdata_object();

//print_object($groupdetail);

$cycle = 0;

foreach($groups as $group) {

     // check for existing
     if ($DB->record_exists('block_mycourses_group_detail', array('groupid' => $group->id))) {
         echo "<p>Group detail already exists for group $group->id on course $group->courseid";

         if ($replacedata) {
             echo "... replacing.</p>";
             if (!$DB->delete_records('block_mycourses_group_detail', array('groupid' => $group->id))) {
                 print_error('deleting record failed');
             }

         } else {
             echo ".</p>";
             continue;
         }

     }

     // insert some test data
     $data = $groupdetail[$cycle];
     $data->groupid = $group->id;
     if (!$DB->insert_record('block_mycourses_group_detail', $data)) {
         print_error('Inserting record failed');
     }

     echo "<p>Inserted record for group $group->id on course $group->courseid</p>";

     // cycle to mix up test data
     if ($cycle >= 2) {
        $cycle = 0;
     } else {
        $cycle++;
     }
}


function get_testdata_object() {

    $groups[0]->startdate = mktime(0, 0, 0, 1, 1, 1998);
    $groups[0]->enddate = mktime(0, 0, 0, 25, 1, 1998); 
    $groups[0]->location = 'Room 1'; 

    $groups[1]->startdate = mktime(0, 0, 0, 5, 9, 2005);
    $groups[1]->enddate = mktime(0, 0, 0, 16, 9, 2005); 
    $groups[1]->location = 'Room 2'; 

    $groups[2]->startdate = mktime(0, 0, 0, 10, 9, 2007);
    $groups[2]->enddate = mktime(0, 0, 0, 12, 9, 2007); 
    $groups[2]->location = 'Room 3'; 

    return $groups;

}

?>
