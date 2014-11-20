<?php 
  header("Cache-Control: no-cache, must-revalidate");
  # Date in the past
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Cache-Control" content="no-cache, must-revalidate" />
<meta http-equiv="Expires" content="Mon, 26 Jul 1997 05:00:00 GMT" />
<style>
.response_headers {
	border: 1px dotted green;
}

.ok{
	color: green;
}

.error{
	color: red;
}
</style>
<title> Exchange Rate Client </title>
<script type="text/JavaScript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
<script type="text/JavaScript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
<script type="text/JavaScript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>
</head>
<body>
<!--
<form>
	<input type="button" value="DO EVAL" onClick="doEval();" />&nbsp;<input type="text" id="eval_text" size="60" /><br />
	Log:<br />
    <textarea rows="10" cols="60" id="log"></textarea><br />
</form>
-->
<script type="text/JavaScript">
function logIt(msg, withTimestamp)
{
  if(withTimestamp)
  {
	msg = getDateTimeString(new Date()) + ": " + msg;
  }

  $('#log').val( msg + "\n" + $('#log').val() );
}

function doEval()
{
  var eval_text = $('#eval_text').val();
  //alert("eval_text='" + eval_text + "'");
  var eval_out;
  try
  {
  eval_out = eval(eval_text);
  }
  catch(err)
  {
	eval_out="Exception: '" + err.message + "'";
  }
  //alert("eval_out='" + eval_out + "'");
  logIt(eval_text + " = " + eval_out, true );
}
</script>
<form>
	<input type="button" value="LOGIN TO SERVER" onClick="loginToServer();">&nbsp;&nbsp;&nbsp;username:&nbsp;<input type="text" size="15" id="username" value="johnny5">&nbsp;&nbsp;&nbsp;password:&nbsp;<input type="text" size="15" id="password" value="some_pass"><br />
Authorization Token:&nbsp;<input type="text" size="20" id="authorization_token"></span>&nbsp;<span id="authorization_time"></span><br />

	<hr style="color: gray; background-color: purple; height: 2px;" />

	currency_list(comma-delimited, e.g. "EUR,JPY,GBP,CAD,AMD"):&nbsp;<input type="text" size="60" value="EUR,JPY,GBP,CAD,AMD" id="currency_list" /><br />
	as_of(e.g., "2012-06-20", "2012-06-19", "2012-06-18", "2012-06-15"):&nbsp;<input type="text" size="10" value="2012-06-19" id="as_of" /><br />
	request_type(e.g., GET, POST, PUT, DELETE):&nbsp;<input type="text" value="GET" id="request_type_input" /><br />
	<input type="button" value="GET EXCHANGE RATES" onClick="repeatGetExchangeRates(true);">&nbsp;&nbsp;Repeat&nbsp;<input type="text" id="repeat_n_times" size="5" value="1">&nbsp;time(s)&nbsp;==>&nbsp;count:<span id="repeat_count"></span><br />
</form>
	<hr style="color: red; background-color: red; height: 2px;" />
	Requested At:&nbsp;<span id="requested_at"></span>&nbsp;&nbsp;&nbsp;&nbsp;Request type:&nbsp;<span id="request_type"></span>&nbsp;&nbsp;&nbsp;&nbsp;HTTP Authorization:&nbsp;<span id="request_http_authorization"></span><br />
	Request URL:&nbsp;<span id="request_url"></span><br />
    <!-- Request headers:<div style="border: 1px dotted green;" id="request_headers"><pre></pre></div> -->
	<hr style="color: blue; background-color: blue; height: 2px;" />
	Response Received At:&nbsp;<span id="response_received_at"></span>&nbsp;&nbsp;&nbsp;Response Status Code:&nbsp;<span id="response_status_code"></span>&nbsp;&nbsp;&nbsp;Response Status Text:&nbsp;<span id="response_status_text"></span><br />
	Response table:&nbsp;<span id="response_table"></span><br />
	Response text:&nbsp;<span id="response_text"></span><br />
	Response headers:&nbsp;<span id="response_headers"></span><br />
<script type="text/JavaScript">

function getDateTimeString(date)
{
	var month = (1*date.getMonth())+1;
	return "" + date.getFullYear() + "-" + padStr(month) + "-" + padStr(date.getDate()) + " " + padStr(date.getHours()) + ":" + padStr(date.getMinutes()) + ":" + padStr(date.getSeconds());
}

function padStr(i)
{ 
    return (i < 10) ? "0" + i : "" + i; 
} 

function objectToString(o)
{
	var output="";
	var property;
	for(property in o)
	{
		//alert("property='" + property + "'");
		var rhs="";
		
		try
		{
		  if(o[property] == null)
		  {
		  	rhs = "NULL";	
		  }
		}
		catch(e)
		{
			rhs = "Exception: '" + e.message + "'";
		}

		if(rhs == "")
		{
			var type = typeof o[property];
	
			if (type == "number") { 
				rhs = "" + o[property];
			} 
			else if (type == "string") { 
				rhs = "" + "'" + o[property] + "'";
			} 
			else if (type == "object") { 
				// either array or object 
				rhs = objectToString(o[property]);
			}	
		}

		//alert("rhs='" + rhs + "'");

		output += "" + property + ": " + rhs + "; ";
	}
	return output;
}/* objectToString() */


function clearAll()
{
	clearTop();
	clearBottom();
}

function clearTop()
{
	$('#requested_at').text('');
	$('#request_type').text('');
	$('#request_http_authorization').text('');
	$('#request_url').text('');
	//$('#request_headers').text('');
}

function clearBottom()
{
	$('#response_received_at').text('');
	$('#response_status_code').text('');
	$('#response_status_text').text('');
	$('#response_headers').text('');
	$('#response_text').text('');
	$('#response_table').text('');
}

var jqXHRequest;
function repeatGetExchangeRates(start)
{
	var prev_repeat_count;
	var new_repeat_count;
	if(start)
	{
		prev_repeat_count = 0;
	}
	else
	{
		prev_repeat_count = $('#repeat_count').text();
		prev_repeat_count = 1 * prev_repeat_count;
	}

	new_repeat_count = prev_repeat_count + 1;

	var repeat_n_times = $('#repeat_n_times').val();
	var repeat_n_times = 1 * repeat_n_times;

	//alert("start=" + start + ", prev_repeat_count=" + prev_repeat_count + ", new_repeat_count=" + new_repeat_count + ", repeat_n_times=" + repeat_n_times );

	if( new_repeat_count <= repeat_n_times )
	{
		//alert("one mo' time!");
		$('#repeat_count').text(new_repeat_count);
		getExchangeRates();
	}
	else
	{
		//alert("'tis 'nuff!");
	}
}

function getExchangeRates()	
{
	clearTop();

	var date_requested = new Date();
	//var timestamp = Date.now();    
	var timestamp = date_requested.valueOf();
	$('#requested_at').html( getDateTimeString(date_requested) );

	var my_request_type=$('#request_type_input').val();
	$('#request_type').text( my_request_type );

	var my_authorization_token=$('#authorization_token').val()
	$('#request_http_authorization').text( my_authorization_token );

	// Place unique=<timestamp> at create unique url every time and thus avoid caching 
	var currency_list = $('#currency_list').val();
	var currency_list_encoded = encodeURIComponent(currency_list); 	

	var as_of= $('#as_of').val();
	var as_of_encoded = encodeURIComponent(as_of); 	

	//alert("currency_list='" + currency_list + "', currency_list_encoded='" + currency_list_encoded + "'");
	var my_url = "server.php?currencies=" + currency_list_encoded + "&as_of=" + as_of_encoded + "&unique=" + timestamp;
	$('#request_url').text( my_url );
	//alert("AJAXING: url='" + my_url + "'...");


jqXHRequest = $.ajax({
   type: my_request_type, // GET, POST, PUT, DELETE etc.
   url: my_url, // The actual URL to make the request
   //data: data, // Any data/parameters to send to the server
   //accepts: "application/json", // For "Accept" in HTTP header of request
   beforeSend: function(xmlHttpRequest) {
     xmlHttpRequest.setRequestHeader('Authorization', my_authorization_token );
	 // KLUDGE: In case REST server is running PHP on fastCGI and cannot read
	 // Authorization: from the HTTP headers since apache_request_headers() will not exist,
	 // "smuggle" authorization into HTTP headers as part of the "Accept:" value list, 
	 // which can be accessed via $_SERVER["HTTP_ACCEPT"].
     xmlHttpRequest.setRequestHeader('Accept', "application/json, authorization/" + my_authorization_token);
   },
   dataType: "json",
   success: getExchangeRatesSuccess,
   error: handleError,

 });


}/* function getExchangeRates() */

function getExchangeRatesSuccess( data, textStatus, jqXHR )
{
		var sWho="getExchangeRatesSuccess";
		//alert(sWho + "()...");

		var date_received= new Date();
		$('#response_received_at').html( getDateTimeString(date_received) );
	    //alert("success: status=" + jqXHR.status + ", statusText='" + jqXHR.statusText + "'");
	    $('#response_status_code').html("<span class=\"ok\">" + jqXHR.status + "</span>");
	    $('#response_status_text').html("<span class=\"ok\">" + jqXHR.statusText + "</span>");

	    //alert("getAllResponseHeaders()='" + jqXHR.getAllResponseHeaders() + "'");
		$('#response_headers').html("<pre class=\"response_headers\">" + jqXHR.getAllResponseHeaders() + "</pre>");

		//alert("responseText='" + jqXHR.responseText + "'");
		$('#response_text').html("<pre>" + jqXHR.responseText + "</pre>");
		
		//alert("success: data=" + objectToString(data) );
		var response_table = "";

		// e.g.,
		// {"now": "Friday, June 22nd, 2012 06:28:10 PM",
		//  "exchange_rates":
		//  [
		// {"currency":"AMD","conversion_rate":"405.729600","as_of_datetime":"2012-06-20 00:00:00","deleted":"0","last_modified":"2012-06-20 22:45:55"}
		// ,{"currency":"CAD","conversion_rate":"1.017727","as_of_datetime":"2012-06-20 00:00:00","deleted":"0","last_modified":"2012-06-20 22:45:55"}
		// ,{"currency":"EUR","conversion_rate":"0.787124","as_of_datetime":"2012-06-20 00:00:00","deleted":"0","last_modified":"2012-06-20 22:45:55"}
		// ,{"currency":"GBP","conversion_rate":"0.635124","as_of_datetime":"2012-06-20 00:00:00","deleted":"0","last_modified":"2012-06-20 22:45:55"}
		// ,{"currency":"JPY","conversion_rate":"79.164880","as_of_datetime":"2012-06-20 00:00:00","deleted":"0","last_modified":"2012-06-20 22:45:55"}
		// ]}

		response_table += "<h3>" + data.now + "</h3>" + "\n";

		response_table += "<table border=\"1\">" + "\n";
		response_table += "<tr><th>currency</th><th>conversion rate</th><th>as of</th></tr>" + "\n";
		var i;
		for(i=0; i<data.exchange_rates.length; i++)
		{
			//alert("data[" + i + "]=" + objectToString(data[i]) );
			response_table += "<tr>" + "\n" +
					" <th>" + data.exchange_rates[i].currency + "</th>" + "\n" +
					" <th style=\"text-align: right\">" + data.exchange_rates[i].conversion_rate + "</th>" + "\n" +
					" <th>" + data.exchange_rates[i].as_of_datetime + "</th>" + "\n" +
					"</tr>" + "\n";
		}
		response_table += "</table>";


		$('#response_table').html( response_table );

		repeatGetExchangeRates(false);
}/* getExchangeRatesSuccess( data, textStatus, jqXHR ) */

function handleError(jqXHR, textStatus, errorThrown)
{
		var sWho="handleError";
		//alert(sWho + "()...");

			var date_received= new Date();
			$('#response_received_at').html( getDateTimeString(date_received) );
		    //alert("error: jqXHR=" + objectToString(jqXHR) );
		    //alert("error: status=" + jqXHR.status + ", statusText='" + jqXHR.statusText + "'");
		    $('#response_status_code').html("<span class=\"error\">" + jqXHR.status + "</span>");
		    $('#response_status_text').html("<span class=\"error\">" + jqXHR.statusText + "</span>");

		    //alert("getAllResponseHeaders()='" + jqXHR.getAllResponseHeaders() + "'");
			$('#response_headers').html("<pre class=\"response_headers\">" + jqXHR.getAllResponseHeaders() + "</pre>");

		    //alert("responeText='" + jqXHR.responseText + "'");
			$('#response_text').html("<pre>" + jqXHR.responseText + "</pre>");

			$('#response_table').html('');
}/* handleError(jqXHR, textStatus, errorThrown) */


function loginToServer()	
{
	clearTop();

	var date_requested = new Date();
	//var timestamp = Date.now();    
	// Place "unique=<timestamp>" into url to create a unique url every time and avoid caching 
	var timestamp = date_requested.valueOf();
	$('#requested_at').html( getDateTimeString(date_requested) );

	var my_request_type=$('#request_type_input').val();
	$('#request_type').text( my_request_type );

	var username= $('#username').val();
	var password= $('#password').val();

	//alert("currency_list='" + currency_list + "', currency_list_encoded='" + currency_list_encoded + "'");
	var my_url = "server.php?verb=login&username=" + username+ "&password=" + password + "&unique=" + timestamp;
	$('#request_url').text( my_url );
	//alert("AJAXING: url='" + my_url + "'...");


jqXHRequest = $.ajax({
   type: my_request_type, // GET, POST, PUT, DELETE etc.
   url: my_url, // The actual URL to make the request
   //data: data, // Any data/parameters to send to the server
   accepts: "application/json", // For "Accept" in HTTP header of request
   //beforeSend: function(xmlHttpRequest) {
     //xmlHttpRequest.setRequestHeader('Accept', "application/json"); // MIME Type
     //alert("before send, xmlHttpRequest=" + objectToString(xmlHttpRequest) );
   //},
   //dataType: "text",
   dataType: "json",
   success: loginSuccess,
   error: handleError,

 });

}/* loginToServer() */


function loginSuccess( data, textStatus, jqXHR )
{
		sWho = "loginSuccess";
		//alert(sWho + "()...");

		var date_received= new Date();
		$('#response_received_at').html( getDateTimeString(date_received) );
	    //alert("success: status=" + jqXHR.status + ", statusText='" + jqXHR.statusText + "'");
	    $('#response_status_code').html("<span class=\"ok\">" + jqXHR.status + "</span>");
	    $('#response_status_text').html("<span class=\"ok\">" + jqXHR.statusText + "</span>");

	    //alert("getAllResponseHeaders()='" + jqXHR.getAllResponseHeaders() + "'");
		$('#response_headers').html("<pre class=\"response_headers\">" + jqXHR.getAllResponseHeaders() + "</pre>");

		//alert(sWho + "(): responseText='" + jqXHR.responseText + "'");
		$('#response_text').html("<pre>" + jqXHR.responseText + "</pre>");

		$('#response_table').html('');
		
		//alert("success: data=" + objectToString(data) );

		// e.g.,
		// {"now": "Friday, June 22nd, 2012 06:28:10 PM",
		//  "authorization_token": "2fad387d7as77ddab"}
		//

		//alert("data.authorization_token = " + data.authorization_token );
		//alert("data.authorization_time= " + data.now);
		$('#authorization_token').val( data.authorization_token );
		$('#authorization_time').text( data.now );

}/* loginSuccess() */




	
</script>
</body>
