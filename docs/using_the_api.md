# Using the API
Once you have created an API, you can use it to read, create, update and delete records from the database.

The main API endpoint is ```http(s)://hostname/installation_path/api_name/data/```.

API specification is automatically generated in OpenAPI format and can be accessed by performing a GET request to ```http(s)://hostname/installation_path/config/api_name/swagger```.

You can also use Swagger UI which is bundled within the installation and can be accessed at ```http(s)://hostname/installation_path/swagger.html?api=api_name```.

While the Swagger UI is a great tool to explore the API, we recommend reading further to understand the API structure and how to use it.

## Working with data

### Read records

Retrieving records from a table or view, can be done by performing a GET request to the table endpoint. ```http(s)://hostname/installation_path/api_name/data/table_name```

For example let's assume we have a table called **`customers`** and we want to retrieve all the records from it.

```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/customers' 
```

Let's assume the table customers which structure is composed of the following columns: `uid, fname, lname, bdate, city, country, account_manager`, where `uid` is primary key and `account_manager` is a foreign key to the `users` table. A possible response will look like this:

```json
{
  "data": [
    {
      "id":"123",
      "type":"customers",
      "attributes": {
        "uid": "123",
        "fname": "John",
        "lname": "Doe",
        "bdate": "1976-10-01",
        "city": "Washington",
        "country": "USA"
        },
        "relationships": {
            "orders": {
                "data": null
            },
            "account_manager": {
                "data": {
                    "id": "a1b2c3d4",
                    "type": "users"
                }
            }
        }
    },
    ...
  ],
  "meta": {
    "total": 214,
    "offset": 0
  }
}
```

The request can be further customized by employing different query parameters for different purposes.

#### Filtering <a id="filtering"></a>
Records filtering is achieved by using the **`filter`** parameter and passing to it a comma separated list of comparison expressions.

The syntax for the comparison expresion is **`field_name + operator + value(s)`**

The supported operators are:
- `=`  equal to (Eg. `name=John`)
- `~=`  ends with (Eg. `filename~=.gif`)
- `=~`  beggins with (Eg. `date=~2020-12`)
- `~=~` contains  (Eg. `filename~=~error`)
- `>` greater than (Eg. `qty>0`)
- `>=` greater than or equal to (Eg. `date>=2025-02-02`)
- `<` less than (Eg. `qty<10`)
- `<=` less than or equal to (Eg. `temp<=-5`)
- `><` one of the semicolon separated values (Eg. `wday><Mon,Tue,Fri`)
- adding ! (exclamation mark) in front of any of the above operators will perform a negation 

Multiple comparison expressions can be passed as a comma separated list of expressions and they will be combined in a logical AND. 

For example: `filter=fname=~John,bdate<2010,city><Washington;London` will return a list of persons which first name begin with John, are born before 2010 and live in Washington or in London

When including relationships, the filter parameters should be used like this: 
```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/customers?include=orders&filter[customers]=qty>100'
```

Filtering can be applied to the linked resources by using the following syntax: `filter[main_resource_name/relationship_name]=expression`

For example: `filter[customers/orders]=100` will return a list of customers which have at least one order with a quantity greater than 100


#### Sparse field selection <a id="sparse-field-selection"></a>
For the records to contain only certain fields, use the **`fields`** parameter and pass it a comma separated list of field names. 

For example, by using **`fields=name,bdate`** all the returned records will contain only the fields `name` and `bdate`

#### Sorting <a id="sorting"></a>
Sorting is performed using the **`sort`** paramenter and passing a comma separated list of fields on which to perform the sort. The default sorting direction is ascending. For sorting descending, prepend a - in front of the field name.

Example:
```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/persons?sort=name,-bdate'
```
In the above example the records will be sorted ascending by name and descending byt bdate (birth date).

When using relationships, the sorting parameters should be used like this: 
```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/customers?include=orders&sort[persons]=name,-bdate&sort[orders]=name,-qty'
```


#### Pagination <a id="pagination"></a>
Pagination is performed using the **`page`** parameter which is to be used as as an array.

The following parameters are supported:

- **page[offset]**: record set offset for primary record set. Default 0
- **page[limit]**: number of records per page for primary record set. Default limit is configured in ```installation_path/application/config/dbapiator.php``` by variable ```$config["default_page_size"]``` for main record set and by ```$config["default_relationships_page_size"]``` for linked resources record set

Example:
```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/persons?page[offset]=10&page[limit]=10'
```

When using relationships, the pagination parameters should be used like this: 
```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/persons?page[persons][offset]=10&page[persons][limit]=10&include=orders'
```




### Reading data from a relationship

To read data from a relationship, you can use the include query parameter. For example, if you have a table called ```vendors``` and a table called ```orders``` that has a foreign key to the ```vendors``` table, you can read the orders for a vendor by using the following request:    
```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/vendors/{vedorId}/orders'
```

The ```{vedorId}``` is the id of the vendor you want to read the orders for. The API will return the orders for the vendor.

The same parameters used to read data from a resource can be used to read data from a relationship.

### Creating a record

To create a record, you can use the POST method to the resource endpoint. For example, to create a new vendor, you can use the following request:
```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/vendors' \
--data '{
    "data": {
        "type": "vendors",
        "attributes": {
            "name": "Vendor Name"
        }
    }
}'  
```
In the same POST request you can include the relationships to be created. For example, to create a new vendor with an order, you can use the following request:
```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/vendors' \
--data '{
    "data": {
        "type": "vendors",
        "attributes": {
            "name": "Vendor Name"
        },
        "relationships": {
            "orders": {
                "data": {
                    "type": "orders",
                    "attributes": {
                        "name": "Order Name"
                    }
                }
            }
        }
    }
}'  
```

### Updating a record

To update a record, you can use the PATCH method to the resource endpoint. For example, to update the name of a vendor, you can use the following request:
```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/vendors/{vendorId}' \
--data '{
    "data": {
        "type": "vendors",
        "id": "{vendorId}",
        "attributes": {
            "name": "New Vendor Name"
        }   
    }
}'  
```

### Deleting a record

To delete a record, you can use the DELETE method to the resource endpoint. For example, to delete a vendor, you can use the following request:
```shell
curl --location -X DELETE 'https://localhost/dbapi/apis/api_name/data/vendors/{vendorId}'
```


