# Introduction
APIATOR is a REST API for SQL database allowing full access to the data in the DB by using plain REST calls.

At the core of APIATOR stays the API configuration file. This file describes the database structure in terms of tables, views and stored procedure and their corresponding fields and relations. 

Aditional to this, one must configure one on more profile files mirroring the structure of the API configuration file and which contains access right per table/view/procedure/field/relation. By using this split design, one can create multiple access profiles with different rights for each specific client type.

API clients are identified by strings which must be uniques with an API domain they are bound with a specific API profile, as defined above.  


# Setup
To Do

# REST calls
Each API is defined by:
- server (hostname/IP)
- protocol (always https)
- port (defaults to 443)
- base path (defaults to v2)

The following end resources and operations are available:
## /{tableLabel}
### GET
Retrieve records from table identified by {tableLabel}

#### Query Parameters

|Parameter name | Type    | Description    |  
|----------|:-------------:|:------|  
| offset | integer | Result set offset<br><br>Default: 0 |  
| limit | integer | Result set page size<br><br>Default: 10 |  
| sort | string| Comma separated list of field names to order by the result. If the field name is preceded by a - sign, the ordering is descending and if not is ascending.<br><br>Sorting can be done only on the primary resource and not on the included relationships<br><br>Eg: sort=field1,-field2 translated to SELECT .... ORDER BY field1 ASC, field2 DESC<br><br>Default: none |  
| fields[{tableLabel}] | string  | Use this paramater to define a sparse fieldset: comma separated list of fields belonging to table identified by {tableLabel} to be included in the record object.<br><br>Eg: fields[table1]=field1,field2 translates into SELECT id, field1, field2 .... <br>Notice that the table key field (in this example named id) will always be included <br><br> Default: includes all table fields which are marked as selectable in the access profile (see bellow access profile spec)|
| include | string | 

## /{tableLabel}/{recId}
## /{tableLabel}/{recId}/relationships/{relName}
## /{viewLabel}
## /{procedureLabel}



 




