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
$target_mp = $current_mp;
$i2c_connected = 1;

// Todo: Read server_target_mp it overrides the target_mp from client 

if(isset($_POST['target_mp'])) {
  $target_mp = $_POST['target_mp'];
  $motor_index = $_POST['motor_index'];
  $tc = $_POST['timeout_counter'];

  error_log("motor_position.php called with new target position ".$target_mp." for Motor ".$motor_index);


  if($i2c_connected) {
    // Read current motor position
    $cp_reg = $motor_index*4 + 2;
    $command = "i2c_control -r ".$cp_reg;
    error_log($command);
    $output = []; // must be re-initialised every time! Otherwise exec keeps adding to it !!
    exec($command, $output, $return);  
    $current_mp = (int)$output[0] & 0x7f; // MSB not valid
    error_log("Current Position of Motor ".$motor_index." read via I2C is ".$current_mp);

    // Read motor target position on Server
    $tp_reg = $motor_index*4 + 1;
    $command = "i2c_control -r ".$tp_reg;
    error_log($command);
    $output = []; 
    exec($command, $output, $return);  
    if($return != 0) {
      error_log("Command ".$command." returned with error ".$return);
    }
    $server_target_mp = (int)$output[0] & 0x7f; // MSB not valid
    error_log("Server Target Position of Motor ".$motor_index." read via I2C is ".$server_target_mp);

    // Read current motor position
    $status_reg = $motor_index*4 + 3;
    $command = "i2c_control -r ".$status_reg;
    $output = []; 
    exec($command, $output, $return);  
    error_log("command ".$command.", exec output ".(int)$output[0].", return ".$return);
    $status = (int)$output[0] & 0x7f; // MSB not valid

    if($status != 0) { 
    //if($server_target_mp != $current_mp) {
      // Button control on server takes priority
      // Update target_mp as feedback to client
      error_log("status == ".$status);
      error_log("Target position of Motor ".$motor_index." was changed on server with Buttons. Updating target_mp to ".$server_target_mp." for client to update the sliders");
      $target_mp = $server_target_mp;
    }
    else if($current_mp != $target_mp) {
      // set new target motor position via I2C
      $command = "i2c_control -w ".$tp_reg." ".$target_mp;
      system($command);
  
      // set direction
      $ctrl_val = 0; // 0 closing, 1 opening
      if($target_mp > $current_mp) {
//        error_log("Starting Motor ".$motor_index." in closing direction from ".$current_mp);
        $ctrl_val = 1;
      }
      $state_reg = $motor_index*4; 
      $command = "i2c_control -w ".$state_reg." ".$ctrl_val;
      system($command);
    } 
  } else {
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
  }
} else if(isset($_POST['motor_index'])) {
  $motor_index = $_POST['motor_index'];
  if($i2c_connected) { 
    // Read current motor position
    $cp_reg = $motor_index*4 + 2;
    error_log($cp_reg);
    $command = "i2c_control -r ".$cp_reg;
    exec($command, $output, $return);  
    //Causes Error: Undefined offset: 0 
    $current_mp = (int)$output[0] & 0x7f; // MSB not valid
    error_log("Current Position of Motor ".$motor_index." read via I2C is ".$current_mp);
   } else {
    $current_mp = 50; // default	
   }
}

// Todo: get current_mp from I2C
if($current_mp == -128) {
  $current_mp = "Invalid";
}	

// Update client by printing data as json object.
echo json_encode(array("target_mp" => $target_mp, "current_mp" => $current_mp));


?>