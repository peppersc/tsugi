<?php
require_once "../../config.php";
require_once $CFG->dirroot."/db.php";
require_once $CFG->dirroot."/lib/lti_util.php";
require_once $CFG->dirroot."/lib/lms_lib.php";
require_once $CFG->dirroot."/core/blob/blob_util.php";
require_once "peer_util.php";

session_start();

// Sanity checks
$LTI = requireData(array('user_id', 'link_id', 'role','context_id'));
$instructor = isInstructor($LTI);
$p = $CFG->dbprefix;

// Model 
$stmt = pdoQueryDie($db,
    "SELECT assn_id, json FROM {$p}peer_assn WHERE link_id = :ID",
    array(":ID" => $LTI['link_id'])
);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$assn_json = null;
$assn_id = false;
if ( $row !== false ) {
    $assn_json = json_decode($row['json']);
    $assn_id = $row['assn_id'];
}

if ( $assn_id == false ) {
    $_SESSION['error'] = 'This assignment is not yet set up';
    header( 'Location: '.sessionize('index.php') ) ;
    return;
}

// Handle the incoming post data
if ( isset($_POST['points']) && isset($_POST['submit_id']) ) {
    if ( strlen($_POST['points']) < 1 ) {
        $_SESSION['error'] = 'Points are required';
        header( 'Location: '.sessionize('grade.php') ) ;
        return;
    }
    $points = $_POST['points']+0;
    if ( !isset($_SESSION['peer_submit_id']) || $_SESSION['peer_submit_id'] != $_POST['submit_id'] ) {
        unset($_SESSION['peer_submit_id']);
        $_SESSION['error'] = 'Error in submission id';
        header( 'Location: '.sessionize('grade.php') ) ;
        return;
    }
    unset($_SESSION['peer_submit_id']);
    $submit_id = $_POST['submit_id']+0; 

    $stmt = pdoQuery($db,
        "INSERT INTO {$p}peer_grade 
            (submit_id, user_id, points, note, created_at, updated_at) 
            VALUES ( :SID, :UID, :POINTS, :NOTE, NOW(), NOW()) 
            ON DUPLICATE KEY UPDATE points = :POINTS, note = :NOTE, updated_at = NOW()",
        array(
            ':SID' => $submit_id,
            ':UID' => $LTI['user_id'],
            ':POINTS' => $points,
            ':NOTE' => $_POST['note'])
    );
    if ( $stmt->success ) {
        $_SESSION['success'] = 'Grade submitted';
    } else {
        $_SESSION['error'] = $stmt->errorImplode;
    }
    header( 'Location: '.sessionize('index.php') ) ;
    return;
}
unset($_SESSION['peer_submit_id']);
 
// Load the the 10 oldest ungraded submissions
$to_grade = loadUngraded($db, $LTI, $assn_id);
if ( count($to_grade) < 1 ) {
    $_SESSION['success'] = 'There are no submissions to grade';
    header( 'Location: '.sessionize('index.php') ) ;
    return;
}

// Grab the oldest one
$to_grade_row = $to_grade[0];
$stmt = pdoQueryDie($db,
    "SELECT json FROM {$CFG->dbprefix}peer_submit 
        WHERE submit_id = :SID",
    array(":SID" => $to_grade_row['submit_id'])
);
$submit_row = $stmt->fetch(PDO::FETCH_ASSOC);
$submit_json = null;
if ( $submit_row !== null ) {
    $submit_json = json_decode($submit_row['json']);
}
if ( $submit_json === null ) {
    $_SESSION['error'] = 'Unable to load submission '.$to_grade_row['submit_id'];
    header( 'Location: '.sessionize('index.php') ) ;
    return;
}

// View 
headerContent();
?>
</head>
<body>
<?php
flashMessages();
welcomeUserCourse($LTI);
?>
<p><b>Please be careful, you cannot revise grades after you submit them.</b></p>
<?php
showSubmission($assn_json, $submit_json);
?>
<form method="post">
<input type="hidden" value="<?php echo($to_grade_row['submit_id']); ?>" name="submit_id">
<input type="number" min="0" max="<?php echo($assn_json->maxpoints); ?>" name="points">
(<?php echo($assn_json->maxpoints); ?> maximum points)<br/>
Comments:<br/>
<textarea rows="5" cols="60" name="note"></textarea><br/>
<input type="submit" value="Grade">
<input type=submit name=doCancel onclick="location='<?php echo(sessionize('index.php'));?>'; return false;" value="Cancel">
</form>
<?php

$_SESSION['peer_submit_id'] = $to_grade_row['submit_id'];
footerContent();