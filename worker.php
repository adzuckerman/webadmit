<?php
$to = "blin@blinkazazi.com";
$subject = "My subject";
$txt = "Hello world!";
$headers = "From: webmaster@example.com" . "\r\n" .
"CC: blinkazazi@hotmail.com";

echo mail($to,$subject,$txt,$headers);
?>


