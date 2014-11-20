<?php

  require_once("rest_server.class.php");

  header("Cache-Control: no-cache, must-revalidate");
  # Date in the past
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

  RestServer::handleRequest();

?>
