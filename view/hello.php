<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Basic MVC</title>
		<style>
			body
			{
				background-size: cover;
                                background-attachment: fixed;
                                background-position: center center;
                                background-color: #3a92c8;
                                background: -webkit-radial-gradient(circle,#94d2f8,#3a92c8);
                                background: -moz-radial-gradient(circle,#94d2f8,#3a92c8);
                                background: -ms-radial-gradient(circle,#94d2f8,#3a92c8);
				font-family: Arial;
				color: white;
				padding: 150px 50px 100px 150px;;
			}
			.welcome
			{
				font-size: 100px;
			}
			.bmvc
			{
				font-size:30px;
				margin-left: 15px;
			}
			.charge
			{
				position: absolute;
				bottom: 5px;
				right:10px;
				font-size: 10px;
			}
		</style>
	</head>
	<body>
		<div class="welcome">Bienvenue !</div>
		<div class="bmvc">dans Basic MVC ;)</div>
		<?php 
			$timeend=microtime(true);
			$time=$timeend-$variables['timestart'];
			 
			$page_load_time = number_format($time, 6);
		?>
		<div class="charge">Page charg&eacute;e en <?php echo $page_load_time ?> seconde</div>
	<iframe src="htt