<?php

	require_once("config.php");

	/** RestServer that serves up Currency Conversion as JSON */
	class RestServer	
	{
		// Put these in the "Allow" response header:
		const SUPPORTED_METHODS="GET";
		//const SUPPORTED_METHODS="GET, POST, PUT, DELETE"; // in future, we'll support GET,POST,PUT,DELETE

		const CONTENT_TYPE_JSON="application/json";

		const MAX_REQUESTS_PER_MINUTE=100;


		const HTTP_BAD_REQUEST=400;
		/* 400: Bad Request: The request cannot be fulfilled due to bad syntax */
		/* In our case, we use it when they attempt a "login" without supplying both a "username" and "password" */
		
		const HTTP_UNAUTHORIZED=401;
		/* 401: Unauthorized */
		/* (in our case REST client didn't supply the Authorization: <authorization_token> in the header, or supplied an invalid authorization token) */

		const HTTP_METHOD_NOT_ALLOWED=405;
		/* 405 Method Not Allowed. e.g., GET,POST,DELETE */

		const HTTP_INTERNAL_SERVER_ERROR=500;
		/* 500: Internal Server Error: A generic error message, given when no more specific message is suitable. */

		const HTTP_SERVICE_UNAVAILABLE=503;
		/* 503: Service Unavailable: The server is currently unavailable (because it is overloaded or down for maintenance).[2] Generally, this is a temporary state. */
		/* In our case, it is because MAX_REQUESTS_PER_MINUTE have been exceeded for this login. */

		protected static $_debug = 0;

		public static function wouldLog( $level=1)
		{
			if( $level <= self::$_debug )
			{
				return true;
			}
		}

		public static function logIt( $msg , $level=1)
		{
			if( self::wouldLog($level) )
			{
				error_log( $msg );
			}
		}

		protected static $_dbh = null;

		/** Will do a "lazy init" of DB handle and return the DB handle.
		 * Connects to database as per params found in Config class and return the DB handle.
		 * Will re-use DB handle if already created.
		*/
		public static function get_dbh()
		{
			if(!self::$_dbh)
			{
					self::$_dbh = new PDO(Config::DB_DSN, Config::DB_USERNAME, Config::DB_PASSWORD);

					self::$_dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, TRUE);
					self::$_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}

			return self::$_dbh;
		}/* get_dbh() */

		public static function handleRequest()
		{
			try
			{
				if( self::wouldLog(4) )
				{
					self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): Hey, Joe: \$_SERVER=" . self::var_dump_str($_SERVER) );

					if( function_exists( 'apache_request_headers' ) )
					{
						self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): Hey, Joe: apache_request_headers()=" . self::var_dump_str( apache_request_headers() ) );
					}
					else
					{
						self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): Sorry, Joe: apache_request_headers() function does not exist." );
					}
				}
	
				$method = $_SERVER['REQUEST_METHOD'];	
	
				self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): method='$method'...");
		
				switch($method)
				{
					case 'GET':
						self::handleGet();
						break;
					default:
					    header('Allow: ' . self::SUPPORTED_METHODS, true, self::HTTP_METHOD_NOT_ALLOWED);
						break;
				}
			}
			catch(Exception $e) 
			{
				error_log(  "ERROR: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): Exception while handling request: '" . $e->getMessage() . "'");
			    header('Allow: ' . self::SUPPORTED_METHODS, true, self::HTTP_INTERNAL_SERVER_ERROR);
				return;
			}

		}/* handleRequest() */


		public static function handleGet()
		{
			header("Content-Type: " . self::CONTENT_TYPE_JSON);

  			// Something like: Monday August 15th, 2005  3:12:46 PM EDT America/New York GMT-4:00 
			$now = date('l, F jS, Y h:i:s A T e \G\M\TP');

			$verb = @$_GET["verb"];

			if(strcasecmp($verb, "login")==0)
			{
					self::handleLogin($_GET, $now);
			}
			else
			{
					$session_id_out = "";

					$return_code = self::checkAuthorization(true, $session_id_out);
					self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): checkAuthorization(): return_code=$return_code, \$session_id_out='$session_id_out'...");

					if($return_code != 0)
					{
					    header('Allow: ' . self::SUPPORTED_METHODS, true, $return_code);
						return;
					}

					self::getCurrencies($now);

					self::logAction("get_currencies", $session_id_out);
			}
		}/* handleGet() */

		/** Create a "token" consisting of random numbers and letters of specified length. */
		public static function createAuthorizationToken($length=10)
		{
				$pool=array(
						array("a","b","c","d","e","f","g","h","i","j","k","m","n","p","q","r","s","t","u","v","w","x","y","z")
						,array("0","1","2","3","4","5","6","7","8","9")
				);

				$output = "";

				for($i=0; $i<$length; $i++)
				{
					$choice1 = rand(0, count($pool)-1);
					
					$choice2 = rand(0, count($pool[$choice1])-1);

					$output .= $pool[$choice1][$choice2];
				}

				return $output;
		}/* createAuthorizationToken($length=10) */


		// Ideally, we use apache_request_headers() to grab the authorization token
		// from the Authorization: part of the HTTP header, e.g.
		//   Authorization: 00170tu3nw27e12qv
		//	
		// But if we're running PHP as fastCGI, apache_request_headers() does 
		// not exist, and instead we'll get the client to "smuggle" the authorization
		// token into the Accept: part of the header which we can access via
		// the $_SERVER[] superglobal array, e.g., 
		//
		//	["HTTP_ACCEPT"]=> string(96)
		//	 "application/json, text/javascript, */*; q=0.01, application/json, authorization/00170tu3nw27e12qv"
		//	
		public static function getAuthorizationTokenFromHttpHeader()
		{
			$auth_token = "";

			if( function_exists( 'apache_request_headers' ) )
			{
				$headers = apache_request_headers();

				if( array_key_exists("Authorization", $headers) )
				{
					$auth_token = @$headers["Authorization"];	
				}
			}

			if( strlen($auth_token) > 0 )
			{
				return $auth_token;
			}

			// See if authorization was "smuggled" in via the Accept header...
			if( array_key_exists("HTTP_ACCEPT", $_SERVER ) )
			{
				$accept = $_SERVER["HTTP_ACCEPT"];

				$accept_array = explode("," , $accept );
				foreach ( $accept_array as $key => $value )
				{
					$accept_array[$key] = trim($accept_array[$key]);

					list($left, $right) = explode("/" , $accept_array[$key] );

					$left=trim($left);
					$right=trim($right);

					if( strcasecmp($left, "authorization") == 0 )
					{
						$auth_token = $right;
						break;
					}
				}
			}

			return $auth_token;

		}/* public static function getAuthorizationTokenFromHttpHeader() */

		public static function checkAuthorization($checkLimitToo, &$session_id_out)
		{
			$session_id_out = self::getAuthorizationtokenFromHttpHeader();

			if(strlen($session_id_out) == 0)
			{
				return self::HTTP_UNAUTHORIZED;
			}

			$sql = "SELECT count(*) AS num_logins FROM sessions WHERE session_id='$session_id_out' AND action='login'";

			self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): Executing sql='" . $sql . "'");

			$dbh = self::get_dbh();	

			$result = $dbh->query($sql);
			$result->setFetchMode( PDO::FETCH_NAMED ); // Return result set as associative array.

			$count=-1;
			$num_logins=-1;
			foreach( $result as $row )
			{
				$count++;
				self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): row[$count]=" . self::var_dump_str($row), 5 );
				$num_logins = $row["num_logins"]; 
			}
			self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): num_logins = $num_logins");

			if($num_logins < 1)
			{
				/* The authorization token is not valid -- not found in sessions. */
				return self::HTTP_UNAUTHORIZED;
			}

			if(!$checkLimitToo)
			{
				return 0;
			}

			$num_requests = self::getNumRequestsInLastMinute($session_id_out);

			if($num_requests > self::MAX_REQUESTS_PER_MINUTE)
			{
				return self::HTTP_SERVICE_UNAVAILABLE;
			}

			return 0;

		}/* public static function checkAuthorization() */


		public static function getNumRequestsInLastMinute($session_id)
		{
			$sql = "SELECT count(*) AS num_gets FROM sessions" . PHP_EOL .
				   "WHERE session_id='$session_id'" . PHP_EOL .
				   "AND action='get_currencies'" . PHP_EOL .
				   "AND last_modified >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)";


			$dbh = self::get_dbh();	
			$result = $dbh->query($sql);

			$result->setFetchMode( PDO::FETCH_NAMED ); // Return result set as associative array.

			$count=-1;
			$num_gets = -1;
			foreach( $result as $row )
			{
				$count++;
				self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): row[$count]=" . self::var_dump_str($row) , 5);
				$num_gets = $row["num_gets"]; 
			}
			self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): session_id=$session_id, num_gets=$num_gets");

			return $num_gets;

		}/* getNumRequests() */

		/** Logs an "action" to the sessions table...for example "login"
		 * or "get_currencies".  Will use "get_currencies" entries to
		 * enforce MAX_REQUEST_PER_MINUTE.
		*/
		public static function logAction($action, $session_id)
		{
			$sql = "INSERT INTO sessions ( session_id, action )" . PHP_EOL . 
				   "VALUES ( '$session_id', '$action' )"
					;

			self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): Executing sql='" . $sql . "'");

			$dbh = self::get_dbh();	

			$dbh->exec($sql);

		}/* public static logAction() */


		/** Reads "username" and "password" and returns an "authorization_token" (or session_id) that the client 
		 * must use when requesting currency exchange rates.     
		 */
		public static function handleLogin($_GET, $now)
		{
			if(! array_key_exists("username", $_GET) || ! array_key_exists("password", $_GET) )
			{
				self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): Sending HTTP 400 - bad request - username and password not supplied...");

			    header('Allow: ' . self::SUPPORTED_METHODS, true, self::HTTP_BAD_REQUEST);

				return;
			}

			$username = $_GET["username"];
			$password= $_GET["password"];

			$session_id= self::createAuthorizationToken(17);	

			self::logAction("login", $session_id);

			echo("{\"now\": \"$now\"," . PHP_EOL .
				 " \"authorization_token\": \"$session_id\"}" . PHP_EOL);

		}/* public static function handleLogin($_GET, $now) */


		public static function getCurrencies($now)
		{

			$currencies = @$_GET["currencies"];
			$currencies_for_sql = self::makeSQLList($currencies);

			$as_of= @$_GET["as_of"];

			$sql = "SELECT currency, conversion_rate, as_of_datetime FROM exchange_rate" . PHP_EOL .
				   "WHERE deleted = 0" . PHP_EOL;
	
			if(strlen($currencies_for_sql) > 0)
			{
				$sql .= "AND currency in (" . $currencies_for_sql . ")" . PHP_EOL;	
			}

			if(strlen($as_of) > 0)
			{
				$sql .= "AND as_of_datetime = '" . $as_of . "'";
			}
			else
			{
				$sql .= "AND as_of_datetime = (SELECT max(as_of_datetime) from exchange_rate)";
			}

			self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): Executing sql='" . $sql . "'");

			$dbh = self::get_dbh();	

			$result = $dbh->query($sql);

			$result->setFetchMode( PDO::FETCH_NAMED ); // Return result set as associative array.

			echo("{\"now\": \"$now\"," . PHP_EOL .
				 " \"exchange_rates\":" . PHP_EOL);

			$count=-1;
			echo " [" . PHP_EOL;
			foreach( $result as $row )
			{
				$count++;

				self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): row[$count]=" . self::var_dump_str($row), 5 );

				if($count > 0)
				{
					echo ",";
				}
				$row_json = json_encode($row);

				self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): row_json[$count]=" . $row_json, 5 );

				echo $row_json . PHP_EOL;
			}
			echo "]";
			echo "}";
		}/* public static function getCurrencies($_GET, $now) */


		/** Trim whitespace from tokens, and single-quote them into output string suitable for use in
		* SQL "in" list, e.g.., "...AND currency in ('USD','EUR')..."
		*
		* Will return an empty string if no tokens are found.
		*/
		public static function makeSQLList($input)
		{
			$arr_out = explode(",",$input);

			self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): input='$input', arr_out=" . self::var_dump_str($arr_out) );
			self::logIt(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): count($arr_out) = " . count($arr_out) );

			if(count($arr_out) == 0)
			{
				return "";
			}

			$output = "";

			// Trim whitespace from tokens, and single-quote them into output string...
			$count = 0;
			foreach( $arr_out as $key => $value )
			{
				//error_log(  "TRACE: " . __FILE__ . ":" . __LINE__ . ":" . __METHOD__ . "(): arr_out[$key] = '" . $arr_out[$key] . "'" );

				$arr_out[$key] = trim($arr_out[$key]);

				/* field is nothing but white space. */
				if(strlen($arr_out[$key]) == 0)
				{
					continue;
				}

				$count++;

				if($count > 1)
				{
					$output .= ",";
				}

				$output .= "'" . $arr_out[$key] . "'";
			}

			return $output;
		}/* public static function makeSQLList($input) */


		/** Prints elements of an array...
		*/
		public static function array_print($array, $name)
		{
			foreach( $array as $key => $value )
			{
				print($name . "['$key'] = '$value'" . PHP_EOL);		
			}
		}/* public static function array_print($array, $name) */


		/** Captures output from var_dump() and returns it as a string
		*/
    	public static function var_dump_str($var)
    	{
        	ob_start();
        	var_dump($var);
        	$contents = ob_get_contents();
        	ob_end_clean();
        	return $contents;
    	}/* function var_dump_str() */

	}/* RestServer */

?>
