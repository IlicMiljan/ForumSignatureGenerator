<?php
  include_once "core/Signature.Class.php";

  $Signature = new Signature("http://forum.cybershark.rs/", "CyberBanner", "");

  $SavePath = $Signature->CreateSignature("http://forum.cybershark.rs/member.php/30918-Viruss");
  echo $Signature->CreateDirectURL($SavePath);
?>
