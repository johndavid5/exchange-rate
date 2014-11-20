John D. Aynedjian
"The Tenacious Techie"
Friday, June 29th, 2012

Live Demo: http://thetenacioustechie.com/exchange-rate/index.php

exchange-rate RESTful service will allow users to:
* supply an authorization token during a "login" request.  This must be passed
  in the HTTP header for any exchange rate requests.
* retrieve rates for one or multiple currencies.
* filter result based on as_of date. (so they only see rates for a particular day)
* limit to 100 GET requests per minute per user

You can play with the "test-jig" and RESTful service at...
    http://johndavid5.com/exchange-rate/index.php

1. tables.sql - schema to create the "exchange_rate" and "sessions" table.

Also populates the "exchange_rate" table with exchange rates for 
"EUR","JPY","GBP","CAD", and "AMD" for 
2012-06-15, 2012-06-18, 2012-06-19, and 2012-06-20.

2. config.php - holds database DSN, username and password to be used by rest_server.class.php to connect to the database.  Modify as required for your particular database server.

3. index.php - a cute little jQuery/AJAX based "test jig".  Hit the 
"LOGIN TO SERVER" button first to get yourself an Authorization Token".
Then hit "GET EXCHANGE RATES" to get yourself some exchange rates.

****************
See how many ways you can cause the request to
fail, e.g., not having an "Authorization Token" or having an invalid one, 
or using a request type other than "GET", or setting Repeat: to 200 and
blasting through the 100 requests per minute limit.
****************

4. server.php - very thin wrapper that invokes RestServer in "rest_server.class.php"
to do all the work.

5. rest_server.class.php - The RestServer class that does all the work.

----------------------------
