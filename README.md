<img src="https://atomjump.com/images/logo80.png">

# db_searcher
Searches your database when you enter 'details [query]' into an AtomJump Messaging window



Note: this release should still be considered an Alpha.



## Searching an AtomJump Messaging database

Note: you should be using MySQL ver >= 5.6, as FULLTEXT indexes are not supported on innodb tables. See the notes below for an alternative.

You should set the config.json 'innodb' field to true.

Create the index first:

```
USE ssshout;
CREATE FULLTEXT INDEX searcher ON tbl_ssshout(var_shouted);
CREATE INDEX ispublic ON tbl_ssshout(int_whisper_to_id);
```

Then include the following SQL to provide a free text search in your config.json 'sqlQuery' field:
```
SELECT var_shouted FROM tbl_ssshout WHERE MATCH(var_shouted) AGAINST('[SEARCH]' IN BOOLEAN MODE) AND int_whisper_to_id IS NULL LIMIT 10
```

For small sites, you can potentially use a MYISAM table, rather than an innodb table before creating the index. We cannot guarantee that will work in multiple server sites, however.
```
ALTER TABLE tbl_ssshout ENGINE = MYISAM;
```


## Searching a generic MySQL database

E.g. in your config.json 'sqlQuery' field:
```
SELECT CONCAT(var_name, ' ', var_description) AS result FROM tbl_bike WHERE var_name LIKE '%[SEARCH]%' OR var_description LIKE '%[SEARCH]%' LIMIT 10
```


## Searching a MySQL database with a free text field

Create the index first:

```
CREATE FULLTEXT INDEX searcher ON tbl_bike(var_name, var_description);
```

Then in your config.json 'sqlQuery' field:
```
SELECT var_name FROM tbl_bike WHERE MATCH(var_name, var_description) AGAINST('[SEARCH]' IN BOOLEAN MODE)
```

Or in this example, a filter at the end, and two fields concatenated:
```
SELECT CONCAT(COALESCE(var_name,''), COALESCE(var_description,'')) FROM tbl_bike WHERE MATCH(var_name, var_description) AGAINST('[SEARCH]' IN BOOLEAN MODE) AND enm_active = 'active'
```
Note: the COALESCE prevents NULL values from returning the whole record as NULL.



## Searching a generic ODBC database

You will need to install the correct ODBC driver for your database, and create a DSN (whether this is on Windows or Unix).

Then enter the DSN name in the config.json 'dsnHere' field.




