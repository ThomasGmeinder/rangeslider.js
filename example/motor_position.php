<?php

// Testing page with local php server:
// cd rangeslider.js 
// php -S localhost:8000
// Enter URL: http://localhost:8000/example

// Possible system states when this script is called:
// - Motor stopped by button after it was started by I2C.
// - Motor stopped by button after it was started by Button.
// - Motor running from this actuator (this_client_started_motor)  
// - Motor running from another actuator (!this_client_started_motor)

// Options to make $target_mp != $current_mp 
// - This client changed target_mp 
// - Another actuator changed current_mp.
// In both cases target_mp != current_mp

// Events that need to be detected and handled by this script
// - This client changed target_mp when motor was stopped -> Start Motor with I2C write

// Possible actuators:
// target_mp chagend. Motor startUser changes slider on browser client
// 

// Todo: 
// Implement something to distinguish between system states 1 and 2. And make sure that multiple clients are supported!
// Comprehensive error checking and reporting to the client by way or AJAX return array 
// If new motor position set by Browser was not reached after expected X, 
// this logic has to determine of two possible reasons:
// - A: Motor problem
// - B: HW buttons changed motor position before the position set by browser could be reached.
// The motor is currently moving to target_mp due to browser event or Button event on the server


// Goal: minimise the logic here. Keep it a thin layer that passes Information between Motor Controller and Client
// By managing the state on the Motor Controller race conditions between multiple clients and other actuators (Buttons, End Switches) can be avoided.

$current_mp = -10; // current motor position
$target_mp = $current_mp;
$i2c_connected = 1;
$client_ip = $_SERVER['REMOTE_ADDR'];

// motor event constants
$NONE = 0;
$STARTING = 1;
$STOPPING = 2;

$motor_index = $_POST['motor_index'];
$regs_per_motor = 5;
$base_reg = $motor_index * $regs_per_motor;

$actuator = "NONE";
$event_this_client_started_motor = 0; // 0 is invalid

$verbose = 1;

function print_log($message) {
 global $verbose; // acess global
 if($verbose > 0) error_log($message);
}

// Todo: Read server_target_mp it overrides the target_mp from client 
function read_i2c_reg($reg_idx) {
    // Read motor target position on Server
    $command = "i2c_control -r ".$reg_idx;
    $output = []; // must be re-initialised every time! Otherwise exec keeps adding to it !!
    exec($command, $output, $return);  
    $read_val = (int)$output[0] & 0x7f; // MSB is not valid
    print_log("I2C Read command '".$command."' returned ".$read_val);
    if($return != 0) {
      print_log("Command ".$command." returned with error ".$return);
    }
    return $read_val;
}

if(isset($_POST['target_mp'])) {
  $target_mp = $_POST['target_mp'];
  $tc = $_POST['timeout_counter'];
  $client_motor_start_request = $_POST['user_moved_slider'];

  print_log("motor_position.php called with new target position ".$target_mp." for Motor ".$motor_index);

  if($i2c_connected) {
    // Read current motor position
    $cp_reg = $base_reg + 2;
    $current_mp = read_i2c_reg($cp_reg);
    print_log("Current Position of Motor ".$motor_index." read via I2C is ".$current_mp);

    // Read motor target position on Server
    $tp_reg = $base_reg + 1;
    $server_target_mp = read_i2c_reg($tp_reg); 
    print_log("Server Target Position of Motor ".$motor_index." read via I2C is ".$server_target_mp);

    // Read Motor State
    $state_reg = $base_reg; 
    $motor_state = read_i2c_reg($state_reg);
    print_log("State of Motor ".$motor_index." read via I2C is ".$motor_state);

    // Determine who is the actuator 
    $actuator_reg = $base_reg + 3;
    $actuator_val = read_i2c_reg($actuator_reg);
    // lower 4 bits are current actuator, upper 4 bits are previous actuator
    if(($actuator_val & 0xf) == 0) $actuator = "I2C";      
    else if(($actuator_val & 0xf) == 1) $actuator = "BUTTON";

    if((($actuator_val >> 4) & 0xf) == 0) $prev_actuator = "I2C";      
    else if((($actuator_val >> 4) & 0xf) == 1) $prev_actuator = "BUTTON";
    
    if($current_mp != $target_mp) { 
      // This can happen for two reasons
      // - This client changed target_mp
      // - Another actuator caused current_mp to change
      $motor_stopped = $motor_state == 2;
      if($motor_stopped) {
        if($client_motor_start_request) { 
          // Start the motor to reach new position target_mp set by client
          $command = "i2c_control -w ".$tp_reg." ".$target_mp;
          system($command);
  
          print_log("Client with IP ".$client_ip." is starting Motor ".$motor_index." from position ".$current_mp." to ".$target_mp);
          // set direction
          $ctrl_val = 0; // 0 closing, 1 opening
          if($target_mp > $current_mp) {
            $ctrl_val = 1;
          }
          $command = "i2c_control -w ".$state_reg." ".$ctrl_val;
          system($command);

        } else{
          // Motor was stopped by another actuator Update target_mp
          print_log("Motor was stopped by another actuator. Updating target motor position to ".$server_target_mp);
          $target_mp = $server_target_mp;
        }
      } else {
        // motor is running
        // It's possible that the server changed the target_mp whilst motors are running.  
        // E.g. when a button is pressed
        // Therefore the target_mp must be updated with server_target_mp
        $target_mp = $server_target_mp;
      }
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
    $cp_reg = $base_reg + 2;
    print_log($cp_reg);
    $command = "i2c_control -r ".$cp_reg;
    exec($command, $output, $return);  
    //Causes Error: Undefined offset: 0 
    $current_mp = (int)$output[0] & 0x7f; // MSB not valid
    print_log("Current Position of Motor ".$motor_index." read via I2C is ".$current_mp);
   } else {
    $current_mp = 50; // default	
   }
}

// Todo: get current_mp from I2C
if($current_mp == -128) {
  $current_mp = "Invalid";
}	

// Update client by printing data as json object.
echo json_encode(
  array(
    "target_mp" => $target_mp, 
    "current_mp" => $current_mp, 
    "actuator" => $actuator,
  )
);


?>
