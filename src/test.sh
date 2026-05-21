#!/bin/bash

curl -X 'POST' \
  'http://localhost:8888/admin/apis' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret' \
  -H 'Content-Type: application/json' \
  -d '{
  "name": "sergiu-test",
  "description": "string",
  "contact": {
    "name": "string",
    "email": "user@example.com",
    "phone": "string"
  }
}'
echo ""
echo --------------------------------
curl -X 'GET' \
  'http://localhost:8888/admin/apis?limit=50' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret'
echo ""
echo --------------------------------
curl -X 'POST' \
  'http://localhost:8888/admin/apis/clone/sergiu-test' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret' \
  -d ''
echo ""
echo --------------------------------
curl -X 'GET' \
  'http://localhost:8888/admin/apis/sergiu-test' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret'
echo ""
echo --------------------------------
curl -X 'PATCH' \
  'http://localhost:8888/admin/apis/sergiu-test' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret' \
  -H 'Content-Type: application/json' \
  -d '{
  "contact": {
    "name": "Sergiu"
  }
}'
echo ""
echo --------------------------------
curl -X 'DELETE' \
  'http://localhost:8888/admin/apis/sergiu-test?force=false' \
  -H 'accept: */*' \
  -H 'X-Admin-API-Key: myverysecuresecret'
echo ""
echo --------------------------------
curl -X 'POST' \
  'http://localhost:8888/admin/apis' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret' \
  -H 'Content-Type: application/json' \
  -d '{
  "name": "sergiu-test",
  "description": "string",
  "contact": {
    "name": "string",
    "email": "user@example.com",
    "phone": "string"
  }
}'
echo ""
echo --------------------------------
curl -X 'GET' \
  'http://localhost:8888/admin/apis/sergiu-test/connection' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret'

echo ""
echo --------------------------------

curl -X 'POST' \
  'http://localhost:8888/admin/apis/sergiu-test/connection:test' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret' \
  -d ''
echo ""
echo --------------------------------

curl -X 'PUT' \
  'http://localhost:8888/admin/apis/sergiu-test/policies/config-network' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret' \
  -H 'Content-Type: application/json' \
  -d '{
  "defaultAction": "allow",
  "rules": [
    {
      "action": "allow",
      "cidr": "192.168.8.0/24",
      "description": "string"
    }
  ]
}'
echo ""
echo --------------------------------
curl -X 'GET' \
  'http://localhost:8888/admin/apis/sergiu-test/policies/config-network' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret'
echo ""
echo --------------------------------
curl -X 'PUT' \
  'http://localhost:8888/admin/apis/sergiu-test/connection' \
  -H 'accept: application/json' \
  -H 'X-Admin-API-Key: myverysecuresecret' \
  -H 'Content-Type: application/json' \
  -d '{
  "driver": "mysql",
  "host": "192.168.8.114",
  "port": 3306,
  "database": "test",
  "username": "vsergiu",
  "password": "parola123",
  "ssl": {
    "mode": "preferred",
    "caPem": "string",
    "clientCertPem": "string",
    "clientKeyPem": "string"
  }
}'







echo ""
echo --------------------------------
curl -X 'DELETE' \
  'http://localhost:8888/admin/apis/sergiu-test?force=false' \
  -H 'accept: */*' \
  -H 'X-Admin-API-Key: myverysecuresecret'