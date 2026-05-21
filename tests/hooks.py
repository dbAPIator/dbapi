import schemathesis

STATE = {}

@schemathesis.hook
def after_call(context, case, response):
    # Capture API ID after creation
    if case.operation.method == "POST" and case.operation.path == "/admin/apis":
        if response.status_code == 201:
            data = response.json()
            STATE["api_id"] = data.get("id")

@schemathesis.hook
def before_call(context, case):
    # Inject apiId into GET /admin/apis/{apiId}
    if case.operation.path == "/admin/apis/{apiId}":
        api_id = STATE.get("api_id")
        if api_id:
            case.path_parameters["apiId"] = api_id