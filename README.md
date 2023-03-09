# DBAPI - automatic REST API for MySQL (and others)
DBAPI creates and exposes a REST API for any MySQL database. It supports multiple APIs for multiple databases on the same install. 

Instant REST API for your MySQL database without the need to write any code. Just connect it to you database  by making an API call an start using it right away. 

It also provides API clients management, authentication services and fine grained rule based access to API endpoints.  

## Table of contents
- [Instalation](#install)
- [Configuration](#config)
- [Usage](#use)


## <a id="install"></a>Installation

### Prerequisites

You can run the app either by installing it under the docroot of a web server or using the PHP built in server. 

- a running Apache (prefered because of support of mod_rewrite and htaccess files) web server with PHP 7.4 support. Might be working as well with PHP8, but I've never tested it.
- PHP7.4
- composer (https://getcomposer.org/)


\* (A docker container is on it's way. Stay tuned)
### Install
Clone repo, install dependencies and make configure rights  

    mkdir dbapi
    cd dbapi
    git clone https://github.com/vsergione/dbapi .
    composer install
    chmod 777 dbconfigs

Optional: launch PHP built in web server
    php -S localhost:4343  

## <a id="setup"></a>Setup

### Setup

Multiple databases/APIs can be set up using the same install of DBAPI.  

Setting up the API for a MySQL database is as easy as making a POST request to http://localhost:4343/apis/ (when using the PHP built in webserver, otherwise is http(s)://your_host_name/installation_path/apis/ with the following payload)

    {
        "name":"example",
        "connection":{
            "dbdriver":"mysqli",
            "hostname": "dbhost",
            "username": "dbuser",
            "password": "dbpass",
            "database": "dbname"
        },
        "security": {
            "from":["0.0.0.0/0","::/0","1::"],
            "rules":[
                ["/.*/i","/.*/i","allow"]
            ],
            "default_policy":"accept"
        }
    }
 
 There is an extra API for managing the API itself. Documentation still in the making...
    
 ## Use
 Let's assume the following database structure
 
 ![DB Structure](docs/example.png "MySQL Workbench export")
 
 
 ### READING DATA
 
 Once the DB is connected the following API endpoints are availble to use:
 - .../apis/{{apiName}}/{{tableOrViewName}}
    - GET: will list the contents of {{tableOrViewName}}. The request supports the following query parameters:
        - page[{{tableOrViewName}}][offset]=0 - offset from where the records will be listed
        - page[{{tableOrViewName}}][limit]=10 - page size
        - sort=(-){{fieldName1}},(-){{fieldName2}} - sort by fields. Default is ascending. Minus '-' in front of the fieldname is for descending
        - filter=(filter1),(filter2), where filters can be like
            - field=value - field equals value 
            - field>value - field greater than value
            - field>=value - field greater than or equal value
            - field<value - field less than value
            - field<=value - field less or equal than value
            - field=~value - field starts with value
            - field~=value - field ends with value
            - field~=~value - field contains with value
            - field><value1;value2... - field is one of the values 
         - fields=field1,field2... - sparse field selection: commas separate list of fields to be included
         - include=relation1,relation2,relation1.subrelation3... - recursive inclusion of relations (1:1 and 1:n)     
                                    
 
 As per the above structure the following endpoints are available
 - .../apis/example/vendors
    - GET will return a list of vendors. The request can be parametrized with: page[vendors][offset], page[vendors][limit], filter, fields, include 
 - .../apis/example/vendors/{vendorName}
 - .../apis/example/vendors/{vendorName}/assets
 - .../apis/example/assets
 - .../apis/example/assets/{assetId}
 - .../apis/example/assets/{assetId}/vendor
 - .../apis/example/assets/{assetId}/location
 - .../apis/example/assets/{assetId}/user
 - .../apis/example/locations
 - .../apis/example/locations/{locationId}
 - .../apis/example/locations/{locationId}/assets
 - .../apis/example/users
 - .../apis/example/users/{userId}
 - .../apis/example/users/{userId}/assets
 