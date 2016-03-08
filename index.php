<?php
// RPC defaults
$host = "localhost";
$path = "/";
$def_port = 7978;

// how long to cache images from webcams in seconds
$img_cache_delay = 1;
$video_dev_pref = "/dev/video";
// resolution of images
$img_res = "320x240";
// number of images in row (i.e. number of columns)
$images_in_row = 3;
// safety stop when searching for pronterfaces, if anything goes bad
// do not probe for more than NUM pronterfaces, under normal conditions
// it stops probing earlier
$printers_max = 100;

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");   // Date in the past
?>
<html>
<head>
<meta http-equiv='PRAGMA' content='NO-CACHE' />
<meta http-equiv='Expires' content='Sat, 26 Jul 1997 05:00:00 GMT' />
</head>
<title>3D printer status</title>
<body>
<h1 style='text-align:center'>3D printer status</h1>
<?php

function print_array($arr)
{
  $s = "";
  if (!$arr)
    return;
  foreach ($arr as $i)
    $s .= gmdate("H:i:s", round(intval($i))) . "; ";
  if (strlen($s) > 0)
    echo(substr($s, 0, -2));
}

function insert_img($id, $img_res, $delay, $html_prefix, $html_suffix)
{
  $id = strval($id);
  if (!strlen($id))
    return;
  $img = "img/img" . $id . ".jpeg";
  if (!file_exists($img) || (time() - filemtime($img) > $delay))
  {
    $fp = fopen("/tmp/v4llock", "w");

    if (flock($fp, LOCK_EX))
    {
      if (!file_exists($img) || (time() - filemtime($img) > $delay))
      {
        shell_exec("streamer -c /dev/video" . $id . " -b 16 -s $img_res -o /tmp/img.jpeg");
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

function insert_status($host, $port, $header)
{
  $socket = @fsockopen($host, $port, $errno, $errstr);

  if (!$socket)
  {
    if ($errno == 111)
      return 0;
    else
      echo("<p>Socket error: $errno - $errstr</p>\n");
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
    echo("<p>xmlrpc: $response[faultString] ($response[faultCode])</p>\n");
  else
  {
    echo("<table border='border' style='margin-left:auto; margin-right:auto; margin-bottom:1em; min-width:60em; border:1px; text-align:center'>\n");
    echo("<tr><th colspan='2'>Extruder temp</th><th colspan='2'>Bed temp</th><th rowspan='2'>Progress</th><th rowspan='2'>ETA</th>");
    echo("<th rowspan='2'>Z</th><th rowspan='2'>Filename</th></tr>\n");
    echo("<tr><th>Current</th><th>Preset</th><th>Current</th><th>Preset</th></tr>");
    echo("<tr><td>" . $response["temps"]["T0"][0] . "</td><td>" . $response["temps"]["T0"][1] . "</td>");
    echo("<td>" . $response["temps"]["B"][0] . "</td><td>" . $response["temps"]["B"][1] . "</td>");
    echo("<td>");
    $progress_f = $response["progress"];
    printf("%6.2f%%", $progress_f);
    echo("</td><td>");
    print_array($response["eta"]);
    echo("</td><td>");
    print_r($response["z"]);
    echo("</td><td>");
    print_r($response["filename"]);
    echo("</td></tr></table>\n");
  }
  return 1;
}

$request = xmlrpc_encode_request("status", array());
$contentlength = strlen($request);
$reqheader = "POST $path HTTP/1.1\r\n" .
  "Host: $host\n" . "User-Agent: PHP query\r\n" .
  "Content-Type: text/xml\r\n".
  "Content-Length: $contentlength\r\n\r\n".
  "$request\r\n";

$port = $def_port;
$stat = -1;
while ($port - $def_port < $printers_max && ($stat = insert_status($host, $port, $reqheader)) != 0)
  $port++;
if ($port == $def_port)
  echo("<p>Unable to connect to Pronterface, maybe it is not running.</p>\n");

$vdp_len = strlen($video_dev_pref);
$cnt = 0;
echo("<table style='margin-left:auto; margin-right:auto; text-align:center; border-spacing:1em'>\n");
foreach (glob($video_dev_pref . "[0-9]*") as $f)
{
  if ($cnt % $images_in_row == 0)
    echo("<tr>");
  insert_img(substr($f, $vdp_len), $img_res, $img_cache_delay, "<td>", "</td>\n");
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
?>
</table>
</body>
</html>
