/**
 * Javascript helper function for cam_mycourses block
 *
 * @package    blocks
 * @subpackage cam_mycourses
 * @author     Dan Marsden  {@link http://danmarsden.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.blocks_cam_mycourses = M.blocks_cam_mycourses || {};

M.blocks_cam_mycourses.init = function(Y, url) {
    var handleClick = function(e) {
        // pass the event facade to the logger or console for inspection:
       //now change the location of the doc object to the new href
       var frame = Y.one('.mycourse_frame');
       var newid = this.getAttribute('id').replace("category","");
       //frame.setAttribute('data', url+newid);
       if (frame && frame.getAttribute('id').replace("mycourseframe","") !== newid) {
           frame.setAttribute('class', 'mycourse_frame_hidden');
       }
        //now create a new frame with the new url
        //first check if it exists and just needs to be displayed
       var existing = Y.one('#mycourseframe'+newid);
       if (existing) {
           existing.setAttribute('class','mycourse_frame');
       } else {
           //create new frame
           var newframe = Y.Node.create('<object id="mycourseframe'+newid+'" class="mycourse_frame" type="text/html" data="'+url+newid+'"></object>');
           frame.get('parentNode').append(newframe);
       }
       var selectedcat = Y.one('.mycourse_categories .selected');
       selectedcat.removeClass('selected');
       this.get('parentNode').addClass('selected');

       e.halt();
    };

    //elements can be targeted using selector syntax:
    Y.on("click", handleClick, ".mycourse_category a");

}