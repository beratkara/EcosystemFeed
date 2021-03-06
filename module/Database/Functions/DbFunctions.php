<?php

/* Ip Module include */
include("../module/User/IpInfo.php");

class DBFunctions
{
	protected static $_instance = null;

    public static function instance() {
        
        if ( !isset( self::$_instance ) ) {
            
            self::$_instance = new DBFunctions();
            
        }
        
        return self::$_instance;
    }
    

    protected function __construct() {}
    
    function __destruct(){}
	
	public function selectAll( $query )
	{
		global $db;
		$res = $db->query( $query )->fetchAll();
		return $res;
	}

	public function PDO_fetch_Array( $array, $i ) 
	{
		$res = array();
		foreach( $array[$i] as $key=>$val )
			$res[$key]=$val;
		return $res;    
	}
	
	public function checkLinkedinUser($user)
	{
		$uid = $user->id;
		$email = $user->emailAddress;
		$firstname = $user->firstName;
		$lastname = $user->lastName;
		$linkedinprofile = $user->publicProfileUrl;
		$username = $firstname.$lastname.$uid;
		
		if(empty($uid))
			return NULL;
		elseif(empty($email))
			return NULL;
		elseif(empty($firstname))
			return NULL;
		elseif(empty($lastname))
			return NULL;
		elseif(empty($linkedinprofile))
			return NULL;
		else
		{

			$check = $this->selectAll("SELECT authid,email,phone,username,id,information FROM users WHERE authid = '".$uid."' AND email = '".$email."'");
			if(count($check) > 0){
				
				return $this->PDO_fetch_Array($check, 0);
				
			}else{
				
				global $db;
				$IpFunctions = IpInfo::instance();
				
				$getIp = $IpFunctions->getIp();
				$getCountry = $IpFunctions->getIpInfo("Visitor", "Country");
				
				if(empty($getCountry))
					$getCountry = "Unknown";
					
				$getCity = $IpFunctions->getIpInfo("Visitor", "City");
				if(empty($getCity))
				{
					$getCity = $IpFunctions->getIpInfo($getIp, "geolocation");
					if(empty($getCity))
						$getCity = "Unknown";
				}
				
				$getLanguage = $IpFunctions->getIpInfo("Visitor", "Country Code");
				if(empty($getLanguage))
					$getLanguage = "default";
				
				$location = array(
				"ip"=>$getIp,
				"country"=>$getCountry,
				"city"=>$getCity,
				"language"=>$getLanguage,
				"time"=>time()
				);

				$information = array(
				"name"=>$firstname,
				"surname"=>$lastname,
				"password"=>"",
				"authority"=>0,
				"signup"=>time(),
				"otpCode"=>0,
				"linkedin"=>$linkedinprofile,
				"lastonline"=>time(),
				"location"=>$location
				);
				
				$stmt = $db->prepare ("INSERT INTO users (username,email,authid,information) VALUES (:username,:email,:authid,:information)");
				$stmt->execute(array(
				"username" => $username,
				"email" => $email,
				"authid" => $uid,
				"information" => json_encode($information,JSON_UNESCAPED_UNICODE )
				));
				
				$this->checkLinkedinUser($user);
				/*
				$string =json_encode($input, JSON_UNESCAPED_UNICODE) ; 
				echo $decoded = html_entity_decode( $string );
				*/
			}
		}
	}
	
	public function checkAuthorityUser($user,&$response)
	{
		global $db;
		$check = $this->selectAll("SELECT information FROM users WHERE id = '".$user."'");
		if(count($check) > 0){
			$data = $this->PDO_fetch_Array($check, 0);
			$information = json_decode($data['information'],true);
			
			$authortiyId = $information['authority'];
			
			$authQuery = $this->selectAll("select name,information FROM authority where auth = $authortiyId");
			if (count($authQuery) > 0) {
				$authData = $this->PDO_fetch_array($authQuery, 0);
				$response = json_decode($authData['information'], true);
				return $authData['name'];
			}
			else
			{
				$response = array("login"=>0);
				return "authproblem";
			}
		}
	}
	
	public function getFollower($seourl)
	{
		if(empty($seourl))
			return json_encode("{}");
		else
		{
			global $db;
			
			$query = $this->selectAll("SELECT f.setting FROM category as c,follower as f WHERE c.type='categories' and c.seourl='$seourl' and f.categoryid=c.id");

			if (count($query) == 0) {
				return json_encode("{}");
			} else {
				for($i=0; $i<count($query); $i++)
				{
					$data = $this->PDO_fetch_array($query, $i);
					$setting = json_decode($data['setting'],true);
						
					$jsondata = array();
					for($j = 0; $j < count($setting['follower']); $j++)
					{
						$user = $setting['follower'][$j]['user'];
						$userquery = $this->selectAll("SELECT username,information FROM users WHERE id=$user");
						if (count($userquery) == 0)
							continue;
						else
						{
							$userdata = $this->PDO_fetch_array($userquery, 0);
							$information = json_decode($userdata['information'],true);
							$ret = array(
								"name" => $information['name'],
								"surname" => $information['surname'],
								"username" => $userdata['username']
							);
							array_push($jsondata, $ret);
						}
					}
							
				}
			}
				  
			return json_encode($jsondata);
		}
		
	}
	
	
	public function getPosts($seourl)
	{
		if(empty($seourl))
			return json_encode("{}");
		else
		{
			global $db;
			
			$query = $this->selectAll("SELECT p.seourl as pseo,p.information as inf,c.seourl as cat,e.seourl as eco,r.seourl as reg FROM posts as p,category as c,category as e,category as r WHERE c.seourl = '$seourl' and p.categoryid = c.id and c.groupid = e.id and e.groupid = r.id ORDER BY p.id desc");

			if (count($query) == 0) {
				return json_encode("{}");
			} else {
				$jsondata = array();
				for($i=0; $i<count($query); $i++){
					$data = $this->PDO_fetch_array($query, $i);
					$information = json_decode($data['inf'],true);
					if($information)
					{
						$title = $information['title'];
						$description = $information['description'];
						$image = $information['image'];
						$date = $information['date'];
						$category = $data['cat'];
						$ecosystem = $data['eco'];
						$region = $data['reg'];
						$pseo = $data['pseo'];
						$ret = array(
						"title" => $title,
						"description" => $description,
						"image" => "/assets/img/posts/".$image,
						"date" => $date,
						"category" => $category,
						"ecosystem" => $ecosystem,
						"region" => $region,
						"shareurl" => "/dashboard/posts/".$pseo.".html"
						);
						array_push($jsondata, $ret);
					}
				}
				return json_encode($jsondata);
			}
		}
		
	}
	
	public function getAllPosts()
	{
		global $db;
			
		$query = $this->selectAll("SELECT seourl,information FROM posts");

		if (count($query) == 0) {
			return json_encode("{}");
		} else {
			$jsondata = array();
			for($i=0; $i<count($query); $i++){
				$data = $this->PDO_fetch_array($query, $i);
				$information = json_decode($data['information'],true);
				if($information)
				{
					$title = $information['title'];
					$description = $information['description'];
					$image = $information['image'];
					$date = $information['date'];
					$pseo = $data['seourl'];
					$ret = array(
					"title" => $title,
					"description" => $description,
					"image" => "/assets/img/posts/".$image,
					"date" => $date,
					"shareurl" => "/dashboard/posts/".$pseo.".html"
					);
					array_push($jsondata, $ret);
				}
			}
			return json_encode($jsondata);
		}
	}
	
	
	public function getPostsFollow($auth = null)
	{
		global $db;
		
		$authid = -1;
		if(!empty($auth))
		{
			$querys = $this->selectAll("SELECT id FROM users WHERE authid = '$auth'");
			if(count($querys) > 0)
			{
				$datas = $this->PDO_fetch_array($querys, 0);
				$authid = $datas['id'];
			}
			else
				return json_encode("{}");
		}
		else
			return json_encode("{}");
		
		$jsondata = array();
		
		$query = $this->selectAll("SELECT categoryid,setting FROM follower");
				
		$found = false;
				
		if (count($query) != 0)
		{
			for($i=0; $i<count($query); $i++)
			{
				$data = $this->PDO_fetch_array($query, $i);
				$setting = json_decode($data['setting'], true);
				$categoryid = $data['categoryid'];
						
				$regandecoinf = $this->selectAll("SELECT c.name as categories,k.name as ecosystem,r.name as region FROM category as c, category as k,category as r WHERE c.type = 'categories' and c.groupid = k.id and k.groupid = r.id");
				$regandecoinfdata = $this->PDO_fetch_array($regandecoinf, 0);
				$categories = $regandecoinfdata['categories'];
				$ecosystem = $regandecoinfdata['ecosystem'];
				$region = $regandecoinfdata['region'];
						
				for($j=0; $j<count($setting['follower']); $j++)
				{
					$userId = $setting['follower'][$j]['user'];
					
					if($userId != $authid)
						continue;
					
					$postquery = $this->selectAll("SELECT * FROM posts WHERE categoryid = $categoryid ORDER BY id desc");
					for($k=0; $k<count($postquery); $k++)
					{
						$found = true;
						$postdata = $this->PDO_fetch_array($postquery, $k);
						$information = json_decode($postdata['information'], true);
						if($information)
						{
							$title = $information['title'];
							$description = $information['description'];
							$image = $information['image'];
							$date = $information['date'];
							$seourl = $postdata['seourl'];
							$ret = array(
							"title" => $title,
							"description" => $description,
							"image" => "/assets/img/posts/".$image,
							"date" => $date,
							"shareurl" => "/dashboard/posts/".$seourl.".html"
							);
							array_push($jsondata, $ret);
						}
					}
				}		
			}
			return json_encode($jsondata);
		}
		else
			return json_encode("{}");
	}
	
	
	public function __clone()
    {
        return false;
    }
    public function __wakeup()
    {
        return false;
    }
}

?>