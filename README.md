

# dbAPIator: Instant REST API for MySQL databases

dbAPIator is a backend for MySQL driven applications. It simplifies the process of creating REST APIs for any MySQL database by autodecting the database schema and creating the API endpoints based on the database structure.

With dbAPIator, developers can quickly and easily generate clean, well-documented APIs that are tailored to their database schema. This automation basically eliminates the need to develop the database backend, allowing developers to focus on other aspects of their projects.

## Features

- automatic API endpoints creation for CRUD operations based on the MySQL database schema
- implements JSON-API specification to allow recursive retrieval, creation and update of linked resources 
- automatic API documentation  generation in Swagger format
- authentication: offers authentication endpoints which can be customized by defining the SQL query.
- authorization: ACLs based on logged in user 
- error handling: provides informative error messages in case of invalid requests, server errors, or other exceptional conditions. Error responses include HTTP status codes, error codes, and human-readable descriptions to help developers diagnose and troubleshoot issues.
- scalability: being stateless, it can horizontally scale to any number of instances. 
- security: implements security best practices to protect against common threats such as injection attacks, cross-site scripting (XSS), and cross-site request forgery (CSRF). This involves input validation, parameterized queries, and encryption of sensitive data.
- multi-database: one dbAPIator instance can be used to connect to multiple databases, each with their separate entry endpoint, access and authorization rules  
- configuration API: configuration of the APIs is performed through a dedicated API where new databases/APIs can be added, update their configuration 
- instant update of the API endpoints when the database struncture changes
 
<!-- - performance: uses **OPcache** for storing precompiled script bytecode in shared memory and **memcached** for caching data -->

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher 
- Apache/Nginx web server 
- Composer (PHP package manager)
- PHP Extensions:
  - mysqli
  - json 
  - mbstring
  - opcache (recommended)
  - memcached (recommended)

For Apache, ensure mod_rewrite is enabled.

For Nginx, ensure the following directives are present in the configuration file:
```
location ~ /instalation_path/ {
    try_files $uri $uri/ /instalation_path/index.php?$args;
}
```



### Install

```shell
mkdir dbapi
cd dbapi
git clone https://github.com/vsergione/dbapi .
chmod 777 dbconfigs
```

Edit ```installation_path/application/config/dbapiator.php``` and set a secret in the ```$config['configApiSecret']``` variable to be used for authenticating config API requests.

## Create API from an existing database

Creating your first API from a preexisting MySQL database is as easy as making a POST request using your favorite HTTP client to the API configuration endpoint ```http(s)://hostname/installation_path/apis``` 

 
 Example using CURL:
 ```shell
curl --location 'https://localhost/dbapi/apis' \
--header 'Content-Type: application/json' \
--header 'x-api-key: myverysecuresecret' \
--data '{
    "name":"api_name",
    "connection":{
        "hostname": "localhost",
        "username": "username",
        "password": "myverysecurepassword",
        "database": "database_name"
    }
}'
```

And that's it! Your API is now ready to use... but not for production! 

API configuration files are stored in the ```installation_path/dbconfigs/api_name/``` directory. Please refer to the [Configuration API](docs/configuration_api.md) documentation for more details.

In order to properly secure your newly created API please refer to [Securying you API](docs/configuration_api.md#security) documentation.    

For more details about the configuration API please refer to the [Configuration API](docs/configuration_api.md) documentation.


## Using the API
You can check the API endpoints by making a GET request to the API configuration endpoint ```http(s)://hostname/installation_path/apis```.
 
 
## Using the API
 
As mentioned before, the API follows the JSON-API specification. Each table or view in the database will be represented as a resource and the API endpoints will be generated accordingly. For example, if you have a table called ```vendors```, the API will create an endpoint called ```installation_path/api_name/data/vendors```.

### Reading data from a resource
 ```shell
curl --location 'https://localhost/dbapi/apis/api_name/data/vendors' 
```
There are a few query parameters that can be used to customize the records returned:

- page[resource_name][offset]: record set offset
- page[resource_name][limit]: the number of records per page
- sort: comma separated list of fields to sort by. Prefix with '-' for descending order, otherwise ascending.
- filter: a filter to apply to the records. The filter is a comma separated list of filter clauses. Each filter clause is a field name, an operator and a value. The supported operators are:
    - =  equal to
    - != not equal to
    - \> greater than
    - \>= greater than or equal to
    - \< less than
    - \<= less than or equal to
    - \>\< one of the semicolon separated values
- fields[resource_name][]: the fields to return. The fields are the columns of the table. If not specified, all fields will be returned.
- include: the relationships to include. The relationships are the foreign keys of the table. If not specified, no relationships will be included.

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


