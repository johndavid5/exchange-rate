CREATE TABLE `exchange_rate` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `currency` char(3) NOT NULL COMMENT 'EUR, JPY etc...',
  `conversion_rate` decimal(12,6) NOT NULL,
  `as_of_datetime` datetime NOT NULL COMMENT 'external timestamp for last_updated',
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `last_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'current_timestamp',
  PRIMARY KEY (`id`),
  KEY `currency` (`currency`),
  KEY `as_of_datetime` (`as_of_datetime`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
;

-- source: http://www.exchangerate.com/
-- Currency Rates Per 1.00 U.S. Dollar
INSERT INTO exchange_rate
(currency, conversion_rate, as_of_datetime)
VALUES
("EUR", 0.787124, "2012-06-20")    -- European Union: Euro
,("EUR", 0.790330, "2012-06-19")   -- European Union: Euro
,("EUR", 0.793802, "2012-06-18")   -- European Union: Euro
,("EUR", 0.792718, "2012-06-15")   -- European Union: Euro

,("JPY", 79.16488, "2012-06-20")    --  Japan: Yen
,("JPY", 78.92524, "2012-06-19")    --  Japan: Yen
,("JPY", 79.08744, "2012-06-18")    -- Japan: Yen
,("JPY", 78.91834, "2012-06-15")    -- Japan: Yen

,("GBP", 0.635124, "2012-06-20")  --  Great Britain: Pound
,("GBP", 0.637054, "2012-06-19")  --  Great Britain: Pound
,("GBP", 0.638806, "2012-06-18")  --  Great Britain: Pound
,("GBP", 0.641349, "2012-06-15")  --  Great Britain: Pound

,("CAD", 1.017727, "2012-06-20") -- Canada: Dollar
,("CAD", 1.021012, "2012-06-19") -- Canada: Dollar
,("CAD", 1.025771, "2012-06-18") -- Canada: Dollar
,("CAD", 1.024295, "2012-06-15") -- Canada: Dollar

,("AMD", 405.7296, "2012-06-20")  -- Armenia: Dram
,("AMD", 405.7296, "2012-06-19")  -- Armenia: Dram
,("AMD", 405.7296, "2012-06-18") -- Armenia: Dram
,("AMD", 405.7296, "2012-06-15") -- Armenia: Dram
;

-- Let's insert a deleted entry...
INSERT INTO exchange_rate
(currency, conversion_rate, as_of_datetime, deleted)
VALUES
("EUR", 0.787125, "2012-06-20", 1)    -- European Union: Euro
;


CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `last_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'current_timestamp',
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `action` (`action`),
  KEY `last_modified` (`last_modified`)
) ENGINE=InnoDB AUTO_INCREMENT=825 DEFAULT CHARSET=utf8
;
