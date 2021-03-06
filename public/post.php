<?php
require("../res/config.php");
$url = $db->real_escape_string($_POST["url"]);
$name = $db->real_escape_string($_POST["name"]);
$content = $db->real_escape_string($_POST["content"]);
$op = 0;

// Regex patterns to allow post filtering, greentext, and post linking.
$re = "/^^(>[a-zA-Z0-9_ \~\!\@\#\$\%\^\&\*\(\)\+\-\=\`\{\}\|\[\]\\\:\"\;\'\?\,\.\/]*$)/mi";
$re3 = "/^(>>(\\d+))*/mi";
$subst = "<p class=\"quote\">$1</p>";

if ($_FILES["image"]["name"] != "" or $_POST["content"] != ""
and strlen($_POST["content"]) <= 2000
and strlen($_POST["content"]) >= 5
and preg_match("/(      *)/mi", $_POST["content"]) == false) {
  if($_POST['type'] == "thread") {
    if ($_FILES["image"]["name"] != "") {
      $db->real_query("SELECT id FROM posts_".$url." ORDER BY id DESC LIMIT 1");
      $res = $db->use_result();
      while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
      }
      $id++;
      $id = $db->real_escape_string($id);

      $db->query("INSERT INTO posts_".$url." (board, name, content, op, timestamp, bump)
      VALUES ('$url', '$name', '$content', '$id', now(), now())");

      $db->real_query("SELECT content FROM posts_".$url." ORDER BY id DESC LIMIT 1");
      $res = $db->use_result();
      while ($row = $res->fetch_assoc()) {
        $content = $row['content'];
      }

      $content = str_replace("\r\n", "\n", $content);
      $content = str_replace("\r", "\n", $content);
      $content = strip_tags($content);
      $content = preg_replace($re, $subst, $content);

      preg_match_all($re3, $content, $matches);
      $ids = $matches[2];
      $ops = array();
      foreach ($ids as $x) {
        $db->real_query("SELECT op FROM posts_".$url." WHERE id = '$x'");
        $res = $db->use_result();
        while ($row = $res->fetch_assoc()) {
          array_push($ops, $row['op']);
        }
      }

      for ($i = 0; $i < count($ops); $i++) {
        $x = $ids[$i];
        $y = $ops[$i];
        $re4 = "/^(>>($x))*/mi";
        $subst4 = "<a href=\"/$url/$y#$x\">$1</a>";
        $content = preg_replace($re4, $subst4, $content);
      }

      $content = $db->real_escape_string($content);
      $db->query("UPDATE posts_".$url." SET
      content = '$content'
      WHERE id = '$id'");

      $url = $_POST['url'];

      if (!file_exists("$url/$id")) {
        mkdir("$url/$id", 0777, true);
      }
      if (!file_exists("$url/$id/res")) {
        mkdir("$url/$id/res", 0777, true);
      }
      $t_json = fopen("$url/$id/thread.json", "w");
      $txt = "{\n";
      fwrite($t_json, $txt);
      $txt = '    "id": "' . $id . '"' . "\n";
      fwrite($t_json, $txt);
      $txt = "}\n";
      fwrite($t_json, $txt);
      fclose($b_conf);
      copy("../res/temps/thread.php", "$url/$id/index.php");
    }
  }
  else if ($_POST['type'] == "reply") {
    $id = $db->real_escape_string($_POST["id"]);
    $url = $db->real_escape_string($_POST["url"]);
    $content = $db->real_escape_string($_POST["content"]);
    $db->query("INSERT INTO posts_".$url." (board, name, content, op, timestamp, bump)
    VALUES ('$url', '$name', '$content', '$id', now(), now())");

    $bump = 0;
    $bop = 0;
    $db->real_query("SELECT content, id, timestamp, op FROM posts_".$url." ORDER BY id DESC LIMIT 1");
    $res = $db->use_result();
    while ($row = $res->fetch_assoc()) {
      $content = $row['content'];
      $id = $row['id'];
      $bump = $row['timestamp'];
      $bop = $row['op'];
    }

    $bump = $db->real_escape_string($bump);
    $bop = $db->real_escape_string($bop);
    $db->query("UPDATE posts_".$url." SET
    bump = '$bump'
    WHERE id = '$bop'");

    $content = str_replace("\r\n", "\n", $content);
    $content = str_replace("\r", "\n", $content);
    $content = strip_tags($content);
    $content = preg_replace($re, $subst, $content);

    preg_match_all($re3, $content, $matches);
    $ids = $matches[2];
    $ops = array();
    foreach ($ids as $x) {
      $db->real_query("SELECT op FROM posts_".$url." WHERE id = '$x'");
      $res = $db->use_result();
      while ($row = $res->fetch_assoc()) {
        array_push($ops, $row['op']);
      }
    }

    for ($i = 0; $i < count($ops); $i++) {
      $x = $ids[$i];
      $y = $ops[$i];
      $re4 = "/^(>>($x))*/mi";
      $subst4 = "<a href=\"/$url/$y#$x\">$1</a>";
      $content = preg_replace($re4, $subst4, $content);
    }

    $content = $db->real_escape_string($content);
    $url = $db->real_escape_string($url);
    $id = $db->real_escape_string($id);
    $db->query("UPDATE posts_".$url." SET
    content = '$content'
    WHERE id = '$id'");

  }
}
if ($_FILES["image"]["name"] == "" and $_POST["content"] == "") {
  echo "<div class=\"header\"><p>You must have content with your post.</p><br/></div>";
}
if (strlen($_POST["content"]) > 2000) {
  echo "<div class=\"header\"><p>Post too long.</p></div>";
}
if (strlen($_POST["content"]) < 5) {
  echo "<div class=\"header\"><p>Post too short.</p></div>";
}
if (preg_match("/(      *)/mi", $_POST["content"])) {
  echo "<div class=\"header\"><p>Post rejected as spam.</p></div>";
}
if ($_POST["type"] == "thread" and $_FILES["image"]["name"] == "") {
  echo "<div class=\"header\"><p>New threads must have an image.</p></div>";
}
?>
<html>
<head>
  <link rel="stylesheet" type="text/css" href="style/main.css">
</head>
<body>
<div class="header">
<?php
if ($_POST['type'] == "reply") {
  $id = $_POST['id'];
}
if ($_FILES["image"]["name"] != "") {
  $target_dir = "$url/$id/res/";
  $target_file = $target_dir . basename($_FILES["image"]["name"]);
  $uploadOk = 1;
  $imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
  $url = $db->real_escape_string($_POST["url"]);
  $db->real_query("SELECT id FROM posts_".$url." ORDER BY id DESC LIMIT 1");
  $res = $db->use_result();
  while ($row = $res->fetch_assoc()) {
    $id = $row['id'];
  }
  $id = $db->real_escape_string($id);
  if(isset($_POST["submit"])) {
      $check = getimagesize($_FILES["image"]["tmp_name"]);
      if($check !== false) {
          $uploadOk = 1;
      }
      else {
          echo "<p>File is not an image.</p><br/>";
          $uploadOk = 0;
      }
  }
  if (file_exists($target_file)) {
      echo "<p>Image already exists. Rename image.</p><br/>";
      $uploadOk = 0;
  }
  // Check file size
  if ($_FILES["fileToUpload"]["size"] > 500000) {
      echo "<p>Image is too large.</p><br/>";
      $uploadOk = 0;
  }
  // Allow certain file formats
  if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
  && $imageFileType != "gif" ) {
      echo "<p>Only JPG, JPEG, PNG & GIF files are allowed.</p><br/>";
      $uploadOk = 0;
  }
  if ($uploadOk == 0) {
      echo "<p>Your image was not uploaded.</p><br/>";
  }
  else {
      if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
          echo "<p>The image ". basename( $_FILES["image"]["name"]). " has been uploaded.</p><br/>";
	        switch($imageFileType) {
            case 'gif' :
              $src_img = imagecreatefromgif($target_file);
              break;
            case 'png' :
              $src_img = imagecreatefrompng($target_file);
              break;
            case 'jpg' or 'jpeg' :
              $src_img = imagecreatefromjpeg($target_file);
              break;
            default:
              break;
          }
	        $width = 150;
          $height = 150;
          list($width_orig, $height_orig) = getimagesize($target_file);
          $ratio_orig = $width_orig/$height_orig;
          if ($width/$height > $ratio_orig) {
            $width = $height*$ratio_orig;
          }
          else {
            $height = $width/$ratio_orig;
          }
	        $thumb = imagecreatetruecolor($width, $height);
          imagecopyresampled($thumb, $src_img, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
          imagejpeg($thumb, $target_dir . 'thumb_' . $_FILES["image"]["name"]);
          imagedestroy($thumb);
          $image = $db->real_escape_string($target_file);
          $thumb = $db->real_escape_string($target_dir . 'thumb_' . $_FILES["image"]["name"]);
          $db->query("UPDATE posts_".$url." SET
          image = '$image', thumb = '$thumb'
          WHERE id = '$id'");
      }
      else {
          echo "<p>There was an error uploading your file.</p><br/>";
      }
  }
}
if ($_POST['type'] == "reply") {
  $id = $_POST['id'];
}
?>
<?php echo "<a href=\"/$url/\">Return to the board home.</a></br>";
echo "<a href=\"/$url/$id/\">Return to the thread.</a></div>"; ?>
</body>
</html>
