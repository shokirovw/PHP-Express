<?php 

include("Router.php");

$app = new Router ("");

$app->defLocalConst("name", "Uzbekistan Airways");

$app->use(function ($req, $res) {
      $res->write("Welcome | ".$req->getLocalConst("name"));
      return $res->next();
});

$app->get("/contacts", function ($req, $res) {
      $res->write("Contacts page");
      return $res->end();
});

$app->get("/about", function ($req, $res) {
      $res->write("About page");
      return $res->end();
});

?>