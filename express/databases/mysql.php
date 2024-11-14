<?php

class MySqlStorage {
      protected $methods = [];
      protected $connection;
      protected $stringQuery = "";

      public function __construct (...$args) {
            if (count($args) == 4) {
                  $this->connection = mysqli_connect(...$args);
            }
      }

      public function connect (...$args) {
            $x = count($args);
            if ($x == 4) {
                  $this->connection = mysqli_connect(...$args);
            } else if ($x == 1) {
                  if (isset($args[0]["password"])) {
                        $this->connection = mysqli_connect($args[0]["servername"], $args[0]["username"], $args[0]["password"], $args[0]["dbname"]);
                  } else {
                        $this->connection = mysqli_connect(...$args[0]);
                  }
            }
      }

      public function createMethod ($name, $func) {
            $this->methods[$name] = $func;
      }

      public function callMethod ($name, $data = false) {
            $this->methods[$name]($this, $data);
      }

      public function directQuery ($query) {
            if ($this->isQueryValid($query)) {
                  return $this->processQuery($query);
            } else {
                  return false;
            }
      }

      private function convertToString ($x) {
            $str = "";
            if (is_string($x)) {
                  $str .= $x;
            } else if (is_array($x)) {
                  if (count($x) == 0) {
                        return false;
                  } else {
                        for ($i=0; $i < count($x); $i++) { 
                              $str .= $x[$i].",";
                        }
                        $str = rtrim($str, ",");
                  }
            } else {
                  return false;
            }

            return $str." ";
      }

      public function select ($x) {
            $this->stringQuery = "SELECT ".$this->convertToString($x);
            return $this;
      }

      public function from ($x) {
            $this->stringQuery .= "FROM ".$this->convertToString($x);
            return $this;
      }

      private function getProperValueFormat ($value) {
            if (is_string($value)) {
                  return "'".$value."'";
            } else if (is_bool($value)) {
                  return (int) $value;
            } else {
                  return $value;
            }
      }

      public function where ($x) {
            $str = "WHERE ";
            if (is_string($x)) {
                  $str += $x;
            } elseif (is_array($x)) {
                  foreach ($x as $key => $value) {
                        if ($key == "#") {
                              $str .= $value. " ";
                        } else {
                              $str .= $key."=".$this->getProperValueFormat($value). " ";
                        }
                  }
            } else {
                  return false;
            }

            $this->stringQuery .= $str;
            return $this;
      }

      public function limit ($x) {
            if (is_int($x)) {
                  $this->stringQuery .= "LIMIT ". $x;
            } else {
                  return false;
            }

            return $this;
      }

      private function isQueryValid ($x) {
            return !empty($x) && strlen($x) > 5 && $x[0] == "S";
      }

      private function processQuery ($query) {
            $result = mysqli_query($this->connection, $query);
            $this->stringQuery = "";
            if (mysqli_num_rows($result) > 0) {
                  $packet = [];
                  while ($row = mysqli_fetch_assoc($result)) {
                        $packet[] = $row;
                  }
                  if (count($packet) == 1) {
                        return $packet[0];
                  } else {
                        return $packet;
                  }
            } else {
                  return [];
            }
      }

      public function result () {
            echo $this->stringQuery;
            if ($this->isQueryValid($this->stringQuery)) {
                  return $this->processQuery($this->stringQuery);
            } else {
                  return false;
            }
      }
}

?>