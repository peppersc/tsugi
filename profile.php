<?php
// In the top frame, we use cookies for session.
if ( ! defined('COOKIE_SESSION') ) define('COOKIE_SESSION', true);
require_once("config.php");

use \Tsugi\Core\LTIX;

$PDOX = LTIX::getConnection();

header('Content-Type: text/html; charset=utf-8');
session_start();

if ( ! isset($_SESSION['profile_id']) ) {
    header('Location: '.$CFG->wwwroot.'/login');
    return;
}
$stmt = $PDOX->queryDie(
    "SELECT json FROM {$CFG->dbprefix}profile WHERE profile_id = :PID",
    array('PID' => $_SESSION['profile_id'])
);
$profile_row = $stmt->fetch(PDO::FETCH_ASSOC);
if ( $profile_row === false ) {
    $_SESSION['error'] = 'Unable to load profile';
    header('Location: '.$CFG->apphome);
    return;
}

$profile = json_decode($profile_row['json']);
if ( ! is_object($profile) ) $profile = new stdClass();

// Handle the POST data
if ( isset($_POST['subscribe']) ) {
    $profile->subscribe = $_POST['subscribe']+0 ;
    if ( isset($_POST['map']) ) {
        $profile->map = $_POST['map']+0 ;
        $profile->lat = $_POST['lat']+0.0 ;
        $profile->lng = $_POST['lng']+0.0 ;
    }
    $new_json = json_encode($profile);
    $stmt = $PDOX->queryDie(
        "UPDATE {$CFG->dbprefix}profile SET json= :JSON
        WHERE profile_id = :PID",
        array('JSON' => $new_json, 'PID' => $_SESSION['profile_id'])
    );
    $_SESSION['success'] = "Profile updated.";
    header('Location: '.$CFG->apphome);
    return;
}

// Load data from the profile
$subscribe = isset($profile->subscribe) ? $profile->subscribe+0 : false;
$map = isset($profile->map) ? $profile->map+0 : false;
$lat = isset($profile->lat) ? $profile->lat+0.0 : 0.0;
$lng = isset($profile->lng) ? $profile->lng+0.0 : 0.0;

$defaultLat = $lat != 0.0 ? $lat : 42.279070216140425;
$defaultLng = $lng != 0.0 ? $lng : -83.73981015789798;

$OUTPUT->header();
$OUTPUT->bodyStart();
$OUTPUT->topNav();
if ( isset($CFG->google_map_api_key) && ! $CFG->OFFLINE ) {
?>
<script src="https://maps.googleapis.com/maps/api/js?v=3.exp&key=<?= $CFG->google_map_api_key ?>"></script>
<script type="text/javascript">
var map;

function initialize() {
  var myLatlng = new google.maps.LatLng(<?php echo($defaultLat.", ".$defaultLng); ?>);

  var myOptions = {
     zoom: 2,
     center: myLatlng,
     mapTypeId: google.maps.MapTypeId.ROADMAP
     }
  map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);

  var marker = new google.maps.Marker({
  draggable: true,
  position: myLatlng,
  map: map,
  title: "Your location"
  });

  google.maps.event.addListener(marker, 'dragend', function (event) {
    document.getElementById("latbox").value = this.getPosition().lat();
    document.getElementById("lngbox").value = this.getPosition().lng();
  });

}
<?php } else { // OFFLINE ?>
<script type="text/javascript">
var map;

function initialize() { }

<?php } ?>

</script>
<div class="container">
<?php

function radio($var, $num, $val) {
    $ret =  '<input type="radio" name="'.$var.'" id="'.$var.$num.'" value="'.$num.'" ';
    if ( $num == $val ) $ret .= ' checked ';
    echo($ret);
}

function option($num, $val) {
    echo(' value="'.$num.'" ');
    if ( $num == $val ) echo(' selected ');
}

function checkbox($val) {
    echo(' value="1" ');
    if ( $val == 1 ) echo(' checked ');
}

echo('<h4>');echo($_SESSION['displayname']);
echo(' ('.$_SESSION['email'].")</h4>\n");
?>

<p>
<form method="POST">
  <div class="control-group pull-right" style="margin-top: 20px">
    <button type="submit" class="btn btn-primary visible-phone">Save</button>
    <input class="btn btn-warning" type="button" onclick="location.href='<?= $CFG->apphome ?>'; return false;" value="Cancel"/>
  </div>
  <div class="control-group">
    <div class="controls">
        How much mail would you like us to send?
        <label class="radio">
             <?php radio('subscribe',-1,$subscribe) ?> >
                No mail will be sent.
        </label>
        <label class="radio">
             <?php radio('subscribe',0,$subscribe) ?> >
                Keep the mail level as low as possible.
        </label>
        <label class="radio">
             <?php radio('subscribe',1,$subscribe) ?> >
                Send only announcements.
        </label>
        <label class="radio">
             <?php radio('subscribe',2,$subscribe) ?> >
                Send me notification mail for important things like my assignment was graded.
        </label>
      </div>
  </div>
<hr class="hidden-phone"/>
<?php if ( isset($CFG->google_map_api_key) && ! $CFG->OFFLINE ) { ?>
  <hr class="hidden-phone"/>
How would you like to be shown in maps.<br/>
<select name="map">
  <option value="0">--- Please Select ---</option>
  <option <?php option(1,$map); ?>>Don't show me at all</option>
  <option <?php option(2,$map); ?>>Show only my location with no identifying information</option>
  <option <?php option(3,$map); ?>>Show my name (<?php echo($_SESSION['displayname']); ?>)</option>
</select>
<p>
  Move the pointer on the map below until it is at the correct location.
  If you are concerned about privacy, simply put the
  location somewhere <i>near</i> where you live.  Perhaps in the same country, state, or city
  instead of your exact location.
</p>
  <div class="control-group pull-right hidden-phone">
      <button type="submit" style="margin-top: 40px" class="btn btn-primary">Save Profile Data</button>
  </div>

  <div id="map_canvas" style="margin: 10px; width:400px; max-width: 100%; height:400px"></div>

  <div id="latlong" style="display:none" class="control-group">
    <p>Latitude: <input size="30" type="text" id="latbox" name="lat" class="disabled"
    <?php echo(' value="'.htmlent_utf8($lat).'" '); ?>
    ></p>
    <p>Longitude: <input size="30" type="text" id="lngbox" name="lng" class="disabled"
    <?php echo(' value="'.htmlent_utf8($lng).'" '); ?>
    ></p>
  </div>

<p>
If you don't even want to reveal your country, put yourself
in Greenland in the middle of a glacier. One person put their location
in the middle of a bar.  :)
</p>
<?php } ?>
</form>
<?php

// After jquery gets loaded at the *very* end...
$OUTPUT->footerStart();
?>
<script type="text/javascript">
$(document).ready(function() { initialize(); } );
</script>
<?
$OUTPUT->footerEnd();
