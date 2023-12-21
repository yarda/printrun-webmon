<?php
/*
 * This is a KISS (Keep It Simple and Stupid) web monitor for
 * printrun / pronterface written in PHP.
 * Copyright (C) 2016  Jaroslav Škarvada <jskarvad@redhat.com>
 * The code is copied under GPLv3+. Use it at your own risk.
 */

require("config.php");
require("devices.php");
$id = @intval($_GET["id"]);
$img_res = isset($_GET["full"]) ? $img_res_full : $img_res_preview;

$img = "img/img{$id}_{$img_res}.jpeg";
$tmp = "/tmp/img{$id}.jpeg";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Location: {$img}");
ob_flush();
flush();

$cameras = getCameras($video_dev_glob, $rulesets);
if (array_key_exists(strval($id), $cameras)) {
  $camera = $cameras[$id];

  // Set a timeout value in seconds
  $timeout = 30;

  // Generate a new shot after the redirect
  if (!file_exists($img) || (time() - filemtime($img) > $delay)) {
    $fp = fopen($v4l_lock, "w");
    if (flock($fp, LOCK_EX)) {
      if (!file_exists($img) || (time() - filemtime($img) > $delay)) {
        if (!isset($camera["fswebcam_args"])) {
          $command = "timeout {$timeout}s streamer -q -c {$camera['devicePath']} -b 16 -s {$img_res} -o {$tmp}";
        } else {
          $args = array_map('escapeshellarg', $camera["fswebcam_args"]);
          $command = "timeout {$timeout}s fswebcam -q --no-banner --no-timestamp --device {$camera['devicePath']} --resolution {$img_res} --save {$tmp} " . implode(' ', $args);
        }

        exec($command, $output, $retval);

        // Check if the image file exists and rename it
        if (file_exists($tmp)) {
          rename($tmp, $img);
        }
      }
      flock($fp, LOCK_UN);
    }
    fclose($fp);
  }
}
