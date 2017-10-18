<?php

// Testing page with local php server:
// cd rangeslider.js 
// php -S localhost:8000
// Enter URL: http://localhost:8000/example

// Todo: Change the logic so that this happens:
// If new motor position set by Browser was not reached after expected X, 
// this logic has to determine of two possible reasons:
// - A: Motor problem
// - B: HW buttons changed motor position before the position set by browser could be reached.
// The motor is currently moving to target_mp due to browser event or Button event on the server

$current_mp = -10; // current motor position

if(isset($_POST['target_mp'])) {
  $target_mp = $_POST['target_mp'];
  // todo: set new target motor position via I2C
  $tc = $_POST['timeout_counter'];

  // just for testing:
  if($tc > 5) {
    $current_mp = $target_mp; 
  }
  if($tc >= 10 && $tc < 15) {
    if($tc < 14) {
       $current_mp = $target_mp - 5;
    } else {
       // mimick new motor position which is changed locally
       if($target_mp < 50) $target_mp += 50;
       else $target_mp -= 50;
       $current_mp = $target_mp;
    }
  }

} else {
  // Todo: Read current position via I2C
  $current_mp = 50; // default	
}

// Todo: get current_mp from I2C
if($current_mp == -128) {
  $current_mp = "Invalid";
}	

// Update client by printing data as json object.
echo json_encode(array("target_mp" => $target_mp, "current_mp" => $current_mp));


?>