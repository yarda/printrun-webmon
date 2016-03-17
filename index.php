<?php
/*
 * This is an KISS (Keep It Simple and Stupid) web monitor for
 * printrun / pronterface written in PHP.
 * Copyright (C) 2016  Jaroslav Škarvada <jskarvad@redhat.com>
 * The code is copied under GPLv3+. Use it on your own risk.
 */

// RPC defaults
$host = "localhost";
$path = "/";
$def_port = 7978;

// how long to cache content in seconds before refresh is needed
$cache_delay = 5;
$video_dev_pref = "/dev/video";
$page_title = "3D printer status";
// resolution of images
$img_res_preview = "320x240";
$img_res_full = "640x480";
// number of images in row (i.e. number of columns)
$images_in_row = 3;
// safety stop when searching for pronterfaces, if anything goes bad
// do not probe for more than NUM pronterfaces, under normal conditions
// it stops probing earlier
$printers_max = 100;
$status_cache = "data/status-cache";
$v4l_lock = "/tmp/printrun-webmon-v4l.lock";
$status_cache_lock = "/tmp/printrun-webmon-status-cache.lock";

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");   // Date in the past

$preview = true;
if (array_key_exists("cam", $_GET) && strlen($_GET["cam"]))
{
  $id = trim(htmlspecialchars($_GET["cam"]));
  if (preg_match("/^\d+$/", $id))
    $preview = false;
}
?>
<html>
<head>
<meta http-equiv='PRAGMA' content='NO-CACHE' />
<meta http-equiv='Expires' content='Sat, 26 Jul 1997 05:00:00 GMT' />
</head>
<?php
$title = $page_title;
if (!$preview)
  $title .= " - camera $id";
echo("<title>$title</title>\n");
echo("<body>\n");
echo("<h1 style='text-align:center'>$title</h1>\n");

function print_array($f, $arr)
{
  $s = "";
  if (!$arr)
    return;
  foreach ($arr as $i)
    $s .= gmdate("H:i:s", round(intval($i))) . "; ";
  if (strlen($s) > 0)
    fprintf($f, "%s", substr($s, 0, -2));
}

function insert_img($id, $img_res, $delay, $html_prefix, $html_suffix)
{
  global $v4l_lock;

  $id = strval($id);
  if (!strlen($id))
    return;
  $img = "img/img" . $id . "_" . $img_res . ".jpeg";
  if (!file_exists($img) || (time() - filemtime($img) > $delay))
  {
    $fp = fopen($v4l_lock, "w");

    if (flock($fp, LOCK_EX))
    {
      if (!file_exists($img) || (time() - filemtime($img) > $delay))
      {
        shell_exec("streamer -q -c /dev/video" . $id . " -b 16 -s $img_res -o /tmp/img.jpeg");
        rename("/tmp/img.jpeg", $img);
      }
      flock($fp, LOCK_UN);
    }
    fclose($fp);
  }
  if (file_exists($img))
  {
    echo($html_prefix);
    echo("<img src='" . $img . "' />");
    echo($html_suffix);
  }
}

function query_pronterface($f, $host, $port, $header)
{
  $socket = @fsockopen($host, $port, $errno, $errstr);

  if (!$socket)
  {
    if ($errno == 111)
      return 0;
    else
      fprintf($f, "$s\n", "<p>Socket error: $errno - $errstr</p>");
    return -1;
  }

  fputs($socket, $header);

  $data = "";
  while (!feof($socket))
    $data .= fgets($socket, 4096);

  fclose($socket);
  $xml = substr($data, strpos($data, "\r\n\r\n") + 4);
  $response = xmlrpc_decode($xml);

  if ($response && xmlrpc_is_fault($response))
    fprintf($f, "%s\n", "<p>xmlrpc: $response[faultString] ($response[faultCode])</p>");
  else
  {
    fprintf($f, "%s\n", "<table border='border' style='margin-left:auto; margin-right:auto; margin-bottom:1em; min-width:60em; border:1px; text-align:center'>");
    fprintf($f, "%s", "<tr><th colspan='2'>Extruder temp</th><th colspan='2'>Bed temp</th><th rowspan='2'>Progress</th><th rowspan='2'>ETA</th>");
    fprintf($f, "%s\n", "<th rowspan='2'>Z</th><th rowspan='2'>Filename</th></tr>");
    fprintf($f, "%s", "<tr><th>Current</th><th>Preset</th><th>Current</th><th>Preset</th></tr>");
    fprintf($f, "%s", "<tr><td>" . $response["temps"]["T0"][0] . "</td><td>" . $response["temps"]["T0"][1] . "</td>");
    fprintf($f, "%s", "<td>" . $response["temps"]["B"][0] . "</td><td>" . $response["temps"]["B"][1] . "</td>");
    fprintf($f, "%s", "<td>");
    $progress_f = $response["progress"];
    fprintf($f, "%6.2f%%", $progress_f);
    fprintf($f, "%s", "</td><td>");
    print_array($f, $response["eta"]);
    fprintf($f, "%s", "</td><td>");
    fprintf($f, "%s", print_r($response["z"], true));
    fprintf($f, "%s", "</td><td>");
    fprintf($f, "%s", print_r($response["filename"], true));
    fprintf($f, "%s\n", "</td></tr></table>");
  }
  return 1;
}

function insert_status($host, $start_port, $delay, $status_cache)
{
  global $status_cache_lock, $printers_max, $path;

  $fp = fopen($status_cache_lock, "w");
  if (!file_exists($status_cache) || (time() - filemtime($status_cache) > $delay))
  {
    if (flock($fp, LOCK_EX))
    {
      if (!file_exists($status_cache) || (time() - filemtime($status_cache) > $delay))
      {
        $request = xmlrpc_encode_request("status", array());
        $contentlength = strlen($request);
        $reqheader = "POST $path HTTP/1.1\r\n" .
          "Host: $host\n" . "User-Agent: PHP query\r\n" .
          "Content-Type: text/xml\r\n".
          "Content-Length: $contentlength\r\n\r\n".
          "$request\r\n";
        $status = array();
        $port = $start_port;
        $stat = -1;
        $f = fopen($status_cache . ".tmp", "w");
        while ($port - $start_port < $printers_max && ($stat = query_pronterface($f, $host, $port, $reqheader)) != 0)
          $port++;
        if ($port == $start_port)
          fprintf($f, "%s\n", "<p>Unable to connect to Pronterface, maybe it is not running.</p>");
        fclose($f);
        rename($status_cache . ".tmp", $status_cache);
      }
      flock($fp, LOCK_UN);
    }
  }
  if (flock($fp, LOCK_SH))
  {
    $f = fopen($status_cache, "r");
    while (($buf = fgets($f, 4096)) !== false)
      echo($buf);
    fclose($f);
    flock($fp, LOCK_UN);
  }
  fclose($fp);
}

if ($preview)
{
  insert_status($host, $def_port, $cache_delay, $status_cache);
  $vdp_len = strlen($video_dev_pref);
  $cnt = 0;
  echo("<table style='margin-left:auto; margin-right:auto; text-align:center; border-spacing:1em'>\n");
  foreach (glob($video_dev_pref . "[0-9]*") as $f)
  {
    if ($cnt % $images_in_row == 0)
      echo("<tr>");
    $id = substr($f, $vdp_len);
    insert_img($id, $img_res_preview, $cache_delay, "<td><a href='?cam=$id'>", "</a></td>\n");
    $cnt++;
    if ($cnt % $images_in_row == 0)
      echo("</tr>\n");
  }
  if ($cnt % $images_in_row != 0)
  {
    while ($cnt % $images_in_row != 0)
    {
      echo("<td></td>");
      $cnt++;
    }
    echo("</tr>\n");
  }
  echo("</table>\n");
}
else
{
// individual image
  if ($id != "")
  {
    insert_img($id, $img_res_full, $cache_delay, "", "");
    echo("<p style='text-align:center'>[&nbsp;<a href='javascript:history.go(-1)'>Back</a>&nbsp;]</p>\n");
  }
}
?>
</body>
</html>
