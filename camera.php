<?php
/*
 * This is an KISS (Keep It Simple and Stupid) web monitor for
 * printrun / pronterface written in PHP.
 * Copyright (C) 2016  Jaroslav Škarvada <jskarvad@redhat.com>
 * The code is copied under GPLv3+. Use it on your own risk.
 */

require("config.php");
$id = @intval($_GET["id"]);
$img_res = isset($_GET["full"]) ? $img_res_full : $img_res_preview;

$img = "img/img{$id}_{$img_res}.jpeg";
$tmp = "/tmp/img{$id}.jpeg";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Location: {$img}");
ob_flush(); flush();

$video_dev = $video_dev_pref . $id;
if (!in_array($video_dev, $video_dev_blacklist))
{
  // generate new shot after redirect
  if (!file_exists($img) || (time() - filemtime($img) > $delay))
  {
    $fp = fopen($v4l_lock, "w");
    if (flock($fp, LOCK_EX))
    {
      if (!file_exists($img) || (time() - filemtime($img) > $delay))
      {
        if (!array_key_exists($video_dev, $fwswebcam_args)) {
          $command = "streamer -q -c {$video_dev} -b 16 -s {$img_res} -o {$tmp}";
        } else {
          $args = array_map('escapeshellarg', $fwswebcam_args[$video_dev]);
          $command = "fswebcam -q --no-banner --no-timestamp --device {$video_dev} --resolution {$img_res} --save {$tmp} ". implode(' ', $args);
        }
        exec($command, $output, $retval);
        if ($retval == 0)
        {
            rename($tmp, $img);
        }
      }
      flock($fp, LOCK_UN);
    }
    fclose($fp);
  }
}
