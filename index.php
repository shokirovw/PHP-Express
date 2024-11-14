<?php 

include("Router.php");
include('express/databases/mysql.php');

$app = new Router ("/express");

$database = new MySqlStorage ();

// $database->createMethod('abc', function ($db) {
      
// });

// $database->callMethod('abc', ['azamat' => 'arslan']);

$app->defLocalConst("name", "qwerty");

$app->use(function ($req, $res, $next) {
      $res->write("Welcome to our applciation ".$req->getLocalConst("name"). "<br>");
      return $res->next();
});

$app->get("/about/a", [function ($req, $res, $next) {
      $res->write("About page".$req->getFormData("count"));
      return $next();
}, function ($req, $res, $next) {
      $res->write("<b>Assigning key</b>");
      $req->set("key", 1221);
      $req->setSession("avatar", "AvazDdemo");
      return $res->next();
}]);

$app->space('/news', function ($newsRouter) {
      $newsRouter->use(function ($req, $res, $next) { //// somehow vague
            $res->write("Welcome to news space");
      });

      $newsRouter->get("/a", function ($req, $res, $next) {
            $res->write("News A page");
            return $res->redirect("/item/2");
      });

      $newsRouter->get("/item/:id", function ($req, $res, $next) {
            $res->write("News Homepage ");
            $res->write("Session: ". $req->getSession("avatar"). " ");
            $res->write("Requested news id is: ". $req->getQueryParams("id"). "<br>");
      });

      $newsRouter->space("/world", function ($worldNewsRouter) {
            $worldNewsRouter->get("/", function ($req, $res, $next) {
                  $res->render("worldnews", ["title" => "World News | Today", "data" => [
                        "text" => "Bu world news"
                  ]]);
            });
      });
});

// $app->post('/about/a', function ($req, $res, $next) {
//       $res->write("Post request". $req->getFormData("count"));
//       return $res->end();
// });

$app->route('/about/a')->post(function ($req, $res, $next) {
      $res->write("Post request: Count = ". $req->getFormData("count"));
      return $res->end();
})->get(function ($req, $res, $next) {
      $res->write("Get request");
      return $res->next();
}, function ($req, $res, $next) {
      $res->write("Successfully rendered");
      return $res->end();
});

?>