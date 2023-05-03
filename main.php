<?php
    $racine = '';
    //if(isset($_REQUEST['I_controller'])) $racine = '../';
    
    include_once 'config.php';

    if(!isset($_REQUEST['I_controller'])) $controller = $config['index'];
    else $controller = $_REQUEST['I_controller'];
    
    if(!is_file($racine.'controller/'.$controller.'.php'))
    {
        $controller = $config['error'];
            include $racine.'controller/'.$controller.'.php';
            $pageError = new $controller;
            $pageError->index();
            exit();
    }
    
    include $racine.'controller/'.$controller.'.php';
    
    $page = new $controller;
    
    if(isset($_REQUEST['I_method']))
    {
        if(!(method_exists($page,$_REQUEST['I_method'])))
        {
            $controller = $config['error'];
            include $racine.'controller/'.$controller.'.php';
            $pageError = new $controller;
            $pageError->index();
            exit();
        }
        
        if(isset($_REQUEST['I_var']))
        {
            $page->{$_REQUEST['I_method']}($_REQUEST['I_var']);
        }
        else 
        {
            $page->{$_REQUEST['I_method']}();
        }
    }
    else 
    {
        $page->index();
    }
?>
