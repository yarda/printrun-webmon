<?php
require("config.php");

/**
 * Gets the information for a single device using udevadm.
 *
 * @param string $devicePath The path to the device.
 * @return array An associative array of the device's udevadm data.
 */
function getDeviceInfo($devicePath) {
  $info = [];
  $info['devicePath'] = $devicePath;

  // Execute the udevadm command to get environment info for the device
  $udevOutput = shell_exec("udevadm info --query=env --name=" . escapeshellarg($devicePath));

  // Split the output into lines, then iterate and parse each line
  $udevLines = explode("\n", $udevOutput);
  foreach ($udevLines as $line) {
    // Each line is in the form of KEY=value
    if (!empty($line)) {
      list($key, $value) = explode('=', $line, 2);
      $info[$key] = $value;
    }
  }

  return $info;
}

/**
 * Retrieves information for all video devices on the system.
 *
 * @param string $video_dev_glob Glob pattern to match video devices.
 * @return array A list of associative arrays containing udevadm data for each device.
 */
function getAllVideoDeviceInfo($video_dev_glob) {
  $videoDevicePaths = glob($video_dev_glob);
  $allDeviceInfo = [];

  foreach ($videoDevicePaths as $devicePath) {
    // Get info for the device and add it to the main array
    $allDeviceInfo[] = getDeviceInfo($devicePath);
  }

  return $allDeviceInfo;
}

/**
 * Processes video device information with given rules.
 *
 * @param array $videoDevicesInfo Array of video devices info.
 * @param array $rulesets Array of rulesets containing match rules, additional attributes, and actions.
 * @return array Processed array of video devices info.
 */
function processVideoDevicesInfo($videoDevicesInfo, $rulesets) {
  $processedDevices = [];

  foreach ($videoDevicesInfo as $deviceInfo) {
    $includeDevice = true;
    echo "<!-- processing device: {$deviceInfo['devicePath']} -->\r\n";
    $rule_index = 0;
    foreach ($rulesets as $rules) {
      $matched = true;
      foreach ($rules['match_rules'] as $rule) {
        $attribute = $rule[0];
        $value = $rule[1];

        if (!isset($deviceInfo[$attribute]) || $deviceInfo[$attribute] != $value) {
          $matched = false;
          echo "<!-- - not match of rule: $attribute = $value -->\r\n";
          break;
        }
      }
      if (!$matched) {
        $rule_index += 1;
        continue;
      }

      echo "<!-- matched rule #$rule_index -->\r\n";
      /*echo "<!-- matching rule:\r\n";
      print_r($rules);
      echo "-->\r\n";*/

      // Check for 'ignore' action.
      if (isset($rules['action']) && $rules['action'] === 'ignore') {
        echo "<!-- = action = 'ignore' -->\r\n";
        $includeDevice = false;
        break;
      }

      // Add additional args for fwswebcam if any.
      if (isset($rules['fswebcam_args'])) {
        echo "<!-- has fswebcam_args -->\r\n";
        $deviceInfo['fswebcam_args'] = $rules['fswebcam_args'];
      }

      $rule_index += 1;
    }

    if ($includeDevice) {
      echo "<!-- = including device -->\r\n";
      // ID used to select camera.
      if (substr($deviceInfo['devicePath'], 0, strlen('/dev/video')) == '/dev/video') {
        $deviceInfo["id"] = substr($deviceInfo['devicePath'], strlen('/dev/video'));
        echo "<!-- - device id: {$deviceInfo['id']}. -->\r\n";
      } else {
        echo "<!-- Failed to set id for {$deviceInfo['devicePath']}.\r\n";
        echo "It does not have prefix /dev/video -->\r\n";
      }

      if (isset($deviceInfo["id"])) {
        $processedDevices[$deviceInfo["id"]] = $deviceInfo;
      } else {
        $processedDevices[] = $deviceInfo;
      }
    }
  }

  return $processedDevices;
}

/**
 * Main function to collect, process and ignore all devices.
 *
 * @return array Processed video devices info with the defined rules.
 */
function getCameras($video_dev_glob, $rulesets) {
  $videoDevicesInfo = getAllVideoDeviceInfo($video_dev_glob);

  return processVideoDevicesInfo($videoDevicesInfo, $rulesets);
}
