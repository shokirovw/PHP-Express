<?php

include("RegExp.php");

session_start();
class Router {
      protected $sliceLength;
      public $directory;
      protected $responseObject;
      protected $requestObject;
      protected $frozenRoutes = [];
      protected $reqSent = false;
      protected $dataStorage = []; 
      protected $currentRoutePostSent = false;
      protected $temporaryPrefix = "";

      function __construct ($x) {
            $this->sliceLength = strlen($x);
            $this->directory = $x;
            $this->responseObject = new ResponseObject ($x); 
            $this->requestObject = new RequestObject ();
      }

      protected function errorPage () {
            exit;
      }

      public function defLocalConst ($key, $value) {
            $this->requestObject->defLocalConst($key, $value);
      }

      public function getLocalConst ($key) {
            return $this->requestObject->getLocalConst($key);
      }

      protected function ifStringsMatch ($a, $b) {
            for ($j=0; $j < strlen($a); $j++) {
                  if (empty($b[$j]) || ($a[$j] !== $b[$j])) {
                        return false;
                  }
            }
            return true;
      }

      protected function isRouteFrozen ($route) {
            for ($i=0; $i < count($this->frozenRoutes); $i++) {
                  if ($this->ifStringsMatch($this->frozenRoutes[$i], $route)) {
                        return true;
                  };
            }
            return false;
      }

      protected function collectMiddlewares ($a, $numargs, $args) {
            $middlewares = [];
            for ($i=$a; $i < $numargs; $i++) { 
                  if (is_array($args[$i])) {
                        for ($j=0; $j < count($args[$i]); $j++) { 
                              if (is_callable($args[$i][$j])) {
                                    array_push($middlewares, $args[$i][$j]);
                              } else {
                                    $this->errorPage();
                              }
                        }
                  } elseif (is_callable($args[$i]))  {
                        array_push($middlewares, $args[$i]);
                  } else {
                        $this->errorPage();
                  }
            }
            return $middlewares;
      }

      private function executeMiddlewares ($middlewares, $currentRoute) {
            $next = function ($type = "mid") {
                  if ($type == "route") {
                        return ["eventName" => "next_route"];
                  } elseif ($type == "mid") {
                        return ["eventName" => "next"];
                  }
            };

            for ($i=0; $i < count($middlewares); $i++) { 
                  $exRes = $middlewares[$i]($this->requestObject, $this->responseObject, $next);

                  if (isset($exRes["eventName"])) {
                        if ($exRes["eventName"] == "end") {
                              array_push($this->frozenRoutes, $currentRoute);
                              break;
                        } elseif ($exRes["eventName"] == "next") {
                              continue;
                        } elseif ($exRes["eventName"] == "next_route") {
                              break;
                        } else {
                              $this->errorPage();
                        }
                  } else {
                        continue; // if res.end nor next is invoked, then chain continiues properly
                  }
            }

            return true;
      }

      protected function getUrl () {
            return substr($_SERVER["REDIRECT_URL"], $this->sliceLength);
      }

      function space () {
            $numargs = func_num_args();
            $args = func_get_args();

            if (!($numargs >= 2)) {
                  $this->errorPage();
            }

            if (!is_string($args[0])) {
                  $this->errorPage();
            }

            $route = $args[0];

            if (!$this->ifStringsMatch($this->temporaryPrefix.$route, $this->getUrl())) {
                  return;
            }

            $this->temporaryPrefix .= $route;
            $this->responseObject->addDirectory($route);

            $args[1]($this);

            $this->responseObject->removeDirectory($route);            
            $this->temporaryPrefix = substr($this->temporaryPrefix, -1 * strlen($route));

            // $middlewares = $this->collectMiddlewares($startWith, $numargs, $args);

            // $this->executeMiddlewares($middlewares, $route);
      }

      //// passing middlewares as array

      function route ($x) {
            if (isset($x) && is_string($x)) {
                  $this->currentRoute = $x;
                  $this->currentRoutePostSent = false;
                  return $this;
            } else {
                  $this->errorPage();
            }
      }

      protected function doesURLMatch ($route, $url) {
            $data = [];
            $keys = [];
            $re = PathToRegexp::convert($route, $keys);
            $matches = PathToRegexp::match($re, $this->getUrl());

            if (is_null($matches)) {
                  return false;
            }

            for ($i = 0; $i < count($keys); $i++) { 
                  $data[$keys[$i]["name"]] = $matches[$i + 1];
            }

            return $data;
      }

      function get () {
            $numargs = func_num_args();
            $args = func_get_args();

            if (!($numargs > 0)) {
                  $this->errorPage(); return;
            }

            if (!is_string($args[0])) {
                  if ($this->currentRoutePostSent === true) {
                        return;
                  } elseif ($this->currentRoute !== false) {
                        $route = $this->currentRoute;
                        $startWith = 0;
                  } else {
                        $this->errorPage();
                  }
            } else {
                  if ($numargs > 1) {
                        $route = $args[0];
                        $startWith = 1;
                  } else {
                        $this->errorPage();
                  } 
            }

            $route = $this->temporaryPrefix.$route;

            if ($this->isRouteFrozen($route)) {
                  return;
            }

            $matching = $this->doesURLMatch($route, $this->getUrl());

            if ($matching === false) {
                  return;
            } else {
                  $this->requestObject->setQueryParams($matching);
            }

            $middlewares = $this->collectMiddlewares($startWith, $numargs, $args);

            $this->executeMiddlewares($middlewares, $route);

            $this->reqSent = true;
      }

      function post () {
            $numargs = func_num_args();
            $args = func_get_args();

            if (!($numargs > 0)) {
                  $this->errorPage(); return;
            }

            if (!is_string($args[0])) {
                  if ($this->currentRoute !== false) {
                        if (count($_POST) == 0) {
                              return $this;
                        } else {
                              $route = $this->currentRoute;
                              $startWith = 0;
                        }
                  } else {
                        $this->errorPage();
                  }
            } else {
                  if ($numargs > 1) {
                        $route = $args[0];
                        $startWith = 1;
                  } else {
                        $this->errorPage();
                  } 
            }

            if ($this->isRouteFrozen($route)) {
                  return;
            }

            $matching = $this->doesURLMatch($route, $this->getUrl());

            if ($matching === false) {
                  return;
            } else {
                  $this->requestObject->setQueryParams($matching);
            }

            $middlewares = $this->collectMiddlewares($startWith, $numargs, $args);

            $this->executeMiddlewares($middlewares, $route);

            $this->reqSent = true;
            $this->currentRoutePostSent = true;
            return $this;
      }

      function use () {
            $numargs = func_num_args();
            $args = func_get_args();

            if (!($numargs > 0)) {
                  $this->errorPage(); return;
            }

            if (!is_string($args[0])) {
                  $route = '/';
                  $startWith = 0;
            } else {
                  $route = $args[0];
                  $startWith = 1;
            }

            if (!$this->ifStringsMatch($route, $this->getUrl())) {
                  return;
            }

            if ($this->isRouteFrozen($route)) {
                  return;
            }

            $middlewares = $this->collectMiddlewares($startWith, $numargs, $args);

            $this->executeMiddlewares($middlewares, $route);

            $this->reqSent = true;
      }

      function __destruct () {
            $this->currentRoute = "";
            if ($this->reqSent == false) {
                  echo "<br>Requested page has not been found 404";
            }
      }
}


class ResponseObject {
      protected $directory;

      function __construct ($directory) {
            $this->directory = $directory;
      }

      function addDirectory ($space_dir) {
            $this->directory .= $space_dir;
      }

      function removeDirectory ($space_dir) {
            $this->directory = substr($this->directory, -1 * strlen($space_dir));
      }

      public function write ($x) {
            echo $x. "<br>";
      }

      public function next ($type = "mid") {
            if ($type == "route") {
                  return ["eventName" => "next_route"];
            } elseif ($type == "mid") {
                  return ["eventName" => "next"];
            }
      }

      public function render ($filepath, $starter = ["title" => "Document", "data" => []]) {
            $data = $starter['data'];
            include('express/renderer/header.php');
            include('views/'.$filepath.'.inc.php');
            include('express/renderer/footer.php');
      }

      public function redirect ($x) {
            header("Location: ".$this->directory.$x);
            exit;
      }

      public function json ($data) {
            echo json_encode($data);
            return $this->end();
      }

      public function end () {
            return ["eventName" => "end"];
      }
}

class RequestObject {
      public $perRequestStore = [];
      public $localConstStore = [];
      public $queryParams = [];


      //// query params

      function setQueryParams ($data) {
            $this->queryParams = $data;
      }

      function getQueryParams ($key = "") {
            if (empty($key)) {
                  return $this->queryParams;
            } else {
                  if (isset($this->queryParams[$key])) {
                        return $this->queryParams[$key];
                  } else {
                        return null;
                  }
            }
      }

      ///// Per Request Storage

      function set ($key, $value) {
            $this->perRequestStore[$key] = $value;
            return true;
      }

      function get ($key) {
            if (array_key_exists($key, $this->perRequestStore)) {
                  return $this->perRequestStore[$key];
            }
      }

      function delete ($key) {
            unset($this->perRequestStore[$key]);
      }

      function destroy () {
            $this->perRequestStore = [];
      }

 
      ////// Session Storage

      function setSession ($key, $value) {
            $_SESSION[$key] = $value;
      }

      function getSession ($key = "") {
            if (empty($key)) {
                  return $_SESSION;
            } else {
                  if (isset($_SESSION[$key])) {
                        return $_SESSION[$key];
                  } else {
                        return null;
                  }
            }
      }

      function deleteSession ($key) {
            unset($_SESSION[$key]);
      }

      function destroySession () {
            session_unset();
      }


      ////// local Server Storage

      function defLocalConst ($key, $value) {
            if (is_string($key)) {
                  $this->localConstStore[$key] = $value; return true;
            } else {
                  $this->errorPage();
            }
      }

      function getLocalConst ($key) {
            return $this->localConstStore[$key];
      }



      /////// Cookies 

      function setCookie ($key, $value) {
            setcookie($key, $value, time() + (86400 * 30), "/");
      }

      function getCookie ($key = "") {
            if (empty($key)) {
                  return $_COOKIE;
            } else {
                  return $_COOKIE[$key];
            }
      }

      function deleteCookie ($key) {
            setcookie($key, "", time() - 3600);
      }  
      
      
      ////// Get parameters

      function getQueryString ($key = "") {
            if (empty($key)) {
                  return $_GET;
            } else {
                  if (isset($_GET[$key])) {
                        return $_GET[$key];
                  } else {
                        return null;
                  }
            }
      }


      //// Post Data

      function getFormData ($key = "") {
            if (empty($key)) {
                  return $_POST;
            } else {
                  if (isset($_POST[$key])) {
                        return $_POST[$key];
                  } else {
                        return null;
                  }
            }
      } 
}
?>