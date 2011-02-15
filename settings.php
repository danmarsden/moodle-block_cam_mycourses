<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('mycourses_settingsurl', '',
                                            '<a href="'.$CFG->wwwroot.'/blocks/cam_mycourses/displaycategories.php">'.get_string('categorysettings','block_cam_mycourses')."</a>", ''));
}