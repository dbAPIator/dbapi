

# Understanding PnPBase <a id='top'></a>

At the core of the PnPBase stays the understanding that for most database driven applications the structure of the application's database acts like some sort of a DNA for the application itself.  

In order to illustrate the capabilities of PnPBase will take as an example an application intended to manage users, users groups (teams) and their assets. At the minimum level of detail the following database structure should be created.

# Database structure
A database structure typically consists of various tables, views, stored procedures. In our case we will define 3 tables and 1 view. Further customization like triggers and user defined functions are part of the database design but PnPBase role is not to expose or use them directly.

Of course, various method calls of PnPBase, like POST, PATCH, DELETE might trigger certain triggers, and certain user defined functions might be called for example in a  VIEW "SELECT" definition, but PnPBase is agnostic in regard with these database features.

## Table "users"

Used to store information about users, like: first name, last name, username, which team they belong to.

| Field name | Type | Null | Xtra | Foreign Key |
|--|--|--|--|--|
| id | INT | NO | PRIMARY KEY | - |
| username | TEXT | NO | UNIQUE | - |
| password | TEXT | NO | - | - |
| first_name | INT | NO | - | - |
| last_name | INT | NO | - | - |
| team | INT | YES | - | teams . id |

[^ Top](#top)

## Table "teams"

Used to store information about team, like: team name, team leader id

| Field name | Type | Null | Xtra | Foreign Key |
|--|--|--|--|--|
| id | INT | NO | PRIMARY KEY | - |
| name | TEXT | NO | UNIQUE | - |
| teamleader | INT | YES | - | users . id |

[^ Top](#top)

## Table "assets"

Used to store information about assets, like: type (laptop, phone, badge etc), make/brand and model, serial number , owner id.

| Field name | Type | Null | Xtra | Foreign Key |
|--|--|--|--|--|
| id | INT | NO | PRIMARY KEY | - |
| make | TEXT | NO | UNIQUE | - |
| model | TEXT | NO | - | - |
| type | INT | NO | - | - |
| owner | INT | NO | - | users . id |

[^ Top](#top)

---
Because we want to check how many members each team has, we will create a VIEW using the following SQL:

    CREATE VIEW teams_count AS
    SELECT team,count(*) AS cnt FROM users
    GROUP BY team;

## View "teams_count"

| Field name | Type | Null | Xtra | Foreign Key |
|--|--|--|--|--|
| team | INT | NO | - | - |
| cnt | INT | NO | - | - |

[^ Top](#top)

# API Setup/Endpoints <a id='endpoints'></a>
Using the setup script, we will generate a configuration file which enables the following API endpoints:

### Autodetected endpoints
- [/users](#users)
- [/users/\$user_id](#users_id)
- [/users/\$user_id/assets](#users_id_assets)
- [/users/\$user_id/assets/\$asset_id](#users_id_assets_id)
- [/users/\$user_id/teams](#users_id_teams)
- [/teams/](#teams)
- [/teams/\$team_id](#teams_id)
- [/teams/\$team_id/teamleader](#teams_id_teamleader)
- [/teams/\$team_id/users](#teams_id_users)
- [/teams/\$team_id/users/\$user_id](#teams_id_users_id)
- [/assets](#assets)
- [/assets/\$asset_id](#assets_id)
- [/assets/\$asset_id/owner](#assets_id_owner)
- [/teams_count/](#teams_count)

As one might notice there is a pattern in the generated endpoints names. First  segment takes the name of the table or view being exposed. Second follows the ID of the record, while the third segment identifies the relation by using either the foreign key field name as relation identifier for 1:1 relations, or the linked table name for 1:n relationships.  

This might come handy for most of the cases, but sometimes one might wish, or it might be needed, to change the relation name. For such situations the relation name can be changed by editing accordingly the generated PnPBase DB configuration.

### Extendend endpoints

There are certain situations when the setup script is not able to detect various features of the data source. Such situations include unique ID fields in the case of view or relationships between views and table.

As it is the case in our example, the VIEW "teams_count" contains the field "team" which we can see from the VIEW definition that it is unique and can be used as an ID field. Therefore we will modify the generated PnPBase DB configuration file to reflect the fact that key field for VIEW "teams_count" is "team".

In the second place, the field "team" can be considered a foreign key pointing to field ID of table "teams". Again, by modifying the generated PnPBase DB configuration file one can declare the 1:1 relationship between VIEW "teams_count" and TABLE "teams"

- [/teams_count/\$team_id](#teams_count_id)
- [/teams_count/\$team_id/teams](#teams_count_id_teams)
- [/teams/\$team_id/teams_count](#teams_id_teams_count)

[^ Top](#top)

## /users

Endpoint for table users to create and retrieve records

Methods:
- [GET](#get_users)
- [POST](#post_users)


### GET <a id='get_users'></a>
Retrieve records from table **users**
- query parameters:
	- **filter**: comma separated list of filtering criteria. Provided criteria are combined together with a logical AND
	- **sort[*users*]**: comma separated list of fields to be used as sort criteria the records
	- **fields[*users*]**: comma separated list of fields to include in the returned result. By default it will include all fields
	- **include**: comma separated list of related data to include. Possible values: *assets*, *teams*
	- **where**: alternate to "**filter**" parameter allowing to define more complex filtering conditions
- responses:
	- 200 OK
	- 400


### POST<a id='post_users'></a>
Create new users
- body data:
	- description: JSON:API document object containing one or more
	- syntax:
	- example:
- responses:

[^ Endpoints](#endpoints) / [^ Top](#top)


## /users/$user_id <a id='users_id'></a>
Methods:
- [GET](#get_users_id)
- [PATCH](#patch_users_id)
- [DELETE](#delete_users_id)

### GET<a id='get_users_id'></a>
Retrieve user by ID
- path parameters
	- $id - user ID as it is stored in field id of table users which is marked as PRIMARY KEY
- query parameters
	- include
	- fields

### PATCH<a id='patch_users_id'></a>
Update user identified by ID
- path parameters
	- $id - user ID as it is stored in field id of table users which is marked as PRIMARY KEY

### DELETE<a id='delete_users_id'></a>

[^ Endpoints](#endpoints) / [^ Top](#top)

## /users/\$user_id/assets<a id='users_id_assets'></a>
Endpoint for table assets to  create and retrieve records related to a record from table users identified by ID

Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)

### GET <a id='get_users_id_assets'></a>

### POST <a id='post_users_id_assets'></a>

### DELETE <a id='delete_users_id_assets'></a>

[^ Endpoints](#endpoints) / [^ Top](#top)

## /users/\$user_id/assets/\$asset_id<a id='users_id_assets_id'></a>

Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)

### GET <a id='get_users_id_assets'></a>

### POST <a id='post_users_id_assets'></a>

### DELETE <a id='delete_users_id_assets'></a>

[^ Endpoints](#endpoints) / [^ Top](#top)


## /users/$id/teams <a id='users_id_teams'></a>
Endpoint for table assets to  edit and retrieve records related to a record from table users identified by ID

Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)

## /teams/<a id='teams'></a>
Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)

## /teams/\$team_id<a id='teams_id'></a>
Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)



## /teams/$team_id/teamleader<a id='teams_id_teamleader'></a>
Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)


## /teams/$team_id/users<a id='teams_id_users'></a>
Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)

## /teams/$team_id/users/\$user_id<a id='teams_id_users_id'></a>
Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)

## /teams/$team_id/teams_count<a id='teams_id_teams_count'></a>
Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)

## /assets<a id='assets'></a>

Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)



## /assets/$asset_id<a id='assets_id'></a>


Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)

## /assets/$id/owner<a id='assets_id_owner'></a>
Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)


## /teams_count<a id='teams_count'></a>

Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)

## /teams_count/$team_id<a id='teams_count_id'></a>

Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)

## /teams_count/$team_id/teams<a id='teams_count_id_teams'></a>

Methods:
- [GET](#get_users_id_assets)
- [POST](#post_users_id_assets)
- [PATCH](#post_users_id_assets)
- [DELETE](#delete_users_id_assets)
### GET <a id='get_users_id_assets'></a>
### POST <a id='post_users_id_assets'></a>
### PATCH <a id='post_users_id_assets'></a>
### DELETE <a id='delete_users_id_assets'></a>
[^ Endpoints](#endpoints) / [^ Top](#top)
