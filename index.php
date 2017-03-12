<?php require("config.php"); ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="chrome=1" />
  <link rel="stylesheet" href="theme.css" type="text/css" media="all" />
  <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js" type="text/javascript"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js" type="text/javascript"></script>
  <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.2/jquery-ui.css" type="text/css" />
  <title><?php echo $page_title ?></title>
<body>

<div id="menu">
  <div id="printers"></div>
  <div id="rightbottom">
    <div id="controls">
      Saturation
      <div id="saturation" class="slider"></div>
      Brightness
      <div id="brightness" class="slider"></div>
    </div>
  </div>
</div>

<div id="cameras">
<?php
$camera = @intval($_GET["camera"]);
foreach (glob($video_dev_pref."*") as $filename) {
  $cameras[substr($filename, strlen($video_dev_pref), 100)] = TRUE;
}
//unset($cameras[$camera]);
?>
<img data-src="camera.php?id=<?php echo $camera ?>&amp;full" id="mainCamera" class="camera">
<div class="othercameras">
  <?php foreach($cameras as $c => $_) { ?>
    <a href="./?camera=<?php echo $c ?>">
      <img data-src="camera.php?id=<?php echo $c ?>" class="camera">
    </a>
  <?php } ?>
</div>
</div>

<script>

$("#saturation").slider({
  orientation: "horizontal",
  range: "min",
  min: 10,
  max: 500,
  value: localStorage.getItem("saturation") ? localStorage.getItem("saturation") : 100,
  change: function(event, ui) {
    localStorage.setItem("saturation", ui.value);
    setCameras();
  }
});

$("#brightness").slider({
  orientation: "horizontal",
  range: "min",
  min: 10,
  max: 800,
  value: localStorage.getItem("brightness") ? localStorage.getItem("brightness") : 100,
  change: function(event, ui) {
    localStorage.setItem("brightness", ui.value);
    setCameras();
  }
});

function setCameras() {
  var saturation = localStorage.getItem("saturation");
  var brightness = localStorage.getItem("brightness");
  $(".camera").css("filter",        "saturate("+saturation+"%) brightness("+brightness+"%)");
  $(".camera").css("-webkit-filter","saturate("+saturation+"%) brightness("+brightness+"%)");
  $(".camera").css("-moz-filter",   "saturate("+saturation+"%) brightness("+brightness+"%)");
  $(".camera").css("-o-filter",     "saturate("+saturation+"%) brightness("+brightness+"%)");
  $(".camera").css("-ms-filter",    "saturate("+saturation+"%) brightness("+brightness+"%)");
  console.log("set saturation = "+saturation+", brightness = "+brightness);
}
setCameras();

function refresh()
{
  console.log("reloading...");
  $(".camera").each(function() {
    $(this).attr("src", $(this).attr("data-src")+"#"+new Date().getTime());
  });
  $("#printers").load("printers.php");
}

refresh();
setInterval(refresh, <?php echo $cache_delay * 1000 ?>);

</script>
