<?php
require("config.php");
require("devices.php");
$camera = @intval($_GET["camera"]);
$fullscreen = isset($_GET["fullscreen"]);
?>
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
<body class="<?php echo $fullscreen ? "fullscreen" : "" ?>">

<div id="menu">
  <div id="printers"></div>
  <div id="rightbottom">
    <div id="controls">
      Saturation
      <div id="saturation" class="slider"></div>
      Brightness
      <div id="brightness" class="slider"></div>
    </div>
<?php
if ($homepage)
{
  echo "<div id=\"homepage\">";
  echo "<a href=\"$homepage\">Home page</a>";
  echo "</div>";
}
?>
  </div>
</div>

<div id="cameras">
  <?php
    if (!$fullscreen) {
      $cameras = getCameras($video_dev_glob, $rulesets);
      if (!array_key_exists(strval($camera), $cameras)) {
        $first_cam = array_key_first($cameras);
        if ($first_cam != NULL) {
          $camera = intval($first_cam);
        }
      }
    }
  ?>
  <a href="./?camera=<?php echo $camera . ($fullscreen ? "" : "&fullscreen") ?>">
    <img data-src="camera.php?id=<?php echo $camera ?>&amp;full" id="mainCamera" class="camera">
  </a>
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
  max: 300,
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
  max: 900,
  value: localStorage.getItem("brightness") ? localStorage.getItem("brightness") : 100,
  change: function(event, ui) {
    localStorage.setItem("brightness", ui.value);
    setCameras();
  }
});

function setCameras() {
  var saturation = localStorage.getItem("saturation") || 100;
  var brightness = localStorage.getItem("brightness") || 100;
  $(".camera").css("filter",        "saturate("+saturation+"%) brightness("+brightness+"%)");
  $(".camera").css("-webkit-filter","saturate("+saturation+"%) brightness("+brightness+"%)");
  $(".camera").css("-moz-filter",   "saturate("+saturation+"%) brightness("+brightness+"%)");
  $(".camera").css("-o-filter",     "saturate("+saturation+"%) brightness("+brightness+"%)");
  $(".camera").css("-ms-filter",    "saturate("+saturation+"%) brightness("+brightness+"%)");
  console.log("set saturation = "+saturation+", brightness = "+brightness);
}
setCameras();

function refresh() {
  if (document.visibilityState == "visible") {
    console.log("reloading...");
    $(".camera").each(function() {
      $(this).attr("src", $(this).attr("data-src")+"&"+new Date().getTime());
    });
    $("#printers").load("printers.php");
  } else {
    console.log("not visible - skipping reload...");
  }
}
refresh();
setInterval(refresh, <?php echo $cache_delay * 1000 ?>);

</script>
