<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
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
$host = "localhost";
$path = "/";
$port = 7978;

function print_array($arr)
{
  $s = "";
  foreach ($arr as $i)
    $s .= gmdate("H:i:s", round(intval($i))) . "; ";
  if (strlen($s) > 0)
    echo(substr($s, 0, -2));
}

function insert_img($num, $delay)
{
  $img = "img/img" . $num . ".jpeg";
  if (!file_exists($img) || (time() - filemtime($img) > $delay))
  {
    $fp = fopen("/tmp/v4llock", "w");

    if (flock($fp, LOCK_EX))
    {
      if (!file_exists($img) || (time() - filemtime($img) > $delay))
      {
        shell_exec("streamer -c /dev/video" . $num . " -b 16 -s 320x240 -o /tmp/img.jpeg");
        rename("/tmp/img.jpeg", $img);
      }
     flock($fp, LOCK_UN);
    }
    fclose($fp);
  }
  if (file_exists($img))
  {
    echo("<p style='text-align:center'>\n");
    echo("<img src='" . $img . "' />\n");
    echo("</p>\n");
  }
}

$request = xmlrpc_encode_request("status", array());
$contentlength = strlen($request);
$reqheader = "POST $path HTTP/1.1\r\n" .
  "Host: $host\n" . "User-Agent: PHP query\r\n" .
  "Content-Type: text/xml\r\n".
  "Content-Length: $contentlength\r\n\r\n".
  "$request\r\n";

$socket = fsockopen($host, $port, $errno, $errstr);

if (!$socket)
{
  if ($errno == 111)
    echo("<p>Unable to connect to Pronterface, maybe it is not running.</p>\n");
  else
    echo("<p>Socket error: $errno - $errstr</p>\n");
  return;
}

fputs($socket, $reqheader);

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
  echo("<table border='border' style='margin-left:auto; margin-right:auto; min-width:60em; border:1px; text-align: center'>\n");
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
insert_img("0", 1);
insert_img("1", 1);
?>
</body>
</html>
