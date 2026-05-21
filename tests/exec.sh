#!/bin/bash
docker run --rm -t -v "$PWD/openapi:/spec:ro" schemathesis/schemathesis:latest \
    run "http://192.168.8.114:8888/admin-openapi.yaml" \
    --header "X-Admin-API-Key: myverysecuresecret" \
    --phases examples \
    --exclude-checks ignored_auth \
    --report-vcr-path /work/cassette.yaml
