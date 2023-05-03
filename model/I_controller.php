<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of I_controller
 *
 * @author nkunkuma
 */
class I_controller {
    var $bd = '';
    var $connection = '';
    
    public function __construct() {
        date_default_timezone_set('Africa/Douala');
        include 'config.php';
    }
    
    public function load($type, $fichier, $variables = null)
    {
        include 'config.php';
        $chemin = $config['url'];
        if(is_dir($type))
		{
			include_once $type.'/'.$fichier.'.php';
		}
    }
	
    public function load_connect()
    {
        include 'config.php';
		$mysqli = @new mysqli($config['host'], $config['user'], $config['pwd']);
        $this->connection = $mysqli;
        if ($this->connection->connect_error) {return 0;}
        else {return 1;}
    }
	
	public function load_db()
    {
        include 'config.php';
		$mysqli = @new mysqli($config['host'], $config['user'], $config['pwd'], $config['db']);
        $this->connection = $mysqli;
        if ($this->connection->connect_error) {return 0;}
        else {return 1;}
    }
    
    /* Gestion de la BD */
	/* Execution d'une requette */
	 public function query($query)
    {
        $this->load_connect();
        if($this->connection->query($query)){ return 1;}
        else {return 0;}
    }
	
	 public function db_query($query)
    {
        $this->load_db();
        if($this->connection->query($query)){ return 1;}
        else {return 0;}
    }
	
	 public function db_query_return($query)
    {
        $data = array();
		$data['retour'] = 0;
        if($this->load_db())
        {
			$execution    = $this->connection->query($query);
			$i            = 0;
			
			while ($donnee = $execution->fetch_assoc())
			{
				$data['retour']			= 1;
				$data['donnees'][$i] 	= $donnee;
				$i++;
			}
		}
        return $data;
    }
	
    /* insertion */
    public function insert($table, $champ, $donnees)
    {
        $this->load_db();
        $requete = "INSERT INTO ".$table.$champ." VALUES".$donnees." ;";
		if($this->connection->query($requete)){ return 1;}
        else {return 0;}
    }
    
    /* mise à jour */
    public function update($table, $contrainte, $champ, $donnees)
    {
        $this->load_db();
        $requete = "UPDATE ".$table." SET ".$champ." = '".$donnees."' WHERE ".$contrainte." ;";
        if($this->connection->query($requete)){ return 1;}
        else {return 0;}
    }
    
    
    /* selction d'un champ d'une table */
    public function select($table, $champ)
    {
        $data = array();
        $this->load_bd();
        
        $requete      = "SELECT * FROM ".$table.";";
        $execution    = mysqli_query($this->connection, $requete);
        while ($donnee = mysqli_fetch_assoc($execution))
        {
            $data[$donnee[$champ]] = $donnee;
        }
        return $data;
    }
    
    /* selction de toute une table */
    public function select_all($table)
    {
        $data = array();
		$data['retour'] = 0;
        if($this->load_db())
        {
			$requete      = "SELECT * FROM ".$table.";";
			$execution    = $this->connection->query($requete);
			$i            = 0;
			
			while ($donnee = $execution->fetch_assoc())
			{
				$data['retour']			= 1;
				$data['donnees'][$i] 	= $donnee;
				$i++;
			}
		}
        return $data;
    }
	
	public function select_count_all($table)
    {
        $data = array();
		$data['retour'] = 0;
        if($this->load_db())
        {
			$requete      = "SELECT COUNT(*) FROM ".$table.";";
			$execution    = $this->connection->query($requete);
			$i            = 0;
			
			while ($donnee = $execution->fetch_assoc())
			{
				$data['retour']			= 1;
				$data['donnees'][$i] 	= $donnee;
				$i++;
			}
		}
        return $data;
    }
    
    /* selction dans une table avec contrainte */
    public function select_contraint($table, $contrainte)
    {
        $data = array();
		$data['retour'] = 0;
		if($this->load_db())
		{
			
			$requete      = "SELECT * FROM ".$table." ".$contrainte.";";
			$execution    = $this->connection->query($requete);
			$i            = 0;
			
			$data['requete']	= $requete;
			
			while ($donnee = $execution->fetch_assoc())
			{
				$data['retour']			= 1;
				$data['donnees'][$i] 	= $donnee;
				$i++;
			}
		}
        return $data;
    }
	
	/* selction dans une table avec contrainte et affichage choisi */
    public function select_choice_contraint($table, $choice, $contrainte)
    {
        $data = array();
		$data['retour'] = 0;
		if($this->load_db())
		{
			
			$requete      = "SELECT ".$choice." FROM ".$table." ".$contrainte.";";
			$execution    = $this->connection->query($requete);
			$i            = 0;
			
			while ($donnee = $execution->fetch_assoc())
			{
				$data['retour']			= 1;
				$data['donnees'][$i] 	= $donnee;
				$i++;
			}
		}
        return $data;
    }
	
	public function select_count_contraint($table, $contrainte)
    {
        $data = array();
        $data['retour'] = 0;
		if($this->load_db())
		{
			
			$requete      = "SELECT COUNT(*) FROM ".$table." ".$contrainte.";";
			$execution    = $this->connection->query($requete);
			$i            = 0;
			
			while ($donnee = $execution->fetch_assoc())
			{
				$data['retour']			= 1;
				$data['donnees'][$i] 	= $donnee;
				$i++;
			}
		}
        return $data;
    }
	
	public function date_in_db($date)
	{
		$aide = explode("-", $date);
		$date2 = $aide[2]."-".$aide[1]."-".$aide[0];
		return $date2;
	}
	
	public function date_out_db($date)
	{
		$aide = explode("-", $date);
		$date2 = $aide[2]."-".$aide[1]."-".$aide[0];
		return $date2;
	}
	
	public function ImConnected()
	{
		include 'config.php';
		@session_start();
		
		$retour['etat'] = false;
		$retour['active'] = false;
		
		if((isset($_SESSION['infos_user'])) && ($_SESSION['godeals'] == 1))
		{
			$infos_user = $_SESSION['infos_user'];
			
			$SQL = "WHERE login='".$infos_user['login']."' AND mdp='".$infos_user['mdp']."'";
			$donnees = $this->select_contraint('utilisateurs', $SQL);
			if($donnees['retour'])
			{
				$retour['infos-user'] 		= $donnees['donnees'][0];
				$retour['etat'] 			= true;
				$retour['details-user'] 	= array();
				if($retour['infos-user']['active'] == '1')
				{
					$retour['active']	= true;
				}
				
				$SQL2 = "WHERE idUtilisateur='".$infos_user['id']."'";
				$donnees2 = $this->select_contraint('utilisateurs_infos', $SQL2);
				if($donnees2['retour'])
				{
					$retour['details-user'] 	= $donnees2['donnees'][0];
				}
			}
			else
			{
				session_destroy();
				$_SESSION 				= array();
				$retour['infos-user'] 	= array();
				$retour['etat'] 		= false;
				$retour['active'] 		= false;
			}
		}
		else
		{
			session_destroy();
			$_SESSION 					= array();
			$retour['infos-user'] 		= array();
			$retour['etat'] 			= false;
			$retour['active'] 			= false;
		}
		
		return $retour;
	}
	
	public function ImConnectedByID($id)
	{
		include 'config.php';
		@session_start();
		
		$retour['etat'] = false;
		$retour['active'] = false;
		
		// Compte utilisateur
		$SQL = "WHERE id='".$id."'";
		$donnees = $this->select_contraint('utilisateurs', $SQL);
		if($donnees['retour'])
		{
			$retour['infos-user'] 		= $donnees['donnees'][0];
			$retour['etat'] 			= true;
			$retour['details-user'] 	= array();
			if($retour['infos-user']['active'] == '1')
			{
				$retour['active']	= true;
			}
			
			$SQL2 = "WHERE idUtilisateur='".$infos_user['id']."'";
			$donnees2 = $this->select_contraint('utilisateurs_infos', $SQL2);
			if($donnees2['retour'])
			{
				$retour['details-user'] 	= $donnees2['donnees'][0];
			}
		}
		
		$user 				= $retour;
		
		return $retour;
	}
	
	public function ImConnectedMobile($id, $user, $pwd)
	{
		include 'config.php';
		@session_start();
		
		$retour		= "";
		
		$SQL = "WHERE id='".$id."' AND mdp='".$pwd."'";
		$donnees = $this->select_contraint('utilisateurs', $SQL);
		if($donnees['retour'])
		{
			$infosuser 	= $donnees['donnees'][0];
			
			$chiffrelettre = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z','A','B','C','D','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','0','1','2','3','4','5','6','7','8','9');
			
			$code = "";
			for($i=0; $i<15; $i++)
			{
				$code .= $chiffrelettre[rand(0,62)];
			}
			
			$codeJour						= date("YmdHis");
			$codeConnexion					= $code."_".$codeJour; 
			
			$retour		.= $infosuser["id"].":::".$codeConnexion.":::".$infosuser["login"].":::".$infosuser["email"].":::".$infosuser["mdp"].":::".$infosuser["pro"].":::".$infosuser["datecreation"].":::".$infosuser["derniereConnexion"];
			
			$SQL2 = "WHERE idUtilisateur='".$id."'";
			$donnees2 = $this->select_contraint('utilisateurs_infos', $SQL2);
			if($donnees2['retour'])
			{
				$detailsuser = $donnees2['donnees'][0];
				
				$retour		.= ":::".$detailsuser["noms"].":::".$detailsuser["prenoms"].":::".$detailsuser["pseudo"].":::".$config['url']."/".$detailsuser["photo"].":::".$detailsuser["siteweb"].":::".$detailsuser["phone"].":::".$detailsuser["adresse"];
			}
			
		}
		else
		{
			$retour		= "Aucune info !";
		}
		
		return $retour;
	}
	
	/*
	public function ImConnectedPRO()
	{
		include 'config.php';
		@session_start();
		
		$retour['etat'] = false;
		$retour['active'] = false;
		
		if((isset($_SESSION['infos_user'])) && ($_SESSION['godeals'] == 1))
		{
			$infos_user = $_SESSION['infos_user'];
			
			$SQL = "WHERE login='".$infos_user['login']."' AND mdp='".$infos_user['mdp']."' AND pro='1'";
			$donnees = $this->select_contraint('utilisateurs', $SQL);
			if($donnees['retour'])
			{
				$retour['infos-user'] = $donnees['donnees'][0];
				$retour['etat'] = true;
				if($retour['infos-user']['active'] == '1')
				{
					$retour['active'] = true;
					$retour['pro'] = true;
				}
				
			}
			else
			{
				session_destroy();
				$_SESSION = array();
				$retour['infos-user'] = array();
				$retour['etat'] = false;
				$retour['active'] = false;
				$retour['pro'] = false;
			}
		}
		else
		{
			session_destroy();
			$_SESSION = array();
			$retour['infos-user'] = array();
			$retour['etat'] = false;
			$retour['active'] = false;
			$retour['pro'] = false;
		}
		
		return $retour;
	}
	*/
	
	public function GenCode($nombre)
	{
		$chiffrelettre = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z','A','B','C','D','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','0','1','2','3','4','5','6','7','8','9');
		
		$code = "";
		for($i=0; $i<$nombre; $i++)
		{
			$code .= $chiffrelettre[rand(0,62)];
		}
		
		return $code;
	}
	
	public function EnvoiMail($from, $to, $titre, $contenu)
	{
		include 'config.php';
		@session_start();
		
		$to  		= $to; // notez la virgule
		$subject 	= $titre;
		$message 	= '
				<br/>'.$contenu.'<br/>
		';
		
		$message 	= '
		
		<!DOCTYPE html>
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<style>
					body
					{
						margin: 0 auto;
					}
				</style>
			</head>
		<body>
			<div style="background: #ffffff; width: 100%; height: 70px; ">
				<div style="width: 100%; max-width: 500px; height: 100px; text-align: center; margin: 0 auto; padding-top: 20px; padding-left: 30px; padding-right: 30px;">
					<img src="'.$config['url'].'/view/godeals/assets/themes/godeals-content/uploads/2020/07/logo.png" style="width: 75px" />
				</div>
			</div> 
			<div style="background: #efefef; width: 100%; max-width: 500px; min-height: 400px; text-align: left; margin: 0 auto; padding-top: 30px; padding-left: 30px; padding-right: 30px; font-size: 14px; color: #636363; font-family: Arial">
				'.$message.'
			</div>
			<div style="background: #030303; width: 100%; max-width: 500px; height: 60px; text-align: left; margin: 0 auto; padding-top: 20px; padding-left: 30px; padding-right: 30px; font-size: 12px; color: #fefefe; font-family: Arial">
				&copy; 2021 <a href="https://goDeals.fr/" style="color: #ffffff"><b>goDeals</b></a>, Tous droits réservés.
			</div>
		</body>
		</html>
		
		';
		
		// Pour envoyer un mail HTML, l'en-tête Content-type doit être défini
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';

		// En-têtes additionnels
		$headers[] = 'To: <'.$to.'>';
		$headers[] = 'From: goDeals <'.$from.'>';

		// Envoi
		mail($to, $subject, $message, implode("\r\n", $headers));
	}
	
	/* Gestion des Annonces */
	public function Have_this_favori($id_annonce, $id_user)
	{
		include 'config.php';
		
		$SQL = "WHERE id_annonce = '".$id_annonce."' AND id_user = '".$id_user."'";
		$donnees = $this->select_contraint('favoris', $SQL);
		if($donnees['retour'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	
	/* Gestion des badges de notification */
	
	public function HasMessagesDe($id_user, $id_user_from)
	{
		include 'config.php';
		
		$SQL = "WHERE idUtilisateurTo = '".$id_user."' AND idUtilisateurFrom = '".$id_user_from."' AND vue = '0'";
		$donnees = $this->select_contraint('Messages', $SQL);
		if($donnees['retour'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	public function HasBrouillon($id_user)
	{
		include 'config.php';
		
		$SQL = "WHERE idUtilisateur = '".$id_user."'";
		$donnees = $this->select_contraint('annonces_brouillons', $SQL);
		if($donnees['retour'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	public function HasMessages($id_user)
	{
		include 'config.php';
		
		$SQL = "WHERE idUtilisateurTo = '".$id_user."' AND vue = '0'";
		$donnees = $this->select_contraint('Messages', $SQL);
		if($donnees['retour'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	public function HasNotifications($id_user)
	{
		include 'config.php';
		
		$retour = false;
		
		if($this->HasBrouillon($id_user)) $retour = true;
		if($this->HasMessages($id_user)) $retour = true;
			
		return $retour;
	}
	
	public function html2text($Document) {
		$Rules = array ('<',
						'>'
				 );
		$Replace = array ('=tag_l=',
						  '=tag_r='
					);
		return str_replace($Rules, $Replace, $Document);
	}
	
	public function text2html($Document) {
		$Rules = array ('<',
						'>'
				 );
		$Replace = array ('=tag_l=',
						  '=tag_r='
					);
		return str_replace($Replace, $Rules, $Document);
	}
	
	public function dateSince($date)
	{
		$date1 = date("Y-m-d H:i:s");
		$date2 = $date;
		$diff = abs(strtotime($date1) - strtotime($date2));
		
		$retour = array();
		
		$tmp = $diff;
		$retour['difference'] = $tmp;
		
		$retour['date1'] = $date1;
		
		$retour['date2'] = $date2;
		
		$retour['seconde'] = $tmp % 60;
	 
		$tmp = floor( ($tmp - $retour['second']) /60 );
		$retour['minute'] = $tmp % 60;
	 
		$tmp = floor( ($tmp - $retour['minute'])/60 );
		$retour['heure'] = $tmp % 24;
	 
		$tmp = floor( ($tmp - $retour['hour'])  /24 );
		$retour['jour'] = $tmp;
		
		if(($retour['jour'] >= 10) )
		{
			$retour['le_temps'] = "Le ".date("d M y", strtotime($date2));//date("d M Y", $date2)
		}
		
		if(($retour['jour'] > 3) && ($retour['jour'] < 10) )
		{
			$retour['le_temps'] = "Il y a ". $retour['jour'] ." Jrs";
		}
		
		if($retour['jour'] == 3)
		{
			$retour['le_temps'] = "Avant-Hier";
		}
		
		if($retour['jour'] == 2)
		{
			$retour['le_temps'] = "Hier";
		}
		
		if($retour['jour'] < 1)
		{
			$retour['le_temps'] = "Il y a ". $retour['heure'] ."H";
		}
		
		if($retour['heure'] < 1)
		{
			$retour['le_temps'] = "Il y a ". $retour['minute'] ."Min";
		}
		
		if($retour['minute'] < 1)
		{
			$retour['le_temps'] = "Il y a -1Min";
		}
	 
		return $retour;
	}
	
	public function date_all_out($date)
	{
		$mois = array("Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "juillet", "Août", "Septembre", "Octobre", "Novembre", "Decembre");
		$separe = explode(" ", $date);
		$aide = explode("-", $separe[0]);
		$date2 = $aide[2]." ".$mois[($aide[1] - 1)]." ".$aide[0];
		return $date2;
	}
	
	
}

?>
