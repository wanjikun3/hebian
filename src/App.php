<?php

namespace hebian;

class App
{  
    private $_route = [];
    function route($name, $action)
    {
        $this->_route[$name] = $action;
    }

    public function run()
    {
        
        $controller_name = "Index";
        $action_name = "index";
       
        if (isset($_SERVER['PATH_INFO'])) {
            $path = $_SERVER['PATH_INFO'];
            $path = str_replace($_SERVER['SCRIPT_NAME'], "", $path);
        } else {
            $path = str_replace($_SERVER['SCRIPT_NAME'], "", $_SERVER['REQUEST_URI']);
        }
 
        $path=ltrim($path,'/');
        foreach ($this->_route as $reg => $func) {
            if (strpos($reg, ':') !== false) {
                preg_replace_callback(
                    "/:(\w+)/",
                    function ($matches) use (&$reg) {
                        $reg = str_replace($matches[0], "(?<" . $matches[1] . ">\w+)", $reg);
                    },
                    $reg
                );
            }
            $reg = str_replace('/', '\/', $reg);
            if (preg_match('/^' . $reg . '$/', $path, $para)) {
                foreach ($para as $key => $val) {
                    if (is_string($key)) {
                        $_GET[$key] = $val;
                    }
                }
                if (!is_string($func)) {
                    $reflect = new \ReflectionFunction($func);
                    $params = $reflect->getParameters();
                    $args = array();
                    foreach ($params as $i => $param) {
                        $name = $param->getName();
                        $class = $param->getClass();
                        if ($class) {
                            $className = $class->getName();
                            $args[$name] = new $className;
                        } elseif (isset($_GET[$name])) {
                            $args[$name] = $_GET[$name];
                        } elseif ($param->isDefaultValueAvailable()) {
                            $args[$name] = $param->getDefaultValue();
                        } else {
                            $args[$name] = null;
                        }
                    }
                    return $reflect->invokeArgs($args);
                } else {
                    $path = $func;
                }
                break;
            }
        }

        if(!empty($path)){
            $path_arr = explode('/', $path);
            if (count($path_arr) == 2) {
                $controller_name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                    return strtoupper($match[1]);
                }, $path_arr[0]);
                $controller_name = ucfirst($controller_name);
                $action_name = $path_arr[1];
            }elseif(count($path_arr)==1){
                $controller_name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                    return strtoupper($match[1]);
                }, $path_arr[0]);
                $controller_name = ucfirst($controller_name);
            }
        }
        
        $controller_name = "\\app\\controller\\" . $controller_name;
        if (class_exists($controller_name) && method_exists($controller_name, $action_name)) {
            $method = new \ReflectionMethod($controller_name, $action_name);
            $params = $method->getParameters();
            $args = array();
            foreach ($params as $param) {
                $name = $param->getName();
                $class = $param->getClass();
                if ($class) {
                    $className = $class->getName();
                    $args[$name] = new $className;
                } elseif (isset($_GET[$name])) {
                    $args[] = $_GET[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    $args[] = null;
                }
            }
            $method->invokeArgs(new $controller_name, $args);
        }else{
            header('HTTP/1.1 404 Not Found');
            exit('404');
        }
    }
}
