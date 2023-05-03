<?php
include 'model/I_controller.php';
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of welcome
 *
 * @author nkunkuma
 */
class error404 extends I_controller {
    
     function index()
     {
		$data['timestart'] = microtime(true);
        $this->load('view', 'error', $data);
     }
     
}

?>
